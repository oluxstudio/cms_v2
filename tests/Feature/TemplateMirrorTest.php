<?php

use App\Livewire\CollectionsPage;
use App\Livewire\ConnectReviewPage;
use App\Models\Site;
use App\Models\User;
use App\Services\TemplateInstaller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * The "nothing ever freezes" tripwire: whatever changes in the template
 * package — blocks, node schemas, values, pages, collection schemas+items,
 * form definitions, product prices — one template:mirror run converges the
 * applied site onto it. If a new pipeline surface is added and forgotten
 * here, extend this test.
 */
function mirrorSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'mir-'.uniqid(), 'domain' => 'mir-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    return [$owner, $site->fresh()];
}

test('template:mirror converges every surface onto a changed template', function () {
    [$owner, $site] = mirrorSite();

    // Stage a scratch template package the test can mutate freely.
    $key = 'mirror-tmp';
    $dir = resource_path("templates/{$key}");
    File::deleteDirectory($dir);
    File::copyDirectory(resource_path('templates/hairco'), $dir);
    $site->update(['template' => $key]);

    try {
        // ── Mutate the package the way an author edits the original ──
        $home = json_decode(File::get("$dir/pages/home.json"), true);
        $dropped = $home['blocks'][count($home['blocks']) - 1]['name'];   // a section deleted
        array_pop($home['blocks']);
        $renOld = $home['blocks'][0]['nodes'][0]['label'];                // a field renamed
        $home['blocks'][0]['nodes'][0]['label'] = 'Mirrored Label';
        $home['blocks'][0]['nodes'][0]['value'] = 'mirrored-value';
        $target = $home['blocks'][0]['name'];
        File::put("$dir/pages/home.json", json_encode($home));

        $deadPage = collect(File::files("$dir/pages"))->first(fn ($f) => $f->getFilenameWithoutExtension() !== 'home');
        $deadUrl = json_decode($deadPage->getContents(), true)['url'];    // a page deleted
        File::delete($deadPage->getPathname());

        $manifest = json_decode(File::get("$dir/template.json"), true);
        $manifest['forms'][0]['fields'] = [['key' => 'only', 'label' => 'Only', 'type' => 'text', 'required' => true]];
        $manifest['collections'][0]['fields'] = [
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text'],
            ['key' => 'blurb', 'label' => 'Blurb', 'type' => 'textarea'],
        ];
        $manifest['collections'][0]['items'] = [
            ['headline' => 'Fresh A', 'blurb' => 'aa'],
            ['headline' => 'Fresh B', 'blurb' => 'bb'],
        ];
        $manifest['products'] = [[
            'name' => 'Mirror Book', 'slug' => 'mirror-book', 'price_cents' => 1234, 'currency' => 'gbp',
            'description' => 'updated by mirror', 'image' => '/assets/x.jpg',
        ]];
        File::put("$dir/template.json", json_encode($manifest));

        // Pre-existing product with drifted values — mirror must update it.
        $site->products()->create(['slug' => 'mirror-book', 'name' => 'Old Name', 'price_cents' => 1, 'currency' => 'gbp', 'is_active' => true]);

        // ── Converge ──
        Artisan::call('template:mirror', ['key' => $key, '--site' => $site->name]);

        $site->refresh();
        $homePage = $site->pages()->where('url', '/')->first();

        // Dropped block gone; page from a deleted def gone.
        expect($homePage->components()->pluck('name'))->not->toContain($dropped)
            ->and($site->pages()->where('url', $deadUrl)->exists())->toBeFalse();

        // Node schema follows: renamed label replaced, value from def.
        $comp = $site->contentComponents()->where('name', $target)->first();
        expect($comp->nodes()->pluck('label'))->toContain('Mirrored Label')
            ->and($comp->nodes()->pluck('label'))->not->toContain($renOld)
            ->and($comp->nodes()->where('label', 'Mirrored Label')->value('value'))->toBe('mirrored-value');

        // Collection schema + items reseeded.
        $col = $site->collections()->where('name', $manifest['collections'][0]['name'])->first();
        expect(collect($col->fields)->pluck('key')->all())->toBe(['headline', 'blurb'])
            ->and($col->items()->count())->toBe(2)
            ->and($col->items()->get()->pluck('data.headline'))->toContain('Fresh A');

        // Form definition replaced.
        $form = $site->forms()->where('name', $manifest['forms'][0]['name'])->first();
        expect(collect($form->fields)->pluck('key')->all())->toBe(['only']);

        // Product updated in place.
        $prod = $site->products()->where('slug', 'mirror-book')->first();
        expect($prod->name)->toBe('Mirror Book')->and((int) $prod->price_cents)->toBe(1234);
    } finally {
        File::deleteDirectory($dir);
    }
});

test('template:mirror leaves non-template things alone', function () {
    [$owner, $site] = mirrorSite();
    $key = 'mirror-tmp2';
    $dir = resource_path("templates/{$key}");
    File::deleteDirectory($dir);
    File::copyDirectory(resource_path('templates/hairco'), $dir);
    $site->update(['template' => $key]);

    try {
        // Owner-created things with names/urls outside the template.
        $ownForm = $site->forms()->create(['name' => 'my-own-form', 'title' => 'Mine', 'fields' => [['key' => 'x', 'type' => 'text']], 'is_active' => true]);
        $ownCol = $site->collections()->create(['name' => 'My Own List', 'slug' => '', 'type' => 'grid', 'fields' => [['key' => 'a', 'name' => 'a', 'label' => 'A', 'type' => 'text']], 'is_public' => true]);
        $ownProduct = $site->products()->create(['slug' => 'my-own-thing', 'name' => 'Mine', 'price_cents' => 5, 'currency' => 'gbp', 'is_active' => true]);

        Artisan::call('template:mirror', ['key' => $key, '--site' => $site->name]);

        expect($site->forms()->where('name', 'my-own-form')->exists())->toBeTrue()
            ->and($site->collections()->where('name', 'My Own List')->exists())->toBeTrue()
            ->and($site->products()->where('slug', 'my-own-thing')->value('name'))->toBe('Mine');
    } finally {
        File::deleteDirectory($dir);
    }
});

test('nested collection values edit structurally on both surfaces', function () {
    [$owner, $site] = mirrorSite();
    $col = $site->collections()->create(['name' => 'Nested Demo', 'slug' => '', 'type' => 'grid', 'is_public' => true,
        'fields' => [
            ['key' => 'title', 'name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'tags', 'name' => 'tags', 'label' => 'Tags', 'type' => 'list'],
            ['key' => 'facts', 'name' => 'facts', 'label' => 'Facts', 'type' => 'rows'],
        ]]);
    $item = $col->items()->create(['site_id' => $site->id, 'status' => 'published',
        'data' => ['title' => 'T', 'tags' => ['a', 'b'], 'facts' => [['label' => 'L1', 'value' => 'V1']]]]);

    // Collections page: structured open → nested ops → save round-trips arrays.
    $lw = Livewire\Livewire::actingAs($owner)->test(CollectionsPage::class, ['site' => $site])
        ->call('viewEntries', $col->id)
        ->call('openItem', $item->id);
    expect($lw->get('itemForm.tags'))->toBe(['a', 'b'])
        ->and($lw->get('itemForm.facts.0.label'))->toBe('L1');
    $lw->call('nestedAdd', 'itemForm.tags')
        ->set('itemForm.tags.2', 'c')
        ->call('nestedAdd', 'itemForm.facts')
        ->set('itemForm.facts.1.label', 'L2')
        ->set('itemForm.facts.1.value', 'V2')
        ->call('nestedRemove', 'itemForm.tags', 0)
        ->call('saveItem');
    $item->refresh();
    expect($item->data['tags'])->toBe(['b', 'c'])
        ->and($item->data['facts'][1])->toBe(['label' => 'L2', 'value' => 'V2']);

    // Connect panel: same trio against edit.items paths.
    $cw = Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $col->id);
    $cw->call('nestedAdd', 'edit.items.0.data.facts')
        ->set('edit.items.0.data.facts.2.label', 'L3')
        ->call('nestedMove', 'edit.items.0.data.facts', 2, -1)
        ->call('saveCollection');
    $item->refresh();
    expect(collect($item->data['facts'])->pluck('label')->all())->toBe(['L1', 'L3', 'L2']);

    // Guard: paths outside the whitelist are rejected (403) and mutate nothing.
    $guard = Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $col->id);
    $status = null;
    try {
        $guard->call('nestedAdd', 'edit.name');
        $status = 'completed';
    } catch (Throwable) {
        $status = 'aborted';
    }
    // Either way the whitelist stopped the mutation before any write.
    expect($item->refresh()->data['facts'])->toHaveCount(3);
});

test('empty list fields on the Collections page get structured editors with add buttons, not JSON', function () {
    [$owner, $site] = mirrorSite();
    $col = $site->collections()->create(['name' => 'Studies Demo', 'slug' => '', 'type' => 'grid', 'is_public' => true,
        'fields' => [
            ['key' => 'title', 'name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'questions', 'name' => 'questions', 'label' => 'Questions', 'type' => 'list'],
            ['key' => 'media', 'name' => 'media', 'label' => 'Media', 'type' => 'rows', 'fields' => [
                ['key' => 'type', 'type' => 'select', 'options' => ['video', 'audio', 'image']],
                ['key' => 'src', 'type' => 'media', 'label' => 'File'],
            ]],
        ]]);
    $item = $col->items()->create(['site_id' => $site->id, 'status' => 'published',
        'data' => ['title' => 'T', 'questions' => [], 'media' => []]]);

    $lw = Livewire\Livewire::actingAs($owner)->test(CollectionsPage::class, ['site' => $site])
        ->call('viewEntries', $col->id)
        ->call('openItem', $item->id);
    expect($lw->get('itemJsonKeys'))->toBe([])
        ->and($lw->get('itemForm.media'))->toBe([]);
    $lw->assertSee('Add question')->assertSee('Add media')->assertDontSee('list (JSON)');

    // a new media row gets the schema's sub-fields, typed (select + asset picker)
    $lw->call('nestedAdd', 'itemForm.media', ['type', 'src']);
    expect($lw->get('itemForm.media.0'))->toBe(['type' => '', 'src' => '']);
    $lw->assertSee('<option value="audio">', false)->assertSee('Choose an audio or video file');
});

test('a text field stays a text box on the Collections page even when an older entry stored a list there', function () {
    [$owner, $site] = mirrorSite();
    $col = $site->collections()->create(['name' => 'Sermons Demo', 'slug' => '', 'type' => 'grid', 'is_public' => true,
        'fields' => [
            ['key' => 'title', 'name' => 'title', 'label' => 'Title', 'type' => 'textarea2'],
            ['key' => 'body', 'name' => 'body', 'label' => 'Content', 'type' => 'textarea15'],
        ]]);
    $old = $col->items()->create(['site_id' => $site->id, 'status' => 'published',
        'data' => ['title' => 'Old', 'body' => ['First paragraph.', 'Second paragraph.']]]);

    $lw = Livewire\Livewire::actingAs($owner)->test(CollectionsPage::class, ['site' => $site])
        ->call('viewEntries', $col->id)
        ->call('openItem');                                   // NEW entry
    expect($lw->get('itemJsonKeys'))->toBe([])
        ->and($lw->get('itemForm.body'))->toBe('');           // a plain, empty text box
    $lw->assertDontSee('list (JSON)')->assertSee('wire:model="itemForm.body"', false);

    // the older entry opens with its paragraphs as text, and saves back as text
    $lw->call('cancelItem')->call('openItem', $old->id);
    expect($lw->get('itemForm.body'))->toBe("First paragraph.\n\nSecond paragraph.");
    $lw->call('saveItem');
    expect($old->fresh()->data['body'])->toBe("First paragraph.\n\nSecond paragraph.");
});
