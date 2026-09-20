<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Self-hosts a template's Google Fonts at publish time: downloads the latin
 * woff2 faces into public/assets/fonts, writes a fonts.css of @font-face
 * rules (same family names, so no stylesheet changes needed) and swaps the
 * CDN <link>s in nuxt.config for the local stylesheet.
 *
 * Failure-safe by design: any network problem leaves the CDN links untouched
 * and returns warnings — the template still renders, just not self-hosted.
 */
class FontLocalizer
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

    /** @return array{localized:bool, families:array<int,string>, warnings:array<int,string>} */
    public function localize(string $appDir): array
    {
        $configPath = collect(['nuxt.config.ts', 'nuxt.config.js'])
            ->map(fn ($f) => "$appDir/$f")->first(fn ($p) => File::exists($p));
        if (! $configPath) {
            return ['localized' => false, 'families' => [], 'warnings' => []];
        }

        $config = File::get($configPath);
        if (! preg_match('#https://fonts\.googleapis\.com/css2\?[^\'"\s]+#', $config, $m)) {
            return ['localized' => false, 'families' => [], 'warnings' => []];
        }
        $cssUrl = html_entity_decode($m[0]);

        try {
            $css = Http::withHeaders(['User-Agent' => self::UA])->timeout(30)->get($cssUrl)->throw()->body();
        } catch (\Throwable $e) {
            return ['localized' => false, 'families' => [], 'warnings' => ['Google Fonts stylesheet unreachable — CDN links kept: '.$e->getMessage()]];
        }

        $fontsDir = "$appDir/public/assets/fonts";
        File::ensureDirectoryExists($fontsDir);

        $faces = [];
        $families = [];
        $warnings = [];
        preg_match_all('#/\* ([a-z0-9-]+) \*/\s*@font-face\s*\{([^}]+)\}#', $css, $blocks, PREG_SET_ORDER);
        foreach ($blocks as [$all, $subset, $body]) {
            if ($subset !== 'latin') {
                continue; // lean: latin covers the UI copy; ext subsets stay CDN-free anyway
            }
            if (! preg_match("#font-family:\s*'([^']+)'#", $body, $fam)
                || ! preg_match('#font-style:\s*(\w+)#', $body, $style)
                || ! preg_match('#font-weight:\s*([\d ]+)#', $body, $weight)
                || ! preg_match('#url\((https://[^)]+\.woff2)\)#', $body, $url)) {
                continue;
            }
            $file = strtolower(str_replace(' ', '-', $fam[1]))."-{$style[1]}-".str_replace(' ', '-', trim($weight[1])).'.woff2';
            try {
                if (! File::exists("$fontsDir/$file")) {
                    File::put("$fontsDir/$file", Http::timeout(30)->get($url[1])->throw()->body());
                }
            } catch (\Throwable $e) {
                $warnings[] = "Font file {$file} failed to download — CDN links kept.";

                // Partial sets are worse than the CDN: bail out wholesale.
                return ['localized' => false, 'families' => [], 'warnings' => $warnings];
            }
            $families[$fam[1]] = true;
            $faces[] = "@font-face {\n  font-family: '{$fam[1]}';\n  font-style: {$style[1]};\n"
                ."  font-weight: ".trim($weight[1]).";\n  font-display: swap;\n"
                ."  src: url('/assets/fonts/{$file}') format('woff2');\n}";
        }
        if ($faces === []) {
            return ['localized' => false, 'families' => [], 'warnings' => ['No latin faces found in the Google stylesheet — CDN links kept.']];
        }

        File::put("$fontsDir/fonts.css",
            "/* Self-hosted at publish by Olux Studio (SIL OFL via Google Fonts) — no CDN needed. */\n\n"
            .implode("\n\n", $faces)."\n");

        // Swap the head links: drop preconnects + the css2 link, add the local sheet.
        $config = preg_replace("#^\s*\{\s*rel:\s*'preconnect',\s*href:\s*'https://fonts\.g[^}]+\},?\n#m", '', $config);
        $config = preg_replace(
            "#\{\s*rel:\s*'stylesheet',\s*href:\s*'https://fonts\.googleapis\.com/css2\?[^']*'\s*\}#",
            "{ rel: 'stylesheet', href: '/assets/fonts/fonts.css' }",
            $config,
            1
        );
        File::put($configPath, $config);

        return ['localized' => true, 'families' => array_keys($families), 'warnings' => $warnings];
    }
}
