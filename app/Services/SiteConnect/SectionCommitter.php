<?php

namespace App\Services\SiteConnect;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Form;
use App\Models\IngestedSection;
use App\Models\Node;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Materialises a classified IngestedSection into the real content models — the
 * bridge from staging to the live CMS. Components/collections are composed onto
 * the target page (ordered by the section's position); posts and forms are
 * created site-level (matching the page.json generator's v1 behaviour).
 *
 * Idempotent per section: re-committing updates the same materialised row via
 * the section's committed_ref_id.
 */
class SectionCommitter
{
    public function __construct(private AssetImporter $assets) {}

    public function commit(IngestedSection $section, Page $page): void
    {
        $site = $page->site;

        $ref = match ($section->classification) {
            IngestedSection::COLLECTION => $this->collection($section, $site, $page),
            IngestedSection::POST => $this->post($section, $site),
            IngestedSection::FORM => $this->form($section, $site),
            default => $this->component($section, $site, $page),
        };

        $section->forceFill([
            'committed_ref_type' => $section->classification,
            'committed_ref_id' => $ref->id,
        ])->save();
    }

    private function component(IngestedSection $section, Site $site, Page $page): Component
    {
        $fields = $section->fields ?? [];
        $component = Component::create([
            'site_id' => $site->id,
            'name' => $this->name($fields['heading'] ?? 'Section', $section),
            'author' => 'site-connect',
            'source' => 'imported',
        ]);

        $order = 0;
        foreach (['heading' => 'text', 'subheading' => 'text', 'body' => 'text', 'image' => 'image'] as $key => $type) {
            if (! empty($fields[$key])) {
                $value = (string) $fields[$key];
                if ($type === 'image') {
                    // Pull the client-site image into the media library so the
                    // component serves a CMS-hosted copy (@media/… ref).
                    $value = $this->assets->importRef($site, $value, $this->assetHosts($section, $site));
                }
                Node::create([
                    'component_id' => $component->id, 'parent' => '0',
                    'label' => Str::headline($key), 'type' => $type,
                    'value' => $value, 'order' => $order++,
                ]);
            }
        }
        if (! empty($fields['cta']['href'] ?? null) || ! empty($fields['cta']['label'] ?? null)) {
            $cta = Node::create(['component_id' => $component->id, 'parent' => '0', 'label' => 'Cta', 'type' => 'text', 'value' => '', 'order' => $order++]);
            Node::create(['component_id' => $component->id, 'parent' => $cta->id, 'label' => 'Label', 'type' => 'text', 'value' => (string) ($fields['cta']['label'] ?? ''), 'order' => 0]);
            Node::create(['component_id' => $component->id, 'parent' => $cta->id, 'label' => 'Href', 'type' => 'url', 'value' => (string) ($fields['cta']['href'] ?? ''), 'order' => 1]);
        }

        $page->components()->syncWithoutDetaching([$component->id => ['order' => $section->position]]);

        return $component;
    }

    private function collection(IngestedSection $section, Site $site, Page $page): Collection
    {
        $fields = $section->fields ?? [];
        // Field schema uses the CMS-canonical `key` (HasFieldSchema), plus name for page.json.
        $schema = array_map(fn ($name) => ['key' => $name, 'name' => $name, 'label' => Str::headline($name), 'type' => 'text'], $fields['schema'] ?? []);

        $name = $this->name('Collection', $section);
        $collection = Collection::create([
            'site_id' => $site->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), // unique, queryable by key
            'type' => 'grid',
            'fields' => $schema,
            'is_public' => true,
        ]);

        foreach ($fields['items'] ?? [] as $item) {
            CollectionItem::create([
                'collection_id' => $collection->id,
                'site_id' => $site->id,
                'status' => 'published',
                'data' => $item,
            ]);
        }

        $page->collections()->syncWithoutDetaching([$collection->id => ['order' => $section->position]]);

        return $collection;
    }

    private function post(IngestedSection $section, Site $site): Post
    {
        $fields = $section->fields ?? [];
        $title = $fields['title'] ?: 'Imported post';

        return Post::create([
            'site_id' => $site->id,
            'user_id' => $site->user_id,
            'title' => $title,
            'slug' => Post::uniqueSlug($site->id, $title),
            'excerpt' => $fields['excerpt'] ?? null,
            'body' => $fields['body'] ?? '',
            'cover_image' => empty($fields['image']) ? null
                : $this->assets->importRef($site, (string) $fields['image'], $this->assetHosts($section, $site)),
            'status' => 'published',
            'published_at' => $this->date($fields['publishedAt'] ?? null),
        ]);
    }

    private function form(IngestedSection $section, Site $site): Form
    {
        $fields = $section->fields ?? [];
        $intent = $fields['intent'] ?? 'contact';

        // forms.name is unique per site — suffix until free.
        $base = Str::slug($intent) ?: 'form';
        $name = $base;
        while (Form::where('site_id', $site->id)->where('name', $name)->exists()) {
            $name = $base.'-'.Str::lower(Str::random(5));
        }

        // Normalise to the CMS field schema: `key` is the machine identifier the
        // submission validator + response store read (HasFieldSchema).
        $formFields = array_map(function ($f) {
            $key = $f['key'] ?? $f['name'] ?? Str::slug($f['label'] ?? 'field', '_');

            return [
                'key' => $key,
                'name' => $key,
                'label' => $f['label'] ?? Str::headline($key),
                'type' => $f['type'] ?? 'text',
                'required' => (bool) ($f['required'] ?? false),
                'options' => $f['options'] ?? null,
            ];
        }, $fields['fields'] ?? []);

        return Form::create([
            'site_id' => $site->id,
            'name' => $name,
            'title' => Str::headline($intent),
            'fields' => $formFields,
            'is_active' => true,
        ]);
    }

    /** Hosts asset imports may fetch from: the ingested page's + the site's domain. */
    private function assetHosts(IngestedSection $section, Site $site): array
    {
        return array_values(array_filter([
            parse_url((string) $section->ingestion?->source_url, PHP_URL_HOST),
            $site->domain,
        ]));
    }

    private function name(string $base, IngestedSection $section): string
    {
        $base = trim($base) !== '' ? $base : 'Section';

        return Str::limit(Str::headline($base), 60, '');
    }

    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return now()->toDateTimeString();
        }
    }
}
