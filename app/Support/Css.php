<?php

namespace App\Support;

/**
 * Small shared CSS emission helpers used by both render surfaces
 * (canvas partial + HTML exporter). The Nuxt renderer mirrors these in TS.
 */
class Css
{
    /**
     * Apply an OPACITY to a whole gradient by wrapping every colour token
     * (#hex, $theme-variable, var(--x), rgb()/rgba()) in color-mix() —
     * CSS has no per-layer opacity, so the fade happens per colour stop.
     */
    public static function fadeGradient(string $gradient, int $pct): string
    {
        $pct = max(0, min(100, $pct));
        if ($pct >= 100) {
            return $gradient;
        }

        return preg_replace_callback(
            '/#[0-9a-fA-F]{3,8}\b|\$[a-zA-Z][\w-]*|var\(--[\w-]+\)|rgba?\([^)]*\)/',
            fn ($m) => "color-mix(in srgb, {$m[0]} {$pct}%, transparent)",
            $gradient
        );
    }
}
