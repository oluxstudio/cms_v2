<?php

namespace App\Livewire;

use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\TemplateCreator;
use App\Models\TemplateEntitlement;
use App\Services\TemplateCatalog;
use App\Services\TemplateCommerce;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Marketplace (ACCOUNT level): Browse the template store + My Library.
 * Every filter, the sort, search, tab and page live in the URL so views are
 * shareable and the back button works.
 */
class MarketplaceStore extends Component
{
    use WithPagination;

    public Site $site;

    #[Url(as: 'tab')]
    public string $tab = 'browse';

    #[Url(as: 'q')]
    public string $q = '';

    #[Url(as: 'sort')]
    public string $sort = 'popular';

    #[Url(as: 'category')]
    public array $cats = [];

    #[Url(as: 'tag')]
    public array $tagSel = [];

    #[Url(as: 'creator')]
    public array $creatorSel = [];

    #[Url(as: 'price')]
    public string $price = 'any';

    #[Url(as: 'show')]
    public string $libFilter = 'all';

    public function updated($prop): void
    {
        if (in_array($prop, ['q', 'sort', 'price']) || str_starts_with($prop, 'cats') || str_starts_with($prop, 'tagSel') || str_starts_with($prop, 'creatorSel')) {
            $this->resetPage();
        }
    }

    public function toggleTag(string $tag): void
    {
        $this->tagSel = in_array($tag, $this->tagSel, true)
            ? array_values(array_diff($this->tagSel, [$tag]))
            : [...$this->tagSel, $tag];
        $this->resetPage();
    }

    public function removeFilter(string $prop, string $value): void
    {
        if (in_array($prop, ['cats', 'tagSel', 'creatorSel'], true)) {
            $this->{$prop} = array_values(array_filter($this->{$prop}, fn ($v) => (string) $v !== $value));
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'cats', 'tagSel', 'creatorSel', 'price');
        $this->resetPage();
    }

    public function addToLibrary(string $templateId, TemplateCommerce $commerce): void
    {
        $template = Template::where('status', 'published')->findOrFail($templateId);
        $commerce->addFreeToLibrary(Auth::user(), $template);
        $this->dispatch('toast', level: 'success', title: 'Added to your library', message: $template->name.' is ready to use on any of your sites.');
    }

    public function buy(string $templateId, TemplateCommerce $commerce): void
    {
        $template = Template::where('status', 'published')->findOrFail($templateId);
        if ($commerce->inLibrary(Auth::user(), $template)) {
            return;
        }
        $this->redirect($commerce->checkoutUrl(Auth::user(), $template, url()->full(), $this->site->name));
    }

    public function render(TemplateCatalog $catalog)
    {
        $user = Auth::user();
        $templates = $catalog->browse([
            'search' => $this->q,
            'categories' => $this->cats,
            'tags' => $this->tagSel,
            'creators' => $this->creatorSel,
            'price' => $this->price === 'any' ? '' : $this->price,
        ], $this->sort);

        $owned = TemplateEntitlement::where('user_id', $user->id)->pluck('template_id')->flip();

        // Library rows + usage across the account's sites
        $sites = $user->sites()->get(['id', 'name']);
        $usage = SiteTemplate::whereIn('site_id', $sites->pluck('id'))->whereNotNull('applied_at')
            ->get(['site_id', 'template_id', 'template_version_id'])->groupBy('template_id');
        $library = TemplateEntitlement::with('template.creator')
            ->where('user_id', $user->id)->latest()->get()
            ->filter(fn ($e) => $e->template !== null)
            ->map(function ($e) use ($usage, $sites) {
                $t = $e->template;
                $used = collect($usage->get($t->id) ?? []);

                return (object) [
                    'entitlement' => $e, 'template' => $t,
                    'used_on' => $used->map(fn ($u) => $sites->firstWhere('id', $u->site_id)?->name)->filter()->values(),
                    'update_available' => $used->contains(fn ($u) => $u->template_version_id !== null
                        && $t->latest_version_id !== null && $u->template_version_id < $t->latest_version_id),
                ];
            })->values();
        $library = match ($this->libFilter) {
            'purchased' => $library->filter(fn ($r) => $r->entitlement->source === 'purchase')->values(),
            'free' => $library->filter(fn ($r) => $r->entitlement->source !== 'purchase')->values(),
            'updates' => $library->filter(fn ($r) => $r->update_available)->values(),
            default => $library,
        };

        $allTags = Template::where('status', 'published')->pluck('tags')->flatten()->filter()->unique()->sort()->values();

        // Private templates assigned to THIS site's account ("Made for you").
        $exclusive = Template::where('visibility', 'private')->where('status', 'published')
            ->whereHas('entitlements', fn ($q) => $q->where('user_id', $this->site->user_id))
            ->orderBy('name')->get();

        return view('livewire.marketplace-store', [
            'exclusive' => $exclusive,
            'templates' => $templates,
            'owned' => $owned,
            'library' => $library,
            'libraryCount' => TemplateEntitlement::where('user_id', $user->id)->count(),
            'categories' => collect(config('templates.categories'))->merge($catalog->categories())->unique()->values(),
            'creators' => TemplateCreator::orderBy('name')->get(['id', 'name']),
            'allTags' => $allTags,
            'sites' => $sites,
        ]);
    }
}
