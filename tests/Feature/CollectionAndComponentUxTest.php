<?php

use App\Livewire\CollectionsPage;
use App\Livewire\ComponentsPage;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function uxSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'ux-'.uniqid(),
        'domain' => 'ux.test', 'owner' => $owner->name, 'description' => 't',
    ]);

    return [$owner, $site];
}

test('a collection entry can be added and edited from the entries panel', function () {
    [$owner, $site] = uxSite();
    $collection = $site->collections()->create([
        'name' => 'Stats', 'slug' => 'stats', 'type' => 'list', 'is_public' => true,
        'fields' => [['key' => 'value', 'label' => 'Value', 'type' => 'text'], ['key' => 'label', 'label' => 'Label', 'type' => 'text']],
    ]);

    $c = Livewire::actingAs($owner)->test(CollectionsPage::class, ['site' => $site])
        ->call('viewEntries', $collection->id)
        ->call('openItem')                       // new entry
        ->set('itemForm.value', '99+')
        ->set('itemForm.label', 'Launches')
        ->call('saveItem');

    $item = $collection->items()->first();
    expect($item->data)->toMatchArray(['value' => '99+', 'label' => 'Launches']);

    // Edit it.
    $c->call('openItem', $item->id)
        ->set('itemForm.value', '120+')
        ->call('saveItem');
    expect($collection->items()->first()->data['value'])->toBe('120+')
        ->and($collection->items()->count())->toBe(1);
});

test('the components page filters by tag and switches layout mode', function () {
    [$owner, $site] = uxSite();
    $a = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'T', 'tags' => ['marketing']]);
    Node::create(['component_id' => $a->id, 'label' => 'H', 'type' => 'text', 'value' => 'x', 'parent' => '0', 'order' => 0]);
    $b = Component::create(['site_id' => $site->id, 'name' => 'Footer', 'author' => 'T', 'tags' => ['layout']]);
    Node::create(['component_id' => $b->id, 'label' => 'H', 'type' => 'text', 'value' => 'y', 'parent' => '0', 'order' => 0]);

    $c = Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site]);

    // Tag chips reflect both tags; filtering narrows the list.
    expect($c->get('componentTags'))->toContain('marketing')->toContain('layout');
    $c->call('setTag', 'marketing');
    expect($c->get('components')->pluck('name')->all())->toBe(['Hero']);
    $c->call('setTag', 'marketing'); // toggle off
    expect($c->get('components')->count())->toBe(2);

    // Layout mode persists on the site.
    $c->call('setViewMode', 'compact');
    expect($c->get('viewMode'))->toBe('compact')
        ->and($site->fresh()->getAttr('layout:components'))->toBe('compact');
});
