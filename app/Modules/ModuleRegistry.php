<?php

namespace App\Modules;

use App\Features\FeatureRegistry;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Unified read-layer over the CMS's capabilities ("modules"). Merges two sources:
 *   - built-in modules: config-backed features (config/features.php) + the always-on Forms
 *   - declarative modules: per-site, AI/admin-created rows in the `modules` table
 *
 * This is purely additive — it does NOT replace the feature plumbing
 * (feature: middleware, Site::hasFeature/enableFeature, SiteFeature). It gives the
 * marketplace, nav and the LLM agent one capability map.
 *
 * Descriptor shape:
 *   ['key','name','description','icon','intents'=>[],'frontend_block','enabled','kind'=>'builtin'|'declarative']
 */
class ModuleRegistry
{
    /** Built-in modules (config features + synthetic Forms), with enabled=null (site-agnostic). */
    public static function builtins(): array
    {
        $out = [];

        foreach (FeatureRegistry::all() as $key => $def) {
            $out[$key] = [
                'key'            => $key,
                'name'           => $def['name'] ?? Str::headline($key),
                'description'    => $def['description'] ?? '',
                'icon'           => $def['icon'] ?? 'puzzle',
                'intents'        => $def['intents'] ?? [],
                'frontend_block' => $def['frontend_block'] ?? null,
                'enabled'        => null,
                'kind'           => 'builtin',
            ];
        }

        // Forms is always available (not a toggle), so surface it for routing.
        $out['forms'] = [
            'key'            => 'forms',
            'name'           => 'Forms',
            'description'    => 'Collect submissions with custom forms (contact, enquiry, signup).',
            'icon'           => 'inbox',
            'intents'        => ['form', 'contact', 'enquiry', 'inquiry', 'signup', 'sign up', 'subscribe', 'newsletter', 'feedback', 'submission', 'lead'],
            'frontend_block' => 'contact',
            'enabled'        => null,
            'kind'           => 'builtin',
        ];

        return $out;
    }

    /** All modules for a site (built-ins + declarative), with resolved `enabled`. */
    public static function forSite(Site $site): Collection
    {
        $builtins = collect(static::builtins())->map(function ($m) use ($site) {
            // Forms is always on; other built-ins follow the feature toggle.
            $m['enabled'] = $m['key'] === 'forms' ? true : $site->hasFeature($m['key']);
            return $m;
        });

        $declarative = $site->modules()->get()->map(fn ($mod) => [
            'key'            => $mod->key,
            'name'           => $mod->name,
            'description'    => $mod->description ?? '',
            'icon'           => $mod->icon ?? 'puzzle',
            'intents'        => $mod->intents ?? [],
            'frontend_block' => $mod->frontend['block'] ?? 'module',
            'enabled'        => (bool) $mod->enabled,
            'kind'           => 'declarative',
        ]);

        return $builtins->values()->merge($declarative->values());
    }

    /** Compact list for the agent/marketplace. */
    public static function capabilityMap(Site $site): array
    {
        return static::forSite($site)->map(fn ($m) => [
            'key'     => $m['key'],
            'name'    => $m['name'],
            'desc'    => $m['description'],
            'intents' => $m['intents'],
            'enabled' => $m['enabled'],
            'kind'    => $m['kind'],
        ])->all();
    }

    /** Does a module with this key already exist for the site (built-in or declarative)? */
    public static function existsForSite(Site $site, string $key): bool
    {
        $key = Str::slug($key);

        if (FeatureRegistry::exists($key) || $key === 'forms') {
            return true;
        }

        return $site->modules()->where('key', $key)->exists();
    }

    /** First module whose name/key/intents match the free-text need, or null. */
    public static function findByIntent(Site $site, string $need): ?array
    {
        $need = strtolower(trim($need));
        if ($need === '') {
            return null;
        }

        return static::forSite($site)->first(function ($m) use ($need) {
            if (str_contains($need, strtolower($m['key'])) || str_contains(strtolower($m['name']), $need)) {
                return true;
            }
            foreach ($m['intents'] as $intent) {
                if ($intent !== '' && str_contains($need, strtolower($intent))) {
                    return true;
                }
            }
            return false;
        });
    }
}
