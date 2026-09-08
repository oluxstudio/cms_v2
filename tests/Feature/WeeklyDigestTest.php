<?php

use App\Mail\WeeklyDigest;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function digestSite(): Site
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'dig-'.uniqid(), 'domain' => 'dig-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->enableFeature('bookings');
    $site->enableFeature('invoices');

    return $site;
}

test('an active site owner gets a digest with the right totals; quiet and opted-out sites are skipped', function () {
    Mail::fake();

    $active = digestSite();
    Booking::create(['site_id' => $active->id, 'customer_name' => 'C', 'customer_email' => 'c@x.test', 'status' => 'confirmed', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHour()]);
    Invoice::create(['site_id' => $active->id, 'number' => 'INV-'.uniqid(), 'customer_name' => 'C', 'customer_email' => 'c@x.test', 'currency' => 'gbp', 'status' => 'paid', 'paid_at' => now()->subDay(), 'items' => [], 'subtotal_cents' => 12000, 'total_cents' => 12000]);

    $quiet = digestSite();

    $optedOut = digestSite();
    Booking::create(['site_id' => $optedOut->id, 'customer_name' => 'D', 'customer_email' => 'd@x.test', 'status' => 'confirmed', 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);
    $optedOut->setAttr('weekly_digest', '0');

    $this->artisan('site:digest')->assertSuccessful();

    Mail::assertQueued(WeeklyDigest::class, function (WeeklyDigest $m) use ($active) {
        return $m->site->id === $active->id
            && $m->stats['bookings_held'] === 1
            && $m->stats['revenue_cents'] === 12000
            && $m->hasTo($active->user->email);
    });
    // The quiet and opted-out fixture sites get nothing (other test data in the
    // shared testing DB may legitimately produce digests of its own).
    Mail::assertNotQueued(WeeklyDigest::class, fn ($m) => $m->site->id === $quiet->id);
    Mail::assertNotQueued(WeeklyDigest::class, fn ($m) => $m->site->id === $optedOut->id);
});
