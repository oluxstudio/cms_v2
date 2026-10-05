<?php

use App\Livewire\ConnectReviewPage;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use App\Models\User;
use App\Services\TemplateInstaller;
use App\Support\TemplatePaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

function gridSite(): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'api', 'source' => 'api']);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Heading', 'type' => 'text', 'value' => 'Hello', 'order' => 0]);
    $mk = function (string $slug, array $fields, array $rows) use ($site) {
        $col = Collection::create(['site_id' => $site->id, 'name' => Str::headline($slug), 'slug' => $slug, 'type' => 'grid', 'is_public' => true, 'fields' => $fields]);
        foreach ($rows as $i => $data) {
            CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id, 'status' => 'published', 'data' => $data, 'position' => $i]);
        }

        return $col;
    };
    $contact = $mk('contact-info', [['key' => 'label', 'name' => 'label', 'type' => 'text']], [['label' => 'Email'], ['label' => 'Phone']]);
    $words = $mk('hero-words', [['key' => 'word', 'name' => 'word', 'type' => 'text']], [['word' => 'Web'], ['word' => 'Apps']]);

    return [$user, $site, $hero, $contact, $words, $mk];
}

test('a component panel shows a grid for its data source and for every collection field', function () {
    [$user, $site, $hero, $contact, $words] = gridSite();
    $hero->update(['collection_id' => $contact->id]);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Hero Words', 'type' => 'collection', 'value' => $words->id, 'order' => 1]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id);

    $lists = $lw->get('edit.lists');
    expect(array_keys($lists))->toEqualCanonicalizing([(string) $contact->id, (string) $words->id])
        ->and($lists[(string) $words->id]['count'])->toBe(2)
        ->and(collect($lists[(string) $words->id]['items'])->pluck('label')->all())->toBe(['Web', 'Apps'])
        ->and($lw->get('edit.collection.id'))->toBe($contact->id);
    $lw->assertSee('Data source — Contact Info')->assertSee('▦ Hero Words');

    // A tile opens that entry in its collection.
    $lw->call('openCollectionItem', $words->id, 1)
        ->assertSet('selectedKind', 'collection')
        ->assertSet('selectedId', $words->id)
        ->assertDispatched('olx-editor-focus', target: 'item', index: 1);
});

test('nested list fields edit structurally even when empty, and new entries start as lists', function () {
    [$user, $site, , , , $mk] = gridSite();
    $plans = $mk('pricing-plans', [
        ['key' => 'name', 'name' => 'name', 'type' => 'text'],
        ['key' => 'features', 'name' => 'features', 'type' => 'list'],
        ['key' => 'links', 'name' => 'links', 'type' => 'rows', 'fields' => [['key' => 'label'], ['key' => 'href']]],
    ], [['name' => 'Starter', 'features' => [], 'links' => []]]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $plans->id)
        ->assertSet('edit.fieldDefs.features.type', 'list')
        ->assertSet('edit.fieldDefs.links.fields', ['label', 'href'])
        ->assertSee('+ add feature')
        ->assertSee('+ add link');

    // The first row of an empty rows list gets the schema's sub-fields.
    $lw->call('nestedAdd', 'edit.items.0.data.links', ['label', 'href'])
        ->call('nestedAdd', 'edit.items.0.data.features')
        ->set('edit.items.0.data.features.0', 'Up to 3 pages')
        ->call('saveCollection');
    $data = $plans->items()->first()->data;
    expect($data['links'])->toEqual([['label' => '', 'href' => '']])
        ->and($data['features'])->toBe(['Up to 3 pages']);

    // New entries carry empty lists, not strings.
    $firstId = $plans->items()->first()->id;
    $lw->call('addItem');
    $new = $plans->items()->where('id', '!=', $firstId)->first()->data;
    expect($new['features'])->toBe([])->and($new['links'])->toBe([]);
});

test('a list field can be added to a collection, seeded from its default', function () {
    [$user, $site, , $contact] = gridSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $contact->id)
        ->set('newField.label', 'Hours')
        ->set('newField.type', 'list')
        ->set('newField.default', "Mon–Fri 9–5\nSat 10–2")
        ->call('addCollectionField')
        ->assertSet('edit.fieldDefs.hours.type', 'list');

    expect($contact->items()->get()->every(fn ($i) => $i->data['hours'] === ['Mon–Fri 9–5', 'Sat 10–2']))->toBeTrue();
});

test('installing a template turns a block\'s extra lists into collection fields', function () {
    [, $site, $hero, $contact, $words] = gridSite();
    $key = TemplatePaths::UPLOAD_PREFIX.Str::lower(Str::random(10));
    $app = TemplatePaths::appDir($key).'/app';
    File::ensureDirectoryExists("$app/components");
    File::ensureDirectoryExists("$app/composables");
    File::put("$app/composables/useSiteData.ts", "export const useHeroWords = () => { const { items } = useCms(); return computed(() => items('hero-words', [])) }\n"
        ."export const useContactInfo = () => { const { items } = useCms(); return computed(() => items('contact-info', [])) }\n");
    File::put("$app/components/HeroBlock.vue", "<script setup lang=\"ts\">\nconst words = useHeroWords()\nconst tiles = useContactInfo()\n</script>\n");

    try {
        $link = new ReflectionMethod(TemplateInstaller::class, 'linkComponentCollections');
        $link->invoke(app(TemplateInstaller::class), $site, $key);
        $link->invoke(app(TemplateInstaller::class), $site, $key); // idempotent
    } finally {
        File::deleteDirectory(TemplatePaths::uploadsRoot().'/'.$key);
    }

    $hero->refresh();
    $fields = $hero->nodes()->where('type', 'collection')->get();
    expect($hero->collection_id)->toBe($words->id) // first accessor used = primary data source
        ->and($fields)->toHaveCount(1)
        ->and($fields->first()->value)->toBe((string) $contact->id)
        ->and($fields->first()->label)->toBe('Contact Info');
});
