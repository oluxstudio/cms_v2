<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Payments\Drivers\StripeConnectGateway;
use App\Payments\SitePaymentOnboarding;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Stripe\StripeClient;

/**
 * Payments — its own page: connect (or key-configure) Stripe, flip the
 * Accept-payments switch, pick the currency, and see the money at a glance
 * (live Stripe balance for connected accounts + what this CMS has recorded).
 */
class SitePaymentsPage extends Component
{
    public Site $site;

    public bool $acceptPayments = false;

    public string $siteCurrency = 'gbp';

    public string $pubKey = '';

    public string $secretKey = '';

    public string $webhookSecret = '';

    public bool $hasSecret = false;

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->canManageTeam(Auth::user()), 403);

        $ps = $site->paymentSettings;
        $this->acceptPayments = (bool) ($ps?->enabled);
        $this->siteCurrency = $site->currency ?? 'gbp';
        $this->pubKey = $ps->stripe_publishable ?? '';
        $this->hasSecret = (bool) ($ps && filled($ps->stripe_secret));

        if ($flash = session('mp-message')) {
            $this->successMessage = $flash;
        }
    }

    #[Computed]
    public function settings(): ?SitePaymentSettings
    {
        return $this->site->paymentSettings;
    }

    public function connectAvailable(): bool
    {
        return StripeConnectGateway::platformConfigured();
    }

    public function save(): void
    {
        abort_unless($this->site->canManageTeam(Auth::user()), 403);
        $this->successMessage = $this->errorMessage = '';

        $this->validate([
            'pubKey' => ['nullable', 'string', 'max:255'],
            'secretKey' => ['nullable', 'string', 'max:255'],
            'webhookSecret' => ['nullable', 'string', 'max:255'],
        ]);

        $ps = $this->site->paymentSettings ?: new SitePaymentSettings(['site_id' => $this->site->id]);
        $ps->site_id = $this->site->id;
        if (filled($this->pubKey)) {
            $ps->stripe_publishable = $this->pubKey;
        }
        if (filled($this->secretKey)) {
            $ps->stripe_secret = $this->secretKey;
        }
        if (filled($this->webhookSecret)) {
            $ps->stripe_webhook_secret = $this->webhookSecret;
        }
        $ps->livemode = str_starts_with($this->pubKey, 'pk_live_');

        // Which driver? Keys entered → manual stripe; connected account → connect.
        if (filled($ps->stripe_secret) && filled($ps->stripe_publishable) && ! $ps->connect_account_id) {
            $ps->provider = 'stripe';
        }
        $ps->provider = $ps->provider ?: config('payments.default');

        // The explicit ON/OFF switch — can't be on without a working setup.
        $configured = $ps->isConfigured() || ($ps->connect_account_id && $ps->connect_charges_enabled);
        if ($this->acceptPayments && ! $configured) {
            $this->errorMessage = 'Connect Stripe (or add API keys) before switching payments on.';
            $this->acceptPayments = false;
        }
        $ps->enabled = $this->acceptPayments;
        $ps->save();
        unset($this->settings);
        $this->hasSecret = filled($ps->stripe_secret);
        $this->secretKey = $this->webhookSecret = '';

        if (array_key_exists(strtolower($this->siteCurrency), config('currencies'))) {
            $this->site->update(['currency' => strtolower($this->siteCurrency)]);
        }

        $this->successMessage = $ps->enabled
            ? 'Saved — this site is accepting payments.'
            : 'Saved — payments are switched off.';
    }

    /** Re-ask Stripe whether onboarding finished (charges enabled). */
    public function refreshStatus(): void
    {
        abort_unless($this->site->canManageTeam(Auth::user()), 403);
        try {
            app(SitePaymentOnboarding::class)->syncAccount($this->site);
            $this->site->refresh();
            unset($this->settings);
            $this->acceptPayments = (bool) $this->site->paymentSettings?->enabled;
            $this->successMessage = $this->site->paymentsEnabled()
                ? 'Stripe says: ready — this site is accepting payments.'
                : 'Stripe says: onboarding not finished yet — continue on Stripe to complete it.';
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Could not reach Stripe: '.$e->getMessage();
        }
    }

    /** Live balance from the connected Stripe account (null when unavailable). */
    #[Computed]
    public function stripeBalance(): ?array
    {
        $ps = $this->site->paymentSettings;
        if (! $ps?->connect_account_id || ! StripeConnectGateway::platformConfigured()) {
            return null;
        }
        try {
            $client = new StripeClient([
                'api_key' => config('services.stripe_platform.secret'),
                'stripe_version' => config('services.stripe.api_version'),
            ]);
            $balance = $client->balance->retrieve([], ['stripe_account' => $ps->connect_account_id]);
            $sum = fn ($rows) => collect($rows)->sum('amount');

            return [
                'available_cents' => (int) $sum($balance->available ?? []),
                'pending_cents' => (int) $sum($balance->pending ?? []),
                'currency' => $balance->available[0]->currency ?? ($this->site->currency ?? 'gbp'),
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** What this CMS has recorded — collected (30 days) and outstanding. */
    #[Computed]
    public function money(): array
    {
        $since = now()->subDays(30);
        $collected = 0;
        $outstanding = 0;
        if ($this->site->hasFeature('invoices')) {
            $collected += (int) Invoice::where('site_id', $this->site->id)->where('status', 'paid')->where('paid_at', '>=', $since)->sum('total_cents');
            $outstanding += (int) Invoice::where('site_id', $this->site->id)->collectible()->sum('total_cents');
        }
        if ($this->site->hasFeature('store')) {
            $collected += (int) Order::where('site_id', $this->site->id)->whereIn('status', ['paid', 'fulfilled'])->where('created_at', '>=', $since)->sum('total_cents');
        }

        return ['collected_cents' => $collected, 'outstanding_cents' => $outstanding];
    }

    public function render()
    {
        return view('livewire.site-payments-page');
    }
}
