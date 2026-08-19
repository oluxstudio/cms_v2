<?php

namespace App\Services\SiteConnect;

use App\Models\Collection;
use App\Models\Component;
use App\Models\Form;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Site;
use App\Services\BookingService;
use Illuminate\Support\Str;

/**
 * Builds the `page.json` contract document for a single page — the single source
 * of truth a client site hydrates from (Part 3 of the Site Connect spec).
 *
 * It does NOT invent a new content model: it reads the page's already-composed
 * Components / Collections / Forms and the site's Posts, and reshapes them into
 * the versioned contract. Same content, one more delivery format alongside the
 * existing content.json.
 *
 * Contract shape (schemaVersion 2):
 *   {
 *     schemaVersion,
 *     siteData:  { name, domain, logo, icon, description, theme, nav, currency, version, generatedAt },
 *     pageData:  { name, url, slug, createdAt, meta, styles },
 *     componentData[], collectionData[], formData[], bookingData{}, postData[]
 *   }
 */
class PageJsonGenerator
{
    /**
     * Generate the full page.json document. `$version` is stamped as-is (the
     * publisher decides the number); pass the page's current version for a
     * preview/read, or the freshly-bumped one on publish.
     */
    public function generate(Page $page, ?int $version = null): array
    {
        $site = $page->site;
        $version ??= (int) $page->page_json_version;

        $components = $page->components()->with('nodes')->get();

        // Cross-type page order: components then collections then forms then
        // posts, each in its own order — a single increasing `position`
        // sequence. (Stage 4's builder will let users freely interleave types;
        // for the contract we emit a stable, deterministic ordering.)
        // NB: use reference closures — PHP arrow fns capture $cursor by value, so
        // a shared post-increment counter must be passed by reference to advance.
        $cursor = 0;

        $componentEntries = $components
            ->sortBy(fn ($c) => (int) $c->pivot->order)
            ->map(function (Component $c) use ($site, &$cursor) {
                return $this->component($c, $site, $cursor++);
            })
            ->values()->all();

        $collectionEntries = $page->collections()->with('items')->get()
            ->sortBy(fn ($c) => (int) $c->pivot->order)
            ->map(function ($c) use (&$cursor) {
                return $this->collection($c, $cursor++);
            })
            ->values()->all();

        $formEntries = $this->pageForms($site)
            ->map(function (Form $f) use (&$cursor) {
                return $this->form($f, $cursor++);
            })
            ->values()->all();

        $postEntries = $this->sitePosts($site)
            ->map(fn (Post $p) => $this->post($p, $site))
            ->values()->all();

        return [
            'schemaVersion' => (int) config('site_connect.schema_version', 2),
            'siteData' => [
                'name' => $site->name,
                'domain' => $site->domain,
                'logo' => $this->siteAsset($site, 'logo'),
                'icon' => $this->siteAsset($site, 'favicon') ?? $this->siteAsset($site, 'icon'),
                'description' => $site->description,
                'theme' => $this->theme($site),
                'nav' => $this->nav($site),
                'currency' => $site->currency,
                'version' => $version,
                'generatedAt' => now()->toIso8601String(),
            ],
            'pageData' => [
                'name' => $page->name,
                'url' => $page->url,
                'slug' => $this->pageSlug($page),
                'createdAt' => $page->created_at?->toIso8601String(),
                'meta' => [
                    'description' => (string) $page->getAttr('description', ''),
                    'ogImage' => $this->resolveImage($site, (string) $page->getAttr('og_image', '')),
                    'keywords' => $page->keywords,
                ],
                // Extracted + edited page CSS (populated by ingestion; empty string
                // keeps the contract stable until then).
                'styles' => (string) $page->getAttr('page_styles', ''),
            ],
            'componentData' => $componentEntries,
            'collectionData' => $collectionEntries,
            'formData' => $formEntries,
            'bookingData' => $this->bookings($site),
            'postData' => $postEntries,
        ];
    }

    /** A site branding asset (logo/favicon/icon) from attributes, @media-resolved. */
    private function siteAsset(Site $site, string $key): ?string
    {
        $value = (string) ($site->getAttr($key, '') ?? '');
        $resolved = $this->resolveImage($site, $value);

        return $resolved === '' ? null : $resolved;
    }

    /**
     * Bookable services + availability the client renders a booking widget from.
     * Empty when the site has no bookings feature. Never exposes customer bookings.
     */
    private function bookings(Site $site): array
    {
        if (! $site->hasFeature('bookings')) {
            return ['services' => [], 'availability' => null];
        }

        $settings = app(BookingService::class)->settings($site);
        $weekdayIndex = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

        return [
            'services' => $site->services()->where('is_active', true)->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Service $s) => [
                    'slug' => $s->slug,
                    'name' => $s->name,
                    'kind' => $s->kind,
                    'description' => $s->description,
                    'duration' => $s->duration_min,
                    'price' => $s->formattedPrice(),
                    'priceCents' => $s->price_cents,
                    'currency' => $s->currency,
                    'capacity' => $s->capacity,
                    'requiresPayment' => (bool) $s->requires_payment,
                    'deposit' => $s->hasDeposit() ? ['fixed' => $s->deposit_cents, 'pct' => $s->deposit_pct] : null,
                    'resources' => $s->activeResources()->get()
                        ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
                    'formFields' => $s->formFields(),
                ])->values()->all(),
            'availability' => [
                'weekdays' => array_values(array_filter(array_map(fn ($d) => $weekdayIndex[$d] ?? null, $settings['days'] ?? []), fn ($v) => $v !== null)),
                'slotMinutes' => $settings['slot'] ?? null,
                'horizonDays' => $settings['horizon'] ?? null,
            ],
        ];
    }

    /** Theme mapped onto the contract's nested fonts/colors/spacing shape. */
    private function theme(Site $site): array
    {
        $t = $site->themeValues();

        return [
            'fonts' => [
                'heading' => $t['heading_font'] ?? $t['font'] ?? 'Inter',
                'body' => $t['font'] ?? 'Inter',
            ],
            'colors' => [
                'primary' => $t['accent'] ?? '#6366f1',
                'surface' => $t['surface'] ?? '#ffffff',
                'text' => $t['text'] ?? '#1f2937',
                'muted' => $t['muted'] ?? '#6b7280',
            ],
            'spacing' => [
                'base' => $t['base_size'] ?? '16px',
                'sectionY' => $t['section_y'] ?? '4rem',
            ],
            'radius' => $t['radius'] ?? '12px',
            'containerMax' => $t['container_max'] ?? '1200px',
        ];
    }

    /** Site nav from live pages (label + href). */
    private function nav(Site $site): array
    {
        return $site->livePages()->get()
            ->map(fn (Page $p) => ['label' => $p->name, 'href' => $p->url])
            ->values()->all();
    }

    private function component(Component $c, Site $site, int $position): array
    {
        return [
            'id' => $c->id,
            'key' => $this->key($c->name),
            'position' => $position,
            'fields' => $this->nodesToFields(Component::buildNodeTree($c->nodes), $site),
        ];
    }

    private function collection($collection, int $position): array
    {
        $schema = collect($collection->fields ?? [])
            ->map(fn ($f) => $f['name'] ?? $f['key'] ?? null)
            ->filter()->values()->all();

        // Fall back to the union of item data keys when no schema is declared.
        if (empty($schema)) {
            $schema = $collection->items
                ->flatMap(fn ($i) => array_keys($i->data ?? []))
                ->unique()->values()->all();
        }

        return [
            'id' => $collection->id,
            'key' => $collection->slug ?: $this->key($collection->name),
            'position' => $position,
            'schema' => $schema,
            'items' => $collection->items
                ->where('status', 'published')
                ->map(fn ($i) => ['id' => $i->id] + ($i->data ?? []))
                ->values()->all(),
        ];
    }

    private function post(Post $p, Site $site): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'excerpt' => $p->excerpt,
            'body' => (string) $p->body,
            'publishedAt' => $p->published_at?->toIso8601String(),
            'featuredImage' => $this->image($site, $p->cover_image),
        ];
    }

    private function form(Form $f, int $position): array
    {
        return [
            'id' => $f->id,
            'key' => $this->key($f->name),
            'position' => $position,
            // A form's endpoint can be overridden in the review editor (external
            // action); otherwise reuse the EXISTING submission endpoint
            // (throttled + honeypot + origin-checked) — no new write path.
            'submitUrl' => ($f->delivery['external_action'] ?? null) ?: $f->toApiArray()['submit_url'],
            'fields' => array_map(fn ($field) => [
                'name' => $field['key'] ?? $field['name'] ?? $this->key($field['label'] ?? ''),
                'type' => $field['type'] ?? 'text',
                'label' => $field['label'] ?? Str::headline($field['key'] ?? $field['name'] ?? ''),
                'required' => (bool) ($field['required'] ?? false),
                'options' => $field['options'] ?? null,
            ], $f->fields ?? []),
            'successMessage' => $this->formSuccessMessage($f),
        ];
    }

    /**
     * Flatten a node tree into a flat `fields` object keyed by camelCased label.
     * Leaf scalars → value; image → {src, alt}; a node WITH children → nested
     * object (e.g. cta → {label, href}).
     */
    private function nodesToFields(array $nodeTree, Site $site): array
    {
        $fields = [];
        foreach ($nodeTree as $node) {
            $key = $this->key($node['label'] ?? '');
            if ($key === '') {
                continue;
            }

            if (! empty($node['children'])) {
                $fields[$key] = $this->nodesToFields($node['children'], $site);

                continue;
            }

            $fields[$key] = $this->nodeValue($node, $site);
        }

        return $fields;
    }

    private function nodeValue(array $node, Site $site): mixed
    {
        return match ($node['type'] ?? 'text') {
            'image' => $this->image($site, $node['value'] ?? ''),
            'boolean' => filter_var($node['value'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($node['value'] ?? null) ? 0 + $node['value'] : ($node['value'] ?? null),
            // A collection-typed node embeds the linked collection's published
            // items as an ARRAY field, so a component can carry a list (the
            // client renders it with a [data-olx-item] template inside the
            // field element, exactly like a standalone collection).
            'collection' => $this->linkedItems($site, (string) ($node['value'] ?? '')),
            default => $node['value'] ?? '',
        };
    }

    /** @return array<int,array<string,mixed>> published items of a linked collection (site-scoped). */
    private function linkedItems(Site $site, string $collectionId): array
    {
        if ($collectionId === '') {
            return [];
        }
        $collection = Collection::where('site_id', $site->id)->find($collectionId);

        return $collection
            ? $collection->items()->where('status', 'published')->orderBy('id')->get()
                ->map(fn ($i) => ['id' => $i->id] + ($i->data ?? []))->values()->all()
            : [];
    }

    /** Image field shape {src, alt} with @media/… refs resolved to real URLs. */
    private function image(Site $site, ?string $value): ?array
    {
        $src = $this->resolveImage($site, (string) $value);

        return $src === '' ? null : ['src' => $src, 'alt' => ''];
    }

    private function resolveImage(Site $site, string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, '@media/')) {
            $src = Media::resolveRef($site->id, $value);

            // Client sites hydrate cross-origin — a stored /storage/… path must
            // be absolutised to the CMS origin or it 404s on their domain.
            return $src !== '' && str_starts_with($src, '/') ? url($src) : $src;
        }

        return $value;
    }

    private function formSuccessMessage(Form $f): string
    {
        $delivery = $f->delivery ?? [];

        return $delivery['success_message']
            ?? ($f->email_template['success_message'] ?? null)
            ?? 'Thanks — we’ll be in touch.';
    }

    /** Forms surfaced on the page. v1: the site's active forms (page-level form
     *  association is added in Stage 3); documented in docs/site-connect.md. */
    private function pageForms(Site $site)
    {
        return $site->forms()->where('is_active', true)->get();
    }

    /** Posts surfaced on the page. v1: the site's published posts. */
    private function sitePosts(Site $site)
    {
        return Post::where('site_id', $site->id)
            ->where('status', 'published')
            ->latest('published_at')
            ->get();
    }

    /** A page's slug for the JSON filename: home ("/") → "index". */
    private function pageSlug(Page $page): string
    {
        $url = trim((string) $page->url, '/');

        return $url === '' ? 'index' : Str::slug(str_replace('/', '-', $url));
    }

    private function key(string $label): string
    {
        return Str::camel(Str::slug($label));
    }
}
