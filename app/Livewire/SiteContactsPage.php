<?php

namespace App\Livewire;

use App\Models\Contact;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;

class SiteContactsPage extends Component
{
    public Site $site;

    /** Active sub-view: pipeline (list) | sources (analytics). */
    #[Url(as: 'view')]
    public string $view = 'pipeline';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    /** Currently open contact in the detail drawer (?contact=ID supported). */
    #[Url(as: 'contact')]
    public ?int $selectedId = null;

    /** Note composer in the detail drawer. */
    public string $note = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['pipeline', 'sources'], true) ? $view : 'pipeline';
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
    }

    public function open(int $id): void
    {
        $this->selectedId = $id;
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
    }

    public function updateStatus(int $id, string $status): void
    {
        if (! in_array($status, Contact::STATUSES, true)) {
            return;
        }

        $contact = $this->site->contacts()->find($id);
        if (! $contact || $contact->status === $status) {
            return;
        }

        $from = $contact->status;
        $contact->update(['status' => $status, 'last_activity_at' => now()]);
        $contact->logActivity('status_change', null, ['from' => $from, 'to' => $status]);
    }

    /** Set (or clear) the contact's logo/photo URL — blank falls back to Gravatar/initials. */
    public function setAvatar(int $id, ?string $url): void
    {
        $contact = $this->site->contacts()->find($id);
        if (! $contact) {
            return;
        }
        $url = trim((string) $url);
        if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL) && ! str_starts_with($url, '/')) {
            $this->dispatch('toast', level: 'error', title: 'Invalid URL', message: 'Use a full image URL or pick one from Media.');

            return;
        }
        $data = (array) ($contact->data ?? []);
        if ($url === '') {
            unset($data['avatar']);
        } else {
            $data['avatar'] = $url;
        }
        $contact->update(['data' => $data]);
    }

    public function assign(int $id, ?string $userId): void
    {
        $contact = $this->site->contacts()->find($id);
        if (! $contact) {
            return;
        }

        $userId = $userId !== null && $userId !== '' ? (int) $userId : null;

        // Only members of this site may be assigned
        if ($userId !== null && ! $this->site->members()->whereKey($userId)->exists()) {
            return;
        }

        $contact->update(['assigned_user_id' => $userId, 'last_activity_at' => now()]);

        $name = $userId ? optional(User::find($userId))->name : null;
        $contact->logActivity('assigned', null, [
            'assigned_user_id' => $userId,
            'assigned_to_name' => $name ?? 'Unassigned',
        ]);
    }

    public function addNote(int $id): void
    {
        $this->validate(['note' => ['required', 'string', 'max:2000']]);

        $contact = $this->site->contacts()->find($id);
        if (! $contact) {
            return;
        }

        $contact->logActivity('note', trim($this->note));
        $contact->update(['last_activity_at' => now()]);
        $this->note = '';
    }

    public function deleteContact(int $id): void
    {
        $this->site->contacts()->whereKey($id)->delete();
        if ($this->selectedId === $id) {
            $this->selectedId = null;
        }
    }

    /** Site team members available for assignment. */
    public function getMembersProperty()
    {
        return $this->site->members()->orderBy('name')->get(['users.id', 'name']);
    }

    public function getContactsProperty()
    {
        return $this->site->contacts()
            ->with(['sourceForm:id,title,name', 'assignedUser:id,name'])
            ->withCount('responses')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('last_activity_at')
            ->latest()
            ->get();
    }

    public function getSelectedProperty(): ?Contact
    {
        if (! $this->selectedId) {
            return null;
        }

        return $this->site->contacts()
            ->with([
                'sourceForm:id,title,name',
                'assignedUser:id,name',
                'responses' => fn ($q) => $q->with('form:id,title,name'),
                'activities' => fn ($q) => $q->with('user:id,name')->latest(),
            ])
            ->find($this->selectedId);
    }

    public function getStatusCountsProperty(): array
    {
        $counts = $this->site->contacts()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $counts['all'] = array_sum($counts);

        return $counts;
    }

    /**
     * Lead-source analytics: funnel totals, per-form conversion, and a
     * contacts-created trend for the last 14 days.
     */
    public function getInsightsProperty(): array
    {
        $formIds = Form::where('site_id', $this->site->id)->pluck('id');

        $totalResponses = FormResponse::whereIn('form_id', $formIds)->count();
        $converted = FormResponse::whereIn('form_id', $formIds)->whereNotNull('contact_id')->count();
        $contacts = $this->site->contacts()->count();
        $won = $this->site->contacts()->where('status', 'won')->count();
        $qualified = $this->site->contacts()->whereIn('status', ['qualified', 'won'])->count();

        // Per-form: responses, contacts created, won, conversion rate
        $forms = Form::where('site_id', $this->site->id)
            ->withCount('responses')
            ->get();

        $agg = $this->site->contacts()
            ->selectRaw('source_form_id, count(*) as contacts, sum(case when status = "won" then 1 else 0 end) as won')
            ->groupBy('source_form_id')
            ->get()
            ->keyBy('source_form_id');

        $perForm = $forms->map(function (Form $f) use ($agg) {
            $a = $agg->get($f->id);
            $responses = (int) $f->responses_count;
            $contacts = (int) ($a->contacts ?? 0);

            return [
                'title' => $f->title ?: $f->name,
                'responses' => $responses,
                'contacts' => $contacts,
                'won' => (int) ($a->won ?? 0),
                'rate' => $responses > 0 ? round($contacts / $responses * 100) : 0,
            ];
        })
            ->sortByDesc(fn ($r) => [$r['rate'], $r['contacts']])
            ->values()
            ->all();

        // 14-day contacts-created trend
        $trend = collect(range(13, 0))->map(function ($d) {
            $date = now()->subDays($d);

            return [
                'label' => $date->format('M j'),
                'short' => $date->format('j'),
                'value' => $this->site->contacts()->whereDate('created_at', $date->toDateString())->count(),
            ];
        })->all();

        return [
            'totalResponses' => $totalResponses,
            'converted' => $converted,
            'contacts' => $contacts,
            'qualified' => $qualified,
            'won' => $won,
            'convRate' => $totalResponses > 0 ? round($converted / $totalResponses * 100) : 0,
            'winRate' => $contacts > 0 ? round($won / $contacts * 100) : 0,
            'perForm' => $perForm,
            'trend' => $trend,
        ];
    }

    public function render()
    {
        return view('livewire.site-contacts-page');
    }
}
