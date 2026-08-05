<?php

namespace App\Support;

/**
 * User-defined theme variables ("$primary", "$hero-pad", …) usable in any
 * block prop that takes a colour, size or font. One definition on the Theme
 * tab → referenced everywhere; every render surface resolves them through
 * CSS custom properties (--bk-<name>), so changing a variable restyles the
 * whole site without touching a single block.
 */
class ThemeTokens
{
    /**
     * Replace theme-variable references in a CSS value with var(--bk-name).
     * Both spellings are equivalent: `$name` (builder shorthand) and
     * `--name` (CSS-native, what the pickers insert).
     */
    public static function vars(?string $value): ?string
    {
        if ($value === null || (! str_contains($value, '$') && ! str_contains($value, '--'))) {
            return $value;
        }

        $value = preg_replace('/\$([a-z][a-z0-9_-]*)/i', 'var(--bk-$1)', $value);

        // --name → var(--bk-name), but never inside an existing var(--…).
        return preg_replace('/(?<![\w(-])--([a-z][a-z0-9_-]*)/i', 'var(--bk-$1)', $value);
    }

    /** The :root declarations for a site's variable list. */
    public static function rootCss(array $variables): string
    {
        $out = '';
        foreach ($variables as $var) {
            $name = self::slug((string) ($var['name'] ?? ''));
            $value = trim((string) ($var['value'] ?? ''));
            if ($name !== '' && $value !== '') {
                $out .= "--bk-{$name}:{$value};";
            }
        }

        return $out;
    }

    /** Canonical variable name: lowercase, a-z0-9 and dashes. */
    public static function slug(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9_-]+/', '-', strtolower(trim($name))), '-');
    }

    /** Clean a submitted variables list: slug names, drop empties, dedupe, type. */
    public static function clean(array $variables): array
    {
        $seen = [];
        $out = [];
        foreach ($variables as $var) {
            $name = self::slug((string) ($var['name'] ?? ''));
            $value = trim((string) ($var['value'] ?? ''));
            if ($name === '' || $value === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $type = in_array($var['type'] ?? '', ['color', 'size', 'font', 'other'], true)
                ? $var['type']
                : self::detectType($value);
            $out[] = ['name' => $name, 'value' => $value, 'type' => $type];
        }

        return $out;
    }

    /**
     * What KIND of value this is — colour variables are offered on colour
     * fields (background/color/overlay), size variables on width/height/
     * spacing, font variables on font fields.
     */
    public static function detectType(string $value): string
    {
        $v = strtolower(trim($value));
        if (preg_match('/^(#[0-9a-f]{3,8}|rgba?\(|hsla?\(|oklch\()/', $v)
            || preg_match('/^(red|blue|green|black|white|gray|grey|orange|purple|pink|teal|cyan|navy|gold|silver|transparent|currentcolor)$/', $v)) {
            return 'color';
        }
        if (preg_match('/^-?[\d.]+(px|%|rem|em|vh|vw|pt|ch|svh|dvh)$/', $v) || preg_match('/^(clamp|min|max|calc)\(/', $v)) {
            return 'size';
        }
        if (str_contains($v, 'serif') || str_contains($v, 'monospace') || preg_match('/^[\'"]/', $v)) {
            return 'font';
        }

        return 'other';
    }

    /** The variables of one kind (plus untyped "other" — usable anywhere). */
    public static function ofType(array $variables, string $type): array
    {
        return array_values(array_filter($variables, fn ($v) => ($v['type'] ?? 'other') === $type || ($v['type'] ?? 'other') === 'other'));
    }
}
