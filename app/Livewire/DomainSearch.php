<?php

namespace App\Livewire;

use App\Models\DomainOrder;
use App\Models\Site;
use App\Services\Domains\DomainPurchase;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

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

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'publish.manage'), 403);
        $this->query = $site->getAttr('business_name') ?: '';
        $this->plan = $this->needsPlan() ? 'starter' : '';
    }

    /** Still on the free trial → the checkout also picks a hosting plan. */
    public function needsPlan(): bool
    {
        $sub = Auth::user()->currentSubscription();

        return $sub->plan === 'trial' || $sub->status !== 'active';
    }

    public function search(DomainPurchase $domains): void
    {
        $this->errorMessage = '';
        $this->results = [];
        if (strlen(DomainPurchase::label($this->query)) < 2) {
            $this->errorMessage = 'Type at least two characters.';

            return;
        }
        try {
            $this->results = $domains->search($this->query);
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Domain lookup is unavailable right now — try again in a minute.';
        }
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
        if ($this->needsPlan() && ! config("plans.tiers.{$plan}")) {
            $this->errorMessage = 'Pick a hosting plan.';

            return;
        }

        $url = $domains->start(Auth::user(), $this->site, $domain, $plan, route('site.publish', $this->site->id));
        $this->redirect($url);
    }

    public function render()
    {
        $tiers = collect(config('plans.tiers'))->except('trial')->sortBy('order');
        $sub = Auth::user()->currentSubscription();

        return view('livewire.domain-search', [
            'tiers' => $tiers->map(fn ($t, $k) => $t + ['key' => $k, 'price_cents' => $sub->priceFor($k)]),
            'orders' => DomainOrder::where('site_id', $this->site->id)->latest()->limit(3)->get(),
        ]);
    }
}
