<?php

use App\Livewire\PageComponent;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

/**
 * A site with: Home (live, SEO, 2 sections), About (live, SEO, 1 section),
 * Draft (hidden, no SEO, empty) and Old shop (parked by a template switch).
 */
function pagesRailsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'rails-'.uniqid(),
        'domain' => 'rails-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    $home = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $about = Page::create(['site_id' => $site->id, 'name' => 'About', 'url' => '/about', 'keywords' => '', 'is_published' => true]);
    $draft = Page::create(['site_id' => $site->id, 'name' => 'Draft', 'url' => '/draft', 'keywords' => '', 'is_published' => false]);
    $parked = Page::create(['site_id' => $site->id, 'name' => 'Old shop', 'url' => '/shop', 'keywords' => '', 'is_published' => true,
        'template_keys' => ['hairco'], 'template_active' => false]);

    $hero = $site->contentComponents()->create(['name' => 'Hero banner', 'author' => 't', 'source' => 'app']);
    $cta = $site->contentComponents()->create(['name' => 'Call to action', 'author' => 't', 'source' => 'app']);
    $home->components()->attach($hero->id, ['order' => 1]);
    $home->components()->attach($cta->id, ['order' => 2]);
    $about->components()->attach($hero->id, ['order' => 1]);

    $home->setAttr('description', 'Welcome');
    $about->setAttr('description', 'About us');

    return [$owner, $site, compact('home', 'about', 'draft', 'parked')];
}

test('grid is the default listing', function () {
    [$owner, $site] = pagesRailsSite();

    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->assertSet('viewMode', 'grid')
        ->assertSet('filter', 'all')
        ->assertSet('sort', 'menu');
});

test('the rails compute page stats', function () {
    [$owner, $site] = pagesRailsSite();

    $lw = Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site]);
    $stats = $lw->viewData('stats');

    expect($lw->viewData('total'))->toBe(4)
        ->and($stats['live'])->toBe(2)
        ->and($stats['hidden'])->toBe(1)
        ->and($stats['inactive'])->toBe(1)
        ->and($stats['inNav'])->toBe(3)
        ->and($stats['noSeo'])->toBe(1)     // Draft (the parked page is not counted)
        ->and($stats['empty'])->toBe(1)     // Draft
        ->and($stats['attention'])->toBe(1)
        ->and($stats['sections'])->toBe(3)
        ->and($stats['biggest']->first()->name)->toBe('Home');
});

test('each filter narrows the list', function (string $filter, array $expected) {
    [$owner, $site] = pagesRailsSite();

    $lw = Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('setFilter', $filter);

    expect($lw->viewData('pages')->pluck('name')->sort()->values()->all())->toBe($expected);
})->with([
    'all' => ['all', ['About', 'Draft', 'Home', 'Old shop']],
    'live' => ['live', ['About', 'Home']],
    'hidden' => ['hidden', ['Draft']],
    'inactive' => ['inactive', ['Old shop']],
    'attention' => ['attention', ['Draft']],
]);

test('unknown filters fall back to all and search combines with filters', function () {
    [$owner, $site] = pagesRailsSite();

    $lw = Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('setFilter', 'bogus')
        ->assertSet('filter', 'all')
        ->set('search', 'abo');
    expect($lw->viewData('pages')->pluck('name')->all())->toBe(['About']);

    $lw->call('setFilter', 'hidden')->assertSee('Nothing matches')
        ->call('resetFilters')
        ->assertSet('search', '')->assertSet('filter', 'all');
});

test('sort orders the list', function () {
    [$owner, $site, $p] = pagesRailsSite();
    $p['draft']->forceFill(['updated_at' => now()->addMinute()])->save();

    $lw = Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site]);
    expect($lw->viewData('pages')->first()->name)->toBe('Home'); // menu order: home first

    $lw->set('sort', 'name');
    expect($lw->viewData('pages')->pluck('name')->all())->toBe(['About', 'Draft', 'Home', 'Old shop']);

    $lw->set('sort', 'sections');
    expect($lw->viewData('pages')->pluck('name')->take(2)->all())->toBe(['Home', 'About']);

    $lw->set('sort', 'updated');
    expect($lw->viewData('pages')->first()->name)->toBe('Draft');

    $lw->set('sort', 'nonsense')->assertSet('sort', 'menu');
});

test('the page renders cards, tiles and the right-rail summaries', function () {
    [$owner, $site] = pagesRailsSite();

    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->assertSee('Missing SEO')
        ->assertSee('Inactive template pages')
        ->assertSee('Pages summary')
        ->assertSee('Needs attention')
        ->assertSee('Biggest pages')
        ->assertSee('Recently edited')
        ->assertSee('Related')
        ->assertSee('Hero banner · Call to action')   // section names on the card
        ->assertSee('Inactive — from')                 // parked page keeps its Activate badge
        ->assertSee(url($site->name.'/connect'), false);

    // List view still renders the table.
    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('setViewMode', 'list')
        ->assertSee('Sections')
        ->assertSee('/about');
});

test('the empty state offers to create the first page', function () {
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'rails-'.uniqid(),
        'domain' => 'rails-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->assertSee('No pages yet')
        ->assertSee('Create the first page');
});
