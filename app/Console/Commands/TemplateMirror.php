<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Models\Site;
use App\Templates\TemplatePackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Make applied sites EXACTLY mirror the template — the AUTHORITATIVE
 * counterpart to the additive refresh. Where a refresh only tops up (never
 * deletes, never overwrites), a mirror converges the site onto the project:
 *
 *   · pages the template no longer ships are DELETED,
 *   · every page's blocks match the authored set and order,
 *   · every block's nodes match the def — added, retyped, reordered, and
 *     labels the template dropped are REMOVED (owner custom fields too),
 *   · node values, page attributes and chrome follow the template,
 *   · template collections: field schema AND items reseeded,
 *   · template forms: title + fields replaced (responses kept),
 *   · template products & booking services: updated in place.
 *
 * Anything NOT template-defined (owner-created pages, collections, forms,
 * products) is untouched. Meant for dev sites tracking a template under
 * active authoring — it intentionally overwrites owner edits to template
 * things. Use the plain sync/refresh when owner work must be preserved.
 */
class TemplateMirror extends Command
{
    protected $signature = 'template:mirror {key : Template key}
        {--site= : Only this site (default: every site applied to the template)}
        {--keep-content : Structure/schema only — leave edited values, items and prices alone}';

    protected $description = 'Strict-sync applied sites to the template: the project is the source of truth';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $dir = resource_path("templates/{$key}");
        if (! is_dir($dir)) {
            $this->error("No published template package at resources/templates/{$key}.");

            return self::FAILURE;
        }
        $package = new TemplatePackage($dir);
        $pageDefs = collect($package->pages());
        if ($pageDefs->isEmpty()) {
            $this->error('Template has no page definitions to mirror.');

            return self::FAILURE;
        }
        $manifest = (array) json_decode((string) @file_get_contents("$dir/template.json"), true);
        $layouts = method_exists($package, 'layouts') ? (array) $package->layouts() : [];
        $keepContent = (bool) $this->option('keep-content');

        $sites = Site::query()
            ->when($this->option('site'), fn ($q, $s) => $q->where('name', $s))
            ->where('template', $key)->get();
        if ($sites->isEmpty()) {
            $this->warn('No applied sites found.');

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->line("→ {$site->name}");
            $siteTitle = (string) ($site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $site->name)));
            $sub = fn (string $v) => str_replace('{site_name}', $siteTitle, $v);
            $stats = ['blocks-' => 0, 'nodes+' => 0, 'nodes-' => 0, 'values' => 0, 'pages-' => 0];

            // ── 1. Pages: blocks, nodes, attributes ──
            foreach ($pageDefs as $def) {
                $page = $site->pages()->where('url', $def['url'] ?? '/')->first();
                if (! $page) {
                    continue; // the refresh (template:deploy) creates missing pages
                }
                $blocks = collect($def['blocks'] ?? []);
                $wanted = $blocks->pluck('name')->filter()->values();

                foreach ($page->components()->get() as $c) {
                    if ($wanted->contains($c->name)) {
                        continue;
                    }
                    $page->components()->detach($c->id);
                    if (! $c->pages()->exists()) {
                        $c->delete();
                    }
                    $stats['blocks-']++;
                    $this->line("   − {$page->url}: removed '{$c->name}'");
                }

                $current = $page->components()->get()->keyBy('name');
                foreach ($wanted as $order => $name) {
                    if ($c = $current->get($name)) {
                        $page->components()->updateExistingPivot($c->id, ['order' => $order]);
                    }
                }

                foreach ($blocks as $block) {
                    if ($c = $current->get($block['name'] ?? '')) {
                        $this->mirrorNodes($c, (array) ($block['nodes'] ?? []), $sub, $keepContent, $stats);
                    }
                }

                foreach ((array) ($def['attributes'] ?? []) as $k => $v) {
                    $page->setAttr($k, $sub((string) $v));
                }
            }

            // ── 1b. Chrome (header/footer) follows the layout defs ──
            foreach ($layouts as $layout) {
                foreach ((array) ($layout['blocks'] ?? []) as $block) {
                    if (($block['type'] ?? '') === 'content' || empty($block['name'])) {
                        continue;
                    }
                    if ($c = $site->contentComponents()->where('name', $block['name'])->first()) {
                        $this->mirrorNodes($c, (array) ($block['nodes'] ?? []), $sub, $keepContent, $stats);
                    }
                }
            }

            // ── 2. Pages the template no longer ships are DELETED ──
            $templateUrls = $pageDefs->pluck('url')->all();
            foreach ($site->pages()->whereNotIn('url', $templateUrls)->get() as $extra) {
                foreach ($extra->components()->get() as $c) {
                    $extra->components()->detach($c->id);
                    if (! $c->pages()->exists()) {
                        $c->delete();
                    }
                }
                $this->line("   − page {$extra->url} deleted (not in the template)");
                $extra->delete();
                $stats['pages-']++;
            }

            // ── 3. Template collections: schema + items ──
            foreach ((array) ($manifest['collections'] ?? []) as $cdef) {
                if (empty($cdef['name'])) {
                    continue;
                }
                $col = $site->collections()->where('name', $cdef['name'])->first();
                if (! $col) {
                    continue;
                }
                $wantedFields = collect((array) ($cdef['fields'] ?? []))->map(fn ($f) => [
                    'key' => $f['key'] ?? $f['name'] ?? '',
                    'name' => $f['key'] ?? $f['name'] ?? '',
                    'label' => $f['label'] ?? ucfirst((string) ($f['key'] ?? '')),
                    'type' => $f['type'] ?? 'text',
                ] + array_intersect_key($f, array_flip(['fields', 'options'])))->filter(fn ($f) => $f['key'] !== '')->values()->all();
                $norm = fn ($fs) => collect($fs)->map(fn ($f) => array_intersect_key(
                    (array) $f, array_flip(['key', 'type', 'fields', 'options'])))->values()->all();
                if ($wantedFields !== [] && $norm($col->fields ?? []) != $norm($wantedFields)) {
                    $col->update(['fields' => $wantedFields]);
                    $this->line("   ↺ collection '{$col->name}' field schema updated");
                }
                if ($keepContent || empty($cdef['items'])) {
                    continue;
                }
                $currentItems = $col->items()->orderBy('id')->get()->map(fn ($i) => $i->data)->values()->all();
                $wantedItems = array_values($cdef['items']);
                if ($currentItems == $wantedItems) {
                    continue;
                }
                $col->items()->withTrashed()->forceDelete(); // reseed replaces, never trash
                foreach ($wantedItems as $data) {
                    $col->items()->create(['site_id' => $site->id, 'data' => $data, 'status' => 'published']);
                }
                $this->line("   ↺ collection '{$col->name}' reseeded (".count($wantedItems).' items)');
            }

            // ── 4. Template forms: title + fields replaced, responses kept ──
            foreach ((array) ($manifest['forms'] ?? []) as $fdef) {
                if (empty($fdef['name'])) {
                    continue;
                }
                $form = $site->forms()->firstOrCreate(['name' => $fdef['name']], [
                    'title' => (string) ($fdef['title'] ?? ucfirst($fdef['name'])),
                    'fields' => (array) ($fdef['fields'] ?? []),
                    'is_active' => true,
                ]);
                $want = ['title' => (string) ($fdef['title'] ?? $form->title), 'fields' => (array) ($fdef['fields'] ?? [])];
                if ($form->title !== $want['title'] || $form->fields != $want['fields']) {
                    $form->update($want);
                    $this->line("   ↺ form '{$form->name}' definition updated");
                }
            }

            // ── 5. Template products: updated in place by slug ──
            if (! $keepContent) {
                foreach ((array) ($manifest['products'] ?? []) as $i => $p) {
                    if (empty($p['name'])) {
                        continue;
                    }
                    $slug = $p['slug'] ?? Str::slug($p['name']);
                    $attrs = array_filter([
                        'name' => $p['name'] ?? null,
                        'description' => $p['description'] ?? null,
                        'category' => $p['category'] ?? null,
                        'tags' => $p['tags'] ?? null,
                        'price_cents' => $p['price_cents'] ?? null,
                        'currency' => $p['currency'] ?? null,
                        'image' => $p['image'] ?? null,
                        'inventory' => $p['inventory'] ?? null,
                    ], fn ($v) => $v !== null) + ['sort' => $i];
                    $product = $site->products()->where('slug', $slug)->first();
                    if (! $product) {
                        $site->products()->create($attrs + ['slug' => $slug, 'is_active' => true]);
                        $this->line("   + product '{$p['name']}' created");
                    } elseif (collect($attrs)->contains(fn ($v, $k) => $product->{$k} != $v)) {
                        $product->update($attrs);
                        $this->line("   ↺ product '{$p['name']}' updated");
                    }
                }
            }

            // ── 6. Booking: services updated by name, availability from def ──
            $booking = (array) ($manifest['booking'] ?? []);
            foreach ((array) ($booking['services'] ?? []) as $svc) {
                if (empty($svc['name'])) {
                    continue;
                }
                $attrs = array_filter([
                    'kind' => $svc['kind'] ?? null,
                    'duration_min' => $svc['duration_min'] ?? null,
                    'price_cents' => $svc['price_cents'] ?? null,
                    'description' => $svc['description'] ?? null,
                ], fn ($v) => $v !== null);
                $service = $site->services()->where('name', $svc['name'])->first();
                if (! $service) {
                    $site->services()->create($attrs + ['name' => $svc['name'], 'is_active' => true, 'slug' => '']);
                    $this->line("   + service '{$svc['name']}' created");
                } elseif ($attrs !== [] && collect($attrs)->contains(fn ($v, $k) => $service->{$k} != $v)) {
                    $service->update($attrs);
                    $this->line("   ↺ service '{$svc['name']}' updated");
                }
            }
            $availability = array_intersect_key(
                (array) ($booking['availability'] ?? $booking['settings'] ?? []),
                array_flip(['days', 'open_time', 'close_time', 'slot_minutes', 'lead_hours', 'horizon_days', 'day_hours']),
            );
            if ($availability !== [] && method_exists($site, 'saveFeatureConfig')) {
                $stored = (array) ($site->siteFeatures()->where('key', 'bookings')->value('config') ?? []);
                $site->saveFeatureConfig('bookings', array_merge($stored, $availability)); // def wins
            }

            foreach ($site->pages()->pluck('url') as $url) {
                Cache::forget("page_render:{$site->id}:{$url}");
            }
            $this->info("   ✓ {$stats['blocks-']} block(s) removed · {$stats['pages-']} page(s) pruned · "
                ."{$stats['nodes+']} node(s) added · {$stats['nodes-']} node(s) removed · {$stats['values']} value(s) restored");
        }

        return self::SUCCESS;
    }

    /**
     * Converge one component's nodes onto the def: add missing, retype,
     * reorder, restore values, and REMOVE labels the def no longer has.
     *
     * @param  callable(string):string  $sub  {site_name} substitution
     */
    private function mirrorNodes($component, array $nodeDefs, callable $sub, bool $keepContent, array &$stats): void
    {
        $defs = collect($nodeDefs)->filter(fn ($n) => ! empty($n['label']))->values();
        if ($defs->isEmpty()) {
            return;
        }
        $byLabel = $component->nodes()->get()->groupBy('label');

        foreach ($defs as $order => $def) {
            $label = $def['label'];
            $type = in_array($def['type'] ?? 'text', Node::TYPES, true) ? $def['type'] : 'text';
            $value = $sub((string) ($def['value'] ?? ''));
            $node = $byLabel->get($label)?->first();
            if (! $node) {
                $component->nodes()->create([
                    'label' => $label, 'type' => $type, 'value' => $value,
                    'parent' => (string) ($def['parent'] ?? '0'), 'order' => $order,
                ]);
                $stats['nodes+']++;

                continue;
            }
            $changes = [];
            if ($node->type !== $type) {
                $changes['type'] = $type;
            }
            if ((int) $node->order !== $order) {
                $changes['order'] = $order;
            }
            if (! $keepContent && (string) $node->value !== $value) {
                $changes['value'] = $value;
                $stats['values']++;
            }
            if ($changes !== []) {
                $node->update($changes);
            }
        }

        // Labels the template no longer defines — removed (mirror semantics).
        $wantedLabels = $defs->pluck('label')->flip();
        foreach ($byLabel as $label => $nodes) {
            if (! isset($wantedLabels[$label])) {
                foreach ($nodes as $n) {
                    $n->delete();
                    $stats['nodes-']++;
                }
            }
        }
    }
}
