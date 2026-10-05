<?php

namespace App\Services\Domains;

use App\Jobs\BuildTemplateShell;
use App\Models\AccountSubscription;
use App\Models\DomainOrder;
use App\Models\Site;
use App\Models\User;
use App\Services\PlatformBilling;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
use Throwable;

/**
 * Buy a domain (and, in the same Checkout, the hosting plan) from inside the
 * CMS, then turn it into a live site: register → DNS → attach to the Site →
 * verified + live. Everything after payment is idempotent — the Stripe
 * webhook and the success-return page can both call fulfil().
 *
 * Without platform Stripe keys (local dev) the order is fulfilled instantly
 * via the configured registrar (the fake one by default).
 */
class DomainPurchase
{
    public function __construct(
        private Registrar $registrar,
        private PlatformBilling $billing,
    ) {}

    /** @return list<string> */
    public function tlds(): array
    {
        return array_keys((array) config('domains.tlds'));
    }

    public function priceFor(string $domain): ?int
    {
        // Longest suffix first, so "x.co.uk" never matches ".uk".
        $tlds = (array) config('domains.tlds');
        uksort($tlds, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($tlds as $tld => $cfg) {
            if (Str::endsWith($domain, '.'.$tld)) {
                return (int) $cfg['price_cents'];
            }
        }

        return null;
    }

    /**
     * What this account pays for $domain's first year: the retail price, or 0
     * when its plan — or $buyingPlan, bought in the same checkout — includes a
     * free domain (Growth: .co.uk, Pro/Enterprise: any) and the account hasn't
     * had its free domain yet. Renewals always charge retail.
     */
    public function priceForAccount(User $user, string $domain, ?string $buyingPlan = null): ?int
    {
        $retail = $this->priceFor($domain);
        if ($retail === null) {
            return null;
        }
        $sub = $user->currentSubscription();
        $plan = $buyingPlan ?: ($sub->status === 'active' ? $sub->plan : null);

        return $plan && AccountSubscription::planIncludesDomain($plan, $domain) && ! $this->usedFreeDomain($user)
            ? 0 : $retail;
    }

    /** Has this account already registered its plan's free domain? */
    public function usedFreeDomain(User $user): bool
    {
        return DomainOrder::where('user_id', $user->id)->where('type', 'register')
            ->where('price_cents', 0)->whereIn('status', ['paid', 'registered'])->exists();
    }

    /** The label a visitor typed, without TLD/scheme/spaces. */
    public static function label(string $query): string
    {
        $q = strtolower(trim($query));
        $q = preg_replace('#^https?://#', '', $q);
        $q = preg_replace('#[/?].*$#', '', $q);
        $q = preg_replace('/^www\./', '', $q);
        $q = explode('.', $q)[0] ?? '';

        return trim(preg_replace('/[^a-z0-9-]+/', '-', $q), '-');
    }

    /**
     * Availability + price for every offered TLD.
     *
     * @return list<array{domain:string,tld:string,available:bool,price_cents:int,taken_here:bool}>
     */
    public function search(string $query): array
    {
        $label = self::label($query);
        if (strlen($label) < 2) {
            return [];
        }
        $avail = $this->registrar->available($label, $this->tlds());

        $rows = [];
        foreach (config('domains.tlds') as $tld => $cfg) {
            $domain = "{$label}.{$tld}";
            $takenHere = Site::where('domain', $domain)->exists();
            $rows[] = [
                'domain' => $domain,
                'tld' => $tld,
                'available' => ($avail[$domain] ?? false) && ! $takenHere,
                'price_cents' => (int) $cfg['price_cents'],
                'taken_here' => $takenHere,
            ];
        }

        return $rows;
    }

    /**
     * Start a purchase. Returns a URL to send the buyer to: Stripe Checkout
     * when billing is configured, otherwise the order is fulfilled now and
     * the back URL is returned.
     */
    public function start(User $user, Site $site, string $domain, ?string $plan, string $backUrl): string
    {
        $domain = strtolower(trim($domain));
        $sub = $user->currentSubscription();
        $buyPlan = $plan && config("plans.tiers.{$plan}") && $plan !== 'trial'
            && ! ($sub->plan === $plan && $sub->status === 'active');
        $price = $this->priceForAccount($user, $domain, $buyPlan ? $plan : null);
        abort_if($price === null, 422, 'That domain extension is not offered.');

        $order = DomainOrder::create([
            'user_id' => $user->id, 'site_id' => $site->id, 'domain' => $domain,
            'years' => (int) config('domains.years', 1), 'price_cents' => $price,
            'plan' => $buyPlan ? $plan : null, 'status' => 'checkout',
        ]);

        if (! $this->billing->configured() || ($price === 0 && ! $buyPlan)) {
            $this->fulfil($order);

            return $backUrl;
        }

        $lines = $price > 0 ? [[
            'price_data' => [
                'currency' => 'gbp',
                'product_data' => ['name' => "Domain {$domain} ({$order->years} year)"],
                'unit_amount' => $price,
            ],
            'quantity' => 1,
        ]] : [];
        $params = [
            'mode' => 'payment',
            // Ad-hoc price_data has no product tax code — opt this session out of
            // Stripe Managed Payments (on by default), which would require one.
            'managed_payments' => ['enabled' => false],
            'metadata' => ['kind' => 'domain', 'order_id' => $order->id, 'user_id' => $user->id, 'back' => $backUrl],
            'success_url' => route('site.domain.success', $site).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $backUrl,
        ];
        // Stripe rejects sending BOTH customer and customer_email — even a null one.
        if ($sub->stripe_customer_id) {
            $params['customer'] = $sub->stripe_customer_id;
        } else {
            $params['customer_email'] = $user->email;
        }
        if ($buyPlan) {
            $tier = config("plans.tiers.{$plan}");
            $params['mode'] = 'subscription';
            $lines[] = [
                'price_data' => [
                    'currency' => 'gbp',
                    'product_data' => ['name' => 'Olux '.$tier['name'].' hosting plan'],
                    'unit_amount' => max(1, $sub->priceFor($plan)),
                    'recurring' => ['interval' => 'month'],
                ],
                'quantity' => 1,
            ];
            $params['subscription_data'] = ['metadata' => ['user_id' => $user->id, 'plan' => $plan]];
        }
        $params['line_items'] = $lines;

        $session = $this->billing->client()->checkout->sessions->create($params);
        $order->update(['stripe_session_id' => $session->id]);

        return $session->url;
    }

    /**
     * Start a RENEWAL for a domain this site already owns. Returns a URL to
     * send the buyer to (Stripe Checkout, or the back URL when billing is
     * not configured and the renewal happened instantly).
     */
    public function startRenewal(User $user, DomainOrder $registered, string $backUrl): string
    {
        abort_unless($registered->status === 'registered', 422, 'Only a registered domain can be renewed.');
        $price = $this->priceFor($registered->domain);
        abort_if($price === null, 422, 'That domain extension is not offered.');

        $order = DomainOrder::create([
            'user_id' => $user->id, 'site_id' => $registered->site_id,
            'domain' => $registered->domain, 'type' => 'renew',
            'years' => (int) config('domains.years', 1), 'price_cents' => $price,
            'status' => 'checkout',
        ]);

        if (! $this->billing->configured()) {
            $this->fulfil($order);

            return $backUrl;
        }

        $session = $this->billing->client()->checkout->sessions->create([
            'mode' => 'payment',
            'managed_payments' => ['enabled' => false],
            'customer_email' => $user->email,
            'metadata' => ['kind' => 'domain', 'order_id' => $order->id, 'user_id' => $user->id, 'back' => $backUrl],
            'success_url' => route('site.domain.success', $registered->site).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $backUrl,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'gbp',
                    'product_data' => ['name' => "Renew {$registered->domain} ({$order->years} year)"],
                    'unit_amount' => $price,
                ],
                'quantity' => 1,
            ]],
        ]);
        $order->update(['stripe_session_id' => $session->id]);

        return $session->url;
    }

    // ─────────────────────────────────────────────────────────────
    // On-page payment (Stripe Payment Element)
    // ─────────────────────────────────────────────────────────────

    /**
     * Get a domain purchase ready to pay on the page. The order waits in
     * status `checkout` (not shown as "buying" anywhere) until the payment
     * succeeds. Domain only → a PaymentIntent; domain + hosting plan → a
     * Subscription whose first invoice also carries the domain, so it's one
     * payment either way. Without platform Stripe keys the order is fulfilled
     * straight away (done: true).
     *
     * @return array{order: DomainOrder, client_secret: ?string, done: bool, total_cents: int}
     */
    public function preparePayment(User $user, Site $site, string $domain, ?string $plan): array
    {
        $domain = strtolower(trim($domain));
        $sub = $user->currentSubscription();
        $buyPlan = $plan && config("plans.tiers.{$plan}") && $plan !== 'trial'
            && ! ($sub->plan === $plan && $sub->status === 'active');
        $plan = $buyPlan ? $plan : null;
        $price = $this->priceForAccount($user, $domain, $plan);
        abort_if($price === null, 422, 'That domain extension is not offered.');
        $total = $price + ($plan ? max(1, $sub->priceFor($plan)) : 0);

        // Nothing to pay (no Stripe locally, or the plan's free domain with no plan to buy).
        if (! $this->billing->configured() || $total === 0) {
            $order = DomainOrder::create([
                'user_id' => $user->id, 'site_id' => $site->id, 'domain' => $domain,
                'years' => (int) config('domains.years', 1), 'price_cents' => $price,
                'plan' => $plan, 'status' => 'checkout',
            ]);
            $this->fulfil($order);

            return ['order' => $order->fresh(), 'client_secret' => null, 'done' => true, 'total_cents' => $total];
        }

        // Going back and forth (or reloading) reuses the same unpaid order.
        $order = DomainOrder::where('user_id', $user->id)->where('site_id', $site->id)
            ->where('domain', $domain)->where('type', 'register')->where('status', 'checkout')
            ->where('plan', $plan)->latest()->first()
            ?? DomainOrder::create([
                'user_id' => $user->id, 'site_id' => $site->id, 'domain' => $domain,
                'years' => (int) config('domains.years', 1), 'price_cents' => $price,
                'plan' => $plan, 'status' => 'checkout',
            ]);
        $order->update(['price_cents' => $price]);

        $stripe = $this->billing->client();
        $customer = $this->customerFor($user);
        $meta = ['kind' => 'domain', 'order_id' => $order->id, 'user_id' => $user->id];

        if ($plan) {
            $secret = $this->reusableSecret($order) ?? (function () use ($stripe, $order, $customer, $meta, $plan, $sub, $price) {
                $tier = config("plans.tiers.{$plan}");
                $subscription = $stripe->subscriptions->create([
                    'customer' => $customer,
                    'items' => [[
                        'price_data' => [
                            'currency' => 'gbp',
                            'product' => $this->product('olux_plan_'.$plan, 'Olux '.$tier['name'].' hosting plan'),
                            'unit_amount' => max(1, $sub->priceFor($plan)),
                            'recurring' => ['interval' => 'month'],
                        ],
                    ]],
                    // The domain rides on the first invoice: one payment for both
                    // (no line at all when the plan includes it free).
                    'add_invoice_items' => $price > 0 ? [[
                        'price_data' => [
                            'currency' => 'gbp',
                            'product' => $this->product('olux_domain_registration', 'Domain registration'),
                            'unit_amount' => $price,
                        ],
                    ]] : [],
                    'payment_behavior' => 'default_incomplete',
                    'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                    'metadata' => $meta + ['plan' => $plan, 'domain' => $order->domain],
                    'expand' => ['latest_invoice.confirmation_secret'],
                ]);
                $order->update(['stripe_subscription_id' => $subscription->id]);

                return $subscription->latest_invoice->confirmation_secret->client_secret;
            })();
        } else {
            $secret = $this->reusableSecret($order) ?? (function () use ($stripe, $order, $customer, $meta, $price) {
                $intent = $stripe->paymentIntents->create([
                    'amount' => $price,
                    'currency' => 'gbp',
                    'customer' => $customer,
                    'description' => "Domain {$order->domain} ({$order->years} year)",
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => $meta + ['domain' => $order->domain],
                ]);
                $order->update(['stripe_payment_intent_id' => $intent->id]);

                return $intent->client_secret;
            })();
        }

        return ['order' => $order->fresh(), 'client_secret' => $secret, 'done' => false, 'total_cents' => $total];
    }

    /** A renewal paid on the page — same contract as preparePayment(). */
    public function prepareRenewalPayment(User $user, DomainOrder $registered): array
    {
        abort_unless($registered->status === 'registered', 422, 'Only a registered domain can be renewed.');
        $price = $this->priceFor($registered->domain);
        abort_if($price === null, 422, 'That domain extension is not offered.');

        if (! $this->billing->configured()) {
            $order = DomainOrder::create([
                'user_id' => $user->id, 'site_id' => $registered->site_id, 'domain' => $registered->domain,
                'type' => 'renew', 'years' => (int) config('domains.years', 1), 'price_cents' => $price, 'status' => 'checkout',
            ]);
            $this->fulfil($order);

            return ['order' => $order->fresh(), 'client_secret' => null, 'done' => true, 'total_cents' => $price];
        }

        $order = DomainOrder::where('site_id', $registered->site_id)->where('domain', $registered->domain)
            ->where('type', 'renew')->where('status', 'checkout')->latest()->first()
            ?? DomainOrder::create([
                'user_id' => $user->id, 'site_id' => $registered->site_id, 'domain' => $registered->domain,
                'type' => 'renew', 'years' => (int) config('domains.years', 1), 'price_cents' => $price, 'status' => 'checkout',
            ]);

        $secret = $this->reusableSecret($order) ?? (function () use ($user, $order, $price) {
            $intent = $this->billing->client()->paymentIntents->create([
                'amount' => $price,
                'currency' => 'gbp',
                'customer' => $this->customerFor($user),
                'description' => "Renew {$order->domain} ({$order->years} year)",
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => ['kind' => 'domain', 'order_id' => $order->id, 'user_id' => $user->id, 'domain' => $order->domain],
            ]);
            $order->update(['stripe_payment_intent_id' => $intent->id]);

            return $intent->client_secret;
        })();

        return ['order' => $order->fresh(), 'client_secret' => $secret, 'done' => false, 'total_cents' => $price];
    }

    /**
     * After the Payment Element confirms (or Stripe returns from a bank
     * redirect): check the payment with Stripe — never trust the browser —
     * and fulfil. Returns paid | processing | unpaid.
     */
    public function completePayment(DomainOrder $order): string
    {
        if (in_array($order->status, ['paid', 'registered'], true)) {
            return 'paid';
        }
        if ($order->status !== 'checkout') {
            return 'unpaid';
        }
        $stripe = $this->billing->client();

        if ($order->stripe_subscription_id) {
            $subscription = $stripe->subscriptions->retrieve($order->stripe_subscription_id, ['expand' => ['latest_invoice']]);
            $invoice = $subscription->latest_invoice;
            if (in_array($subscription->status, ['active', 'trialing'], true) || ($invoice->status ?? null) === 'paid') {
                $this->fulfil($order, (object) ['customer' => $subscription->customer, 'subscription' => $subscription->id]);

                return 'paid';
            }

            return 'unpaid';
        }

        if ($order->stripe_payment_intent_id) {
            $intent = $stripe->paymentIntents->retrieve($order->stripe_payment_intent_id);
            if ($intent->status === 'succeeded') {
                $this->fulfil($order, (object) ['customer' => $intent->customer, 'subscription' => null]);

                return 'paid';
            }

            return $intent->status === 'processing' ? 'processing' : 'unpaid';
        }

        return 'unpaid';
    }

    /** Webhook: payment_intent.succeeded (domain only / renewal). */
    public function fulfilFromPaymentIntent(object $intent): void
    {
        if (($intent->metadata->kind ?? '') !== 'domain') {
            return;
        }
        $order = DomainOrder::find((string) ($intent->metadata->order_id ?? ''));
        if ($order && $order->stripe_payment_intent_id === $intent->id) {
            $this->fulfil($order, (object) ['customer' => $intent->customer ?? null, 'subscription' => null]);
        }
    }

    /** Webhook: invoice.paid on a domain + plan subscription. */
    public function fulfilFromInvoice(object $invoice): void
    {
        $subId = $invoice->parent->subscription_details->subscription ?? ($invoice->subscription ?? null);
        $subId = is_object($subId) ? $subId->id : $subId;
        if (! $subId) {
            return;
        }
        $order = DomainOrder::where('stripe_subscription_id', $subId)->first();
        if ($order) {
            $this->fulfil($order, (object) ['customer' => $invoice->customer ?? null, 'subscription' => $subId]);
        }
    }

    /** The order's still-payable client secret, so a retry doesn't open a second payment. */
    private function reusableSecret(DomainOrder $order): ?string
    {
        $payable = ['requires_payment_method', 'requires_confirmation', 'requires_action'];
        try {
            if ($order->stripe_payment_intent_id) {
                $intent = $this->billing->client()->paymentIntents->retrieve($order->stripe_payment_intent_id);
                if (in_array($intent->status, $payable, true) && (int) $intent->amount === (int) $order->price_cents) {
                    return $intent->client_secret;
                }
            }
            if ($order->stripe_subscription_id) {
                $sub = $this->billing->client()->subscriptions->retrieve($order->stripe_subscription_id, ['expand' => ['latest_invoice.confirmation_secret']]);
                if ($sub->status === 'incomplete' && ($sub->latest_invoice->confirmation_secret->client_secret ?? null)) {
                    return $sub->latest_invoice->confirmation_secret->client_secret;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }

    /** The account's Stripe customer, created (and remembered) on first payment. */
    private function customerFor(User $user): string
    {
        $sub = $user->currentSubscription();
        if ($sub->stripe_customer_id) {
            return $sub->stripe_customer_id;
        }
        $customer = $this->billing->client()->customers->create([
            'email' => $user->email, 'name' => $user->name, 'metadata' => ['user_id' => $user->id],
        ]);
        $sub->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /** A fixed platform product (subscriptions need a product id, not inline product data). */
    private function product(string $id, string $name): string
    {
        $stripe = $this->billing->client();
        try {
            return $stripe->products->retrieve($id)->id;
        } catch (InvalidRequestException) {
            return $stripe->products->create(['id' => $id, 'name' => $name])->id;
        }
    }

    /** Success-return: verify the session server-side, then fulfil. Returns the back URL. */
    public function fulfilFromSession(User $user, string $sessionId): ?string
    {
        $session = $this->billing->client()->checkout->sessions->retrieve($sessionId);
        if (! $session || (string) ($session->metadata->user_id ?? '') !== (string) $user->id) {
            return null;
        }
        if (! in_array($session->payment_status, ['paid', 'no_payment_required'], true)) {
            return null;
        }
        $order = DomainOrder::find((string) ($session->metadata->order_id ?? ''));
        if ($order) {
            $this->fulfil($order, $session);
        }

        return (string) ($session->metadata->back ?? '') ?: null;
    }

    /** Webhook entry: a completed checkout with kind=domain. */
    public function fulfilFromWebhookSession(Session $session): void
    {
        $order = DomainOrder::find((string) ($session->metadata->order_id ?? ''));
        if ($order) {
            $this->fulfil($order, $session);
        }
    }

    /**
     * Paid → plan active → domain registered → DNS at us → site live.
     * Idempotent; a registrar failure leaves the order `failed` with the
     * reason (the money is taken — support refunds/retries by hand).
     */
    public function fulfil(DomainOrder $order, ?object $session = null): void
    {
        $order = DB::transaction(function () use ($order) {
            $o = DomainOrder::lockForUpdate()->find($order->id);
            if ($o->status === 'registered') {
                return null;
            }
            if (in_array($o->status, ['pending', 'checkout'], true)) {
                $o->update(['status' => 'paid']);
            }

            return $o;
        });
        if (! $order) {
            return;
        }

        $user = $order->user;
        $site = $order->site;

        // ── Renewal: extend at the registrar, roll the expiry forward. ──
        if ($order->type === 'renew') {
            try {
                $this->registrar->renew($order->domain, $order->years);
            } catch (Throwable $e) {
                report($e);
                $order->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)]);

                return;
            }

            $base = DomainOrder::where('domain', $order->domain)->where('status', 'registered')
                ->max('expires_at');
            $expires = ($base ? Carbon::parse($base) : now())->addYears($order->years);
            $order->update(['status' => 'registered', 'expires_at' => $expires, 'error' => null]);
            // The canonical expiry lives on the ORIGINAL registration row too.
            DomainOrder::where('domain', $order->domain)->where('type', 'register')
                ->where('status', 'registered')->update(['expires_at' => $expires]);

            return;
        }

        if ($order->plan) {
            $this->billing->activate($user, $order->plan, $session);
        }

        try {
            $ref = $this->registrar->register($order->domain, $this->contactFor($user), $order->years);
            $target = (string) config('domains.dns_target') ?: (string) config('publishing.dns_target');
            if ($target !== '') {
                $this->registrar->pointAt($order->domain, $target);
            }
        } catch (Throwable $e) {
            report($e);
            $order->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)]);

            return;
        }

        $order->update([
            'status' => 'registered',
            'registrar_ref' => $ref,
            'expires_at' => now()->addYears($order->years),
            'error' => null,
        ]);

        // Hosting package: the site answers on the new domain, verified + live.
        $site->update([
            'domain' => $order->domain,
            'domain_verified_at' => now(),
            'live' => true,
        ]);

        // Hosting space: make sure the template shell exists so the new domain
        // serves the real site, not the holding page. Never fails the order.
        try {
            BuildTemplateShell::ensure($site->fresh());
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<string,string> */
    private function contactFor(User $user): array
    {
        $d = (array) config('domains.registrant');

        return [
            'name' => $user->name,
            'email' => $user->email,
            'company' => (string) ($d['company'] ?? ''),
            'address' => (string) ($d['address'] ?? ''),
            'city' => (string) ($d['city'] ?? ''),
            'zip' => (string) ($d['zip'] ?? ''),
            'country' => (string) ($d['country'] ?? 'GB'),
            'phone' => (string) ($user->phone ?: ($d['phone'] ?? '')),
        ];
    }
}
