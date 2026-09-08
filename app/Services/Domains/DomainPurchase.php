<?php

namespace App\Services\Domains;

use App\Models\DomainOrder;
use App\Models\Site;
use App\Models\User;
use App\Services\PlatformBilling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
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
        foreach (config('domains.tlds') as $tld => $cfg) {
            if (Str::endsWith($domain, '.'.$tld)) {
                return (int) $cfg['price_cents'];
            }
        }

        return null;
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
        $price = $this->priceFor($domain);
        abort_if($price === null, 422, 'That domain extension is not offered.');

        $sub = $user->currentSubscription();
        $buyPlan = $plan && config("plans.tiers.{$plan}") && $plan !== 'trial'
            && ! ($sub->plan === $plan && $sub->status === 'active');

        $order = DomainOrder::create([
            'user_id' => $user->id, 'site_id' => $site->id, 'domain' => $domain,
            'years' => (int) config('domains.years', 1), 'price_cents' => $price,
            'plan' => $buyPlan ? $plan : null, 'status' => 'pending',
        ]);

        if (! $this->billing->configured()) {
            $this->fulfil($order);

            return $backUrl;
        }

        $lines = [[
            'price_data' => [
                'currency' => 'gbp',
                'product_data' => ['name' => "Domain {$domain} ({$order->years} year)"],
                'unit_amount' => $price,
            ],
            'quantity' => 1,
        ]];
        $params = [
            'mode' => 'payment',
            'customer_email' => $sub->stripe_customer_id ? null : $user->email,
            'customer' => $sub->stripe_customer_id,
            'metadata' => ['kind' => 'domain', 'order_id' => $order->id, 'user_id' => $user->id, 'back' => $backUrl],
            'success_url' => route('site.domain.success', $site).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $backUrl,
        ];
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

    /** Success-return: verify the session server-side, then fulfil. Returns the back URL. */
    public function fulfilFromSession(User $user, string $sessionId): ?string
    {
        $session = $this->billing->client()->checkout->sessions->retrieve($sessionId);
        if (! $session || (int) ($session->metadata->user_id ?? 0) !== $user->id) {
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
    public function fulfil(DomainOrder $order, ?Session $session = null): void
    {
        $order = DB::transaction(function () use ($order) {
            $o = DomainOrder::lockForUpdate()->find($order->id);
            if ($o->status === 'registered') {
                return null;
            }
            if ($o->status === 'pending') {
                $o->update(['status' => 'paid']);
            }

            return $o;
        });
        if (! $order) {
            return;
        }

        $user = $order->user;
        $site = $order->site;

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
