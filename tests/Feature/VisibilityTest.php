<?php

use App\Livewire\ComponentsPage;
use App\Models\Collection;
use App\Models\Component;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function visSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'vis-'.uniqid(), 'domain' => 'vis-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

afterEach(fn () => Carbon::setTestNow());

test('visibleNow evaluates date range, days, time windows and null rules', function () {
    [, $site] = visSite();
    $c = Component::create(['site_id' => $site->id, 'name' => 'Banner', 'author' => 't', 'source' => 'app']);

    expect($c->visibleNow())->toBeTrue(); // no rules → always

    Carbon::setTestNow('2026-12-10 12:00:00'); // a Thursday
    $c->visibility = ['from' => '2026-12-01T00:00', 'until' => '2026-12-26T23:59'];
    expect($c->visibleNow())->toBeTrue();
    $c->visibility = ['from' => '2026-12-20T00:00'];
    expect($c->visibleNow())->toBeFalse();       // not started
    $c->visibility = ['until' => '2026-12-05T00:00'];
    expect($c->visibleNow())->toBeFalse();       // ended

    $c->visibility = ['days' => [6, 7]];         // weekend only
    expect($c->visibleNow())->toBeFalse();
    $c->visibility = ['days' => [4]];            // Thursday
    expect($c->visibleNow())->toBeTrue();

    $c->visibility = ['time_from' => '09:00', 'time_until' => '17:00'];
    expect($c->visibleNow())->toBeTrue();
    $c->visibility = ['time_from' => '22:00', 'time_until' => '02:00']; // overnight
    expect($c->visibleNow())->toBeFalse();
    Carbon::setTestNow('2026-12-10 23:30:00');
    expect($c->visibleNow())->toBeTrue();
});

test('requires_content tracks the linked collection for components and collections', function () {
    [, $site] = visSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Faqs '.uniqid(), 'type' => 'list', 'visibility' => ['requires_content' => true]]);
    $c = Component::create(['site_id' => $site->id, 'name' => 'Faq list', 'author' => 't', 'source' => 'app',
        'collection_id' => $col->id, 'visibility' => ['requires_content' => true]]);

    expect($col->visibleNow())->toBeFalse()->and($c->visibleNow())->toBeFalse(); // empty

    $col->items()->create(['site_id' => $site->id, 'data' => ['q' => 'Hi'], 'status' => 'published']);
    expect($col->fresh()->visibleNow())->toBeTrue()->and($c->fresh()->visibleNow())->toBeTrue();
});

test('payloads carry visibility rules + hint and never omit the component', function () {
    [, $site] = visSite();
    $page = $site->pages()->create(['name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $c = Component::create(['site_id' => $site->id, 'name' => 'Promo banner', 'author' => 't', 'source' => 'app',
        'visibility' => ['until' => '2020-01-01T00:00', 'promo' => 'summer26']]);
    $c->nodes()->create(['label' => 'Headline', 'type' => 'text', 'value' => 'Hi', 'parent' => '0', 'order' => 0]);
    $page->components()->attach($c->id, ['order' => 1]);

    $res = $this->getJson("/api/sites/{$site->name}/content")->json();
    $entry = collect(data_get($res, 'pages.0.wireframe', []))->firstWhere('name', 'Promo banner')
        ?? collect(data_get($res, 'components', []))->firstWhere('name', 'Promo banner');
    expect($entry)->not->toBeNull()
        ->and($entry['visibility']['rules']['promo'])->toBe('summer26')
        ->and($entry['visibility']['visible_now'])->toBeFalse(); // past until-date
});

test('the components editor saves and clears visibility rules', function () {
    [$owner, $site] = visSite();
    $c = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 't', 'source' => 'app']);
    $c->nodes()->create(['label' => 'Headline', 'type' => 'text', 'value' => 'Hi', 'parent' => '0', 'order' => 0]);

    $page = Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site]);
    $page->call('open', $c->id)
        ->set('visFrom', '2026-12-01T00:00')->set('visDays', ['6', '7'])
        ->set('visPromo', 'xmas')->call('save');
    expect($c->fresh()->visibility)->toEqual(['from' => '2026-12-01T00:00', 'days' => [6, 7], 'promo' => 'xmas']);

    // One-sided time pair is refused.
    $page->call('open', $c->id)->set('visTimeFrom', '09:00')->set('visTimeUntil', '')->call('save')
        ->assertHasErrors(['visTimeUntil']);

    // Clearing every field stores null.
    $page->call('open', $c->id)->set('visFrom', '')->set('visDays', [])->set('visPromo', '')->call('save');
    expect($c->fresh()->visibility)->toBeNull();
});
