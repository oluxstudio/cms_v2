<?php

namespace App\Livewire;

use App\Models\Announcement;
use App\Models\User;
use App\Services\AccountActivity;
use App\Services\Announcements;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Platform admin › Announcements: banners shown across the app to everyone,
 * to chosen plans, or to specific accounts — optionally copied into each
 * site's notifications.
 */
class PlatformAnnouncementsPage extends Component
{
    /** Announcement id being edited; '' = new; null = drawer closed. */
    public ?string $editing = null;

    public array $form = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
    }

    public function create(): void
    {
        $this->editing = '';
        $this->form = [
            'title' => '', 'body' => '', 'level' => 'info', 'link_url' => '', 'link_label' => '',
            'audience' => 'all', 'plans' => [], 'emails' => '',
            'starts_at' => '', 'ends_at' => '', 'dismissible' => true, 'add_to_alerts' => false,
        ];
        $this->resetErrorBag();
    }

    public function edit(string $id): void
    {
        $a = Announcement::findOrFail($id);
        $this->editing = $a->id;
        $this->form = [
            'title' => $a->title, 'body' => (string) $a->body, 'level' => $a->level,
            'link_url' => (string) $a->link_url, 'link_label' => (string) $a->link_label,
            'audience' => $a->audience, 'plans' => (array) $a->plans,
            'emails' => User::whereIn('id', (array) $a->account_ids)->pluck('email')->implode("\n"),
            'starts_at' => $a->starts_at?->format('Y-m-d\TH:i') ?? '',
            'ends_at' => $a->ends_at?->format('Y-m-d\TH:i') ?? '',
            'dismissible' => $a->dismissible, 'add_to_alerts' => $a->add_to_alerts,
        ];
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->editing = null;
    }

    /** Emails in the form, resolved to account ids; unknown ones reported as a validation error. */
    private function resolveAccounts(): array
    {
        $emails = collect(preg_split('/[\s,;]+/', strtolower((string) $this->form['emails'])))->filter()->unique();
        $users = User::whereIn('email', $emails)->pluck('id', 'email');
        $missing = $emails->reject(fn ($e) => $users->has($e));
        if ($missing->isNotEmpty()) {
            $this->addError('form.emails', 'No account for: '.$missing->take(5)->implode(', '));
        }

        return $users->values()->all();
    }

    public function save(Announcements $announcements): void
    {
        $this->validate([
            'form.title' => ['required', 'string', 'max:140'],
            'form.body' => ['nullable', 'string', 'max:500'],
            'form.level' => ['required', 'in:info,warning,critical'],
            'form.link_url' => ['nullable', 'url', 'max:500'],
            'form.link_label' => ['nullable', 'string', 'max:40'],
            'form.audience' => ['required', 'in:all,plans,accounts'],
            'form.plans' => ['array', $this->form['audience'] === 'plans' ? 'min:1' : 'nullable'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after:form.starts_at'],
        ], [
            'form.title.required' => 'Give the announcement a title.',
            'form.plans.min' => 'Pick at least one plan.',
            'form.ends_at.after' => 'The end must be after the start.',
        ]);
        $accountIds = [];
        if ($this->form['audience'] === 'accounts') {
            $accountIds = $this->resolveAccounts();
            if ($this->getErrorBag()->has('form.emails')) {
                return;
            }
            if ($accountIds === []) {
                $this->addError('form.emails', 'Add at least one account email.');

                return;
            }
        }

        $data = [
            'title' => trim($this->form['title']),
            'body' => trim((string) $this->form['body']) ?: null,
            'level' => $this->form['level'],
            'link_url' => trim((string) $this->form['link_url']) ?: null,
            'link_label' => trim((string) $this->form['link_label']) ?: null,
            'audience' => $this->form['audience'],
            'plans' => $this->form['audience'] === 'plans' ? array_values($this->form['plans']) : null,
            'account_ids' => $this->form['audience'] === 'accounts' ? $accountIds : null,
            'starts_at' => $this->form['starts_at'] ? Carbon::parse($this->form['starts_at']) : null,
            'ends_at' => $this->form['ends_at'] ? Carbon::parse($this->form['ends_at']) : null,
            'dismissible' => (bool) $this->form['dismissible'],
            'add_to_alerts' => (bool) $this->form['add_to_alerts'],
        ];
        $a = $this->editing
            ? tap(Announcement::findOrFail($this->editing))->update($data)
            : Announcement::create($data + ['created_by' => Auth::id()]);
        Announcements::flush();

        $sent = 0;
        if ($a->add_to_alerts && ! $a->alerts_sent_at && $a->state() !== 'ended') {
            $sent = $announcements->pushToAlerts($a);
        }
        AccountActivity::record(Auth::id(), 'announcement.saved', 'Published an announcement: '.$a->title, ['category' => 'Sites', 'meta' => ['id' => $a->id]]);

        $this->editing = null;
        $this->dispatch('toast', level: 'success', title: 'Announcement saved',
            message: 'Reaches '.number_format($announcements->audienceQuery($a)->count()).' accounts.'.($sent ? " Added to {$sent} sites' notifications." : ''));
    }

    public function endNow(string $id): void
    {
        Announcement::whereKey($id)->update(['ends_at' => now()]);
        Announcements::flush();
    }

    public function delete(string $id): void
    {
        Announcement::whereKey($id)->delete();
        \DB::table('announcement_dismissals')->where('announcement_id', $id)->delete();
        Announcements::flush();
        $this->editing = null;
    }

    public function render(Announcements $announcements)
    {
        $all = Announcement::latest()->get();
        $dismissals = \DB::table('announcement_dismissals')->selectRaw('announcement_id, count(*) as n')->groupBy('announcement_id')->pluck('n', 'announcement_id');
        $rows = $all->map(fn (Announcement $a) => [
            'a' => $a,
            'state' => $a->state(),
            'reach' => $announcements->audienceQuery($a)->count(),
            'dismissed' => (int) ($dismissals[$a->id] ?? 0),
        ]);

        $preview = null;
        if ($this->editing !== null && ($this->form['title'] ?? '') !== '') {
            $preview = new Announcement([
                'title' => $this->form['title'], 'body' => $this->form['body'] ?: null, 'level' => $this->form['level'],
                'link_url' => $this->form['link_url'] ?: null, 'link_label' => $this->form['link_label'] ?: null,
                'dismissible' => (bool) $this->form['dismissible'],
            ]);
        }

        return view('livewire.platform-announcements-page', [
            'rows' => $rows,
            'stats' => [
                'active' => $rows->where('state', 'active')->count(),
                'scheduled' => $rows->where('state', 'scheduled')->count(),
                'reach' => (int) $rows->where('state', 'active')->sum('reach'),
            ],
            'plans' => collect(config('plans.tiers'))->map(fn ($t) => $t['name'] ?? '')->all(),
            'preview' => $preview,
        ]);
    }
}
