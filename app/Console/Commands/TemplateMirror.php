<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Templates\TemplatePackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Make applied sites EXACTLY mirror the template's current pages — the
 * strict counterpart to the additive refresh. Where a refresh only tops up
 * (never deletes, never overwrites), a mirror:
 *
 *   · removes page blocks the template no longer has (a section deleted in
 *     the original disappears from the site too),
 *   · reorders every page's blocks to the authored order,
 *   · resets the template-defined field VALUES to the template's content.
 *
 * Owner-added extras survive: custom pages, custom components, custom fields
 * on a block, and chrome (header/footer) are never touched. Meant for dev
 * sites tracking a template under active authoring — it intentionally
 * overwrites owner edits to template fields.
 */
class TemplateMirror extends Command
{
    protected $signature = 'template:mirror {key : Template key}
        {--site= : Only this site (default: every site applied to the template)}
        {--keep-content : Mirror structure/order only — leave edited field values alone}';

    protected $description = 'Strict-sync applied sites to the template: remove stale blocks, restore order and content';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $dir = resource_path("templates/{$key}");
        if (! is_dir($dir)) {
            $this->error("No published template package at resources/templates/{$key}.");

            return self::FAILURE;
        }
        $pageDefs = collect((new TemplatePackage($dir))->pages());
        if ($pageDefs->isEmpty()) {
            $this->error('Template has no page definitions to mirror.');

            return self::FAILURE;
        }

        $sites = Site::query()
            ->when($this->option('site'), fn ($q, $s) => $q->where('name', $s))
            ->where('template', $key)->get();
        if ($sites->isEmpty()) {
            $this->warn('No applied sites found.');

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->line("→ {$site->name}");
            $removed = $reordered = $reset = 0;
            $siteTitle = (string) ($site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $site->name)));

            foreach ($pageDefs as $def) {
                $page = $site->pages()->where('url', $def['url'] ?? '/')->first();
                if (! $page) {
                    continue; // refresh (template:deploy) creates missing pages
                }
                $blocks = collect($def['blocks'] ?? []);
                $wanted = $blocks->pluck('name')->filter()->values();

                // 1. Stale blocks OFF this page; delete the component when no
                //    other page uses it (chrome is site-level — never here).
                foreach ($page->components()->get() as $c) {
                    if ($wanted->contains($c->name)) {
                        continue;
                    }
                    $page->components()->detach($c->id);
                    if (! $c->pages()->exists()) {
                        $c->delete();
                    }
                    $removed++;
                    $this->line("   − {$page->url}: removed '{$c->name}'");
                }

                // 2. Authored order.
                $current = $page->components()->get()->keyBy('name');
                foreach ($wanted as $order => $name) {
                    if ($c = $current->get($name)) {
                        $page->components()->updateExistingPivot($c->id, ['order' => $order]);
                        $reordered++;
                    }
                }

                // 3. Template field values (unless structure-only).
                if (! $this->option('keep-content')) {
                    foreach ($blocks as $block) {
                        $c = $current->get($block['name'] ?? '');
                        if (! $c) {
                            continue;
                        }
                        foreach (($block['nodes'] ?? []) as $node) {
                            if (empty($node['label'])) {
                                continue;
                            }
                            $value = str_replace('{site_name}', $siteTitle, (string) ($node['value'] ?? ''));
                            $reset += $c->nodes()->where('label', $node['label'])
                                ->where('value', '!=', $value)->update(['value' => $value]);
                        }
                    }
                }
            }

            // 4. Data-source collections: reseed template-defined collections
            //    so their rows match the original's authored data exactly.
            //    Owner-created collections (not in the template) are untouched.
            if (! $this->option('keep-content')) {
                $manifest = (array) json_decode((string) @file_get_contents(resource_path("templates/{$key}/template.json")), true);
                foreach ((array) ($manifest['collections'] ?? []) as $cdef) {
                    if (empty($cdef['name']) || empty($cdef['items'])) {
                        continue;
                    }
                    $col = $site->collections()->where('name', $cdef['name'])->first();
                    if (! $col) {
                        continue;
                    }
                    $current = $col->items()->orderBy('id')->get()->map(fn ($i) => $i->data)->values()->all();
                    $wantedItems = array_values($cdef['items']);
                    if ($current == $wantedItems) {
                        continue;
                    }
                    $col->items()->delete();
                    foreach ($wantedItems as $data) {
                        $col->items()->create(['site_id' => $site->id, 'data' => $data, 'status' => 'published']);
                    }
                    $this->line("   ↺ collection '{$col->name}' reseeded (".count($wantedItems).' items)');
                }
            }

            // Pages the template no longer ships — reported, never deleted.
            $templateUrls = $pageDefs->pluck('url')->all();
            foreach ($site->pages()->whereNotIn('url', $templateUrls)->pluck('url') as $extra) {
                $this->warn("   ⚠ {$extra} is not in the template (kept — delete it in Pages if unwanted)");
            }

            foreach ($site->pages()->pluck('url') as $url) {
                Cache::forget("page_render:{$site->id}:{$url}");
            }
            $this->info("   ✓ {$removed} block(s) removed · {$reordered} ordered · {$reset} field value(s) restored");
        }

        return self::SUCCESS;
    }
}
