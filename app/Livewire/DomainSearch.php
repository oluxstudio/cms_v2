<?php

namespace App\Livewire;

use App\Models\DomainOrder;
use App\Models\Site;
use App\Services\Domains\DomainPurchase;
use App\Support\PlanCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

/**
 * "Buy a domain" panel on the Go-live page: search across offered TLDs,
 * pick a hosting plan when the account is still on trial, pay once, and
 * the site is live on the new domain when the buyer comes back.
 */
class DomainSearch extends Component
{
    public Site $site;

    public string $query = '';

    /** @var list<array{domain:string,tld:string,available:bool,price_cents:int,taken_here:bool}> */
    public array $results = [];

    public string $plan = '';

    public string $errorMessage = '';

    /** The domain the client picked from the results (drives the sticky bar). */
    public ?string $selected = null;

    /** Payment step (Stripe Payment Element): the unpaid order being paid for. */
    public ?string $payOrderId = null;

    public ?string $clientSecret = null;

    public int $payTotalCents = 0;

    public string $payError = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'publish.manage'), 403);
        $this->query = $site->getAttr('business_name') ?: '';
        $this->plan = $this->needsPlan() ? 'starter' : '';
    }

    /** On the trial, or a plan without own domains (Free) → the checkout also picks a paid plan. */
    public function needsPlan(): bool
    {
        $sub = Auth::user()->currentSubscription();

        return $sub->plan === 'trial' || $sub->status !== 'active' || ! $sub->allowsCustomDomain();
    }

    public function search(DomainPurchase $domains): void
    {
        $this->errorMessage = '';
        $this->results = [];
        if (strlen(DomainPurchase::label($this->query)) < 2) {
            $this->errorMessage = 'Type at least two characters.';

            return;
        }
        $this->selected = null;
        try {
            $this->results = $this->withAccountPrices($domains->search($this->query), $domains);
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Domain lookup is unavailable right now — try again in a minute.';
        }
    }

    /**
     * Each result's price for THIS account: 0 when its plan (or the plan picked
     * alongside) includes the domain free for year 1. retail_cents keeps the list price.
     */
    private function withAccountPrices(array $rows, DomainPurchase $domains): array
    {
        $plan = $this->needsPlan() ? ($this->plan ?: null) : null;

        return array_map(function ($r) use ($domains, $plan) {
            $r['retail_cents'] = $r['retail_cents'] ?? $r['price_cents'];
            $r['price_cents'] = $domains->priceForAccount(Auth::user(), $r['domain'], $plan) ?? $r['retail_cents'];
            $r['free'] = $r['price_cents'] === 0 && $r['retail_cents'] > 0;

            return $r;
        }, $rows);
    }

    /** Picking a different plan can make a domain free (or not). */
    public function updatedPlan(DomainPurchase $domains): void
    {
        $this->results = $this->withAccountPrices($this->results, $domains);
    }

    public function select(string $domain): void
    {
        $row = collect($this->results)->firstWhere('domain', $domain);
        $this->selected = ($row && $row['available']) ? $domain : null;
        $this->dispatch('go-live-stage', stage: $this->selected ? 'chosen' : 'search', domain: $this->selected);
    }

    /** Continue from the sticky bar → the payment step. */
    public function continueToCheckout(DomainPurchase $domains): void
    {
        if ($this->selected) {
            $this->buy($this->selected, $domains);
        }
    }

    /** "More ideas" chips: simple variants of the searched label. */
    public function suggestions(): array
    {
        $label = DomainPurchase::label($this->query);
        if (strlen($label) < 2) {
            return [];
        }
        $type = strtolower((string) $this->site->getAttr('business_type', ''));
        // TODO: add a town-based suggestion once the site stores an address.

        return collect([
            str_replace('-', '', $label),
            explode('-', $label)[0],
            $type && ! str_contains($label, $type) ? $label.'-'.preg_replace('/[^a-z0-9]+/', '', $type) : $label.'uk',
        ])->unique()->reject(fn ($s) => $s === $label || strlen($s) < 3)->take(3)->values()->all();
    }

    public function searchFor(string $label, DomainPurchase $domains): void
    {
        $this->query = $label;
        $this->search($domains);
    }

    public function buy(string $domain, DomainPurchase $domains): void
    {
        abort_unless($this->site->allows(Auth::user(), 'publish.manage'), 403);
        $this->errorMessage = '';

        $row = collect($this->results)->firstWhere('domain', $domain);
        if (! $row || ! $row['available']) {
            $this->errorMessage = 'Search again and pick an available domain.';

            return;
        }
        $plan = $this->needsPlan() ? $this->plan : null;
        if ($this->needsPlan() && (! config("plans.tiers.{$plan}") || ! (config("plans.tiers.{$plan}.limits.custom_domain") ?? true) || (int) config("plans.tiers.{$plan}.price_cents") <= 0)) {
            $this->errorMessage = 'Pick a hosting plan.';

            return;
        }

        try {
            $this->openPayment($domains->preparePayment(Auth::user(), $this->site, $domain, $plan));
        } catch (ApiErrorException $e) {
            report($e);
            $this->errorMessage = 'Payments are unavailable right now — try again in a minute.';
        }
    }

    /** Show the payment step for a prepared order (or finish at once when no payment was needed). */
    private function openPayment(array $prepared): void
    {
        if ($prepared['done']) {
            $this->finish($prepared['order']);

            return;
        }
        $this->payOrderId = $prepared['order']->id;
        $this->clientSecret = $prepared['client_secret'];
        $this->payTotalCents = $prepared['total_cents'];
        $this->payError = '';
        $this->dispatch('go-live-stage', stage: 'pay', domain: $prepared['order']->domain);
    }

    /** Leave the payment step (the unpaid order is reused if they come back). */
    public function backToSearch(): void
    {
        $this->reset('payOrderId', 'clientSecret', 'payTotalCents', 'payError');
        $this->dispatch('go-live-stage', stage: $this->selected ? 'chosen' : 'search', domain: $this->selected);
    }

    /**
     * The Payment Element confirmed in the browser — check it with Stripe
     * before anything is registered.
     */
    public function paymentConfirmed(DomainPurchase $domains): void
    {
        abort_unless($this->site->allows(Auth::user(), 'publish.manage'), 403);
        $order = $this->payingOrder();
        if (! $order) {
            return;
        }
        $state = $domains->completePayment($order);
        match ($state) {
            'paid' => $this->finish($order->fresh()),
            'processing' => $this->payError = 'Your bank is still confirming the payment. We\'ll set up '.$order->domain.' as soon as it does — you can leave this page.',
            default => $this->payError = 'The payment didn\'t go through. Nothing was charged — check the details and try again.',
        };
    }

    private function payingOrder(): ?DomainOrder
    {
        return $this->payOrderId
            ? DomainOrder::where('site_id', $this->site->id)->where('user_id', Auth::id())->find($this->payOrderId)
            : null;
    }

    private function finish(DomainOrder $order): void
    {
        $failed = $order->status === 'failed';
        session()->flash('toast', $failed
            ? ['level' => 'error', 'title' => 'Payment received — registration needs a hand', 'message' => "We couldn't register {$order->domain} automatically. We're on it; contact support if it persists."]
            : ['level' => 'success', 'title' => $order->type === 'renew' ? 'Domain renewed' : 'Payment received',
                'message' => $order->type === 'renew' ? "{$order->domain} is renewed for another year." : "{$order->domain} is yours. Your site is going live on it now."]);
        $this->redirect(route('site.publish', $this->site->name));
    }

    /** The publishable key + return URL the Payment Element needs. */
    public function paymentConfig(): array
    {
        return [
            'key' => (string) config('services.stripe_platform.key'),
            'secret' => $this->clientSecret,
            'returnUrl' => route('site.domain.success', $this->site).'?order='.$this->payOrderId,
        ];
    }

    /** Renew a registered domain for another year (paid on the page, or instant without billing). */
    public function renew(string $orderId, DomainPurchase $domains): void
    {
        abort_unless($this->site->allows(Auth::user(), 'publish.manage'), 403);
        $order = DomainOrder::where('site_id', $this->site->id)->where('status', 'registered')->findOrFail($orderId);

        try {
            $this->openPayment($domains->prepareRenewalPayment(Auth::user(), $order));
        } catch (ApiErrorException $e) {
            report($e);
            $this->errorMessage = 'Payments are unavailable right now — try again in a minute.';
        }
    }

    public function render()
    {
        $sub = Auth::user()->currentSubscription();
        // Plans that can be bought with a domain: paid ones that allow an own domain.
        $tiers = PlanCatalog::publicTiers($sub->plan)->except('trial')
            ->filter(fn ($t) => ($t['price_cents'] ?? 0) > 0 && ($t['limits']['custom_domain'] ?? true));

        return view('livewire.domain-search', [
            'tiers' => $tiers->map(fn ($t, $k) => $t + ['key' => $k, 'price_cents' => $sub->priceFor($k)]),
            'orders' => DomainOrder::where('site_id', $this->site->id)->where('status', '!=', 'checkout')->latest()->limit(3)->get(),
        ]);
    }
}
