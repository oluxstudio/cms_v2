<?php

namespace App\Support;

use App\Models\Site;
use InvalidArgumentException;

/**
 * Fronts config/email_templates.php — the catalog of every admin-editable
 * outbound email (same pattern as FeatureRegistry over config/features.php).
 */
class EmailTemplateCatalog
{
    /** @return array<string,array> key => entry */
    public static function all(): array
    {
        return config('email_templates', []);
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function get(string $key): array
    {
        $entry = self::all()[$key] ?? null;
        if ($entry === null) {
            throw new InvalidArgumentException("Unknown email template [{$key}].");
        }

        return $entry;
    }

    /**
     * Catalog grouped for the Emails page list — feature-gated entries are
     * kept but flagged, so the admin can see what exists before enabling.
     *
     * @return array<string,array<string,array>> group label => (key => entry + available flag)
     */
    public static function grouped(Site $site): array
    {
        $out = [];
        foreach (self::all() as $key => $entry) {
            $entry['key'] = $key;
            $entry['available'] = ! $entry['feature'] || $site->hasFeature($entry['feature']);
            $out[$entry['group']][$key] = $entry;
        }

        return $out;
    }

    /** Has this site stored its own subject or sections for the template? */
    public static function isCustomized(Site $site, string $key, ?array $attrMap = null): bool
    {
        [$subjectKey, $sectionsKey] = EmailTemplate::attrKeys($key);
        if ($attrMap !== null) {
            return array_key_exists($subjectKey, $attrMap) || array_key_exists($sectionsKey, $attrMap);
        }

        return $site->getAttr($subjectKey) !== null || $site->getAttr($sectionsKey) !== null;
    }
}
