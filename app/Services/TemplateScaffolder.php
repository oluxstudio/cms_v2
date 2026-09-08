<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Templates\TemplatePackage;

/**
 * Scaffolds a template package's pages into REAL site content: each page def
 * (resources/templates/{key}/pages/*.json) becomes a Page, each block a
 * Component with its Node tree, attached in order. Existing pages (by url)
 * are left untouched, so re-applying is safe.
 */
class TemplateScaffolder
{
    /** @return array{pages: int, components: int} */
    public function apply(Site $site, TemplatePackage $package): array
    {
        return $this->applyPages($site, $package->pages());
    }

    /**
     * Scaffold pages from a pages array (package OR contract shape — they are
     * identical: url/name/attributes/blocks[nodes]). Pages whose URL already
     * exists are skipped, so re-applying is safe and content is preserved.
     *
     * @return array{pages:int,components:int}
     */
    public function applyPages(Site $site, array $pageDefs): array
    {
        $author = $site->user?->name ?? 'Olux';
        // The business name from signup when known, else a title-cased slug.
        $siteTitle = (string) ($site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $site->name)));
        $pages = 0;
        $components = 0;

        foreach ($pageDefs as $def) {
            $url = $def['url'] ?? '/';

            // Reuse an existing page (template update) — never recreate it or
            // touch its attributes, but DO walk its blocks below so updated
            // manifests can top up new components/fields on existing pages.
            /** @var Page $page */
            $page = $site->pages()->where('url', $url)->first();
            if (! $page) {
                $page = $site->pages()->create([
                    'name' => $def['name'] ?? 'Page',
                    'url' => $url,
                    'keywords' => $def['keywords'] ?? '',
                    'is_published' => true,
                ]);
                foreach (($def['attributes'] ?? []) as $key => $value) {
                    $page->setAttr($key, str_replace('{site_name}', $siteTitle, (string) $value));
                }
                $pages++;
            }

            foreach (($def['blocks'] ?? []) as $order => $block) {
                $name = $block['name'] ?? 'Block';

                // ONE component per block name per site — a block used on
                // several pages shares a single record (like the original
                // app's one-record-per-key model), so editing it anywhere
                // updates it everywhere and key-based editing is unambiguous.
                // Legacy sites may hold several copies of a name (old per-page
                // installs): ALWAYS prefer the copy already attached to THIS
                // page, so refreshes top up the component the page renders and
                // never attach a sibling duplicate next to it.
                $component = $page->components()->where('components.name', $name)->first()
                    ?? $site->contentComponents()->where('name', $name)->first();
                if ($component) {
                    // Template updated since this site's install: top up any
                    // manifest fields the component doesn't have yet (never
                    // touching values the owner may have edited).
                    $have = $component->nodes()->pluck('label')->flip();
                    $max = (int) $component->nodes()->max('order');
                    foreach (($block['nodes'] ?? []) as $i => $node) {
                        $label = $node['label'] ?? null;
                        if (! $label || isset($have[$label])) {
                            continue;
                        }
                        $component->nodes()->create([
                            'label' => $label,
                            'type' => in_array($node['type'] ?? 'text', Node::TYPES, true) ? $node['type'] : 'text',
                            'value' => str_replace('{site_name}', $siteTitle, (string) ($node['value'] ?? '')),
                            'parent' => (string) ($node['parent'] ?? '0'),
                            'order' => ++$max,
                        ]);
                    }
                }
                if (! $component) {
                    $component = Component::create([
                        'site_id' => $site->id,
                        'name' => $name,
                        'author' => $author,
                        'source' => 'app',
                    ]);
                    foreach (($block['nodes'] ?? []) as $i => $node) {
                        $component->nodes()->create([
                            'label' => $node['label'] ?? 'Field',
                            'type' => in_array($node['type'] ?? 'text', Node::TYPES, true) ? $node['type'] : 'text',
                            'value' => str_replace('{site_name}', $siteTitle, (string) ($node['value'] ?? '')),
                            'parent' => (string) ($node['parent'] ?? '0'),
                            'order' => $node['order'] ?? $i,
                        ]);
                    }
                    $components++;
                }
                $page->components()->syncWithoutDetaching([$component->id => ['order' => $order]]);
            }
        }

        return ['pages' => $pages, 'components' => $components];
    }

    /**
     * Scaffold the template's CHROME — the layout blocks (header/nav before
     * the content slot, footer after) that wrap every page. Created ONCE as
     * site-level components (attached to no page) and tagged chrome:header /
     * chrome:footer so the content API can wrap each page's wireframe with
     * them. Idempotent by component name.
     */
    public function applyChrome(Site $site, array $layoutBlocks): int
    {
        $author = $site->user?->name ?? 'Olux';
        $siteTitle = (string) ($site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $site->name)));
        $zone = 'chrome:header';
        $created = 0;

        // The ACTIVE template owns the chrome: clear stale chrome tags first
        // (a previous template's header/footer must not render on this one);
        // switching back re-tags the still-existing components below.
        foreach ($site->contentComponents()->get() as $c) {
            $tags = collect($c->tags ?? []);
            if ($tags->contains(fn ($t) => str_starts_with((string) $t, 'chrome:'))) {
                $c->update(['tags' => $tags->reject(fn ($t) => str_starts_with((string) $t, 'chrome:'))->values()->all()]);
            }
        }

        foreach ($layoutBlocks as $block) {
            if (($block['type'] ?? '') === 'content') {
                $zone = 'chrome:footer';

                continue;
            }
            $name = $block['name'] ?? null;
            if (! $name) {
                continue;
            }
            // Already scaffolded (this or an earlier install, even untagged —
            // e.g. sites created before chrome existed): just (re)tag it.
            if ($existing = $site->contentComponents()->where('name', $name)->first()) {
                $tags = collect($existing->tags ?? [])->reject(fn ($t) => str_starts_with((string) $t, 'chrome:'))->push($zone);
                $existing->update(['tags' => $tags->values()->all()]);

                continue;
            }
            $component = Component::create([
                'site_id' => $site->id,
                'name' => $name,
                'author' => $author,
                'source' => 'app',
                'tags' => [$zone],
            ]);
            foreach (($block['nodes'] ?? []) as $i => $node) {
                $component->nodes()->create([
                    'label' => $node['label'] ?? 'Field',
                    'type' => in_array($node['type'] ?? 'text', Node::TYPES, true) ? $node['type'] : 'text',
                    'value' => str_replace('{site_name}', $siteTitle, (string) ($node['value'] ?? '')),
                    'parent' => (string) ($node['parent'] ?? '0'),
                    'order' => $node['order'] ?? $i,
                ]);
            }
            $created++;
        }

        return $created;
    }
}
