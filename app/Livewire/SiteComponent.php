<?php

namespace App\Livewire;

use App\Livewire\Forms\SiteForm;
use App\Models\Site;
use App\Services\AccountActivity;
use App\Services\Blueprints\BlueprintRegistry;
use App\Services\Blueprints\SalonBlueprint;
use App\Services\SampleSiteSeeder;
use App\Support\SiteProperties;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class SiteComponent extends Component
{
    public SiteForm $form;

    public array $sites = [];

    public string $filter = 'all';

    public string $search = '';

    public bool $showCreate = false;

    /** Scaffold a populated starter (pages, components, testimonials, contact form). */
    public bool $addSample = true;

    /**
     * Starter content: 'business' (the ready-made setup for the chosen kind of
     * business), 'sample' (generic), 'salon' (Salon & Barber) or 'blank'.
     */
    public string $starter = 'sample';

    // ── Create-site lightbox: the business behind the site ──
    /** Kind of business (BlueprintRegistry type) — tailors the starter pages. */
    public string $type = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    /** Live web-address check: null = not checked yet. */
    public ?bool $available = null;

    /** The address keeps following the business name until it's edited. */
    public bool $addressEdited = false;

    /** Opened from the onboarding checklist's "Create site" step. */
    #[On('open-create-site')]
    public function openCreate(): void
    {
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
        $this->resetErrorBag();
    }

    /** Business name typed → suggest the web address from it (until edited). */
    public function updatedFormOwner(string $value): void
    {
        if (! $this->addressEdited) {
            $this->form->name = Str::slug($value);
        }
        $this->checkAddress();
    }

    public function updatedFormName(string $value): void
    {
        $this->form->name = Str::slug($value);
        $this->addressEdited = $this->form->name !== '' && $this->form->name !== Str::slug((string) $this->form->owner);
        $this->checkAddress();
    }

    /** Picking a kind of business makes its ready-made setup the starter. */
    public function updatedType(string $value): void
    {
        if (BlueprintRegistry::exists($value)) {
            $this->starter = 'business';
        }
    }

    public function checkAddress(): void
    {
        $label = (string) $this->form->name;
        $this->available = $label === '' ? null
            : (strlen($label) >= 4 && Site::validSubdomainLabel($label) && ! Site::nameTaken($label) && ! Site::where('name', $label)->exists());
    }

    /** Sites used / allowed on the account's plan — the lightbox shows it up front. */
    public function getPlanRoomProperty(): array
    {
        $sub = Auth::user()->currentSubscription();

        return [
            'plan' => $sub->tier()['name'] ?? 'Free trial',
            'used' => Auth::user()->sites()->count(),
            'limit' => $sub->sitesLimit(),
            'can' => $sub->canCreateSite(),
            'expired' => $sub->trialExpired(),
        ];
    }

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
                    // Team membership: a site-scoped row opens exactly that
                    // site; an account-wide row (site_id null) opens every
                    // site the account owns.
                    ->orWhereIn('id', Auth::user()->memberships()->whereNotNull('site_id')->pluck('site_id'))
                    ->orWhereIn('user_id', Auth::user()->memberships()->whereNull('site_id')->pluck('account_id'));
            }))
            ->withCount(['pages', 'components', 'contacts'])->with('user:id,name');

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
            ->map(fn ($s) => array_merge($s->toArray(), [
                'owner' => $s->user->name ?? '—',
                'url' => $s->publicUrl(),
                'is_owner' => $s->user_id === Auth::id(),
            ]))
            ->all();
    }

    public function selected(string $id): mixed
    {
        $site = $this->findAccessibleSite($id);

        return redirect('/'.$site->name.'/dashboard');
    }

    public function create(): void
    {
        // The web address follows the business name unless typed; the
        // instant subdomain fills the domain (a real one is added on Go live).
        $this->form->name = Str::slug((string) ($this->form->name ?: $this->form->owner));
        if (trim((string) $this->form->domain) === '') {
            $base = (string) config('publishing.subdomain_base') ?: 'oluxstudio.com';
            $this->form->domain = $this->form->name.'.'.$base;
        }
        $this->validate([
            'form.owner' => ['required', 'string', 'min:2', 'max:80'],
            'form.name' => ['required', 'min:4', 'max:63', 'alpha_dash', 'unique:sites,name',
                fn ($attr, $value, $fail) => (Site::nameTaken((string) $value) || ! Site::validSubdomainLabel((string) $value)) && $fail('That address is taken or not allowed — try another.')],
            'form.domain' => ['required', 'string', 'max:190'],
            'form.description' => ['required', 'string', 'min:10', 'max:500'],
            'type' => ['nullable', 'string', fn ($attr, $value, $fail) => $value !== '' && ! BlueprintRegistry::exists($value) && $fail('Pick one of the listed kinds of business.')],
            'contactEmail' => ['nullable', 'email', 'max:190'],
            'contactPhone' => ['nullable', 'max:40', 'regex:/^[0-9+().\-\s\/ext]{3,40}$/i'],
        ], [
            'form.owner.required' => 'What is the business (or project) called?',
            'form.description.required' => 'Add a sentence about what the site is for.',
            'form.description.min' => 'A little more, please — at least a short sentence.',
        ], [
            'form.owner' => 'business name', 'form.name' => 'web address', 'form.description' => 'description',
            'contactEmail' => 'email', 'contactPhone' => 'phone',
        ]);

        // Plan enforcement: site count is capped by the subscription tier
        // (config/plans.php; null = unlimited). Over the cap → upgrade prompt.
        $sub = Auth::user()->currentSubscription();
        if (! $sub->canCreateSite()) {
            $limit = $sub->sitesLimit();
            $reason = $sub->trialExpired()
                ? 'Your free trial has ended — upgrade to keep creating and managing sites.'
                : "The {$sub->tier()['name']} plan includes {$limit} ".str('site')->plural((int) $limit).". You're using {$sub->sitesUsage()}. Upgrade to add more.";
            $this->showCreate = false;
            $this->dispatch('upgrade-required', reason: $reason, cta: 'See plans');

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

        // Every site starts with the full commerce suite enabled (all basic
        // tier) — so the Commerce nav is populated from day one.
        $site->enableCommerceSuite();

        // The business behind the site: Site Properties + the AI's context.
        $site->setAttr('business_name', (string) $this->form->owner);
        $site->setAttr('site_purpose', (string) $this->form->description);
        if ($this->type !== '') {
            $site->setAttr('business_type', $this->type);
        }
        $values = array_filter(['site_name' => (string) $this->form->owner, 'email' => trim($this->contactEmail)]);
        $rows = trim($this->contactPhone) !== '' ? ['phones' => [['label' => 'Main', 'value' => trim($this->contactPhone)]]] : [];
        try {
            SiteProperties::save($site, ['values' => $values] + ($rows ? ['rows' => $rows] : []), Auth::user()?->name);
            // Setup isn't an edit: no history entry (the checklist's "update your
            // pages" step counts content versions).
            \App\Models\ContentVersion::where('site_id', $site->id)->delete();
        } catch (\Throwable $e) {
            report($e); // details can always be added on Site Properties
        }

        // Optional starter content so the site isn't a blank canvas (onboarding).
        match (true) {
            $this->starter === 'business' && BlueprintRegistry::exists($this->type) => BlueprintRegistry::forType($this->type)->apply($site),
            $this->starter === 'salon' => app(SalonBlueprint::class)->apply($site),
            in_array($this->starter, ['sample', 'business'], true) => app(SampleSiteSeeder::class)->seed($site),
            default => null,
        };

        AccountActivity::siteCreated($site);

        $this->form->reset();
        $this->reset(['type', 'contactEmail', 'contactPhone', 'available', 'addressEdited']);
        $this->starter = 'sample';
        $this->showCreate = false;
        $this->loadSites();
        $this->dispatch('onboarding-updated'); // advance the checklist
        $this->dispatch('toast', level: 'success', title: 'Site created', message: $site->name.' is ready — next, choose a template and edit its content.');
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
        $user = Auth::user();

        return view('livewire.site-component', [
            'businessTypes' => BlueprintRegistry::types(),
            // Right rail: the get-started checklist and the account's plan.
            'steps' => \App\Support\Onboarding::steps($user),
            'progress' => \App\Support\Onboarding::progress($user),
            'sub' => $user->currentSubscription(),
        ]);
    }
}
