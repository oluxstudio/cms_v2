<?php

namespace App\Support;

use App\Models\Site;
use App\Templates\TemplatePackage;
use App\Templates\TemplateRegistry;

/**
 * The site's template colour variables (--color-primary, --color-frame …),
 * editable with a picker on the Properties page. Defaults are the template's
 * :root values (published as tokens/css-colors.json); the owner's choices are
 * stored in the site theme under the same names and templates apply them at
 * runtime from the content API's `site.theme`. Only values that differ from
 * the template's default are stored, so "reset" is just picking the default.
 */
class SiteColors
{
    /** #rgb / #rrggbb / #rrggbbaa, or rgb()/rgba()/hsl()/hsla(). */
    public const PATTERN = '/^(#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6}|#[0-9a-fA-F]{8}|(rgb|rgba|hsl|hsla)\([\d\s.,%\/]+\))$/';

    /** The template's colour variables and defaults: ['color-primary' => '#ec0470', …]. */
    public static function defaults(Site $site): array
    {
        $key = (string) $site->template;
        if ($key === '') {
            return [];
        }
        // Built-in packages (resources/templates) first, then uploaded / GitHub
        // templates, whose package lives under the user-templates storage.
        $template = TemplateRegistry::find($key);
        if (! ($template && method_exists($template, 'cssColors'))) {
            $dir = TemplatePaths::packageDir($key);
            $template = is_file($dir.'/template.json') || is_dir($dir.'/tokens') ? new TemplatePackage($dir) : null;
        }

        return $template ? $template->cssColors() : [];
    }

    /** Every template colour with the owner's override applied. */
    public static function current(Site $site): array
    {
        $theme = is_array($site->theme) ? $site->theme : [];
        $out = [];
        foreach (self::defaults($site) as $name => $default) {
            $out[$name] = (string) ($theme[$name] ?? $default);
        }

        return $out;
    }

    /** Store the overrides (values that differ from the template default); other theme keys stay. */
    public static function save(Site $site, array $values): void
    {
        $defaults = self::defaults($site);
        $theme = collect(is_array($site->theme) ? $site->theme : [])
            ->reject(fn ($v, $k) => isset($defaults[$k]))
            ->all();
        foreach ($defaults as $name => $default) {
            $value = trim((string) ($values[$name] ?? ''));
            if ($value !== '' && strcasecmp($value, $default) !== 0) {
                $theme[$name] = $value;
            }
        }
        if ($theme !== (is_array($site->theme) ? $site->theme : [])) {
            $site->update(['theme' => $theme]);
        }
    }

    /** "color-primary" → "Primary". */
    public static function label(string $name): string
    {
        return ucwords(str_replace(['color-', '-'], ['', ' '], $name));
    }
}
