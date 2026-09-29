<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\PlatformSalesPage;
use App\Models\CreatorPayout;
use App\Models\PlatformSetting;
use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Models\TemplatePurchase;
use App\Models\User;
use App\Services\CreatorPayouts;
use App\Services\TemplateCommerce;
use App\Services\TwoFactor;
use App\Support\ConfigOverlay;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Stripe\Checkout\Session;

function slSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

function slTemplate(?User $owner, string $source = 'custom', int $price = 5000): Template
{
    $slug = 'sl-'.Str::lower(Str::random(8));

    return Template::create([
        'uuid' => (string) Str::uuid(), 'name' => 'Sl '.$slug, 'slug' => $slug, 'status' => 'published',
        'source' => $source, 'user_id' => $owner?->id, 'price_cents' => $price, 'currency' => 'gbp',
    ]);
}

function slSession(Template $t, User $buyer, ?string $pi = null): Session
{
    return Session::constructFrom([
        'id' => 'cs_test_'.Str::random(12),
        'payment_intent' => $pi ?? 'pi_'.Str::random(12),
        'metadata' => ['kind' => 'template', 'template_id' => $t->id, 'user_id' => $buyer->id],
    ]);
}

beforeEach(function () {
    config(['services.stripe_platform.fee_percent' => 20]);
    $this->settingsBefore = (int) PlatformSetting::max('id');
});
afterEach(function () {
    PlatformSetting::where('id', '>', $this->settingsBefore)->delete();
    ConfigOverlay::refresh();
});

test('a store sale is recorded as paid with the creator share split out', function () {
    $creator = User::factory()->create();
    $buyer = User::factory()->create();
    $creatorTpl = slTemplate($creator);
    $ownTpl = slTemplate(null, 'builtin');

    app(TemplateCommerce::class)->fulfilFromSession(slSession($creatorTpl, $buyer));
    app(TemplateCommerce::class)->fulfilFromSession(slSession($ownTpl, $buyer));

    $a = TemplatePurchase::where('template_id', $creatorTpl->id)->first();
    $b = TemplatePurchase::where('template_id', $ownTpl->id)->first();
    expect($a->status)->toBe('paid')
        ->and($a->creator_user_id)->toBe($creator->id)
        ->and($a->platform_fee_cents)->toBe(1000)
        ->and($a->creator_amount_cents)->toBe(4000)
        ->and($b->creator_user_id)->toBeNull()
        ->and($b->platform_fee_cents)->toBe(5000)
        ->and($b->creator_amount_cents)->toBe(0);
});

test('a refund marks the sale refunded and takes the template back', function () {
    $buyer = User::factory()->create();
    $t = slTemplate(User::factory()->create());
    $pi = 'pi_'.Str::random(12);
    app(TemplateCommerce::class)->fulfilFromSession(slSession($t, $buyer, $pi));
    expect(TemplateEntitlement::where('user_id', $buyer->id)->where('template_id', $t->id)->exists())->toBeTrue();

    app(TemplateCommerce::class)->refundByPaymentIntent($pi);

    expect(TemplatePurchase::where('stripe_payment_intent_id', $pi)->value('status'))->toBe('refunded')
        ->and(TemplateEntitlement::where('user_id', $buyer->id)->where('template_id', $t->id)->exists())->toBeFalse();
});

test('owed excludes sales on hold, and a manual payout settles them with clawbacks netted', function () {
    $creator = User::factory()->create();
    $admin = slSuper();
    $t = slTemplate($creator);
    $mk = fn (int $daysAgo, string $status = 'paid') => TemplatePurchase::create([
        'uuid' => (string) Str::uuid(), 'template_id' => $t->id, 'user_id' => User::factory()->create()->id,
        'creator_user_id' => $creator->id, 'price_cents' => 5000, 'currency' => 'gbp',
        'platform_fee_cents' => 1000, 'creator_amount_cents' => 4000, 'status' => $status,
        'purchased_at' => now()->subDays($daysAgo),
    ]);
    $old = $mk(10);
    $recent = $mk(1);

    $payouts = app(CreatorPayouts::class);
    expect($payouts->summaryFor($creator->id))->toMatchArray(['payable' => 4000, 'held' => 4000, 'owed' => 4000]);

    $first = $payouts->payOut($creator, $admin, 'manual', 'Bank ref 123');
    expect($first->status)->toBe('paid')->and($first->amount_cents)->toBe(4000)
        ->and($old->fresh()->payout_id)->toBe($first->id)
        ->and($recent->fresh()->payout_id)->toBeNull();

    // The paid-out sale is refunded later; the next payout claws it back.
    $old->update(['status' => 'refunded', 'refunded_at' => now()]);
    $recent->update(['purchased_at' => now()->subDays(8)]);
    $newer = $mk(9);
    expect($payouts->summaryFor($creator->id)['owed'])->toBe(4000 + 4000 - 4000);

    $second = $payouts->payOut($creator, $admin, 'manual', 'Bank ref 456');
    expect($second->amount_cents)->toBe(4000)
        ->and($old->fresh()->clawback_payout_id)->toBe($second->id)
        ->and($payouts->summaryFor($creator->id)['owed'])->toBe(0);
});

test('a Stripe payout transfers to the connected account, and a failed transfer releases the sales', function () {
    $creator = User::factory()->create(['stripe_account_id' => 'acct_test123', 'stripe_charges_enabled' => true]);
    $admin = slSuper();
    $t = slTemplate($creator);
    TemplatePurchase::create([
        'uuid' => (string) Str::uuid(), 'template_id' => $t->id, 'user_id' => User::factory()->create()->id,
        'creator_user_id' => $creator->id, 'price_cents' => 2500, 'currency' => 'gbp',
        'platform_fee_cents' => 500, 'creator_amount_cents' => 2000, 'status' => 'paid', 'purchased_at' => now()->subDays(30),
    ]);

    $failing = new class
    {
        public $transfers;

        public function __construct()
        {
            $this->transfers = new class
            {
                public function create(array $p, array $o)
                {
                    throw new RuntimeException('Insufficient platform balance');
                }
            };
        }
    };
    $failed = app(CreatorPayouts::class)->payOut($creator, $admin, 'stripe', null, $failing);
    expect($failed->status)->toBe('failed')->and($failed->note)->toContain('Insufficient platform balance')
        ->and(app(CreatorPayouts::class)->summaryFor($creator->id)['owed'])->toBe(2000);

    $sent = [];
    $ok = new class($sent)
    {
        public $transfers;

        public function __construct(array &$sent)
        {
            $this->transfers = new class($sent)
            {
                public function __construct(private array &$sent) {}

                public function create(array $p, array $o)
                {
                    $this->sent[] = [$p, $o];

                    return (object) ['id' => 'tr_test_1'];
                }
            };
        }
    };
    $paid = app(CreatorPayouts::class)->payOut($creator, $admin, 'stripe', null, $ok);
    expect($paid->status)->toBe('paid')->and($paid->stripe_transfer_id)->toBe('tr_test_1')
        ->and($sent[0][0]['destination'])->toBe('acct_test123')
        ->and($sent[0][0]['amount'])->toBe(2000)
        ->and($sent[0][1]['idempotency_key'])->toBe('creator-payout-'.$paid->id);
});

test('the sales page is super-only and the fee can be changed', function () {
    $this->actingAs(User::factory()->create())->get('/admin/sales')->assertForbidden();
    $admin = slSuper();
    $this->actingAs($admin)->withSession([EnsureSuperAdmin::SESSION_KEY => now()])->get('/admin/sales')->assertOk()->assertSee('Sales &amp; payouts', false);

    Livewire::actingAs($admin)->test(PlatformSalesPage::class)->set('feePercent', '15')->call('saveFee')->assertHasNoErrors();
    expect((float) config('services.stripe_platform.fee_percent'))->toBe(15.0)
        ->and(app(TemplateCommerce::class)->feeCents(10000))->toBe(1500);
});

test('the payout drawer records a manual payout', function () {
    $creator = User::factory()->create();
    $t = slTemplate($creator);
    TemplatePurchase::create([
        'uuid' => (string) Str::uuid(), 'template_id' => $t->id, 'user_id' => User::factory()->create()->id,
        'creator_user_id' => $creator->id, 'price_cents' => 1000, 'currency' => 'gbp',
        'platform_fee_cents' => 200, 'creator_amount_cents' => 800, 'status' => 'paid', 'purchased_at' => now()->subDays(20),
    ]);

    Livewire::actingAs(slSuper())->test(PlatformSalesPage::class)
        ->call('startPayout', $creator->id)
        ->assertSet('payMethod', 'manual')
        ->call('payOut')->assertHasErrors('payNote')
        ->set('payNote', 'Paid by bank transfer')
        ->call('payOut')->assertHasNoErrors();

    expect(CreatorPayout::where('creator_user_id', $creator->id)->value('amount_cents'))->toBe(800);
});
