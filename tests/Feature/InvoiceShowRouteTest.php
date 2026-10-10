<?php

use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('the invoice detail page and PDF open for ULID invoice ids', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'inv-show-'.uniqid().'.test', 'currency' => 'gbp']);
    $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
    $site->enableFeature('invoices');
    $invoice = Invoice::create([
        'site_id' => $site->id, 'number' => 'INV-'.substr(uniqid(), -5), 'customer_name' => 'Ada', 'customer_email' => 'ada@example.com',
        'items' => [['description' => 'Work', 'qty' => 1, 'unit_cents' => 5000]], 'subtotal_cents' => 5000, 'tax_bp' => 0, 'tax_cents' => 0,
        'total_cents' => 5000, 'currency' => 'gbp', 'status' => 'sent',
    ]);

    $this->actingAs($user)->get(route('site.invoice.show', [$site->name, $invoice->id]))->assertOk()->assertSee($invoice->number);
    $this->actingAs($user)->get(route('site.invoice.pdf', [$site->name, $invoice->id]))->assertOk();
});
