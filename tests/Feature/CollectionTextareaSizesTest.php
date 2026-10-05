<?php

use App\Livewire\CollectionDetailPage;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Site;
use App\Models\User;
use App\Services\CollectionSourceExtractor;
use App\Support\CollectionEntryLayout;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

/** A site with a "Notes" collection holding one entry; $fields replaces the schema. */
function sizedCollection(array $fields, array $data): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'ta-'.uniqid().'.test']);
    $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Notes', 'slug' => 'notes-'.uniqid(), 'type' => 'grid', 'fields' => $fields]);
    $item = CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id, 'data' => $data, 'status' => 'published']);

    return [$user, $site, $col, $item];
}

test('textarea2, textarea, textarea6, textarea10 and textarea15 edit with 2, 4, 6, 10 and 15 rows', function () {
    expect(array_map(fn ($t) => Collection::textareaRows($t), ['textarea2', 'textarea', 'textarea6', 'textarea10', 'textarea15']))
        ->toBe([2, 4, 6, 10, 15])
        ->and(Collection::textareaRows('text'))->toBeNull()
        ->and(Collection::FIELD_TYPES)->toContain('textarea10')->toContain('toggle')
        ->and(CollectionDetailPage::FIELD_TYPES)->toHaveKeys(['textarea2', 'textarea6', 'textarea10', 'textarea15', 'toggle'])
        ->and(CollectionSourceExtractor::DECLARED_TYPES)->toContain('textarea15')->toContain('toggle');

    [$user, $site, $col, $item] = sizedCollection(
        [['key' => 'tiny', 'label' => 'Tiny', 'type' => 'textarea2'], ['key' => 'long', 'label' => 'Long', 'type' => 'textarea15']],
        ['tiny' => 'a', 'long' => 'b'],
    );

    $html = Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('editItem', $item->id)->html();
    expect($html)->toMatch('/wire:model\.blur="itemForm\.tiny"\s+rows="2"/')
        ->toMatch('/wire:model\.blur="itemForm\.long"\s+rows="15"/');

    // The Edit fields picker can switch a field to another size.
    Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('openFields')->set('fieldRows.0.type', 'textarea10')->call('saveFields')->assertHasNoErrors();
    expect($col->fresh()->fields[0]['type'])->toBe('textarea10');
});

test('a toggle field is an on/off switch that stores true/false, like a checkbox', function () {
    [$user, $site, $col, $item] = sizedCollection(
        [['key' => 'title', 'label' => 'Title', 'type' => 'text'], ['key' => 'featured', 'label' => 'Featured', 'type' => 'toggle']],
        ['title' => 'One', 'featured' => false],
    );

    $page = Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('editItem', $item->id)
        ->assertSet('itemForm.featured', false);
    expect($page->html())->toContain('bkf-switch');

    $page->set('itemForm.featured', true)->call('saveItem')->assertHasNoErrors();
    expect($item->fresh()->data['featured'])->toBeTrue();

    // "1" / "0" strings (e.g. from an import) come back as real booleans for the switch.
    $item->update(['data' => ['title' => 'One', 'featured' => '1']]);
    Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('editItem', $item->id)->assertSet('itemForm.featured', true);

    expect($col->buildValidationRules()['featured'])->toBe(['nullable', 'boolean']);
});

test('the edit form lists fields in the same order as view mode', function () {
    [$user, $site, $col, $item] = sizedCollection([
        ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ['key' => 'tags', 'label' => 'Tags', 'type' => 'tags'],
        ['key' => 'price', 'label' => 'Price', 'type' => 'number'],
        ['key' => 'photo', 'label' => 'Photo', 'type' => 'image'],
        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
    ], [
        'notes' => str_repeat('A long description of the service. ', 4),
        'tags' => ['cut', 'colour'],
        'price' => 45,
        'photo' => 'https://example.test/cut.jpg',
        'title' => 'Cut & finish',
    ]);

    // View mode: title is the heading, then media → facts → chips → text.
    $sections = CollectionEntryLayout::sections($col->fields, $item->data, $site);
    expect($sections['heading'])->toBe('title')
        ->and(collect($sections['media'])->pluck('key')->all())->toBe(['photo'])
        ->and(collect($sections['facts'])->pluck('key')->all())->toBe(['price'])
        ->and(collect($sections['chips'])->pluck('key')->all())->toBe(['tags'])
        ->and(collect($sections['text'])->pluck('key')->all())->toBe(['notes']);

    $html = Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('editItem', $item->id)->html();
    $pos = fn ($k) => strpos($html, 'itemForm.'.$k);
    expect($pos('title'))->toBeLessThan($pos('photo'))
        ->and($pos('photo'))->toBeLessThan($pos('price'))
        ->and($pos('price'))->toBeLessThan($pos('tags'))
        ->and($pos('tags'))->toBeLessThan($pos('notes'));

    // A new (empty) entry is ordered by field type the same way.
    expect(CollectionEntryLayout::order($col->fields, [], $site))->toBe(['title', 'photo', 'price', 'tags', 'notes']);
});
