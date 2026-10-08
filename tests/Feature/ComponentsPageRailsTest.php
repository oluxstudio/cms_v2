<?php

use App\Livewire\ComponentsPage;
use App\Models\Collection;
use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

/**
 * A site with four components covering every rail bucket:
 *   Hero     — live on Home, 2 fields (1 empty), tagged
 *   Team     — live on Home + About, data source collection, 3 filled fields
 *   Parked   — only on a page of a non-current template (inactive)
 *   Loose    — on no page, no fields
 */
function componentsRailsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'rails-'.uniqid(),
        'domain' => 'rails.test', 'owner' => $owner->name, 'description' => 't',
    ]);
    $home = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $about = Page::create(['site_id' => $site->id, 'name' => 'About', 'url' => '/about', 'keywords' => '', 'is_published' => true]);
    $old = Page::create(['site_id' => $site->id, 'name' => 'Old landing', 'url' => '/old', 'keywords' => '', 'is_published' => true, 'template_active' => false]);
    $col = Collection::create(['site_id' => $site->id, 'name' => 'People', 'type' => 'list', 'is_public' => true]);

    $node = fn ($c, $label, $value, $i = 0) => Node::create(['component_id' => $c->id, 'label' => $label, 'type' => 'text', 'value' => $value, 'parent' => '0', 'order' => $i]);

    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'T', 'source' => 'app', 'tags' => ['marketing']]);
    $node($hero, 'Heading', 'Hi', 0);
    $node($hero, 'Sub', '', 1);
    $hero->pages()->attach($home->id, ['order' => 0]);

    $team = Component::create(['site_id' => $site->id, 'name' => 'Team', 'author' => 'T', 'source' => 'api', 'collection_id' => $col->id]);
    $node($team, 'A', 'a', 0);
    $node($team, 'B', 'b', 1);
    $node($team, 'C', 'c', 2);
    $team->pages()->attach([$home->id => ['order' => 1], $about->id => ['order' => 0]]);

    $parked = Component::create(['site_id' => $site->id, 'name' => 'Parked', 'author' => 'T']);
    $node($parked, 'X', 'x');
    $parked->pages()->attach($old->id, ['order' => 0]);

    $loose = Component::create(['site_id' => $site->id, 'name' => 'Loose', 'author' => 'T']);

    // Make "recently edited" deterministic: Loose newest, Hero oldest.
    foreach (['Hero' => 4, 'Team' => 3, 'Parked' => 2, 'Loose' => 1] as $name => $daysAgo) {
        $c = Component::where('site_id', $site->id)->where('name', $name)->first();
        DB::table('components')->where('id', $c->id)->update(['updated_at' => now()->subDays($daysAgo)]);
        DB::table('nodes')->where('component_id', $c->id)->update(['updated_at' => now()->subDays($daysAgo)]);
    }

    return [$owner, $site, compact('hero', 'team', 'parked', 'loose', 'col')];
}

test('the rails count components, placements, data sources, empty fields and inactive ones', function () {
    [$owner, $site] = componentsRailsSite();

    $stats = Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])->viewData('stats');

    expect($stats['total'])->toBe(4)
        ->and($stats['used'])->toBe(2)          // Hero, Team
        ->and($stats['unused'])->toBe(1)        // Loose
        ->and($stats['inactive'])->toBe(1)      // Parked
        ->and($stats['source'])->toBe(1)        // Team
        ->and($stats['fields'])->toBe(6)
        ->and($stats['empty'])->toBe(1)
        ->and($stats['emptyComponents'])->toBe(1)
        ->and($stats['attention'])->toBe(3)     // Hero (empty), Parked (inactive), Loose (no fields)
        ->and($stats['placements'])->toBe(3)
        ->and($stats['mostUsed']->first()->name)->toBe('Team')
        ->and($stats['recentlyEdited']->first()->name)->toBe('Loose')
        ->and($stats['byTag'])->toBe(['marketing' => 1]);
});

test('each filter narrows the listing', function (string $filter, array $expected) {
    [$owner, $site] = componentsRailsSite();

    $c = Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('setFilter', $filter);

    expect($c->get('components')->pluck('name')->sort()->values()->all())->toBe($expected)
        ->and($c->viewData('components')->pluck('name')->sort()->values()->all())->toBe($expected);
})->with([
    'all' => ['all', ['Hero', 'Loose', 'Parked', 'Team']],
    'on pages' => ['used', ['Hero', 'Team']],
    'unused' => ['unused', ['Loose']],
    'with data source' => ['source', ['Team']],
    'inactive' => ['inactive', ['Parked']],
    'needs attention' => ['attention', ['Hero', 'Loose', 'Parked']],
]);

test('an unknown filter falls back to all and filters combine with search', function () {
    [$owner, $site] = componentsRailsSite();

    $c = Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('setFilter', 'bogus')->assertSet('filter', 'all')
        ->set('search', 'mark'); // matches Hero's tag
    expect($c->get('components')->pluck('name')->all())->toBe(['Hero']);

    $c->call('resetListing')->assertSet('search', '')->assertSet('filter', 'all');
    expect($c->get('components')->count())->toBe(4);
});

test('the sort orders by recently edited, name, most used and most fields', function () {
    [$owner, $site] = componentsRailsSite();
    $c = Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site]);

    expect($c->get('components')->pluck('name')->all())->toBe(['Loose', 'Parked', 'Team', 'Hero']);
    $c->set('sort', 'name');
    expect($c->get('components')->pluck('name')->all())->toBe(['Hero', 'Loose', 'Parked', 'Team']);
    $c->set('sort', 'used');
    expect($c->get('components')->pluck('name')->take(2)->all())->toBe(['Team', 'Hero']);
    $c->set('sort', 'fields');
    expect($c->get('components')->pluck('name')->take(2)->all())->toBe(['Team', 'Hero']);
    $c->set('sort', 'nope')->assertSet('sort', 'updated');
});

test('grid is the default listing', function () {
    [$owner, $site] = componentsRailsSite();

    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->assertSet('viewMode', 'grid');
});

test('the page renders the cards and the right-rail summaries', function () {
    [$owner, $site, $c] = componentsRailsSite();

    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->assertOk()
        ->assertSee('Components summary')
        ->assertSee('Needs attention')
        ->assertSee('Most used')
        ->assertSee('Recently edited')
        ->assertSee('Related')
        ->assertSee('1 empty field')
        ->assertSee('People')                 // Team's data source on its card
        ->assertSeeHtml(url($site->name.'/connect?component='.$c['team']->id))
        ->assertSeeHtml('data-confirm=');

    // List and compact views render the table.
    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('setViewMode', 'list')->assertSee('Data source')->assertSee('Inactive')
        ->call('setViewMode', 'compact')->assertOk();
});

test('empty states distinguish no components from no matches', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'rails-empty-'.uniqid(), 'domain' => 'e.test', 'owner' => $owner->name, 'description' => 't']);

    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->assertSee('No components yet');

    [$owner, $site] = componentsRailsSite();
    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->set('search', 'zzz-nothing')->assertSee('Nothing matches')->assertSee('Show all components');
});

test('a component can be duplicated with its fields but not its placements', function () {
    [$owner, $site, $c] = componentsRailsSite();

    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('duplicateComponent', $c['hero']->id);

    $copy = Component::where('site_id', $site->id)->where('name', 'Hero (copy)')->firstOrFail();
    expect($copy->nodes()->pluck('label')->all())->toBe(['Heading', 'Sub'])
        ->and($copy->pages()->count())->toBe(0)
        ->and($copy->tags)->toBe(['marketing']);
});

test('the collection select scopes the listing, tiles and rails', function () {
    [$owner, $site, $c] = componentsRailsSite();

    $page = Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->set('filterCollection', $c['col']->id);

    expect($page->viewData('components')->pluck('name')->all())->toBe(['Team'])
        ->and($page->viewData('stats')['total'])->toBe(1)
        ->and($page->viewData('siteTotal'))->toBe(4);
    $page->assertDontSee('Loose');
});

test('blank Site Properties settings are not reported as empty fields to fill in', function () {
    [$user, $site] = componentsRailsSite();
    $props = Component::create(['site_id' => $site->id, 'name' => config('site-properties.component'), 'author' => 'app', 'source' => 'app', 'tags' => [config('site-properties.tag')]]);
    Node::create(['component_id' => $props->id, 'parent' => '0', 'label' => 'Fax', 'type' => 'text', 'value' => '', 'order' => 0]);

    $page = Livewire::actingAs($user)->test(ComponentsPage::class, ['site' => $site]);
    expect($page->viewData('stats')['emptyList']->pluck('id'))->not->toContain($props->id);
});
