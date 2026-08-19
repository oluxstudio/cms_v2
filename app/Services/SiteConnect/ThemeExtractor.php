<?php

namespace App\Services\SiteConnect;

/**
 * Derives a coarse site theme from ingested CSS: the most-used colour values map
 * to primary/text/surface, and the first body/heading font-family becomes the
 * font pair. Deliberately conservative — the owner refines it via the existing
 * theme editor; this is a sensible starting point, not a pixel-perfect capture.
 *
 * @return array shaped for Site::$theme (font, accent, text, surface, ...)
 */
class ThemeExtractor
{
    public function extract(string $css): array
    {
        $theme = [];

        // Colours: rank hex values by frequency (ignore #fff/#000 as too generic
        // for "primary", but keep them as candidates for surface/text).
        preg_match_all('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', $css, $m);
        $colours = collect($m[0])->map(fn ($c) => strtolower($this->expand($c)))->countBy()->sortDesc();

        $accent = $colours->reject(fn ($n, $c) => in_array($c, ['#ffffff', '#000000'], true))->keys()->first();
        if ($accent) {
            $theme['accent'] = $accent;
        }

        // Fonts: first font-family declaration's first family.
        if (preg_match('/font-family\s*:\s*([^;}\n]+)/i', $css, $fm)) {
            $first = trim(explode(',', $fm[1])[0], " \"'");
            if ($first !== '') {
                $theme['font'] = $first;
            }
        }

        // Base font size.
        if (preg_match('/(?:html|body)[^{]*\{[^}]*font-size\s*:\s*([\d.]+px)/i', $css, $fs)) {
            $theme['base_size'] = $fs[1];
        }

        return $theme;
    }

    private function expand(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }
}
