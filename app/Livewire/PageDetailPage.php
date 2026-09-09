<?php

namespace App\Livewire;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\Visit;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dedicated admin page for ONE site page: basic editing (name/url/published)
 * and the full metadata set (title, description, keywords, social & SEO tags,
 * custom attributes). Deliberately NO builder features — content/layout stay
 * in the block editor.
 */
class PageDetailPage extends Component
{
    /** Attribute keys owned by the builder/pipeline — never edited here. */
    private const MANAGED_ATTRS = [
        'title', 'description', 'og_title', 'og_description', 'og_image',
        'canonical_url', 'robots', 'custom_js', 'page_styles',
    ];

    public Site $site;

    public Page $page;

    /** edit | meta */
    #[Url(as: 'tab')]
    public string $tab = 'edit';

    // ── Edit tab ──
    public string $name = '';

    public string $url = '';

    public bool $isPublished = true;

    // ── Metadata tab ──
    public string $metaTitle = '';

    public string $metaDescription = '';

    public string $metaKeywords = '';

    public string $ogTitle = '';

    public string $ogDescription = '';

    public string $ogImage = '';

    public string $canonicalUrl = '';

    public string $robots = '';

    /** @var array<int,array{key:string,value:string}> */
    public array $attrRows = [];

    public string $successMessage = '';

    public function mount(Site $site, Page $page): void
    {
        abort_unless($page->site_id === $site->id, 404);
        $this->site = $site;
        $this->page = $page;
        if (! in_array($this->tab, ['edit', 'meta', 'content', 'sources'], true)) {
            $this->tab = 'edit';
        }
        $this->fill_();
        if ($this->tab === 'sources') {
            $this->fillSources();
        }
    }

    private function fill_(): void
    {
        $p = $this->page;
        $this->name = $p->name;
        $this->url = $p->url;
        $this->isPublished = (bool) $p->is_published;

        $attrs = $p->attrMap();
        $this->metaTitle = (string) ($attrs['title'] ?? '');
        $this->metaDescription = (string) ($attrs['description'] ?? '');
        $this->metaKeywords = (string) $p->keywords;
        $this->ogTitle = (string) ($attrs['og_title'] ?? '');
        $this->ogDescription = (string) ($attrs['og_description'] ?? '');
        $this->ogImage = (string) ($attrs['og_image'] ?? '');
        $this->canonicalUrl = (string) ($attrs['canonical_url'] ?? '');
        $this->robots = (string) ($attrs['robots'] ?? '');
        $this->attrRows = collect($attrs)->except(self::MANAGED_ATTRS)
            ->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])
            ->values()->all();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['edit', 'meta', 'content', 'sources'], true) ? $tab : 'edit';
        if ($this->tab === 'sources') {
            $this->fillSources();
        }
    }

    // ── Content sources: what dynamic content this page shows ──────────
    public array $srcProducts = ['enabled' => false, 'category' => '', 'tags' => '', 'limit' => 12];

    public array $srcPosts = ['enabled' => false, 'category' => '', 'tag' => '', 'limit' => 3];

    /** collection_id => ['attached' => bool, 'limit' => string] */
    public array $srcCollections = [];

    public string $collectionSearch = '';

    private function fillSources(): void
    {
        $cfg = json_decode((string) $this->page->getAttr('content_sources'), true) ?: [];
        $p = $cfg['products'] ?? [];
        $this->srcProducts = [
            'enabled' => (bool) ($p['enabled'] ?? false),
            'category' => (string) ($p['category'] ?? ''),
            'tags' => implode(', ', $p['tags'] ?? []),
            'limit' => (int) ($p['limit'] ?? 12),
        ];
        $o = $cfg['posts'] ?? [];
        $this->srcPosts = [
            'enabled' => (bool) ($o['enabled'] ?? false),
            'category' => (string) ($o['category'] ?? ''),
            'tag' => (string) ($o['tag'] ?? ''),
            'limit' => (int) ($o['limit'] ?? 3),
        ];
        $attached = $this->page->collections()->get()->keyBy('id');
        $this->srcCollections = [];
        foreach ($this->site->collections()->orderBy('name')->get() as $col) {
            $pivot = $attached[$col->id]->pivot ?? null;
            $settings = $pivot ? (is_array($pivot->settings) ? $pivot->settings : json_decode((string) $pivot->settings, true)) : null;
            $this->srcCollections[$col->id] = [
                'attached' => (bool) $pivot,
                'limit' => (string) ($settings['limit'] ?? ''),
            ];
        }
    }

    /** Category options for the source dropdowns. */
    public function getProductCategoriesProperty(): array
    {
        return $this->site->products()->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }

    public function getPostCategoriesProperty(): array
    {
        return Post::where('site_id', $this->site->id)
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }

    public function getSourceCollectionsProperty()
    {
        return $this->site->collections()->withCount('items')
            ->when($this->collectionSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->collectionSearch.'%'))
            ->orderBy('name')->get();
    }

    public function saveSources(): void
    {
        abort_unless($this->site->allows(auth()->user(), 'pages.manage'), 403);
        $this->validate([
            'srcProducts.category' => ['nullable', 'string', 'max:60'],
            'srcProducts.tags' => ['nullable', 'string', 'max:200'],
            'srcProducts.limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'srcPosts.category' => ['nullable', 'string', 'max:60'],
            'srcPosts.tag' => ['nullable', 'string', 'max:60'],
            'srcPosts.limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $cfg = [
            'products' => [
                'enabled' => (bool) $this->srcProducts['enabled'],
                'category' => trim((string) $this->srcProducts['category']),
                'tags' => collect(explode(',', (string) $this->srcProducts['tags']))
                    ->map(fn ($t) => trim($t))->filter()->unique()->values()->all(),
                'limit' => (int) ($this->srcProducts['limit'] ?: 12),
            ],
            'posts' => [
                'enabled' => (bool) $this->srcPosts['enabled'],
                'category' => trim((string) $this->srcPosts['category']),
                'tag' => trim((string) $this->srcPosts['tag']),
                'limit' => (int) ($this->srcPosts['limit'] ?: 3),
            ],
        ];
        $this->page->setAttr('content_sources', json_encode($cfg));

        // Collections: reconcile pivots + per-attachment limits.
        $maxOrder = (int) \DB::table('page_collection')->where('page_id', $this->page->id)->max('order');
        foreach ($this->srcCollections as $colId => $row) {
            $exists = $this->page->collections()->where('collections.id', $colId)->exists();
            if (($row['attached'] ?? false)) {
                $limit = (int) ($row['limit'] ?? 0);
                $settings = $limit > 0 ? json_encode(['limit' => min(50, $limit)]) : null;
                $exists
                    ? $this->page->collections()->updateExistingPivot($colId, ['settings' => $settings])
                    : $this->page->collections()->attach($colId, ['order' => ++$maxOrder, 'settings' => $settings]);
            } elseif ($exists) {
                $this->page->collections()->detach($colId);
            }
        }

        $this->page->refresh();
        $this->fillSources();
        $this->successMessage = 'Content sources saved.';
    }

    /** Summary tiles for the Content tab's left rail. */
    public function getSummaryProperty(): array
    {
        $components = $this->page->components()->withCount('nodes')->get();

        return [
            'components' => $components->count(),
            'fields' => (int) $components->sum('nodes_count'),
            'visits_30d' => Visit::forSite($this->site->id)->humans()
                ->where('created_at', '>=', now()->subDays(30))
                ->where('path', rtrim($this->page->url, '/') ?: '/')
                ->count(),
            'updated' => $this->page->updated_at,
        ];
    }

    /** Save the Edit tab: name / url / published. */
    public function saveEdit(): void
    {
        abort_unless($this->site->allows(auth()->user(), 'pages.manage'), 403);
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'url' => ['required', 'string', 'max:190', 'regex:/^\/[a-zA-Z0-9\-_\/]*$/'],
        ], ['url.regex' => 'URLs start with / and may only contain letters, numbers, dashes and slashes.']);

        $this->page->update([
            'name' => trim($this->name),
            'url' => trim($this->url),
            'is_published' => $this->isPublished,
        ]);
        $this->page->refresh();
        $this->successMessage = 'Page saved.';
    }

    /** Save the Metadata tab: meta tags + custom attributes. */
    public function saveMeta(): void
    {
        abort_unless($this->site->allows(auth()->user(), 'pages.manage'), 403);
        $this->validate([
            'metaTitle' => ['nullable', 'string', 'max:200'],
            'metaDescription' => ['nullable', 'string', 'max:5000'],
            'metaKeywords' => ['nullable', 'string', 'max:500'],
            'ogTitle' => ['nullable', 'string', 'max:200'],
            'ogDescription' => ['nullable', 'string', 'max:1000'],
            'ogImage' => ['nullable', 'string', 'max:2000'],
            'canonicalUrl' => ['nullable', 'string', 'max:2000'],
            'robots' => ['nullable', 'string', 'max:100'],
            'attrRows.*.key' => ['nullable', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_\-]*$/'],
            'attrRows.*.value' => ['nullable', 'string', 'max:5000'],
        ], ['attrRows.*.key.regex' => 'Attribute keys may only contain letters, numbers, dashes and underscores.']);

        $this->page->update(['keywords' => trim($this->metaKeywords)]);

        // Meta tags: blank clears the attribute.
        foreach ([
            'title' => $this->metaTitle,
            'description' => $this->metaDescription,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_image' => $this->ogImage,
            'canonical_url' => $this->canonicalUrl,
            'robots' => $this->robots,
        ] as $key => $value) {
            trim($value) !== '' ? $this->page->setAttr($key, trim($value)) : $this->page->forgetAttr($key);
        }

        // Reconcile custom attributes.
        $kept = [];
        foreach ($this->attrRows as $row) {
            $key = trim($row['key']);
            if ($key === '' || in_array($key, self::MANAGED_ATTRS, true)) {
                continue;
            }
            $this->page->setAttr($key, (string) $row['value']);
            $kept[] = $key;
        }
        foreach (array_keys($this->page->attrMap()) as $existing) {
            if (! in_array($existing, self::MANAGED_ATTRS, true) && ! in_array($existing, $kept, true)) {
                $this->page->forgetAttr($existing);
            }
        }

        $this->page->refresh();
        $this->fill_();
        $this->successMessage = 'Metadata saved.';
    }

    public function addAttrRow(): void
    {
        if (count($this->attrRows) < 60) {
            $this->attrRows[] = ['key' => '', 'value' => ''];
        }
    }

    public function removeAttrRow(int $i): void
    {
        unset($this->attrRows[$i]);
        $this->attrRows = array_values($this->attrRows);
    }

    public function render()
    {
        return view('livewire.page-detail-page');
    }
}
