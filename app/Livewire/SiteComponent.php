<?php

namespace App\Livewire;

use App\Livewire\Forms\SiteForm;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SiteComponent extends Component
{
    public SiteForm $form;

    public array $sites = [];

    public string $filter = 'all';

    public string $search = '';

    public bool $showCreate = false;

    public function mount(): void
    {
        $this->loadSites();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->loadSites();
    }

    #[On('site-search')]
    public function handleSearch(string $query): void
    {
        $this->search = trim($query);
        $this->loadSites();
    }

    public function loadSites(): void
    {
        // Sites the user owns OR is a member of — super admins see EVERY site.
        $query = Site::query()
            ->unless(Auth::user()?->isSuper(), fn ($q) => $q->where(function ($w) {
                $w->where('user_id', Auth::id())
                    ->orWhereHas('members', fn ($m) => $m->where('users.id', Auth::id()))
                    // Account-team membership: members see every site of the
                    // client accounts they belong to.
                    ->orWhereIn('user_id', Auth::user()->memberships()->pluck('account_id'));
            }))
            ->withCount(['pages', 'components'])->with('user:id,name');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('domain', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            });
        }

        match ($this->filter) {
            'active' => $query->has('pages'),
            'recent' => $query->where('created_at', '>=', now()->subDays(7)),
            default => null,
        };

        // The card view reads plain array keys — provide every one it shows.
        $this->sites = $query->latest()->get()
            ->map(fn ($s) => array_merge($s->toArray(), ['owner' => $s->user->name ?? '—']))
            ->all();
    }

    public function selected(string $id): mixed
    {
        $site = $this->findAccessibleSite($id);

        return redirect('/'.$site->name.'/dashboard');
    }

    public function create(): void
    {
        $this->validate([
            'form.name' => 'required|unique:sites,name',
            'form.domain' => 'required',
            'form.owner' => 'required',
        ]);

        // Plan enforcement: site count is limited by the subscription tier
        // (null limit = unlimited on Enterprise).
        $sub = Auth::user()->currentSubscription();
        $limits = $sub->tier()['limits'] ?? [];
        // array_key_exists, NOT ?? — a null limit means UNLIMITED (Enterprise).
        $limit = array_key_exists('sites', $limits) ? $limits['sites'] : 1;
        if ($limit !== null && Site::where('user_id', Auth::id())->count() >= $limit) {
            $this->dispatch('toast', level: 'error', title: 'Plan limit reached',
                message: "Your {$sub->tier()['name']} plan allows {$limit} ".str('site')->plural($limit).'. Upgrade to add more.');
            $this->redirect(route('account.subscription'));

            return;
        }

        $site = Site::create([
            ...$this->form->all(),
            'user_id' => Auth::id(),
        ]);

        // The creator is the site's owner member
        $site->members()->syncWithoutDetaching([Auth::id() => ['role' => 'owner']]);

        // Blank scaffold: bind the generic renderer and give the site a Home page.
        $site->update(['template' => 'blank']);
        $site->pages()->firstOrCreate(['url' => '/'], ['name' => 'Home', 'keywords' => '', 'is_published' => true]);

        $this->form->reset();
        $this->showCreate = false;
        $this->loadSites();
    }

    public function delete(string $id): void
    {
        // Only the site owner may delete the site
        Site::where('user_id', Auth::id())->findOrFail($id)->delete();
        $this->loadSites();
    }

    /** Fetch a site by id only if the current user owns or belongs to it. */
    private function findAccessibleSite(string $id): Site
    {
        $site = Site::findOrFail($id);
        abort_unless($site->accessibleBy(Auth::user()), 403);

        return $site;
    }

    public function render()
    {
        return view('livewire.site-component');
    }
}
