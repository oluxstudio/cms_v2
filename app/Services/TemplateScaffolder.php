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
        $author = $site->user?->name ?? 'Olux';
        $siteTitle = ucwords(str_replace('-', ' ', $site->name));
        $pages = 0;
        $components = 0;

        foreach ($package->pages() as $def) {
            $url = $def['url'] ?? '/';
            if ($site->pages()->where('url', $url)->exists()) {
                continue;
            }

            /** @var Page $page */
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

            foreach (($def['blocks'] ?? []) as $order => $block) {
                $component = Component::create([
                    'site_id' => $site->id,
                    'name' => $block['name'] ?? 'Block',
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
                $page->components()->syncWithoutDetaching([$component->id => ['order' => $order]]);
                $components++;
            }
        }

        return ['pages' => $pages, 'components' => $components];
    }
}
