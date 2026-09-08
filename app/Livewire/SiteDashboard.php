<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Estimate;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Site;
use App\Models\SiteActivityLog;
use App\Models\Todo;
use App\Services\OpsAlerts;
use App\Services\VerticalStats;
use App\Support\SiteChecklist;
use Livewire\Component;

class SiteDashboard extends Component
{
    public Site $site;

    public int $pagesCount = 0;

    public int $formsCount = 0;

    public int $mediaCount = 0;

    public int $responsesCount = 0;

    public int $publishedCount = 0;

    public int $contactsCount = 0;

    // ── Ops hub ──────────────────────────────────────────────────────

    /** Today's + tomorrow's bookings: time, customer, service, balance owed. */
    public array $agenda = [];

    /** Unread generated alerts (overdue invoice, stale quote, pending booking). */
    public array $actionItems = [];

    /** Collectible invoices, soonest due first. */
    public array $moneyOwed = [];

    public int $outstandingCents = 0;

    /** New (uncontacted) leads. */
    public array $newContacts = [];

    public array $recentEstimates = [];

    /** This-week ops metrics: bookings, revenue collected, new leads. */
    public array $weekStats = ['bookings' => 0, 'revenue_cents' => 0, 'leads' => 0];

    /** Vertical widget pack (salon pulse / jobs & quotes) — [] when no business_type. */
    public array $verticalStats = [];

    /** Go-live checklist (auto-detected) + its progress. */
    public array $checklist = [];

    public array $checklistProgress = ['done' => 0, 'total' => 0, 'complete' => true, 'pct' => 100];

    /**
     * Commerce tiles for the left rail — bookings, orders, stock, invoices.
     * Only tiles with something to show are included (see commerceTiles()).
     *
     * @var list<array{label:string,value:int,seg:string,bg:string,fg:string,icon:string,hint:string}>
     */
    public array $commerceTiles = [];

    public array $recentResponses = [];

    public array $recentContacts = [];

    public array $team = [];

    /** Activity feed — from site_activity_logs */
    public array $recentActivities = [];

    /** Pending tasks summary for the right rail */
    public array $pendingTasks = [];

    /** User-added quick links for the right rail: [['label','url'], …] */
    public array $quickLinks = [];

    public bool $addingLink = false;

    public string $linkLabel = '';

    public string $linkUrl = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->quickLinks = json_decode((string) $site->getAttr($this->quickLinksKey(), '[]'), true) ?: [];

        $this->pagesCount = Page::where('site_id', $site->id)->count();
        $this->publishedCount = Page::where('site_id', $site->id)->where('is_published', true)->count();
        $this->formsCount = Form::where('site_id', $site->id)->count();
        $this->mediaCount = Media::where('site_id', $site->id)->count();
        $this->contactsCount = $site->contacts()->count();

        $this->commerceTiles = $this->commerceTiles($site);
        $this->loadOps($site);

        $this->team = $site->members()
            ->get(['users.id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'initials' => $u->initials()])
            ->toArray();

        $this->recentContacts = $site->contacts()
            ->latest()
            ->limit(3)
            ->get(['id', 'name', 'email', 'status', 'created_at'])
            ->toArray();

        $formIds = Form::where('site_id', $site->id)->pluck('id');
        $this->responsesCount = FormResponse::whereIn('form_id', $formIds)->count();

        $this->recentResponses = FormResponse::whereIn('form_id', $formIds)
            ->with('form:id,name')
            ->latest()
            ->limit(4)
            ->get()
            ->toArray();

        // ── Activity feed (site_activity_logs) ───────────────────────────
        $this->recentActivities = SiteActivityLog::where('site_id', $site->id)
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($log) {
                [$badgeLabel, $badgeBg, $badgeFg] = $log->actionBadge();
                [$iconBg, $iconFg] = $log->iconColors();

                return [
                    'id' => $log->id,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'action' => $log->action,
                    'title' => $log->title,
                    'description' => $log->description,
                    'url' => $log->url,         // relative path, e.g. /pages
                    'icon_path' => $log->iconPath(),
                    'icon_bg' => $iconBg,
                    'icon_fg' => $iconFg,
                    'badge_label' => $badgeLabel,
                    'badge_bg' => $badgeBg,
                    'badge_fg' => $badgeFg,
                    'user_name' => $log->user?->name ?? 'System',
                    'user_init' => $log->user ? strtoupper(substr($log->user->name, 0, 1)) : 'S',
                    'user_color' => $log->user ? null : '#6b7280',
                    'created_at' => $log->created_at->diffForHumans(),
                    'meta' => $log->meta ?? [],
                ];
            })
            ->toArray();

        // ── Pending tasks (right rail quick view) ───────────────────────
        $this->pendingTasks = Todo::where('site_id', $site->id)
            ->whereIn('status', ['todo', 'pending', 'in_progress', 'backlog'])
            ->with(['assignee:id,name', 'items'])
            ->orderByRaw("FIELD(priority, 'high', 'normal', 'low') ASC")
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'due_at' => $t->due_at?->format('M j') ?? null,
                'due_passed' => $t->due_at?->isPast() ?? false,
                'assigned_to' => $t->assignee?->name ?? null,
                'assigned_init' => $t->assignee ? strtoupper(substr($t->assignee->name, 0, 1)) : null,
                'progress' => $t->progress(),
                'items_total' => $t->items->count(),
                'items_done' => $t->items->where('done', true)->count(),
            ])
            ->toArray();
    }

    /**
     * The operational data: agenda, action items, money owed, new enquiries,
     * week stats and the go-live checklist. Every block no-ops when its
     * feature is off, so a content-only site stays clean.
     */
    private function loadOps(Site $site): void
    {
        $weekAgo = now()->subDays(7);

        if ($site->hasFeature('bookings')) {
            $this->agenda = Booking::where('site_id', $site->id)->active()
                ->whereBetween('starts_at', [today()->startOfDay(), today()->addDay()->endOfDay()])
                ->with('service:id,name')->orderBy('starts_at')->limit(8)->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'time' => $b->starts_at->format('H:i'),
                    'day' => $b->starts_at->isToday() ? 'Today' : 'Tomorrow',
                    'customer' => $b->customer_name ?: $b->customer_email,
                    'service' => $b->service?->name,
                    'status' => $b->status,
                    'balance_cents' => $b->balanceCents(),
                ])->toArray();
            $this->weekStats['bookings'] = Booking::where('site_id', $site->id)->active()
                ->whereBetween('starts_at', [$weekAgo, now()])->count();
        }

        if ($site->hasFeature('invoices')) {
            $collectible = Invoice::where('site_id', $site->id)->collectible()->orderBy('due_date');
            $this->outstandingCents = (int) $collectible->clone()->sum('total_cents');
            $this->moneyOwed = $collectible->limit(5)->get()
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'number' => $i->number,
                    'customer' => $i->customer_name ?: $i->customer_email,
                    'total_cents' => $i->total_cents,
                    'due' => $i->due_date?->format('j M'),
                    'overdue' => $i->status === 'overdue' || ($i->due_date && $i->due_date->isPast()),
                ])->toArray();
            $this->weekStats['revenue_cents'] += (int) Invoice::where('site_id', $site->id)
                ->where('status', 'paid')->where('paid_at', '>=', $weekAgo)->sum('total_cents');
        }
        if ($site->hasFeature('store')) {
            $this->weekStats['revenue_cents'] += (int) Order::where('site_id', $site->id)
                ->whereIn('status', ['paid', 'fulfilled'])->where('created_at', '>=', $weekAgo)->sum('total_cents');
        }

        $this->newContacts = Contact::where('site_id', $site->id)->where('status', 'new')
            ->latest()->limit(5)->get(['id', 'name', 'email', 'created_at'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name ?: $c->email, 'ago' => $c->created_at->diffForHumans(null, true)])
            ->toArray();
        $this->weekStats['leads'] = Contact::where('site_id', $site->id)->where('created_at', '>=', $weekAgo)->count();

        if ($site->hasFeature('estimator')) {
            $this->recentEstimates = Estimate::where('site_id', $site->id)->latest()->limit(3)
                ->get(['id', 'reference', 'customer_name', 'status', 'created_at'])
                ->map(fn ($e) => ['id' => $e->id, 'reference' => $e->reference, 'name' => $e->customer_name, 'status' => $e->status, 'ago' => $e->created_at->diffForHumans(null, true)])
                ->toArray();
        }

        $this->actionItems = Alert::visibleTo($site, auth()->user())
            ->whereNull('read_at')->whereIn('type', OpsAlerts::TYPES)
            ->latest()->limit(6)->get(['id', 'level', 'type', 'title', 'body', 'link'])
            ->toArray();

        $this->verticalStats = VerticalStats::for($site) ?? [];
        $this->checklist = SiteChecklist::steps($site);
        $this->checklistProgress = SiteChecklist::progress($site);
    }

    /** Quick links are personal — keyed per user on the site's attributes. */
    private function quickLinksKey(): string
    {
        return 'quicklinks:'.auth()->id();
    }

    public function addQuickLink(): void
    {
        $this->validate([
            'linkLabel' => ['required', 'string', 'max:40'],
            'linkUrl' => ['required', 'string', 'max:500'],
        ]);

        $url = trim($this->linkUrl);
        // Accept in-app paths (/tekstack/pages) and full URLs; default bare
        // hosts (docs.example.com) to https.
        if (! preg_match('#^(https?://|/)#i', $url)) {
            $url = 'https://'.$url;
        }

        $this->quickLinks[] = ['label' => trim($this->linkLabel), 'url' => $url];
        $this->site->setAttr($this->quickLinksKey(), json_encode($this->quickLinks));
        $this->reset(['linkLabel', 'linkUrl', 'addingLink']);
    }

    public function removeQuickLink(int $index): void
    {
        unset($this->quickLinks[$index]);
        $this->quickLinks = array_values($this->quickLinks);
        $this->site->setAttr($this->quickLinksKey(), json_encode($this->quickLinks));
    }

    /**
     * Bookings / orders / stock / invoices for the rail. A tile appears only
     * when its feature is on AND it has at least one thing to report — a fresh
     * site shows none of them.
     */
    private function commerceTiles(Site $site): array
    {
        $tiles = [];
        $add = function (string $label, int $value, string $seg, string $bg, string $fg, string $icon, string $hint = '') use (&$tiles) {
            if ($value > 0) {
                $tiles[] = compact('label', 'value', 'seg', 'bg', 'fg', 'icon', 'hint');
            }
        };

        if ($site->hasFeature('bookings')) {
            $add('Upcoming bookings',
                Booking::where('site_id', $site->id)->where('status', '!=', 'cancelled')->where('starts_at', '>=', now())->count(),
                'bookings', '#d9f068', '#2b3110',
                'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z');
        }
        if ($site->hasFeature('store')) {
            $add('Orders',
                Order::where('site_id', $site->id)->whereIn('status', ['paid', 'fulfilled'])->count(),
                'orders', '#d7c3f5', '#33245c',
                'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z');
            $add('Products in stock',
                Product::where('site_id', $site->id)->where('is_active', true)->where('inventory', '>', 0)->count(),
                'store', '#e6d6c6', '#4a3628',
                'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4');
        }
        if ($site->hasFeature('invoices')) {
            $add('Invoices sent',
                Invoice::where('site_id', $site->id)->where('status', 'sent')->count(),
                'invoices', '#cfe8ff', '#123a5c',
                'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z');
            $add('Invoices overdue',
                Invoice::where('site_id', $site->id)
                    ->where(fn ($q) => $q->where('status', 'overdue')
                        ->orWhere(fn ($q) => $q->where('status', 'sent')->whereDate('due_date', '<', today())))
                    ->count(),
                'invoices', '#fecaca', '#7f1d1d',
                'M12 9v2m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
                'Needs chasing');
        }

        return $tiles;
    }

    public function render()
    {
        return view('livewire.site-dashboard');
    }
}
