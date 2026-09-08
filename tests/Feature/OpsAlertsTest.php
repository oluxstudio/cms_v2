<?php

use App\Models\Alert;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use App\Services\OpsAlerts;

function opsSite(array $features = ['invoices', 'bookings']): Site
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'ops-'.uniqid(), 'domain' => 'ops-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    foreach ($features as $f) {
        $site->enableFeature($f);
    }

    return $site;
}

test('the sweep raises one deduped alert per overdue invoice and pending booking', function () {
    $site = opsSite();
    Invoice::create(['site_id' => $site->id, 'number' => 'INV-'.uniqid(), 'customer_name' => 'A', 'customer_email' => 'a@x.test', 'currency' => 'gbp', 'status' => 'overdue', 'items' => [], 'subtotal_cents' => 5000, 'total_cents' => 5000, 'due_date' => now()->subDays(5)]);
    Booking::create(['site_id' => $site->id, 'customer_name' => 'B', 'customer_email' => 'b@x.test', 'status' => 'pending', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);

    OpsAlerts::sweep($site);
    OpsAlerts::sweep($site); // idempotent

    $alerts = Alert::where('site_id', $site->id)->get();
    expect($alerts->where('type', 'invoice_overdue'))->toHaveCount(1)
        ->and($alerts->where('type', 'booking_pending'))->toHaveCount(1)
        ->and($alerts->first()->dedupe_key)->not->toBeNull();
});

test('features that are off produce no alerts', function () {
    $site = opsSite(features: []);
    Invoice::create(['site_id' => $site->id, 'number' => 'INV-'.uniqid(), 'customer_name' => 'A', 'customer_email' => 'a@x.test', 'currency' => 'gbp', 'status' => 'overdue', 'items' => [], 'subtotal_cents' => 100, 'total_cents' => 100, 'due_date' => now()->subDay()]);

    OpsAlerts::sweep($site);
    expect(Alert::where('site_id', $site->id)->count())->toBe(0);
});
