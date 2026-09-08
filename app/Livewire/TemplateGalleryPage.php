<?php

namespace App\Livewire;

use App\Models\Site;
use App\Models\Template;
use App\Services\TemplateCatalog;
use App\Services\TemplateInstaller;
use App\Support\CuratedTemplates;
use App\Support\TemplateCards;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public template gallery: anyone can browse designs and open the
 * right-side detail drawer with a live preview. Guests get a sign-up CTA;
 * logged-in owners save a design straight to one of their sites (it lands
 * on that site's My Designs page).
 */
class TemplateGalleryPage extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $category = 'all';

    #[Url]
    public string $price = 'all';

    /** Card key open in the drawer (curated:{key} | catalog:{id}). */
    public ?string $detailKey = null;

    public string $message = '';

    /** Every browsable card, unfiltered — feeds both the grid and the sidebar counts. */
    #[Computed]
    public function pool(): array
    {
        $curated = collect(CuratedTemplates::all())->map(fn ($t) => TemplateCards::fromCurated($t));

        $catalog = app(TemplateCatalog::class)
            ->browse([], 'popular', 60)
            ->getCollection()
            ->reject(fn (Template $t) => $t->builtin_key && collect(CuratedTemplates::all())->firstWhere('key', $t->builtin_key))
            ->map(fn (Template $t) => TemplateCards::fromCatalog($t));

        return $curated->concat($catalog)->values()->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedPrice(): void
    {
        $this->resetPage();
    }

    /** The filtered pool, paginated for the grid. */
    #[Computed]
    public function cards(): LengthAwarePaginator
    {
        $all = collect($this->pool);
        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $all = $all->filter(fn ($c) => str_contains(mb_strtolower($c['name'].' '.$c['description']), $needle));
        }
        if ($this->category !== 'all') {
            $all = $all->filter(fn ($c) => strcasecmp($c['category'], $this->category) === 0);
        }
        if ($this->price === 'free') {
            $all = $all->filter(fn ($c) => $c['priceCents'] === 0);
        } elseif ($this->price === 'paid') {
            $all = $all->filter(fn ($c) => $c['priceCents'] > 0);
        }

        $all = $all->values();
        $perPage = (int) config('templates.per_page', 12);
        // Clamp so a stale ?page= beyond the last page snaps back instead of
        // rendering an empty grid with a phantom pager.
        $page = min($this->getPage(), max(1, (int) ceil($all->count() / $perPage)));

        return new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /** category => count over the unfiltered pool (sidebar). */
    #[Computed]
    public function categoryCounts(): array
    {
        return collect($this->pool)->groupBy(fn ($c) => $c['category'] ?: 'Other')->map->count()->sortKeys()->all();
    }

    #[Computed]
    public function detail(): ?array
    {
        return $this->detailKey ? collect($this->pool)->firstWhere('key', $this->detailKey) : null;
    }

    /** Sites the signed-in user may save designs to. */
    #[Computed]
    public function mySites(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        return Site::where('user_id', $user->id)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all();
    }

    public function openDetail(string $key): void
    {
        $this->detailKey = $key;
        $this->message = '';
    }

    public function closeDetail(): void
    {
        $this->detailKey = null;
    }

    public function saveToSite(string $siteId, TemplateInstaller $installer)
    {
        $user = Auth::user();
        $card = $this->detail;
        if (! $user || ! $card) {
            return;
        }
        $site = Site::findOrFail($siteId);
        abort_unless($site->canManageTeam($user), 403);

        try {
            $result = str_starts_with($card['key'], 'curated:')
                ? $installer->saveCuratedToSite($site, $card['builtin'])
                : $installer->saveCatalogToSite($user, $site, Template::where('slug', explode(':', $card['key'], 2)[1])->firstOrFail());
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();

            return;
        }
        if (is_string($result)) {
            return $this->redirect($result); // Stripe checkout for paid templates
        }

        $this->message = '“'.$card['name'].'” saved to '.$site->name.' — open My Designs to make it the active look.';
    }

    public function render()
    {
        return view('livewire.template-gallery-page');
    }
}
