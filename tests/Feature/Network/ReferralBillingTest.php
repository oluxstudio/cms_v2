<?php

use App\Models\AccountSubscription;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\User;
use App\Modules\Network\Contracts\ReferralBillingService;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralEvent;
use App\Modules\Network\Models\ReferralPayout;
use App\Modules\Network\NetworkException;
use App\Modules\Network\NetworkStripe;
use App\Services\PlatformBilling;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Stripe\Exception\InvalidRequestException;

uses(DatabaseTransactions::class);

/** Records every Stripe call instead of hitting the network. */
class NetBillFakeStripe extends NetworkStripe
{
    public array $calls = [];

    public int $failTransfers = 0;

    public function configured(): bool
    {
        return true;
    }

    public function createInvoiceItem(array $params, string $idempotencyKey): string
    {
        $this->calls[] = ['invoiceItem', $params, $idempotencyKey];

        return 'ii_'.uniqid();
    }

    public function createTransfer(array $params, string $idempotencyKey): string
    {
        $this->calls[] = ['transfer', $params, $idempotencyKey];
        if ($this->failTransfers-- > 0) {
            throw new InvalidRequestException('Insufficient funds in Stripe balance.');
        }

        return 'tr_'.uniqid();
    }

    public function createBalanceTransaction(string $customerId, array $params, string $idempotencyKey): string
    {
        $this->calls[] = ['balance', ['customer' => $customerId] + $params, $idempotencyKey];

        return 'cbtxn_'.uniqid();
    }

    public function invoiceItemIdsOn(string $invoiceId): array
    {
        return [];
    }

    public function count(string $type): int
    {
        return collect($this->calls)->where(0, $type)->count();
    }
}

beforeEach(function () {
    $this->stripe = new NetBillFakeStripe;
    app()->instance(NetworkStripe::class, $this->stripe);
    app()->forgetInstance(ReferralBillingService::class);

    // Audit log through the engine contract (fake: write the event row directly).
    $log = Mockery::mock(ReferralService::class);
    $log->shouldReceive('log')->andReturnUsing(function (Referral $r, string $type, ?User $u = null, array $data = []) {
        ReferralEvent::create(['referral_id' => $r->id, 'type' => $type, 'user_id' => $u?->id, 'data' => $data]);
    });
    app()->instance(ReferralService::class, $log);

    $this->billing = app(ReferralBillingService::class);
});

function netBillSite(array $sub = []): Site
{
    $user = User::factory()->create();
    if ($sub !== []) {
        AccountSubscription::create(['user_id' => $user->id, 'plan' => 'growth', 'status' => 'active'] + $sub);
    }

    return Site::create(['user_id' => $user->id, 'name' => 'netbill-'.uniqid(), 'domain' => 'nb-'.uniqid().'.test', 'owner' => $user->name, 'description' => 't']);
}

function netBillReferral(Site $from, Site $to, array $attrs = []): Referral
{
    return Referral::create($attrs + [
        'reference' => 'R'.strtoupper(substr(uniqid(), -8)),
        'from_site_id' => $from->id, 'to_site_id' => $to->id,
        'customer_name' => 'Jane Doe', 'customer_email' => 'jane-'.uniqid().'@example.test',
        'status' => 'converted', 'fee_cents' => 1500, 'currency' => 'gbp', 'olux_cut_pct' => 3,
        'converted_at' => now()->subDays(8), 'converted_via' => 'manual',
    ]);
}

function netBillConnect(Site $site, bool $enabled = true): void
{
    SitePaymentSettings::create(['site_id' => $site->id, 'provider' => 'stripe_connect', 'enabled' => true,
        'connect_account_id' => 'acct_'.uniqid(), 'connect_charges_enabled' => $enabled]);
}

test('bills only once the dispute window has passed, onto the receiver subscription', function () {
    $from = netBillSite();
    $to = netBillSite(['stripe_customer_id' => 'cus_recv', 'stripe_subscription_id' => 'sub_recv']);
    $fresh = netBillReferral($from, $to, ['converted_at' => now()->subDays(3)]);
    $due = netBillReferral($from, $to);

    expect($this->billing->billDue())->toBe(1);
    expect($fresh->refresh()->status)->toBe('converted');
    expect(fn () => $this->billing->bill($fresh))->toThrow(NetworkException::class);

    $due->refresh();
    expect($due->status)->toBe('billed')
        ->and($due->billed_at)->not->toBeNull()
        ->and($due->stripe_invoice_item_id)->toStartWith('ii_');

    [, $params] = $this->stripe->calls[0];
    expect($params)->toMatchArray(['customer' => 'cus_recv', 'amount' => 1500, 'currency' => 'gbp', 'subscription' => 'sub_recv'])
        ->and($params['description'])->toBe("Referral {$due->reference} — Jane Doe (from {$from->name})")
        ->and($params['metadata']['referral_id'])->toBe($due->id);

    $payout = $due->payout;
    expect($payout->site_id)->toBe($from->id)
        ->and($payout->status)->toBe('pending')
        ->and($payout->gross_cents)->toBe(1500)
        ->and($payout->olux_cents)->toBe(45)
        ->and($payout->net_cents)->toBe(1455)
        ->and($payout->method)->toBe('connect_transfer');
    expect(ReferralEvent::where('referral_id', $due->id)->where('type', 'billed')->exists())->toBeTrue();
});

test('billing is idempotent', function () {
    $ref = netBillReferral(netBillSite(), netBillSite(['stripe_customer_id' => 'cus_x']));

    $this->billing->bill($ref);
    $this->billing->bill($ref->refresh());
    $this->billing->billDue();

    expect($this->stripe->count('invoiceItem'))->toBe(1)
        ->and(ReferralPayout::where('referral_id', $ref->id)->count())->toBe(1);
});

test('receiver without a Stripe customer is skipped (logged once), not thrown', function () {
    $ref = netBillReferral(netBillSite(), netBillSite());

    expect($this->billing->billDue())->toBe(0);
    expect($this->billing->billDue())->toBe(0);

    expect($ref->refresh()->status)->toBe('converted')
        ->and($this->stripe->calls)->toBe([])
        ->and(ReferralEvent::where('referral_id', $ref->id)->where('type', 'bill_skipped')->count())->toBe(1);
});

test('paid platform invoice webhook collects the referral and transfers to the referrer Connect account', function () {
    config(['services.stripe_platform.webhook_secret' => 'whsec_netbill']);
    $from = netBillSite();
    netBillConnect($from);
    $ref = $this->billing->bill(netBillReferral($from, netBillSite(['stripe_customer_id' => 'cus_r'])));

    $payload = json_encode(['id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => 'invoice.paid', 'data' => ['object' => [
        'id' => 'in_netbill', 'object' => 'invoice', 'customer' => 'cus_r',
        'lines' => ['object' => 'list', 'has_more' => false, 'data' => [
            ['id' => 'il_1', 'object' => 'line_item', 'parent' => ['type' => 'invoice_item_details', 'invoice_item_details' => ['invoice_item' => $ref->stripe_invoice_item_id]]],
            ['id' => 'il_2', 'object' => 'line_item', 'invoice_item' => 'ii_unrelated'],
        ]],
    ]]]);
    $t = time();
    $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_netbill');

    app(PlatformBilling::class)->handleWebhook($payload, $sig);

    $ref->refresh();
    $payout = $ref->payout;
    expect($ref->status)->toBe('collected')
        ->and($ref->collected_at)->not->toBeNull()
        ->and($payout->status)->toBe('transferred')
        ->and($payout->stripe_transfer_id)->toStartWith('tr_')
        ->and($payout->paid_at)->not->toBeNull();

    $transfer = collect($this->stripe->calls)->firstWhere(0, 'transfer')[1];
    expect($transfer)->toMatchArray(['amount' => 1455, 'currency' => 'gbp', 'transfer_group' => $ref->reference,
        'destination' => SitePaymentSettings::where('site_id', $from->id)->value('connect_account_id')]);

    // A second delivery of the same webhook changes nothing.
    expect($this->billing->collectForStripeInvoice('in_netbill', [$ref->stripe_invoice_item_id]))->toBe(0);
    expect($this->stripe->count('transfer'))->toBe(1);
});

test('olux credit method credits the referrer subscription customer', function () {
    $from = netBillSite(['stripe_customer_id' => 'cus_referrer']);
    $this->billing->setPayoutMethod($from, 'olux_credit');
    $ref = $this->billing->bill(netBillReferral($from, netBillSite(['stripe_customer_id' => 'cus_r'])));
    expect($ref->payout->method)->toBe('olux_credit');

    $this->billing->collectForStripeInvoice('in_1', [$ref->stripe_invoice_item_id]);

    $payout = $ref->payout()->first();
    expect($payout->status)->toBe('credited')->and($payout->stripe_balance_txn_id)->toStartWith('cbtxn_');
    expect(collect($this->stripe->calls)->firstWhere(0, 'balance')[1])
        ->toMatchArray(['customer' => 'cus_referrer', 'amount' => -1455, 'currency' => 'gbp']);
    expect(fn () => $this->billing->setPayoutMethod($from, 'cheque'))->toThrow(NetworkException::class);
});

test('without a ready Connect account the payout stays ready, then retry pays it', function () {
    $from = netBillSite();
    netBillConnect($from, enabled: false);
    $ref = $this->billing->bill(netBillReferral($from, netBillSite(['stripe_customer_id' => 'cus_r'])));

    $this->billing->markCollected($ref);
    expect($ref->payout()->first()->status)->toBe('ready')
        ->and($this->stripe->count('transfer'))->toBe(0)
        ->and($this->billing->earningsSummary($from)['connect_ready'])->toBeFalse();

    SitePaymentSettings::where('site_id', $from->id)->update(['connect_charges_enabled' => true]);
    expect($this->billing->retryPayouts())->toBe(1);
    expect($ref->payout()->first()->status)->toBe('transferred');
});

test('a Stripe failure marks the payout failed and the retry succeeds', function () {
    $from = netBillSite();
    netBillConnect($from);
    $this->stripe->failTransfers = 1;
    $ref = $this->billing->bill(netBillReferral($from, netBillSite(['stripe_customer_id' => 'cus_r'])));

    $this->billing->markCollected($ref);
    $payout = $ref->payout()->first();
    expect($payout->status)->toBe('failed')->and($payout->failure)->toContain('Insufficient funds');
    expect(ReferralEvent::where('referral_id', $ref->id)->where('type', 'payout_failed')->exists())->toBeTrue();

    $this->artisan('network:payouts-retry')->assertSuccessful();
    $payout->refresh();
    expect($payout->status)->toBe('transferred')->and($payout->failure)->toBeNull();
    // The retry used a fresh idempotency key.
    $keys = collect($this->stripe->calls)->where(0, 'transfer')->pluck(2);
    expect($keys->unique()->count())->toBe(2);
});

test('earnings summary adds up a referrer site payouts', function () {
    $from = netBillSite();
    netBillConnect($from);
    $to = netBillSite(['stripe_customer_id' => 'cus_r']);

    $paid = $this->billing->bill(netBillReferral($from, $to));
    $this->billing->markCollected($paid);                       // £14.55 transferred
    $this->billing->bill(netBillReferral($from, $to));          // £14.55 pending

    $s = $this->billing->earningsSummary($from);
    expect($s)->toMatchArray([
        'pending_cents' => 1455, 'ready_cents' => 0, 'paid_cents' => 1455, 'credited_cents' => 0,
        'lifetime_cents' => 2910, 'currency' => 'gbp', 'connect_ready' => true, 'method' => 'connect_transfer',
    ]);
    expect(ReferralPayout::where('site_id', $from->id)->sum('olux_cents'))->toEqual(90);

    $this->artisan('network:bill-due')->assertSuccessful();
});
