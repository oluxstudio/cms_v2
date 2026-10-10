<?php

namespace App\Livewire;

use App\Models\Contact;
use App\Models\Site;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Models\NetworkProfile;
use App\Modules\Network\NetworkException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Refer to a partner" lightbox — reusable. Mount once per page:
 *   <livewire:refer-to-partner :site="$site" />
 * Open from anywhere (Alpine):
 *   $dispatch('open-refer-partner', { prefill: { name, email, phone, from_contact_id, to_site_id, form_tick } })
 * On success it dispatches `referral-sent` { reference }.
 */
class ReferToPartner extends Component
{
    public Site $site;

    public bool $open = false;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public ?string $fromContactId = null;

    public string $partnerSearch = '';

    /** The receiving site (partner). */
    public string $toSiteId = '';

    public string $note = '';

    /** email = send the customer a consent email (default) · form = they already ticked consent on our form. */
    public string $consentMethod = 'email';

    /** Reference of the referral just sent (shows the success state). */
    public string $sentReference = '';

    public function mount(Site $site, array $prefill = [], bool $open = false): void
    {
        $this->site = $site;
        if ($prefill || $open) {
            $this->openFor($prefill);
        }
    }

    #[On('open-refer-partner')]
    public function openFor(array $prefill = []): void
    {
        $this->resetErrorBag();
        $this->reset('name', 'email', 'phone', 'fromContactId', 'partnerSearch', 'toSiteId', 'note', 'consentMethod', 'sentReference');

        $contactId = $prefill['from_contact_id'] ?? null;
        if ($contactId && ($c = Contact::where('site_id', $this->site->id)->find($contactId))) {
            $this->fromContactId = $c->id;
            $this->name = (string) $c->name;
            $this->email = (string) $c->email;
            $this->phone = (string) $c->phone;
        }
        foreach (['name', 'email', 'phone'] as $k) {
            if (filled($prefill[$k] ?? null) && is_scalar($prefill[$k])) {
                $this->{$k} = mb_substr(trim((string) $prefill[$k]), 0, 190);
            }
        }
        if (filled($prefill['to_site_id'] ?? null) && is_string($prefill['to_site_id'])) {
            $this->toSiteId = $prefill['to_site_id'];
        }
        if (! empty($prefill['form_tick'])) {
            $this->consentMethod = 'form';
        }
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function choosePartner(string $siteId): void
    {
        $this->toSiteId = $siteId;
        $this->resetErrorBag('toSiteId');
    }

    public function clearPartner(): void
    {
        $this->toSiteId = '';
    }

    private function svc(): ReferralService
    {
        return app(ReferralService::class);
    }

    /** The chosen partner's profile — only accepting members other than this site. */
    private function partner(): ?NetworkProfile
    {
        if ($this->toSiteId === '' || $this->toSiteId === $this->site->id) {
            return null;
        }

        return NetworkProfile::with('site')->where('site_id', $this->toSiteId)->where('accepting', true)->first();
    }

    public function submit(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'network.manage'), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:1000'],
            'toSiteId' => ['required', 'string'],
            'consentMethod' => ['required', 'in:email,form'],
        ], ['toSiteId.required' => 'Choose a partner to refer this customer to.'], ['toSiteId' => 'partner']);

        $partner = $this->partner();
        if (! $partner || ! $partner->site) {
            $this->addError('toSiteId', 'That partner is not accepting referrals right now.');

            return;
        }

        try {
            $referral = $this->svc()->refer($this->site, $partner->site, array_filter([
                'name' => trim($this->name),
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone) ?: null,
                'note' => trim($this->note) ?: null,
                'from_contact_id' => $this->fromContactId,
            ], fn ($v) => $v !== null), Auth::user(), $this->consentMethod === 'form');
        } catch (NetworkException $e) {
            $this->addError('refer', $e->getMessage());

            return;
        }

        $this->sentReference = (string) $referral->reference;
        $this->dispatch('referral-sent', reference: $this->sentReference);
    }

    public function render()
    {
        $svc = $this->svc();
        $partners = collect();
        $profile = null;
        $planAllows = false;
        if ($this->open) {
            $planAllows = $svc->planAllows($this->site);
            $profile = $svc->profileFor($this->site);
            if ($this->toSiteId === '') {
                $q = trim($this->partnerSearch);
                $partners = $svc->findPartners($this->site, $q !== '' ? ['q' => $q] : [])
                    ->filter(fn ($p) => $p instanceof NetworkProfile)->take(8)->values();
            }
        }

        return view('livewire.refer-to-partner', [
            'partner' => $this->open ? $this->partner() : null,
            'partners' => $partners,
            'planAllows' => $planAllows,
            'isMember' => (bool) $profile?->isMember(),
            'canManage' => $this->site->allows(Auth::user(), 'network.manage'),
            'cutPct' => (float) config('network.olux_cut_pct'),
        ]);
    }
}
