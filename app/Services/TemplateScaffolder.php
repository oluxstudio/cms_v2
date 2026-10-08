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
    /** @param  callable(int $done, string $pageName): void|null  $onPage  progress after each page */
    public function applyPages(Site $site, array $pageDefs, ?callable $onPage = null, bool $topUp = true): array
    {
        $author = $site->user?->name ?? 'Olux';
        // The business name from signup when known, else a title-cased slug.
        $siteTitle = (string) ($site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $site->name)));
        $pages = 0;
        $components = 0;

        $progressDone = 0;
        $perPage = self::perPageBlocks($pageDefs);
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
                $component = $page->components()->where('components.name', $name)->first();
                if (isset($perPage[$name])) {
                    // Per-page content (the template gives this block different text on
                    // different pages — a page hero): each page keeps its OWN copy.
                    if ($component && $topUp && $component->pages()->count() > 1
                        && ! $this->ownsSharedCopy($component, $url, $perPage[$name])) {
                        $page->components()->detach($component->id);
                        $component = null;
                    }
                } else {
                    $component ??= $site->contentComponents()->where('name', $name)->first();
                }
                if ($component && $topUp && ! empty($block['nodes'])) {
                    // A redesigned block no longer has some of its auto-named
                    // fields ("Text B", "Headline C"…): drop those nobody ever
                    // edited, so the editor shows only fields the block uses.
                    $manifestLabels = array_flip(array_filter(array_map(fn ($n) => $n['label'] ?? null, $block['nodes'])));
                    foreach ($component->nodes()->get() as $stale) {
                        if (! isset($manifestLabels[$stale->label]) && $stale->type !== 'collection'
                            && preg_match(self::AUTO_LABEL, (string) $stale->label) && self::neverEdited($stale)) {
                            $stale->delete();
                        }
                    }
                }
                if ($component && $topUp) {
                    // Template updated since this site's install: top up any
                    // manifest fields the component doesn't have yet (never
                    // touching values the owner may have edited).
                    $existing = $component->nodes()->get()->keyBy('label');
                    $have = $existing->map(fn () => true);
                    $max = (int) $component->nodes()->max('order');
                    // Repeatable rows ("Slide 3 Image"): the owner adds and removes
                    // them, so never re-create a row past the ones the site has.
                    // (Item nodes are tagged "item:{key}" by the extractor.)
                    $rowsHad = [];
                    foreach (($block['nodes'] ?? []) as $node) {
                        if (str_starts_with((string) ($node['description'] ?? $node['kind'] ?? ''), 'item:')
                            && preg_match('/^(.+?) \d+(?: |$)/', (string) ($node['label'] ?? ''), $m)) {
                            $rowsHad[$m[1]] ??= $existing->keys()->reduce(fn (int $c, $l) => preg_match('/^'.preg_quote($m[1], '/').' (\d+)(?: |$)/', (string) $l, $h) ? max($c, (int) $h[1]) : $c, 0);
                        }
                    }
                    foreach (($block['nodes'] ?? []) as $i => $node) {
                        $label = $node['label'] ?? null;
                        if ($label && preg_match('/^(.+?) (\d+)(?: |$)/', $label, $m) && ($rowsHad[$m[1]] ?? 0) > 0 && (int) $m[2] > $rowsHad[$m[1]]) {
                            continue; // a row the owner removed
                        }
                        if ($label && isset($have[$label])) {
                            // The template's text for this field changed (fields are named by
                            // position, so a redesigned block shifts them): a value the owner
                            // never edited follows the template; an edited one is kept.
                            $current = $existing[$label];
                            $value = str_replace('{site_name}', $siteTitle, (string) ($node['value'] ?? ''));
                            if ((string) $current->value !== $value && self::neverEdited($current)) {
                                \Illuminate\Support\Facades\DB::table($current->getTable())->where('id', $current->id)->update(['value' => $value]);
                            }

                            continue;
                        }
                        if (! $label) {
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
            if ($onPage) {
                $onPage(++$progressDone, (string) ($def['name'] ?? 'Page'));
            }
        }

        return ['pages' => $pages, 'components' => $components];
    }

    /** Field labels the extractor makes up by position (never typed by an owner). */
    private const AUTO_LABEL = '/^(Text|Headline|Subheadline|Caption|Image|CTA|Email|Phone)( [A-Z])?( Label| Link)?$/';

    /** A field still as the template seeded it — never saved since it was created. */
    private static function neverEdited(Node $node): bool
    {
        return $node->created_at && $node->updated_at && $node->updated_at->lte($node->created_at->copy()->addSeconds(2));
    }

    /**
     * Blocks whose content belongs to each page — flagged `perPage` by the
     * template (`// @olux-per-page`, e.g. a page hero reading its copy by
     * route), or given different text on different pages. Those get one
     * component PER PAGE instead of one shared record. Maps name => [url =>
     * what identifies that page's copy: its template text, page name and url].
     *
     * @return array<string, array<string, string>>
     */
    public static function perPageBlocks(array $pageDefs): array
    {
        $texts = [];
        $flagged = [];
        $who = [];
        foreach ($pageDefs as $def) {
            $url = $def['url'] ?? '/';
            foreach (($def['blocks'] ?? []) as $block) {
                $name = $block['name'] ?? 'Block';
                $texts[$name][$url] = collect($block['nodes'] ?? [])->map(fn ($n) => ($n['label'] ?? '').'='.trim((string) ($n['value'] ?? '')))->implode("\n");
                $who[$name][$url] = trim(($def['name'] ?? '').' '.str_replace(['/', '-'], ' ', $url));
                if (! empty($block['perPage'])) {
                    $flagged[$name] = true;
                }
            }
        }
        $out = [];
        foreach ($texts as $name => $byUrl) {
            if (isset($flagged[$name]) || count(array_unique($byUrl)) > 1) {
                $out[$name] = array_map(fn ($u) => $byUrl[$u]."\n".$who[$name][$u], array_combine(array_keys($byUrl), array_keys($byUrl)));
            }
        }

        return $out;
    }

    /**
     * A per-page block that several pages still share (older installs): the page
     * whose template text / name is closest to the stored values keeps it — the
     * others get their own copies — so owner edits stay on the page they were
     * made for (an edited "Bible study" hero stays on /bible-study).
     */
    private function ownsSharedCopy(Component $component, string $url, array $texts): bool
    {
        $stored = $component->nodes()->orderBy('order')->get()->map(fn ($n) => $n->label.'='.trim((string) $n->value))->implode("\n");
        $score = function (string $text) use ($stored): float {
            similar_text(mb_strtolower($stored), mb_strtolower($text), $pct);

            return $pct;
        };
        $owner = $component->pages()->pluck('url')
            ->filter(fn ($u) => isset($texts[$u]))
            ->sortByDesc(fn ($u) => $score($texts[$u]))
            ->first();

        return $owner === null || $owner === $url;
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
