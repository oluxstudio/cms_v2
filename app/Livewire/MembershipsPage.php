<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Collection as CollectionModel;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Modules\Memberships\Member;
use App\Modules\Memberships\MembershipAccess;
use App\Modules\Memberships\MembershipService;
use App\Modules\Memberships\MembershipTier;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Memberships admin — members (grid/list, detail panel, CSV), tiers (CRUD +
 * order) and members-only content (posts / pages / collections per tier).
 */
class MembershipsPage extends Component
{
    use WithLayoutMode;

    public Site $site;

    #[Url(except: 'members')]
    public string $tab = 'members';

    // ── Members ──
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(except: 'recent')]
    public string $sort = 'recent';

    #[Url(as: 'member')]
    public ?string $selectedId = null;

    public string $noteDraft = '';

    public string $changeTierId = '';

    public bool $showAdd = false;

    public string $addName = '';

    public string $addEmail = '';

    public string $addTierId = '';

    // ── Tiers ──
    public ?string $tierId = null;      // editing (null + showTierForm = new)

    public bool $showTierForm = false;

    public string $tName = '';

    public string $tDescription = '';

    public string $tBenefits = '';      // one per line

    public string $tPrice = '0';

    public string $tInterval = 'month';

    public bool $tActive = true;

    // ── Members-only content ──
    public string $cType = 'post';

    public string $cId = '';

    public array $cTiers = [];

    public string $flash = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('memberships', 'grid');
        if (! in_array($this->tab, ['members', 'tiers', 'content'], true)) {
            $this->tab = 'members';
        }
        if ($this->selectedId && ! Member::where('site_id', $site->id)->whereKey($this->selectedId)->exists()) {
            $this->selectedId = null;
        }
        if ($this->selectedId) {
            $this->selectMember($this->selectedId);
        }
    }

    public function getCanManageProperty(): bool
    {
        return $this->site->allows(Auth::user(), 'memberships.manage');
    }

    private function guardManage(): void
    {
        abort_unless($this->canManage, 403);
    }

    private function service(): MembershipService
    {
        return app(MembershipService::class);
    }

    private function member(string $id): Member
    {
        return Member::where('site_id', $this->site->id)->findOrFail($id);
    }

    private function tier(string $id): MembershipTier
    {
        return MembershipTier::where('site_id', $this->site->id)->findOrFail($id);
    }

    // ── members ─────────────────────────────────────────────────────────

    public function setStatus(string $status): void
    {
        $this->statusFilter = in_array($status, ['all', ...Member::STATUSES], true) ? $status : 'all';
    }

    public function selectMember(string $id): void
    {
        $m = $this->member($id);
        $this->selectedId = $m->id;
        $this->noteDraft = (string) $m->notes;
        $this->changeTierId = (string) $m->tier_id;
    }

    public function closeMember(): void
    {
        $this->reset(['selectedId', 'noteDraft', 'changeTierId']);
    }

    public function saveNotes(): void
    {
        $this->guardManage();
        $this->validate(['noteDraft' => ['nullable', 'string', 'max:5000']]);
        $this->member($this->selectedId)->update(['notes' => trim($this->noteDraft) ?: null]);
        $this->flash = 'Notes saved.';
    }

    public function changeTier(): void
    {
        $this->guardManage();
        $m = $this->member($this->selectedId);
        $tier = $this->tier($this->changeTierId);
        if ($tier->id === $m->tier_id) {
            return;
        }
        $from = $m->tier?->name ?? '—';
        $updates = ['tier_id' => $tier->id];
        // Stripe-billed members keep their subscription price; others follow the tier.
        if (! $m->stripe_subscription_id) {
            $updates += ['interval' => $tier->interval, 'currency' => $tier->currency];
        }
        $m->update($updates);
        $m->log('tier_changed', $from.' → '.$tier->name.($m->stripe_subscription_id ? ' (billing unchanged)' : ''));
        $this->flash = 'Tier changed to '.$tier->name.'.';
    }

    public function cancelMember(string $id): void
    {
        $this->guardManage();
        try {
            $this->service()->cancel($this->member($id), 'team');
            $this->flash = 'Membership cancelled.';
        } catch (\Throwable $e) {
            report($e);
            $this->flash = 'Could not cancel on Stripe: '.$e->getMessage();
        }
    }

    public function resendLink(string $id): void
    {
        $this->guardManage();
        $m = $this->member($id);
        if ($m->status === 'cancelled' || $m->status === 'pending') {
            $this->flash = 'Only active members can sign in.';

            return;
        }
        $this->service()->sendMagicLink($m);
        $this->flash = 'Sign-in link sent to '.$m->email.'.';
    }

    public function addMember(): void
    {
        $this->guardManage();
        $data = $this->validate([
            'addName' => ['required', 'string', 'max:160'],
            'addEmail' => ['required', 'email', 'max:190'],
            'addTierId' => ['required', Rule::exists('membership_tiers', 'id')->where('site_id', $this->site->id)],
        ]);
        $email = mb_strtolower(trim($data['addEmail']));
        if (Member::where('site_id', $this->site->id)->where('email', $email)->exists()) {
            $this->addError('addEmail', 'That email is already on the member list.');

            return;
        }
        $tier = $this->tier($data['addTierId']);
        // Team-added members are complimentary — nobody is billed without checkout.
        $m = Member::create([
            'site_id' => $this->site->id, 'tier_id' => $tier->id, 'name' => trim($data['addName']), 'email' => $email,
            'status' => 'pending', 'price_cents' => 0, 'interval' => $tier->interval, 'currency' => $tier->currency,
        ]);
        $m->log('added', $tier->isFree() ? null : 'Complimentary — not billed');
        $this->service()->activate($m, null);
        $this->reset(['showAdd', 'addName', 'addEmail', 'addTierId']);
        $this->flash = $m->name.' added — a welcome email with a sign-in link is on its way.';
    }

    public function exportCsv()
    {
        $site = $this->site;

        return response()->streamDownload(function () use ($site) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Tier', 'Status', 'Price', 'Joined', 'Renews', 'Cancelled', 'Notes']);
            Member::with('tier')->where('site_id', $site->id)->orderBy('created_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $m) {
                    // Neutralise spreadsheet formulas in visitor-supplied text.
                    $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : (string) $v;
                    fputcsv($out, [
                        $safe($m->name), $safe($m->email), $safe($m->tier?->name), $m->status, $m->priceLabel(),
                        $m->joined_at?->toDateString(), $m->renews_at?->toDateString(), $m->cancelled_at?->toDateString(), $safe($m->notes),
                    ]);
                }
            });
            fclose($out);
        }, $site->name.'-members-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    // ── tiers ───────────────────────────────────────────────────────────

    public function newTier(): void
    {
        $this->guardManage();
        $this->reset(['tierId', 'tName', 'tDescription', 'tBenefits', 'tPrice', 'tInterval', 'tActive']);
        $this->showTierForm = true;
    }

    public function editTier(string $id): void
    {
        $this->guardManage();
        $t = $this->tier($id);
        $this->tierId = $t->id;
        $this->tName = $t->name;
        $this->tDescription = (string) $t->description;
        $this->tBenefits = implode("\n", (array) $t->benefits);
        $this->tPrice = number_format($t->price_cents / 100, 2, '.', '');
        $this->tInterval = $t->interval;
        $this->tActive = (bool) $t->active;
        $this->showTierForm = true;
    }

    public function cancelTierForm(): void
    {
        $this->reset(['tierId', 'showTierForm', 'tName', 'tDescription', 'tBenefits', 'tPrice', 'tInterval', 'tActive']);
    }

    public function saveTier(): void
    {
        $this->guardManage();
        $this->validate([
            'tName' => ['required', 'string', 'max:120'],
            'tDescription' => ['nullable', 'string', 'max:2000'],
            'tBenefits' => ['nullable', 'string', 'max:4000'],
            'tPrice' => ['required', 'numeric', 'min:0', 'max:100000'],
            'tInterval' => ['required', Rule::in(['month', 'year'])],
        ]);
        $cents = (int) round(((float) $this->tPrice) * 100);
        if ($cents > 0 && $cents < 50) {
            $this->addError('tPrice', 'Paid tiers must be at least 0.50.');

            return;
        }
        $fields = [
            'name' => trim($this->tName),
            'description' => trim($this->tDescription) ?: null,
            'benefits' => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $this->tBenefits)), fn ($l) => $l !== '')),
            'price_cents' => $cents,
            'interval' => $this->tInterval,
            'active' => $this->tActive,
        ];
        if ($this->tierId) {
            $t = $this->tier($this->tierId);
            $t->update($fields);
        } else {
            MembershipTier::create($fields + [
                'site_id' => $this->site->id,
                'slug' => MembershipTier::uniqueSlug($this->site->id, $fields['name']),
                'currency' => $this->service()->currency($this->site),
                'sort' => (int) MembershipTier::where('site_id', $this->site->id)->max('sort') + 1,
            ]);
        }
        $this->cancelTierForm();
        $this->flash = 'Tier saved.';
    }

    public function moveTier(string $id, int $dir): void
    {
        $this->guardManage();
        $tiers = MembershipTier::where('site_id', $this->site->id)->orderBy('sort')->orderBy('created_at')->get()->values();
        $i = $tiers->search(fn ($t) => $t->id === $id);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= $tiers->count()) {
            return;
        }
        $order = $tiers->pluck('id')->all();
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        foreach ($order as $pos => $tid) {
            MembershipTier::whereKey($tid)->update(['sort' => $pos]);
        }
    }

    public function toggleTier(string $id): void
    {
        $this->guardManage();
        $t = $this->tier($id);
        $t->update(['active' => ! $t->active]);
    }

    public function deleteTier(string $id): void
    {
        $this->guardManage();
        $t = $this->tier($id);
        if ($t->members()->exists()) {
            $t->update(['active' => false]);
            $this->flash = '“'.$t->name.'” has members, so it was hidden instead of deleted.';

            return;
        }
        $t->delete();
        $this->flash = 'Tier deleted.';
    }

    // ── members-only content ────────────────────────────────────────────

    public function updatedCType(): void
    {
        $this->cId = '';
    }

    public function gateContent(): void
    {
        $this->guardManage();
        $this->validate([
            'cType' => ['required', Rule::in(MembershipAccess::TYPES)],
            'cId' => ['required', 'string', 'max:40'],
            'cTiers' => ['array'],
            'cTiers.*' => [Rule::exists('membership_tiers', 'id')->where('site_id', $this->site->id)],
        ]);
        abort_unless($this->contentQuery($this->cType)->whereKey($this->cId)->exists(), 404);
        MembershipAccess::updateOrCreate(
            ['site_id' => $this->site->id, 'content_type' => $this->cType, 'content_id' => $this->cId],
            ['tier_ids' => array_values(array_unique($this->cTiers))],
        );
        $this->reset(['cId', 'cTiers']);
        $this->flash = 'Marked as members-only.';
    }

    public function toggleRuleTier(string $ruleId, string $tierId): void
    {
        $this->guardManage();
        $rule = MembershipAccess::where('site_id', $this->site->id)->findOrFail($ruleId);
        $this->tier($tierId);
        $ids = $rule->tierIds();
        $ids = in_array($tierId, $ids, true) ? array_values(array_diff($ids, [$tierId])) : [...$ids, $tierId];
        $rule->update(['tier_ids' => $ids]);
    }

    public function ungate(string $ruleId): void
    {
        $this->guardManage();
        MembershipAccess::where('site_id', $this->site->id)->whereKey($ruleId)->delete();
        $this->flash = 'Now public again.';
    }

    private function contentQuery(string $type)
    {
        return match ($type) {
            'post' => Post::where('site_id', $this->site->id),
            'page' => Page::where('site_id', $this->site->id),
            default => CollectionModel::where('site_id', $this->site->id),
        };
    }

    // ── render ──────────────────────────────────────────────────────────

    public function render()
    {
        $siteId = $this->site->id;
        $tiers = MembershipTier::where('site_id', $siteId)->orderBy('sort')->orderBy('created_at')->get();
        $stats = $this->stats($tiers);

        $needle = trim($this->search);
        $members = Member::with('tier')->where('site_id', $siteId)
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($needle !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$needle.'%')->orWhere('email', 'like', '%'.$needle.'%')))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'renewal', fn ($q) => $q->orderByRaw('renews_at IS NULL')->orderBy('renews_at'))
            ->when($this->sort === 'recent', fn ($q) => $q->orderByDesc('created_at'))
            ->limit(500)->get();

        $selected = $this->selectedId ? Member::with('tier')->where('site_id', $siteId)->find($this->selectedId) : null;

        // Members-only content: rules + the titles they point at (3 queries, no N+1).
        $rules = MembershipAccess::where('site_id', $siteId)->latest()->get();
        $titles = [
            'post' => Post::where('site_id', $siteId)->whereIn('id', $rules->where('content_type', 'post')->pluck('content_id'))->pluck('title', 'id'),
            'page' => Page::where('site_id', $siteId)->whereIn('id', $rules->where('content_type', 'page')->pluck('content_id'))->pluck('name', 'id'),
            'collection' => CollectionModel::where('site_id', $siteId)->whereIn('id', $rules->where('content_type', 'collection')->pluck('content_id'))->pluck('name', 'id'),
        ];
        $gated = $rules->pluck('content_id')->all();
        $options = $this->tab === 'content'
            ? $this->contentQuery($this->cType)->whereNotIn('id', $gated)
                ->orderBy($this->cType === 'post' ? 'title' : 'name')->limit(300)
                ->get(['id', $this->cType === 'post' ? 'title' : 'name'])
                ->mapWithKeys(fn ($r) => [$r->id => $r->title ?? $r->name])
            : collect();

        return view('livewire.memberships-page', [
            'tiers' => $tiers,
            'stats' => $stats,
            'members' => $members,
            'selected' => $selected,
            'history' => $selected ? $selected->events()->limit(20)->get() : collect(),
            'rules' => $rules,
            'titles' => $titles,
            'contentOptions' => $options,
            'paymentsReady' => $this->service()->canBillRecurring($this->site),
            'currency' => $this->service()->currency($this->site),
        ]);
    }

    /** All rail numbers from a handful of aggregate queries. */
    private function stats($tiers): array
    {
        $siteId = $this->site->id;
        $monthStart = now()->startOfMonth();

        $byStatus = Member::where('site_id', $siteId)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $agg = Member::where('site_id', $siteId)->selectRaw(
            'SUM(CASE WHEN joined_at >= ? THEN 1 ELSE 0 END) new_month,
             SUM(CASE WHEN cancelled_at >= ? THEN 1 ELSE 0 END) churn_month,
             SUM(CASE WHEN status = ? AND price_cents > 0 THEN 1 ELSE 0 END) paid_active,
             SUM(CASE WHEN status = ? AND price_cents = 0 THEN 1 ELSE 0 END) free_active',
            [$monthStart, $monthStart, 'active', 'active'],
        )->first();
        $mrrRows = Member::where('site_id', $siteId)->where('status', 'active')->where('price_cents', '>', 0)
            ->selectRaw("currency, SUM(CASE WHEN `interval` = 'year' THEN price_cents / 12 ELSE price_cents END) cents")
            ->groupBy('currency')->pluck('cents', 'currency');
        $currency = $this->service()->currency($this->site);
        $mrrMain = (int) round((float) ($mrrRows[$currency] ?? $mrrRows->sortDesc()->first() ?? 0));
        $mrrCurrency = isset($mrrRows[$currency]) || $mrrRows->isEmpty() ? $currency : $mrrRows->sortDesc()->keys()->first();

        $active = (int) ($byStatus['active'] ?? 0);
        $churn = (int) ($agg->churn_month ?? 0);
        $startBase = $active + $churn - (int) ($agg->new_month ?? 0);

        $byTier = Member::where('site_id', $siteId)->whereIn('status', ['active', 'past_due'])
            ->selectRaw('tier_id, COUNT(*) n')->groupBy('tier_id')->pluck('n', 'tier_id');

        return [
            'total' => (int) $byStatus->sum(),
            'byStatus' => $byStatus->all(),
            'active' => $active,
            'pastDue' => (int) ($byStatus['past_due'] ?? 0),
            'newMonth' => (int) ($agg->new_month ?? 0),
            'churnMonth' => $churn,
            'churnRate' => $startBase > 0 ? round($churn / $startBase * 100, 1) : 0,
            'paid' => (int) ($agg->paid_active ?? 0),
            'free' => (int) ($agg->free_active ?? 0),
            'mrr' => Money::format($mrrMain, $mrrCurrency),
            'mrrOther' => $mrrRows->except($mrrCurrency)->map(fn ($c, $code) => Money::format((int) round((float) $c), $code))->values()->all(),
            'byTier' => $tiers->map(fn ($t) => ['tier' => $t, 'n' => (int) ($byTier[$t->id] ?? 0)])->filter(fn ($r) => $r['n'] > 0)->values(),
            'paidTiers' => $tiers->where('active', true)->where('price_cents', '>', 0)->count(),
            'pastDueList' => Member::where('site_id', $siteId)->where('status', 'past_due')->orderBy('updated_at')->limit(5)->get(['id', 'name', 'email', 'renews_at']),
            'stalePending' => Member::where('site_id', $siteId)->where('status', 'pending')->where('created_at', '<', now()->subDay())->count(),
            'recentJoins' => Member::with('tier:id,name')->where('site_id', $siteId)->whereNotNull('joined_at')->orderByDesc('joined_at')->limit(5)->get(['id', 'name', 'tier_id', 'joined_at']),
        ];
    }
}
