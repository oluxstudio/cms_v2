<?php

namespace App\Support;

use App\Models\Site;
use App\Templates\TemplateAppRegistry;

/**
 * Page layouts derived from the site's applied template: every page the
 * template ships (resources/templates/{key}/pages/*.json) is a ready-made
 * composition of blocks with default content — offered as a starting layout
 * when the owner creates a new page.
 */
class TemplateLayouts
{
    /** @return array<string, array{key:string,name:string,blocks:list<string>,def:array}> */
    public static function for(Site $site): array
    {
        $key = $site->renderTemplateKey();
        if ($key === '' || ! TemplateAppRegistry::find($key)) {
            return [];
        }

        $layouts = [];
        foreach (glob(resource_path("templates/{$key}/pages/*.json")) ?: [] as $file) {
            $def = json_decode((string) file_get_contents($file), true);
            if (! is_array($def) || empty($def['blocks'])) {
                continue;
            }
            $slug = basename($file, '.json');
            $layouts[$slug] = [
                'key' => $slug,
                'name' => (string) ($def['name'] ?? ucfirst($slug)),
                'blocks' => array_values(array_filter(array_map(
                    fn ($b) => (string) ($b['name'] ?? ''), $def['blocks']
                ))),
                'def' => $def,
            ];
        }

        return $layouts;
    }
}
