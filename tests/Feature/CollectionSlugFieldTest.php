<?php

use App\Livewire\CollectionDetailPage;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Site;
use App\Models\User;
use App\Services\CollectionSourceExtractor;
use App\Services\TemplateInstaller;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

// Reusable field types: a slug made from other fields (unique, rebuilt on
// save), on/off toggles, date & time — declared by a template or picked in
// "Edit fields", with stored values following a renamed / retyped field.

function slugSite(array $fields = []): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'slug-'.uniqid().'.test']);
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Events', 'slug' => 'events-'.uniqid(), 'type' => 'grid', 'is_public' => true,
        'fields' => $fields ?: [
            ['key' => 'title', 'name' => 'title', 'type' => 'text', 'label' => 'Title'],
            ['key' => 'slug', 'name' => 'slug', 'type' => 'slug', 'label' => 'Slug', 'auto' => 'slug:title+date'],
            ['key' => 'date', 'name' => 'date', 'type' => 'datetime', 'label' => 'Date'],
            ['key' => 'featured', 'name' => 'featured', 'type' => 'toggle', 'label' => 'Featured'],
        ]]);

    return [$user, $site, $col];
}

function slugEntry(Collection $col, array $data): CollectionItem
{
    return CollectionItem::create(['collection_id' => $col->id, 'site_id' => $col->site_id, 'data' => $data, 'status' => 'published']);
}

it('reads slug, toggle and renamed fields from a template', function () {
    $root = storage_path('framework/testing/slug-fields-'.uniqid());
    File::ensureDirectoryExists("$root/app/composables");
    File::put("$root/app/composables/useData.ts", <<<'TS'
/** @olux-collection Events
 * @olux-field title text required
 * @olux-field slug slug from=title+date was=id label="Slug (web address)"
 * @olux-field code slug
 * @olux-field date datetime
 * @olux-field featured toggle
 */
const events = [
	{ slug: 'one-2026-10-18', title: 'One', date: '2026-10-18T19:00', featured: true },
]
TS);
    $cols = collect(app(CollectionSourceExtractor::class)->fromSources($root))->keyBy('name');
    File::deleteDirectory($root);

    $f = collect($cols['Events']['fields'])->keyBy('key');
    expect($f['slug']['type'])->toBe('slug')
        ->and($f['slug']['auto'])->toBe('slug:title+date')
        ->and($f['slug']['was'])->toBe('id')
        ->and($f['slug']['label'])->toBe('Slug (web address)')
        ->and($f['slug'])->not->toHaveKey('optionsFrom')
        ->and($f['code']['auto'])->toBe('slug:title')      // no from= → made from the title
        ->and($f['date']['type'])->toBe('datetime')
        ->and($f['featured']['type'])->toBe('toggle');
});

it('fills a slug from the title and the date (Y-m-d), unique in the collection, rebuilt on save', function () {
    [, , $col] = slugSite();

    $a = slugEntry($col, ['title' => 'Worship Night', 'date' => '2026-10-18T19:00']);
    $b = slugEntry($col, ['title' => 'Worship Night', 'date' => '2026-10-18T21:30']);
    $c = slugEntry($col, ['title' => "Women's Brunch & Talk", 'date' => '2026-11-14T10:00']);
    $d = slugEntry($col, ['title' => '', 'date' => '']);

    expect($a->data['slug'])->toBe('worship-night-2026-10-18')
        ->and($b->data['slug'])->toBe('worship-night-2026-10-18-2')   // same title + day → made unique
        ->and($c->data['slug'])->toBe('womens-brunch-talk-2026-11-14')
        ->and($d->data['slug'] ?? '')->toBe('');                         // nothing to make it from yet

    // Typed values are never kept: the slug follows the title and date.
    $a->update(['data' => ['title' => 'Worship & Prayer Night', 'date' => '2026-10-19T19:00', 'slug' => 'typed-by-hand']]);
    expect($a->fresh()->data['slug'])->toBe('worship-prayer-night-2026-10-19');

    // Deleted entries keep theirs reserved (restoring one never clashes).
    $b->delete();
    expect(slugEntry($col, ['title' => 'Worship Night', 'date' => '2026-10-18T08:00'])->data['slug'])->toBe('worship-night-2026-10-18');
});

it('moves a renamed field\'s values and converts retyped values when a template is re-applied', function () {
    [, $site, $col] = slugSite([
        ['key' => 'id', 'name' => 'id', 'type' => 'text', 'label' => 'Id', 'hidden' => true],
        ['key' => 'title', 'name' => 'title', 'type' => 'text', 'label' => 'Title'],
        ['key' => 'date', 'name' => 'date', 'type' => 'date', 'label' => 'Date'],
        ['key' => 'price', 'name' => 'price', 'type' => 'select', 'label' => 'Price', 'options' => ['0', '10']],
        ['key' => 'featured', 'name' => 'featured', 'type' => 'select', 'label' => 'Featured', 'options' => ['yes', 'no']],
    ]);
    $one = slugEntry($col, ['id' => 'worshipnight', 'title' => 'Worship Night', 'date' => '2026-10-18T19:00', 'price' => '10', 'featured' => 'yes']);
    $two = slugEntry($col, ['id' => 'camp', 'title' => 'Autumn Camp', 'date' => '2026-10-17', 'price' => '0', 'featured' => 'no']);

    $def = ['name' => 'Events', 'fields' => [
        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'declared' => true],
        ['key' => 'slug', 'label' => 'Slug (web address)', 'type' => 'slug', 'auto' => 'slug:title+date', 'was' => 'id', 'required' => false, 'declared' => true],
        ['key' => 'date', 'label' => 'Date', 'type' => 'datetime', 'required' => false, 'declared' => true],
        ['key' => 'price', 'label' => 'Price', 'type' => 'number', 'required' => false, 'declared' => true],
        ['key' => 'featured', 'label' => 'Featured', 'type' => 'toggle', 'required' => false, 'declared' => true],
    ], 'items' => []];
    $apply = new ReflectionMethod(TemplateInstaller::class, 'applyCollections');
    $apply->invoke(app(TemplateInstaller::class), $site, [$def], true, []);

    $fields = collect($col->fresh()->fields)->keyBy('key');
    expect($fields->has('id'))->toBeFalse()
        ->and(array_keys($fields->all())[0])->toBe('slug')                // same place the id had
        ->and($fields['slug']['type'])->toBe('slug')->and($fields['slug']['auto'])->toBe('slug:title+date')
        ->and($fields['slug'])->not->toHaveKey('hidden')                  // the template's definition, not the old id's
        ->and($fields['date']['type'])->toBe('datetime')
        ->and($fields['price']['type'])->toBe('number')->and($fields['price'])->not->toHaveKey('options')
        ->and($fields['featured']['type'])->toBe('toggle');

    $one = $one->fresh()->data;
    $two = $two->fresh()->data;
    expect($one)->not->toHaveKey('id')
        ->and($one['slug'])->toBe('worship-night-2026-10-18')
        ->and($two['slug'])->toBe('autumn-camp-2026-10-17')
        ->and($one['featured'])->toBeTrue()->and($two['featured'])->toBeFalse()
        ->and($one['price'])->toBe(10)->and($two['price'])->toBe(0);
});

it('renames into a key an earlier update already added, while it is still empty', function () {
    [, $site, $col] = slugSite([
        ['key' => 'id', 'name' => 'id', 'type' => 'text', 'label' => 'Id', 'hidden' => true],
        ['key' => 'title', 'name' => 'title', 'type' => 'text', 'label' => 'Title'],
        ['key' => 'slug', 'name' => 'slug', 'type' => 'text', 'label' => 'Slug'],
    ]);
    $one = slugEntry($col, ['id' => 'worshipnight', 'title' => 'Worship Night']);
    $def = ['name' => 'Events', 'fields' => [
        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'declared' => true],
        ['key' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'auto' => 'slug:title', 'was' => 'id', 'declared' => true],
    ], 'items' => []];
    (new ReflectionMethod(TemplateInstaller::class, 'applyCollections'))->invoke(app(TemplateInstaller::class), $site, [$def], true, []);

    expect(collect($col->fresh()->fields)->pluck('key')->all())->toBe(['slug', 'title'])
        ->and($one->fresh()->data)->not->toHaveKey('id')
        ->and($one->fresh()->data['slug'])->toBe('worship-night');
});

it('picks a slug\'s fields in Edit fields and keeps types it cannot pick', function () {
    [$user, $site, $col] = slugSite([
        ['key' => 'title', 'name' => 'title', 'type' => 'text', 'label' => 'Title'],
        ['key' => 'date', 'name' => 'date', 'type' => 'datetime', 'label' => 'Date'],
        ['key' => 'featured', 'name' => 'featured', 'type' => 'select', 'label' => 'Featured', 'options' => ['yes', 'no']],
        ['key' => 'media', 'name' => 'media', 'type' => 'rows', 'label' => 'Media', 'fields' => [['key' => 'src', 'type' => 'media']]],
    ]);
    $entry = slugEntry($col, ['title' => 'City Serve Day', 'date' => '2026-11-07T09:00', 'featured' => 'yes']);

    $page = Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id, 'screen' => 'fields'])
        ->assertSet('fieldRows.1.type', 'datetime')
        ->assertSet('fieldRows.3.type', 'rows')                          // not reset to text
        ->assertSeeHtml('value="rows"')
        ->call('addFieldRow')
        ->set('fieldRows.4.label', 'Slug')
        ->set('fieldRows.4.type', 'slug')
        ->assertSee('Made from');
    // A slug needs at least one field to be made from.
    $page->call('saveFields')->assertHasErrors('fieldRows.4.slugFrom');
    $page->set('fieldRows.4.slugFrom', ['title', 'date'])
        ->set('fieldRows.2.type', 'toggle')
        ->call('saveFields')->assertHasNoErrors();

    $fields = collect($col->fresh()->fields)->keyBy('key');
    expect($fields['slug']['type'])->toBe('slug')
        ->and($fields['slug']['auto'])->toBe('slug:title+date')
        ->and($fields['slug'])->not->toHaveKey('required')
        ->and($fields['media']['type'])->toBe('rows')->and($fields['media']['fields'])->toHaveCount(1)
        ->and($fields['featured']['type'])->toBe('toggle');
    // Existing entries: slug filled, "yes" became on.
    expect($entry->fresh()->data['slug'])->toBe('city-serve-day-2026-11-07')
        ->and($entry->fresh()->data['featured'])->toBeTrue();
});

it('shows the slug read-only and dates with a date & time picker in the entry form', function () {
    [$user, $site, $col] = slugSite();
    $entry = slugEntry($col, ['title' => 'Worship Night', 'date' => '2026-10-18T19:00', 'featured' => 'yes']);

    Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id, 'entry' => $entry->id, 'screen' => 'edit'])
        ->assertSeeHtml('type="datetime-local"')
        ->assertDontSeeHtml('wire:model="itemForm.slug"')
        ->assertSee('worship-night-2026-10-18')
        ->assertSee('Slug from Title + Date')
        ->assertSet('itemForm.featured', true)                          // an older "yes" edits as on
        ->set('itemForm.title', 'Worship Night Live')
        ->call('saveItem');

    expect($entry->fresh()->data['slug'])->toBe('worship-night-live-2026-10-18')
        ->and($entry->fresh()->data['featured'])->toBeTrue();
});
