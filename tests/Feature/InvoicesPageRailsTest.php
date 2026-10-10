<?php

use App\Livewire\InvoicesPage;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

function invoicesRailsSite(): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'inv-rails-'.uniqid().'.test', 'currency' => 'gbp']);
    $site->enableFeature('invoices');

    return [$user, $site];
}

function invoicesRailsMake(Site $site, string $number, string $status, int $cents, array $extra = []): Invoice
{
    return Invoice::create([
        'site_id' => $site->id, 'number' => $number, 'customer_name' => $extra['name'] ?? 'Ada',
        'customer_email' => $extra['email'] ?? 'ada@example.com', 'items' => [['description' => 'Work', 'qty' => 1, 'unit_cents' => $cents]],
        'subtotal_cents' => $cents, 'tax_bp' => 0, 'tax_cents' => 0, 'total_cents' => $cents, 'currency' => 'gbp', 'status' => $status,
    ] + array_diff_key($extra, ['name' => 1, 'email' => 1]));
}

test('invoice rails: tiles, aged receivables, attention and top customers come from the data', function () {
    Mail::fake();
    [$user, $site] = invoicesRailsSite();

    invoicesRailsMake($site, 'INV-0001', 'draft', 5000);
    invoicesRailsMake($site, 'INV-0002', 'sent', 10000, ['due_date' => now()->addDays(5)->toDateString(), 'sent_at' => now()]);
    invoicesRailsMake($site, 'INV-0003', 'overdue', 20000, ['due_date' => now()->subDays(10)->toDateString(), 'sent_at' => now()->subDays(20),
        'name' => 'Bob', 'email' => 'bob@example.com', 'stripe_session_id' => 'cs_test_1']);
    invoicesRailsMake($site, 'INV-0004', 'overdue', 7000, ['due_date' => now()->subDays(45)->toDateString(), 'sent_at' => now()->subDays(60)]);
    invoicesRailsMake($site, 'INV-0005', 'paid', 30000, ['sent_at' => now()->subDays(4), 'paid_at' => now(), 'name' => 'Bob', 'email' => 'bob@example.com']);
    invoicesRailsMake($site, 'INV-0006', 'cancelled', 999);
    invoicesRailsMake($site, 'INV-0007', 'sent', 4000, ['recur_interval' => 'monthly', 'due_date' => now()->addDays(20)->toDateString(),
        'sent_at' => now(), 'recur_next_on' => now()->addMonth()->toDateString()]);

    $page = Livewire::actingAs($user)->test(InvoicesPage::class, ['site' => $site]);
    $o = $page->instance()->overview;

    expect($o['counts'])->toMatchArray(['all' => 7, 'draft' => 1, 'sent' => 2, 'overdue' => 2, 'paid' => 1, 'recurring' => 1, 'void' => 1, 'outstanding' => 4])
        ->and($o['outstanding'])->toBe(41000)
        ->and($o['overdueCents'])->toBe(27000)
        ->and($o['paidMonth'])->toBe(30000)
        ->and($o['avgDays'])->toEqual(4.0)
        ->and($o['aging'])->toBe(['current' => 14000, '1-30' => 20000, '31-60' => 7000, '60+' => 0])
        ->and($o['chase']->pluck('number')->all())->toBe(['INV-0004', 'INV-0003'])
        ->and($o['unsent']->pluck('number')->all())->toBe(['INV-0001'])
        ->and($o['failed']->pluck('number')->all())->toBe(['INV-0003'])
        ->and($o['topCustomers']->first()['email'])->toBe('bob@example.com')
        ->and($o['topCustomers']->first()['cents'])->toBe(50000);

    $page->assertSee('Receivables')->assertSee('Needs attention')->assertSee('Top customers')
        ->assertSee('payment not completed')->assertSee('Related')
        ->assertSee(route('site.contacts', $site->name))->assertSee(route('site.invoice.pdf', [$site->name, Invoice::where('number', 'INV-0002')->value('id')]));
});

test('invoice list: grid by default, filters, tile filters, sort and client filter', function () {
    [$user, $site] = invoicesRailsSite();
    invoicesRailsMake($site, 'INV-0001', 'draft', 5000);
    invoicesRailsMake($site, 'INV-0002', 'sent', 90000, ['due_date' => now()->addDays(30)->toDateString()]);
    invoicesRailsMake($site, 'INV-0003', 'overdue', 1000, ['due_date' => now()->subDay()->toDateString(), 'email' => 'cy@example.com']);
    invoicesRailsMake($site, 'INV-0004', 'cancelled', 2000);
    invoicesRailsMake($site, 'INV-0005', 'paid', 3000, ['recur_interval' => 'yearly', 'paid_at' => now()]);

    $page = Livewire::actingAs($user)->test(InvoicesPage::class, ['site' => $site])->assertSet('viewMode', 'grid');
    $numbers = fn () => $page->instance()->invoices->pluck('number')->sort()->values()->all();

    expect($numbers())->toHaveCount(5);
    $page->call('setFilter', 'void');
    expect($numbers())->toBe(['INV-0004']);
    $page->call('setFilter', 'cancelled');   // legacy key → void (clicking the active filter again clears it)
    expect($numbers())->toHaveCount(5);
    $page->call('setFilter', 'recurring');
    expect($numbers())->toBe(['INV-0005']);
    $page->call('setFilter', 'outstanding');
    expect($numbers())->toBe(['INV-0002', 'INV-0003']);
    $page->call('setFilter', 'month');
    expect($numbers())->toHaveCount(5);
    $page->call('setFilter', 'bogus')->assertSet('statusFilter', 'all');

    $page->set('sort', 'amount');
    expect($page->instance()->invoices->first()->number)->toBe('INV-0002');
    $page->set('sort', 'due');
    expect($page->instance()->invoices->first()->number)->toBe('INV-0003');
    $page->set('sort', 'nonsense')->assertSet('sort', 'newest');

    $page->call('filterClient', 'cy@example.com');
    expect($numbers())->toBe(['INV-0003']);
    $page->set('search', 'INV-0002');
    expect($numbers())->toBe([]);
    $page->call('resetFilters')->assertSet('clientFilter', 'all')->assertSet('search', '');
    expect($numbers())->toHaveCount(5);

    $page->call('setViewMode', 'list')->assertSet('viewMode', 'list')->assertSee('INV-0002');
    expect($site->fresh()->getAttr('layout:invoices'))->toBe('list');
});

test('invoice actions still work from the redesigned page', function () {
    Mail::fake();
    [$user, $site] = invoicesRailsSite();
    $draft = invoicesRailsMake($site, 'INV-0001', 'draft', 5000);

    Livewire::actingAs($user)->test(InvoicesPage::class, ['site' => $site])
        ->call('sendInvoice', $draft->id)
        ->call('markPaid', $draft->id)
        ->call('openForm')->assertSet('formOpen', true)
        ->set('genPrompt', 'Logo design 250 for Jane Doe jane@studio.com')
        ->call('generateFromPrompt')
        ->assertSet('formOpen', false);

    expect($draft->fresh()->status)->toBe('paid')
        ->and(Invoice::where('site_id', $site->id)->where('customer_email', 'jane@studio.com')->value('total_cents'))->toBe(25000);
});
