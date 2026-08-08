<?php

namespace App\Livewire;

use App\Models\ContactSubmission;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use App\Models\Subscription;
use Livewire\Component;
use Livewire\WithPagination;

class SiteSubmissionsPage extends Component
{
    use WithPagination;

    public Site $site;

    /** Active tab: 'subscriptions' | 'contact' | 'forms' */
    public string $tab = 'subscriptions';

    /**
     * For the forms tab — filter by a specific Form ID.
     * Empty string = show all forms.
     */
    public string $formFilter = '';

    /** Open detail row (contact / form response) */
    public ?string $openId = null;

    // ─────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    // ─────────────────────────────────────────────────────────────
    // Tab switching
    // ─────────────────────────────────────────────────────────────

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->openId = null;
        $this->formFilter = '';
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────────────────
    // Detail expand / collapse
    // ─────────────────────────────────────────────────────────────

    public function toggleOpen(string $id): void
    {
        $this->openId = ($this->openId === $id) ? null : $id;

        if ($this->openId !== null) {
            $this->markOneRead($id);
        }
    }

    private function markOneRead(string $id): void
    {
        if ($this->tab === 'contact') {
            ContactSubmission::where('site_id', $this->site->id)
                ->find($id)
                ?->markAsRead();
        } elseif ($this->tab === 'forms') {
            // Guard: response must belong to a form owned by this site
            FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $this->site->id))
                ->find($id)
                ?->markAsRead();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Bulk actions
    // ─────────────────────────────────────────────────────────────

    public function markAllRead(): void
    {
        if ($this->tab === 'contact') {
            ContactSubmission::where('site_id', $this->site->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

        } elseif ($this->tab === 'forms') {
            $query = FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $this->site->id))
                ->whereNull('read_at');

            if ($this->formFilter) {
                $query->where('form_id', (int) $this->formFilter);
            }

            $query->update(['read_at' => now()]);
        }
    }

    public function deleteSubmission(string $id): void
    {
        if ($this->tab === 'subscriptions') {
            Subscription::where('site_id', $this->site->id)->find($id)?->delete();

        } elseif ($this->tab === 'contact') {
            ContactSubmission::where('site_id', $this->site->id)->find($id)?->delete();

        } elseif ($this->tab === 'forms') {
            FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $this->site->id))
                ->find($id)
                ?->delete();
        }

        if ($this->openId === $id) {
            $this->openId = null;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────

    public function render()
    {
        // ── Subscriptions ───────────────────────────────────────
        $subscriptions = Subscription::where('site_id', $this->site->id)
            ->latest()
            ->paginate(20, pageName: 'subPage');

        // ── Contact messages ────────────────────────────────────
        $contacts = ContactSubmission::where('site_id', $this->site->id)
            ->latest()
            ->paginate(20, pageName: 'conPage');

        // ── Custom forms ─────────────────────────────────────────
        // All Form records for this site (used as filter pills)
        $formList = Form::where('site_id', $this->site->id)
            ->orderBy('name')
            ->get();

        // Paginated responses, optionally filtered by form
        $responsesQuery = FormResponse::whereHas(
            'form', fn ($q) => $q->where('site_id', $this->site->id)
        )->with('form');

        if ($this->formFilter) {
            $responsesQuery->where('form_id', (int) $this->formFilter);
        }

        $formResponses = $responsesQuery->latest()->paginate(20, pageName: 'frmPage');

        // ── Counts for tab badges ────────────────────────────────
        $counts = [
            'subscriptions' => Subscription::where('site_id', $this->site->id)->count(),
            'contact' => ContactSubmission::where('site_id', $this->site->id)->count(),
            'unread_contact' => ContactSubmission::where('site_id', $this->site->id)->whereNull('read_at')->count(),
            'forms' => FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $this->site->id))->count(),
            'unread_forms' => FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $this->site->id))->whereNull('read_at')->count(),
        ];

        return view('livewire.site-submissions-page', compact(
            'subscriptions', 'contacts', 'formList', 'formResponses', 'counts'
        ));
    }
}
