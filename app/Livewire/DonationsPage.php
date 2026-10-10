<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Node;
use App\Models\Site;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Donations — the house 3-rail page: giving stats (left), the donations as
 * grid cards with search / status pills / sort + a Settings tab (centre),
 * monthly trend, needs-attention, top donors and related links (right).
 */
class DonationsPage extends Component
{
    use WithLayoutMode;

    public const FILTERS = ['all', 'paid', 'pending'];

    public const SORTS = ['newest', 'oldest', 'largest'];

    public Site $site;

    /** Centre tab: donations | settings */
    #[Url(except: 'donations')]
    public string $tab = 'donations';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $filter = 'all';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    // ── Settings form (the donations feature config, same schema as the Marketplace) ──
    public string $sCurrency = 'usd';

    public string $sAmounts = '';

    public string $sHeadline = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('donations', 'grid');
        $this->fillSettings();
    }

    private function fillSettings(): void
    {
        $cfg = $this->site->feature('donations');
        $this->sCurrency = (string) ($cfg['currency'] ?? 'usd');
        $this->sAmounts = (string) ($cfg['suggested_amounts'] ?? '');
        $this->sHeadline = (string) ($cfg['headline'] ?? '');
    }

    public function getCanManageProperty(): bool
    {
        return $this->site->canManageTeam(Auth::user());
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['donations', 'settings'], true) ? $tab : 'donations';
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    /** Every donation, newest first — ONE query; stats and the list derive from it. */
    public function getDonationsProperty(): Collection
    {
        return $this->site->donations()->latest()->get();
    }

    public function getStatsProperty(): array
    {
        $currency = $this->site->currency ?? 'gbp';
        $all = $this->donations;
        $paid = $all->where('status', 'paid');
        $raised = (int) $paid->sum('amount_cents');
        $count = $paid->count();
        $monthStart = now()->startOfMonth();
        $paidAt = fn ($d) => $d->paid_at ?? $d->created_at;

        $thisMonth = $paid->filter(fn ($d) => $paidAt($d)->gte($monthStart));
        $lastMonth = $paid->filter(fn ($d) => $paidAt($d)->gte($monthStart->copy()->subMonth()) && $paidAt($d)->lt($monthStart));

        // Donors: by email, falling back to name; anonymous gifts don't count as a donor.
        $donorKey = fn ($d) => mb_strtolower(trim((string) ($d->donor_email ?: $d->donor_name)));
        $byDonor = $paid->filter(fn ($d) => $donorKey($d) !== '')->groupBy($donorKey);

        $largest = $paid->sortByDesc('amount_cents')->first();

        // Last six months, oldest → newest.
        $trend = collect(range(5, 0))->map(function ($ago) use ($paid, $paidAt) {
            $start = now()->startOfMonth()->subMonths($ago);
            $end = $start->copy()->endOfMonth();

            return [
                'label' => $start->format('M'),
                'cents' => (int) $paid->filter(fn ($d) => $paidAt($d)->between($start, $end))->sum('amount_cents'),
            ];
        })->all();

        $topDonors = $byDonor->map(fn ($g) => [
            'name' => $g->first()->donor_name ?: $g->first()->donor_email,
            'email' => $g->first()->donor_email,
            'cents' => (int) $g->sum('amount_cents'),
            'gifts' => $g->count(),
        ])->sortByDesc('cents')->take(5)->values()->all();

        return [
            'currency' => $currency,
            'raisedCents' => $raised,
            'raisedMajor' => Money::format($raised, $currency),
            'count' => $count,
            'avg' => Money::format($count > 0 ? (int) round($raised / $count) : 0, $currency),
            'monthCents' => (int) $thisMonth->sum('amount_cents'),
            'monthCount' => $thisMonth->count(),
            'lastMonthCents' => (int) $lastMonth->sum('amount_cents'),
            'donors' => $byDonor->count(),
            'repeatDonors' => $byDonor->filter(fn ($g) => $g->count() > 1)->count(),
            'largest' => $largest,
            'pending' => $all->where('status', 'pending')->count(),
            'counts' => [
                'all' => $all->count(),
                'paid' => $count,
                'pending' => $all->where('status', 'pending')->count(),
            ],
            'trend' => $trend,
            'topDonors' => $topDonors,
        ];
    }

    /** The filtered + sorted cards shown in the centre. */
    public function getVisibleProperty(): Collection
    {
        $needle = mb_strtolower(trim($this->search));

        return $this->donations
            ->when($needle !== '', fn ($c) => $c->filter(fn ($d) => str_contains(
                mb_strtolower($d->donor_name.' '.$d->donor_email.' '.$d->message), $needle)))
            ->when($this->filter !== 'all', fn ($c) => $c->where('status', $this->filter))
            ->sortBy(fn ($d) => match ($this->sort) {
                'oldest' => $d->created_at->getTimestamp(),
                'largest' => -$d->amount_cents,
                default => -$d->created_at->getTimestamp(),
            })
            ->values();
    }

    /** Whether the site links to its donate page anywhere (a page or a block value). */
    public function getDonateLinkedProperty(): bool
    {
        if ($this->site->pages()->where('url', 'like', '%donat%')->exists()) {
            return true;
        }

        return Node::where('value', 'like', '%/donate%')
            ->whereHas('component', fn ($q) => $q->where('site_id', $this->site->id))
            ->exists();
    }

    /** Save the donations settings — same shape the Marketplace writes. */
    public function saveSettings(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate([
            'sCurrency' => ['required', 'string', 'max:3'],
            'sAmounts' => ['nullable', 'string', 'max:200'],
            'sHeadline' => ['nullable', 'string', 'max:160'],
        ]);

        $amounts = collect(explode(',', $this->sAmounts))
            ->map(fn ($a) => trim($a))->filter(fn ($a) => is_numeric($a) && (float) $a > 0)
            ->map(fn ($a) => rtrim(rtrim(number_format((float) $a, 2, '.', ''), '0'), '.'))
            ->values();
        if ($amounts->isEmpty()) {
            $this->addError('sAmounts', 'Add at least one amount, e.g. 5, 10, 25.');

            return;
        }

        $schema = FeatureRegistry::get('donations')['settings'] ?? [];
        $config = array_merge($this->site->feature('donations'), [
            'currency' => strtolower($this->sCurrency),
            'suggested_amounts' => $amounts->implode(', '),
            'headline' => trim($this->sHeadline) ?: ($schema['headline']['default'] ?? ''),
        ]);
        $this->site->saveFeatureConfig('donations', $config);
        $this->fillSettings();
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Donation settings updated — the donate page uses them now.');
    }

    /** Delete a donation record (e.g. clearing test donations) —
     *  confirmation happens in the shared modal (data-confirm). */
    public function deleteDonation(string $id): void
    {
        $this->site->donations()->whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.donations-page');
    }
}
