<?php

use App\Livewire\CollectionDetailPage;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Site;
use App\Models\User;
use App\Support\CollectionAutoFields;
use App\Support\SiteProperties;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

/** A site with an "Orders" collection: two typed fields, two older entries. */
function autoSite(): array
{
    $user = User::factory()->create(['name' => 'Ada Owner']);
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'af-'.uniqid().'.test']);
    $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Orders', 'slug' => 'orders-'.uniqid(), 'type' => 'grid', 'is_public' => true,
        'fields' => [['key' => 'title', 'label' => 'Title', 'type' => 'text'], ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea']]]);
    foreach (['First', 'Second'] as $i => $t) {
        $item = CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id, 'data' => ['title' => $t, 'notes' => 'n'], 'position' => $i, 'status' => 'published']);
        DB::table('collection_items')->where('id', $item->id)->update(['created_at' => now()->subDays(5 - $i)->setTime(9, 30), 'updated_at' => now()->subDays(5 - $i)->setTime(9, 30)]);
    }

    return [$user, $site, $col];
}

test('Edit fields can make a field system-filled or hidden; existing entries are back-filled in order', function () {
    [$user, $site, $col] = autoSite();

    Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('openFields')
        ->call('addFieldRow')->set('fieldRows.2.label', 'Order no')->set('fieldRows.2.auto', 'number')
        ->call('addFieldRow')->set('fieldRows.3.label', 'Created')->set('fieldRows.3.auto', 'created_at')->set('fieldRows.3.required', true)
        ->call('addFieldRow')->set('fieldRows.4.label', 'Edited by')->set('fieldRows.4.auto', 'updated_by')
        ->set('fieldRows.1.hidden', true)
        ->call('saveFields')->assertHasNoErrors();

    $fields = collect($col->fresh()->fields)->keyBy('key');
    expect($fields['order_no']['auto'])->toBe('number')
        ->and($fields['created']['auto'])->toBe('created_at')
        ->and($fields['created'])->not->toHaveKey('required')         // system fields are never "required"
        ->and($fields['notes']['hidden'])->toBeTrue();

    $items = $col->items()->orderBy('created_at')->get();
    expect($items->pluck('data.order_no')->all())->toBe([1, 2])
        ->and($items[0]->data['created'])->toBe(now()->subDays(5)->setTime(9, 30)->format('Y-m-d H:i'))   // their own date, not now
        ->and($items[0]->data['edited_by'])->toBe('System');
});

test('a new entry gets the next number, the dates and who made it; editing refreshes only the "updated" fields', function () {
    [$user, $site, $col] = autoSite();
    $col->update(['fields' => array_merge($col->fields, [
        ['key' => 'no', 'label' => 'No', 'type' => 'number', 'auto' => 'number'],
        ['key' => 'created', 'label' => 'Created', 'type' => 'text', 'auto' => 'created_at'],
        ['key' => 'updated', 'label' => 'Updated', 'type' => 'text', 'auto' => 'updated_at'],
        ['key' => 'by', 'label' => 'By', 'type' => 'text', 'auto' => 'created_by'],
        ['key' => 'last', 'label' => 'Last', 'type' => 'text', 'auto' => 'updated_by'],
    ])]);
    CollectionAutoFields::backfill($col->fresh());

    $this->travelTo(now()->setTime(14, 5));
    $page = Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('openItem')->set('itemForm.title', 'Third')
        ->call('saveItem')->assertHasNoErrors();
    $new = $col->items()->where('data->title', 'Third')->first();
    expect($new->data['no'])->toBe(3)
        ->and($new->data['created'])->toBe(now()->format('Y-m-d H:i'))
        ->and($new->data['by'])->toBe('Ada Owner')
        ->and($new->data['last'])->toBe('Ada Owner');

    $editor = User::factory()->create(['name' => 'Ben Editor']);
    $this->travelTo(now()->addHours(2));
    $this->actingAs($editor);
    // An edit can't change what the system set once (e.g. via the API).
    $new->update(['data' => array_merge($new->fresh()->data, ['title' => 'Third (edited)', 'no' => 999, 'by' => 'Mallory'])]);
    $new->refresh();
    expect($new->data['no'])->toBe(3)
        ->and($new->data['created'])->toBe(now()->subHours(2)->format('Y-m-d H:i'))
        ->and($new->data['updated'])->toBe(now()->format('Y-m-d H:i'))
        ->and($new->data['by'])->toBe('Ada Owner')
        ->and($new->data['last'])->toBe('Ben Editor');

    // Reordering is not an edit.
    $before = $new->data;
    $new->update(['position' => 99]);
    expect($new->fresh()->data)->toBe($before);
});

test('public submissions are numbered and signed "Website visitor"; deleted numbers are never reused', function () {
    [, $site, $col] = autoSite();
    $col->update(['fields' => array_merge($col->fields, [
        ['key' => 'no', 'label' => 'No', 'type' => 'number', 'auto' => 'number'],
        ['key' => 'by', 'label' => 'By', 'type' => 'text', 'auto' => 'created_by'],
    ])]);
    CollectionAutoFields::backfill($col->fresh());
    $col->items()->orderByDesc('created_at')->first()->delete();   // #2 → trash

    $item = CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id, 'data' => ['title' => 'From the site'], 'status' => 'pending']);
    expect($item->data['no'])->toBe(3)
        ->and($item->data['by'])->toBe(app()->runningInConsole() ? 'System' : 'Website visitor');
});

test('a field filled from a Site Property always shows the current value', function () {
    [$user, $site, $col] = autoSite();
    SiteProperties::save($site, ['rows' => ['phones' => [['label' => 'Main', 'value' => '+44 113 000 0000']]]]);
    $col->update(['fields' => array_merge($col->fields, [['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'auto' => 'property:phone']])]);
    CollectionAutoFields::backfill($col->fresh());

    expect($col->items()->first()->data['phone'])->toBe('{{phone}}');
    $api = $this->getJson("/api/sites/{$site->name}/collections")->assertOk()->json();
    expect(json_encode($api))->toContain('+44 113 000 0000');

    expect(CollectionAutoFields::options($site))->toHaveKey('property:phone')
        ->and(CollectionAutoFields::valid('property:phone'))->toBeTrue()
        ->and(CollectionAutoFields::valid('nonsense'))->toBeFalse();
});

test('the entry form hides hidden fields, shows system fields read-only, and keeps hidden values on save', function () {
    [$user, $site, $col] = autoSite();
    $col->update(['fields' => [
        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['key' => 'secret', 'label' => 'Internal ref', 'type' => 'text', 'hidden' => true, 'required' => true],
        ['key' => 'no', 'label' => 'Order no', 'type' => 'number', 'auto' => 'number'],
    ]]);
    $item = $col->items()->first();
    $item->update(['data' => ['title' => 'First', 'secret' => 'REF-7']]);

    Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('editItem', $item->id)
        ->assertSeeHtml('itemForm.title')
        ->assertDontSeeHtml('itemForm.secret')
        ->assertSee('Order no')
        ->assertSee('Filled automatically')
        ->set('itemForm.title', 'First!')
        ->call('saveItem')->assertHasNoErrors();               // hidden + required doesn't block

    expect($item->fresh()->data['secret'])->toBe('REF-7')
        ->and($item->fresh()->data['title'])->toBe('First!');
});
