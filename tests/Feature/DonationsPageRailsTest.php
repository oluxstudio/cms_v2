<?php

use App\Livewire\DonationsPage;
use App\Models\Donation;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function donationsRailsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'dr-'.uniqid(), 'domain' => 'dr-'.uniqid().'.test', 'owner' => 'x', 'description' => 't', 'currency' => 'gbp']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('donations');

    return [$owner, $site];
}

function donationsRailsGift(Site $site, array $attrs = []): Donation
{
    return $site->donations()->create($attrs + [
        'donor_name' => 'Ada', 'donor_email' => 'ada@example.test', 'amount_cents' => 1000,
        'currency' => 'gbp', 'status' => 'paid', 'paid_at' => now(),
    ]);
}

test('the donations page renders the three rails with stats, grid cards and related links', function () {
    [$owner, $site] = donationsRailsSite();
    donationsRailsGift($site, ['amount_cents' => 2500, 'message' => 'Keep going!']);
    donationsRailsGift($site, ['amount_cents' => 500]);
    donationsRailsGift($site, ['donor_name' => 'Bob', 'donor_email' => 'bob@example.test', 'amount_cents' => 9000]);
    donationsRailsGift($site, ['donor_name' => 'Cy', 'donor_email' => 'cy@example.test', 'status' => 'pending', 'paid_at' => null]);

    $this->actingAs($owner)->get("/{$site->name}/donations")->assertOk()
        ->assertSee('Raised this month')->assertSee('All-time total')->assertSee('Repeat donors')
        ->assertSee('Largest gift')->assertSee('Keep going!')->assertSee('Top donors')
        ->assertSee('Needs attention')->assertSee('Payments aren')->assertSee('Related');

    $c = Livewire::actingAs($owner)->test(DonationsPage::class, ['site' => $site]);
    $s = $c->instance()->stats;
    expect($s['raisedCents'])->toBe(12000)
        ->and($s['donors'])->toBe(2)
        ->and($s['repeatDonors'])->toBe(1)
        ->and($s['largest']->amount_cents)->toBe(9000)
        ->and($s['counts'])->toBe(['all' => 4, 'paid' => 3, 'pending' => 1])
        ->and($c->get('viewMode'))->toBe('grid');
});

test('search, status filter and sort narrow the donations list', function () {
    [$owner, $site] = donationsRailsSite();
    donationsRailsGift($site, ['amount_cents' => 100]);
    donationsRailsGift($site, ['donor_name' => 'Bob', 'donor_email' => 'bob@example.test', 'amount_cents' => 700]);
    donationsRailsGift($site, ['donor_name' => 'Cy', 'donor_email' => 'cy@example.test', 'status' => 'pending', 'paid_at' => null]);

    $c = Livewire::actingAs($owner)->test(DonationsPage::class, ['site' => $site]);
    expect($c->set('search', 'bob')->instance()->visible->pluck('donor_name')->all())->toBe(['Bob']);
    $c->set('search', '')->call('setFilter', 'pending');
    expect($c->instance()->visible->pluck('donor_name')->all())->toBe(['Cy']);
    $c->call('setFilter', 'nope');
    expect($c->get('filter'))->toBe('all');
    $c->set('sort', 'largest');
    expect($c->instance()->visible->first()->amount_cents)->toBe(1000);
});

test('the settings tab saves suggested amounts to the donations feature config', function () {
    [$owner, $site] = donationsRailsSite();

    Livewire::actingAs($owner)->test(DonationsPage::class, ['site' => $site])
        ->call('setTab', 'settings')->assertSee('Donate page settings')
        ->set('sAmounts', '3, abc, 15.50, 40')->set('sHeadline', 'Help us')->set('sCurrency', 'eur')
        ->call('saveSettings')->assertHasNoErrors();

    $cfg = $site->fresh()->feature('donations');
    expect($cfg['suggested_amounts'])->toBe('3, 15.5, 40')
        ->and($cfg['headline'])->toBe('Help us')
        ->and($cfg['currency'])->toBe('eur');

    Livewire::actingAs($owner)->test(DonationsPage::class, ['site' => $site])
        ->set('sAmounts', 'none')->call('saveSettings')->assertHasErrors('sAmounts');
});

test('deleting a donation still works and the empty state shows', function () {
    [$owner, $site] = donationsRailsSite();
    $d = donationsRailsGift($site);

    Livewire::actingAs($owner)->test(DonationsPage::class, ['site' => $site])
        ->call('deleteDonation', $d->id)->assertSee('No donations yet');
    expect($site->donations()->count())->toBe(0);
});
