<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Site;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Models\NetworkProfile;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralPayout;
use App\Modules\Network\NetworkException;
use App\Modules\Network\ReferralManager;
use App\Services\Blueprints\BlueprintRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Referral Network admin — /{siteID}/network.
 * Tabs: Profile (join / edit membership), Find partners, Sent, Received.
 * Viewing needs network.view (route); every action needs network.manage and a
 * paid plan (trial accounts browse partners read-only).
 */
class NetworkPage extends Component
{
    use WithLayoutMode;

    public const TABS = ['profile', 'partners', 'sent', 'received'];

    /** Receiver-visible statuses — the customer's details only reach the partner once they consent. */
    public const RECEIVED_VISIBLE = ['shared', 'accepted', 'declined', 'converted', 'disputed', 'void', 'billed', 'collected', 'expired'];

    /** Statuses that count as a converted lead. */
    public const WON = ['converted', 'disputed', 'billed', 'collected'];

    public Site $site;

    #[Url(except: 'profile')]
    public string $tab = 'profile';

    // ── Profile form ──
    public string $businessType = '';

    public array $services = [];

    public string $serviceInput = '';

    public string $area = '';

    public string $postcode = '';

    public int $radiusKm = 25;

    public string $pitch = '';

    /** Fee per converted lead, in pounds (as typed). */
    public string $feePounds = '15.00';

    public bool $accepting = true;

    public bool $termsAgreed = false;

    // ── Find partners ──
    public string $q = '';

    public string $filterType = '';

    public string $filterPostcode = '';

    public string $filterRadius = '';

    // ── Decline / dispute reason box ──
    public ?string $reasonFor = null;

    /** decline | dispute */
    public string $reasonKind = '';

    public string $reason = '';

    public string $notice = '';

    public string $error = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('network', 'grid');
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'profile';
        }
        $this->fillProfile();
    }

    private function svc(): ReferralService
    {
        return app(ReferralService::class);
    }

    private function fillProfile(): void
    {
        $p = $this->svc()->profileFor($this->site);
        if (! $p) {
            return;
        }
        $this->businessType = (string) $p->business_type;
        $this->services = array_values((array) $p->services);
        $this->area = (string) $p->area;
        $this->postcode = (string) $p->postcode;
        $this->radiusKm = (int) ($p->radius_km ?: 25);
        $this->pitch = (string) $p->pitch;
        $this->feePounds = number_format(((int) $p->fee_cents) / 100, 2, '.', '');
        $this->accepting = (bool) $p->accepting;
    }

    public function canManage(): bool
    {
        return $this->site->allows(Auth::user(), 'network.manage');
    }

    public function planAllows(): bool
    {
        return $this->svc()->planAllows($this->site);
    }

    private function guard(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function sent(string $id): Referral
    {
        return Referral::where('from_site_id', $this->site->id)->findOrFail($id);
    }

    private function received(string $id): Referral
    {
        return Referral::where('to_site_id', $this->site->id)->whereIn('status', self::RECEIVED_VISIBLE)->findOrFail($id);
    }

    /** Run a service call, turning NetworkException into a friendly message. */
    private function attempt(callable $fn, string $success): bool
    {
        $this->error = '';
        try {
            $fn();
            $this->notice = $success;

            return true;
        } catch (NetworkException $e) {
            $this->notice = '';
            $this->error = $e->getMessage();

            return false;
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'profile';
    }

    #[On('referral-sent')]
    public function referralSent(string $reference = ''): void
    {
        $this->notice = 'Referral '.$reference.' sent.';
        $this->tab = 'sent';
    }

    // ── Profile ────────────────────────────────────────────────────

    public function addService(): void
    {
        foreach (preg_split('/\s*,\s*/', trim($this->serviceInput)) as $s) {
            $s = mb_substr(trim(strip_tags($s)), 0, 60);
            if ($s !== '' && count($this->services) < 20 && ! in_array(mb_strtolower($s), array_map('mb_strtolower', $this->services), true)) {
                $this->services[] = $s;
            }
        }
        $this->serviceInput = '';
    }

    public function removeService(int $i): void
    {
        unset($this->services[$i]);
        $this->services = array_values($this->services);
    }

    private function feeCents(): int
    {
        return (int) round(((float) str_replace([',', '£', ' '], '', $this->feePounds)) * 100);
    }

    private function validatedProfile(): array
    {
        if (trim($this->serviceInput) !== '') {
            $this->addService();
        }
        $min = (int) config('network.fee_min_cents');
        $max = (int) config('network.fee_max_cents');
        $this->validate([
            'businessType' => ['required', 'string', 'max:60'],
            'services' => ['array', 'max:20'],
            'services.*' => ['string', 'max:60'],
            'area' => ['required', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'radiusKm' => ['required', 'integer', 'between:1,500'],
            'pitch' => ['nullable', 'string', 'max:500'],
            'feePounds' => ['required', 'numeric', 'min:'.($min / 100), 'max:'.($max / 100)],
            'accepting' => ['boolean'],
        ], [], ['businessType' => 'business type', 'radiusKm' => 'radius', 'feePounds' => 'fee per converted lead']);

        return [
            'business_type' => trim($this->businessType),
            'services' => array_values($this->services),
            'area' => trim($this->area),
            'postcode' => strtoupper(trim($this->postcode)) ?: null,
            'radius_km' => $this->radiusKm,
            'pitch' => trim($this->pitch) ?: null,
            'fee_cents' => $this->feeCents(),
            'accepting' => $this->accepting,
        ];
    }

    public function join(): void
    {
        $this->guard();
        $this->validate(['termsAgreed' => ['accepted']], ['termsAgreed.accepted' => 'Please agree to the network terms to join.']);
        $data = $this->validatedProfile();
        $this->attempt(function () use ($data) {
            $this->svc()->saveProfile($this->site, $data);
            $this->svc()->join($this->site, Auth::user());
        }, 'Welcome to the Olux referral network.');
        $this->termsAgreed = false;
    }

    public function saveProfile(): void
    {
        $this->guard();
        $data = $this->validatedProfile();
        $this->attempt(fn () => $this->svc()->saveProfile($this->site, $data), 'Network profile saved.');
    }

    public function leave(): void
    {
        $this->guard();
        if ($this->attempt(fn () => $this->svc()->leave($this->site, Auth::user()), 'You have stopped receiving referrals. Open referrals will run their course.')) {
            $this->accepting = false;
        }
    }

    // ── Sent ───────────────────────────────────────────────────────

    public function cancel(string $id): void
    {
        $this->guard();
        $r = $this->sent($id);
        $this->attempt(fn () => $this->svc()->cancel($r, Auth::user()), 'Referral '.$r->reference.' cancelled.');
    }

    // ── Received ───────────────────────────────────────────────────

    public function accept(string $id): void
    {
        $this->guard();
        $r = $this->received($id);
        $this->attempt(fn () => $this->svc()->accept($r, Auth::user()), 'Referral '.$r->reference.' accepted.');
    }

    public function markWon(string $id): void
    {
        $this->guard();
        $r = $this->received($id);
        $this->attempt(fn () => $this->svc()->markConverted($r, 'manual', Auth::user()), 'Referral '.$r->reference.' marked as won.');
    }

    public function startReason(string $id, string $kind): void
    {
        $this->guard();
        $this->received($id);
        $this->reasonFor = $id;
        $this->reasonKind = $kind === 'dispute' ? 'dispute' : 'decline';
        $this->reason = '';
        $this->resetErrorBag('reason');
    }

    public function cancelReason(): void
    {
        $this->reasonFor = null;
        $this->reasonKind = '';
        $this->reason = '';
    }

    public function submitReason(): void
    {
        $this->guard();
        if (! $this->reasonFor) {
            return;
        }
        $r = $this->received($this->reasonFor);
        if ($this->reasonKind === 'dispute') {
            $this->validate(['reason' => ['required', 'string', 'min:10', 'max:500']], [], ['reason' => 'reason']);
            $ok = $this->attempt(fn () => $this->svc()->dispute($r, Auth::user(), trim($this->reason)), 'Dispute raised on '.$r->reference.'. Olux will review it.');
        } else {
            $this->validate(['reason' => ['nullable', 'string', 'max:300']], [], ['reason' => 'reason']);
            $ok = $this->attempt(fn () => $this->svc()->decline($r, Auth::user(), trim($this->reason) ?: null), 'Referral '.$r->reference.' declined.');
        }
        if ($ok) {
            $this->cancelReason();
        }
    }

    // ── Data ───────────────────────────────────────────────────────

    public static function disputeDeadline(Referral $r): ?Carbon
    {
        return $r->converted_at?->copy()->addDays((int) config('network.dispute_days', 7));
    }

    public static function canDispute(Referral $r): bool
    {
        $d = self::disputeDeadline($r);

        return $r->status === 'converted' && $d && now()->lte($d);
    }

    private function stats(): array
    {
        $sent = Referral::where('from_site_id', $this->site->id)->selectRaw('COUNT(*) AS n, SUM(CASE WHEN status IN (?,?,?,?) THEN 1 ELSE 0 END) AS won',
            self::WON)->first();
        $received = Referral::where('to_site_id', $this->site->id)->whereIn('status', self::RECEIVED_VISIBLE)
            ->selectRaw("COUNT(*) AS n, SUM(CASE WHEN status = 'shared' THEN 1 ELSE 0 END) AS waiting")->first();
        $pay = ReferralPayout::where('site_id', $this->site->id)->selectRaw("
            SUM(CASE WHEN status IN ('transferred','credited') THEN net_cents ELSE 0 END) AS paid,
            SUM(CASE WHEN status IN ('pending','ready','failed') THEN net_cents ELSE 0 END) AS owed
        ")->first();

        $n = (int) ($sent->n ?? 0);
        $won = (int) ($sent->won ?? 0);

        return [
            'sent' => $n, 'won' => $won, 'rate' => $n ? (int) round($won / $n * 100) : 0,
            'received' => (int) ($received->n ?? 0), 'waiting' => (int) ($received->waiting ?? 0),
            'earned' => (int) ($pay->paid ?? 0), 'owed' => (int) ($pay->owed ?? 0),
        ];
    }

    public function render()
    {
        $svc = $this->svc();
        $profile = $svc->profileFor($this->site);
        $planAllows = $svc->planAllows($this->site);
        $isMember = (bool) $profile?->isMember();

        $partners = collect();
        if ($this->tab === 'partners') {
            $filters = array_filter([
                'q' => trim($this->q), 'business_type' => trim($this->filterType),
                'postcode' => trim($this->filterPostcode), 'radius_km' => $this->filterRadius !== '' ? (int) $this->filterRadius : null,
            ], fn ($v) => $v !== '' && $v !== null);
            // findPartners() returns NetworkProfile rows (accepting members other than this site).
            $partners = $svc->findPartners($this->site, $filters)->filter(fn ($p) => $p instanceof NetworkProfile)->values();
            (new Collection($partners->all()))->loadMissing('site');
        }

        $sent = $this->tab === 'sent'
            ? Referral::where('from_site_id', $this->site->id)->with(['toSite', 'events'])->latest()->limit(100)->get()
            : collect();
        $received = $this->tab === 'received'
            ? Referral::where('to_site_id', $this->site->id)->whereIn('status', self::RECEIVED_VISIBLE)->with(['fromSite', 'events'])->latest()->limit(100)->get()
            : collect();

        return view('livewire.network-page', [
            'profile' => $profile,
            'isMember' => $isMember,
            'planAllows' => $planAllows,
            'canManage' => $this->canManage(),
            'canAct' => $this->canManage() && $planAllows,
            'stats' => $this->stats(),
            'partners' => $partners,
            'sentList' => $sent,
            'receivedList' => $received,
            'businessTypes' => BlueprintRegistry::types(),
            'cutPct' => (float) config('network.olux_cut_pct'),
            'feeCents' => max(0, $this->feeCents()),
        ]);
    }

    /** Display name for a partner site. */
    public static function siteLabel(?Site $site): string
    {
        return ReferralManager::businessName($site);
    }
}
