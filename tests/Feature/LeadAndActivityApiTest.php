<?php

use App\Livewire\BookingsPage;
use App\Livewire\InvoicesPage;
use App\Mail\FormSubmissionNotification;
use App\Models\Alert;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Site;
use App\Models\SiteActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function leadSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'lead-'.uniqid(),
        'domain' => 'lead.test', 'owner' => $owner->name, 'description' => 'test',
    ]);

    return [$owner, $site];
}

test('the quote API returns calculated results for a named estimator', function () {
    [, $site] = leadSite();
    $site->enableFeature('estimator');
    $est = $site->estimators()->create(['name' => 'Cleaner', 'slug' => 'cleaner']);
    $est->fields()->create(['site_id' => $site->id, 'key' => 'area', 'label' => 'Area', 'type' => 'number']);
    $est->fields()->create(['site_id' => $site->id, 'key' => 'rate', 'label' => 'Rate', 'type' => 'fixed', 'value' => 4]);
    $est->calcs()->create(['site_id' => $site->id, 'name' => 'Cost', 'formula' => 'area * rate', 'format' => 'money']);

    $this->postJson("/api/sites/{$site->name}/quote", ['estimator' => 'cleaner', 'fields' => ['area' => 5]])
        ->assertOk()
        ->assertJsonPath('results.0.formatted', '£20.00');
});

test('the interest API captures the contact, notifies the dashboard and emails the owner', function () {
    Mail::fake();
    [$owner, $site] = leadSite();

    $this->postJson("/api/sites/{$site->name}/interest", [
        'name' => 'Ira Interested', 'email' => 'ira@example.com',
        'subject' => 'Kitchen refit', 'message' => 'Do you cover my area?', 'source' => 'pricing-page',
    ])->assertStatus(201);

    expect(Contact::where('site_id', $site->id)->where('email', 'ira@example.com')->exists())->toBeTrue()
        ->and(Alert::where('site_id', $site->id)->where('type', 'interest')->exists())->toBeTrue()
        ->and(SiteActivityLog::where('site_id', $site->id)->where('entity_type', 'interest')->value('title'))
        ->toContain('Kitchen refit');
    Mail::assertSent(FormSubmissionNotification::class, fn ($m) => $m->hasTo($owner->email));
});

test('booking confirm and cancel land in recent activity', function () {
    Mail::fake();
    [$owner, $site] = leadSite();
    $site->enableFeature('bookings');
    $svc = Service::create(['site_id' => $site->id, 'name' => 'Consult', 'slug' => 'consult-'.uniqid(), 'kind' => 'slot', 'duration_min' => 60, 'is_active' => true]);
    $booking = Booking::create([
        'site_id' => $site->id, 'service_id' => $svc->id, 'reference' => 'BK'.strtoupper(substr(uniqid(), -8)),
        'customer_name' => 'Bea Booker', 'customer_email' => 'bea@example.com',
        'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(),
        'busy_from' => now()->addDay(), 'busy_until' => now()->addDay()->addHour(),
        'status' => 'pending', 'params' => [], 'quantity' => 1, 'total_cents' => 0, 'currency' => 'gbp',
    ]);

    $page = Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site]);
    $page->call('setStatus', $booking->id, 'confirmed');
    $page->call('setStatus', $booking->id, 'cancelled');

    $actions = SiteActivityLog::where('site_id', $site->id)->where('entity_type', 'booking')->pluck('action');
    expect($actions)->toContain('confirmed')->toContain('cancelled');
});

test('drafting and sending an invoice land in recent activity', function () {
    Mail::fake();
    [$owner, $site] = leadSite();
    $site->enableFeature('invoices');

    Livewire::actingAs($owner)->test(InvoicesPage::class, ['site' => $site])
        ->set('customerName', 'Ivy Client')
        ->set('customerEmail', 'ivy@example.com')
        ->set('items', [['description' => 'Design work', 'qty' => 2, 'price' => '150']])
        ->call('saveInvoice');

    $invoice = Invoice::where('site_id', $site->id)->first();
    expect($invoice)->not->toBeNull();
    expect(SiteActivityLog::where('site_id', $site->id)->where('entity_type', 'invoice')->where('action', 'created')->exists())->toBeTrue();

    Livewire::actingAs($owner)->test(InvoicesPage::class, ['site' => $site])
        ->call('sendInvoice', $invoice->id);
    expect(SiteActivityLog::where('site_id', $site->id)->where('entity_type', 'invoice')->where('action', 'sent')->exists())->toBeTrue();
});
