<?php

use App\Models\Activity;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Estimate;
use App\Models\Site;
use App\Models\User;
use App\Services\VerticalStats;

function verticalSite(string $type): Site
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'vs-'.uniqid(), 'domain' => 'vs-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->enableFeature('bookings');
    $site->setAttr('business_type', $type);

    return $site;
}

function salonVisit(Site $site, string $email, $at, string $status = 'confirmed'): Booking
{
    return Booking::create(['site_id' => $site->id, 'customer_name' => ucfirst(explode('@', $email)[0]), 'customer_email' => $email, 'status' => $status, 'starts_at' => $at, 'ends_at' => (clone $at)->addHour()]);
}

test('no business_type or unknown type means no pack', function () {
    $site = verticalSite('salon');
    $site->setAttr('business_type', '');
    expect(VerticalStats::for($site))->toBeNull();
});

test('the salon pack finds due-back clients, counts no-shows and computes the rebooking rate', function () {
    $site = verticalSite('barber');
    $due = 'due-'.uniqid().'@x.test';
    $back = 'back-'.uniqid().'@x.test';
    $fresh = 'fresh-'.uniqid().'@x.test';

    salonVisit($site, $due, now()->subWeeks(8));                       // overdue, no later visit → due back
    salonVisit($site, $back, now()->subWeeks(8));                      // overdue but…
    salonVisit($site, $back, now()->subWeeks(1));                      // …came back → repeat + not due
    salonVisit($site, $fresh, now()->subDays(3));                      // recent, single visit
    salonVisit($site, 'ns-'.uniqid().'@x.test', now()->startOfWeek()->addHours(10), 'no_show');

    $stats = VerticalStats::for($site);
    expect($stats['pack'])->toBe('salon');

    $names = collect($stats['list'])->pluck('title')->implode(' ');
    expect($names)->toContain('Due')->not->toContain('Back');

    $tiles = collect($stats['tiles'])->keyBy('label');
    expect($tiles['No-shows']['value'])->toBe(1)
        // 4 distinct emails in 90 days, 1 repeats → 25%
        ->and($tiles['Rebooking rate']['value'])->toBe('25%');
    expect($stats['histogram'])->not->toBeNull();
});

test('the trades pack reports the quote pipeline with a chase flag and lead response speed', function () {
    $site = verticalSite('electrician');
    $mk = fn (string $status, $created) => tap(Estimate::create(['site_id' => $site->id, 'estimator_id' => null, 'reference' => 'EST'.strtoupper(substr(uniqid(), -6)), 'trade' => 'elec', 'customer_name' => 'C', 'customer_email' => uniqid().'@x.test', 'inputs' => [], 'results' => [], 'cost_low_cents' => 10000, 'cost_high_cents' => 20000, 'hours' => 1, 'completion' => 1, 'status' => $status]))
        ->forceFill(['created_at' => $created])->save();
    $mk('new', now()->subDays(9)); // stale → chase
    $mk('contacted', now()->subDays(2));
    $mk('won', now()->subDays(20));
    $mk('lost', now()->subDays(20));

    $owner = $site->user;
    $contact = Contact::create(['site_id' => $site->id, 'name' => 'Lead', 'email' => 'lead-'.uniqid().'@x.test', 'status' => 'contacted']);
    $contact->forceFill(['created_at' => now()->subDays(2)])->save();
    Activity::create(['contact_id' => $contact->id, 'user_id' => null, 'type' => 'note', 'body' => 'inbound']);          // ignored
    tap(Activity::create(['contact_id' => $contact->id, 'user_id' => $owner->id, 'type' => 'note', 'body' => 'called them']))
        ->forceFill(['created_at' => now()->subDays(2)->addHours(5)])->save();

    $stats = VerticalStats::for($site);
    expect($stats['pack'])->toBe('trades');
    $tiles = collect($stats['tiles'])->keyBy('label');
    expect($tiles['New leads']['value'])->toBe(1)
        ->and($tiles['New leads']['flag'])->toBeTrue()
        ->and($tiles['Won']['hint'])->toContain('50% win rate');
    expect(collect($stats['list'])->pluck('sub')->implode(' '))->toContain('median 5h');
});
