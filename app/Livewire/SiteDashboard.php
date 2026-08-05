<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Site;
use App\Models\Page;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Media;
use App\Models\SiteActivityLog;
use App\Models\Todo;

class SiteDashboard extends Component
{
    public Site $site;

    public int $pagesCount      = 0;
    public int $formsCount      = 0;
    public int $mediaCount      = 0;
    public int $responsesCount  = 0;
    public int $publishedCount  = 0;
    public int $contactsCount   = 0;
    public int $productivity    = 0;

    public array $recentPages        = [];
    public array $recentResponses    = [];
    public array $recentContacts     = [];
    public array $team               = [];
    public array $chartData          = [];

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

        $this->pagesCount      = Page::where('site_id', $site->id)->count();
        $this->publishedCount  = Page::where('site_id', $site->id)->where('is_published', true)->count();
        $this->formsCount      = Form::where('site_id', $site->id)->count();
        $this->mediaCount      = Media::where('site_id', $site->id)->count();
        $this->contactsCount   = $site->contacts()->count();

        $this->productivity = $this->pagesCount > 0
            ? (int) round($this->publishedCount / $this->pagesCount * 100)
            : 0;

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

        $this->recentPages = Page::where('site_id', $site->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'url', 'is_published', 'created_at'])
            ->toArray();

        $this->recentResponses = FormResponse::whereIn('form_id', $formIds)
            ->with('form:id,name')
            ->latest()
            ->limit(4)
            ->get()
            ->toArray();

        $this->chartData = collect(range(6, 0))->map(function ($daysAgo) use ($site) {
            $date = now()->subDays($daysAgo);
            return [
                'label' => $date->format('D'),
                'value' => Page::where('site_id', $site->id)
                    ->whereDate('created_at', $date->toDateString())
                    ->count(),
            ];
        })->toArray();

        // ── Activity feed (site_activity_logs) ───────────────────────────
        $this->recentActivities = SiteActivityLog::where('site_id', $site->id)
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($log) {
                [$badgeLabel, $badgeBg, $badgeFg] = $log->actionBadge();
                [$iconBg, $iconFg]                = $log->iconColors();

                return [
                    'id'           => $log->id,
                    'entity_type'  => $log->entity_type,
                    'entity_id'    => $log->entity_id,
                    'action'       => $log->action,
                    'title'        => $log->title,
                    'description'  => $log->description,
                    'url'          => $log->url,         // relative path, e.g. /pages
                    'icon_path'    => $log->iconPath(),
                    'icon_bg'      => $iconBg,
                    'icon_fg'      => $iconFg,
                    'badge_label'  => $badgeLabel,
                    'badge_bg'     => $badgeBg,
                    'badge_fg'     => $badgeFg,
                    'user_name'    => $log->user?->name ?? 'System',
                    'user_init'    => $log->user ? strtoupper(substr($log->user->name, 0, 1)) : 'S',
                    'user_color'   => $log->user ? null : '#6b7280',
                    'created_at'   => $log->created_at->diffForHumans(),
                    'meta'         => $log->meta ?? [],
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
                'id'            => $t->id,
                'title'         => $t->title,
                'status'        => $t->status,
                'priority'      => $t->priority,
                'due_at'        => $t->due_at?->format('M j') ?? null,
                'due_passed'    => $t->due_at?->isPast() ?? false,
                'assigned_to'   => $t->assignee?->name ?? null,
                'assigned_init' => $t->assignee ? strtoupper(substr($t->assignee->name, 0, 1)) : null,
                'progress'      => $t->progress(),
                'items_total'   => $t->items->count(),
                'items_done'    => $t->items->where('done', true)->count(),
            ])
            ->toArray();
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

    public function render()
    {
        return view('livewire.site-dashboard');
    }
}
