<?php

use App\Livewire\EstimatesPage;
use App\Models\Estimate;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function estimatorRailsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'err-'.uniqid(), 'domain' => 'err-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('estimator');

    return [$owner, $site];
}

function estimatorRailsLead(Site $site, array $attrs = []): Estimate
{
    $e = Estimate::create($attrs + [
        'site_id' => $site->id, 'reference' => Estimate::newReference(), 'trade' => 'cleaner',
        'customer_name' => 'Ada', 'customer_email' => 'ada@example.test', 'inputs' => ['rooms' => 3],
        'results' => [], 'cost_low_cents' => 8000, 'cost_high_cents' => 12000, 'currency' => 'gbp', 'hours' => 2, 'completion' => '2 hours', 'status' => 'new',
    ]);
    if (isset($attrs['created_at'])) {
        $e->forceFill(['created_at' => $attrs['created_at']])->save();
    }

    return $e;
}

test('the estimates page renders the rails, grid cards and related links', function () {
    [$owner, $site] = estimatorRailsSite();
    $cleaner = $site->estimators()->create(['name' => 'Cleaner', 'slug' => 'cleaner', 'sort' => 0]);
    estimatorRailsLead($site, ['estimator_id' => $cleaner->id, 'customer_name' => 'Zed Old', 'created_at' => now()->subDays(5)]);
    estimatorRailsLead($site, ['estimator_id' => $cleaner->id, 'status' => 'won', 'cost_low_cents' => 20000, 'cost_high_cents' => 30000]);

    $this->actingAs($owner)->get("/{$site->name}/estimates")->assertOk()
        ->assertSee('Estimates this month')->assertSee('Converted to won')->assertSee('Average estimate')
        ->assertSee('Pending follow-ups')->assertSee('Top service')
        ->assertSee('Zed Old')->assertSee('Needs attention')->assertSee('not followed up')
        ->assertSee('By service')->assertSee('Related')->assertSee('Contacts');

    $c = Livewire::actingAs($owner)->test(EstimatesPage::class, ['site' => $site]);
    $s = $c->instance()->stats;
    expect($c->get('viewMode'))->toBe('grid')
        ->and($s['overdue'])->toBe(1)
        ->and($s['conversion'])->toBe(50)
        ->and($s['avgCents'])->toBe(17500)
        ->and($s['wonCents'])->toBe(30000)
        ->and($s['byService'][0]['name'])->toBe('Cleaner')
        ->and($s['byService'][0]['count'])->toBe(2);
});

test('status pills, search and sort narrow the leads; status updates still work', function () {
    [$owner, $site] = estimatorRailsSite();
    $cheap = estimatorRailsLead($site, ['customer_name' => 'Cheap', 'cost_high_cents' => 1000]);
    estimatorRailsLead($site, ['customer_name' => 'Dear', 'cost_high_cents' => 99000, 'status' => 'contacted']);

    $c = Livewire::actingAs($owner)->test(EstimatesPage::class, ['site' => $site]);
    $c->set('sort', 'value');
    expect($c->instance()->estimates->first()->customer_name)->toBe('Dear');
    $c->call('setStatusFilter', 'contacted');
    expect($c->instance()->estimates->pluck('customer_name')->all())->toBe(['Dear']);
    $c->call('setStatusFilter', 'all')->set('search', 'cheap');
    expect($c->instance()->estimates->pluck('customer_name')->all())->toBe(['Cheap']);

    $c->call('updateStatus', $cheap->id, 'won');
    expect($cheap->fresh()->status)->toBe('won');
});

test('configuration lives in pill tabs and an estimator deep link reopens its editor', function () {
    [$owner, $site] = estimatorRailsSite();

    $c = Livewire::actingAs($owner)->test(EstimatesPage::class, ['site' => $site])
        ->assertSee('No estimates yet')
        ->call('setTab', 'estimators')->assertSee('No estimators yet')
        ->set('newEstimatorName', 'Mover')->call('createEstimator');
    $mover = $site->estimators()->first();
    expect($c->get('tab'))->toBe('estimators')->and($c->get('selectedId'))->toBe($mover->id);

    $c->call('setEditTab', 'calcs')->assertSee('Add calculation')
        ->call('setEditTab', 'email')->assertSee('Customer email')
        ->call('setEditTab', 'fields')->assertSee('Add field');

    Livewire::withQueryParams(['estimator' => $mover->id, 'step' => 'email'])
        ->actingAs($owner)->test(EstimatesPage::class, ['site' => $site])
        ->assertSet('selectedId', $mover->id)->assertSet('editTab', 'email')
        ->assertSet('eName', 'Mover');
});
