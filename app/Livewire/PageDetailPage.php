<?php

namespace App\Livewire;

use App\Models\Page;
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
        if (! in_array($this->tab, ['edit', 'meta', 'content'], true)) {
            $this->tab = 'edit';
        }
        $this->fill_();
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
        $this->tab = in_array($tab, ['edit', 'meta', 'content'], true) ? $tab : 'edit';
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
