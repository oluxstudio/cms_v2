<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Convention lint for template submissions: scores a staging app's extraction
 * manifest against what the ingestion pipeline can faithfully edit. Run during
 * review so template quality is known BEFORE acceptance — a template that
 * follows the conventions extracts near-perfectly; every finding below marks a
 * spot where editing will be degraded or impossible.
 *
 * Levels: 'error' = editing/theming broken for that area,
 *         'warning' = degraded editing or risky pattern,
 *         'info' = worth knowing, handled automatically.
 */
class TemplateLint
{
    /**
     * @return array{score:int, findings:array<int,array{level:string,area:string,message:string}>}
     */
    public function analyze(array $manifest, ?string $appDir = null): array
    {
        $f = [];

        // ── Pages ──
        $pages = $manifest['pages'] ?? [];
        if (count($pages) === 0) {
            $f[] = $this->finding('error', 'Pages', 'No pages extracted — nothing to scaffold.');
        } elseif (count($pages) === 1) {
            $f[] = $this->finding('warning', 'Pages', 'Single-page app: CMS pages beyond the first render through a generated catch-all route; consider authoring real page files.');
        } else {
            $f[] = $this->finding('info', 'Pages', count($pages).' pages extracted.');
        }

        // Anchor-only navigation (menu hrefs like #services) — page links get
        // rewritten to CMS urls, but in-page anchors won't cross pages.
        $anchorNav = 0;
        foreach ($pages as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                foreach ($block['nodes'] ?? [] as $n) {
                    if (($n['type'] ?? '') === 'url' && str_starts_with((string) $n['value'], '#')) {
                        $anchorNav++;
                    }
                }
            }
        }
        if ($anchorNav > 3) {
            $f[] = $this->finding('warning', 'Navigation', "{$anchorNav} anchor (#…) links — fine on one page, but they can't navigate between CMS pages.");
        }

        // ── Blocks ──
        $totalBlocks = 0;
        foreach ($pages as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                $totalBlocks++;
                $name = $block['name'] ?? $block['blockKey'];
                $nodes = $block['nodes'] ?? [];
                $items = $block['items'] ?? [];

                if (count($nodes) === 0) {
                    $f[] = $this->finding('warning', "Block · {$name}", 'No editable fields extracted — content is hardcoded in markup the extractor could not classify.');

                    continue;
                }

                // Section headline: without one the canvas can only show the block name.
                $hasHeadline = collect($nodes)->contains(fn ($n) => preg_match('/headline|heading|section title/i', $n['label'] ?? '') && ! preg_match('/\d/', $n['label'] ?? ''));
                $isChrome = (bool) preg_match('/header|footer|nav/i', $block['blockKey'] ?? '');
                if (! $hasHeadline && ! $isChrome) {
                    $f[] = $this->finding('warning', "Block · {$name}", 'No section headline found — add a plain <h2> outside loops so editors can retitle the section.');
                }

                // HTML-valued fields: editable, but authors get raw markup.
                $htmlNodes = collect($nodes)->filter(fn ($n) => str_starts_with((string) ($n['kind'] ?? ''), 'html'))->count();
                if ($htmlNodes > 0) {
                    $f[] = $this->finding('info', "Block · {$name}", "{$htmlNodes} field(s) contain inline HTML — editable via textarea, markup preserved.");
                }

                // Machine fields inside item groups (icon classes, css hooks).
                foreach ($items as $g) {
                    $machine = collect($g['fields'] ?? [])->filter(fn ($fl) => preg_match('/^(icon|cls|class|delay|style)$/i', $fl['key'] ?? ''))->count();
                    if ($machine > 0) {
                        $f[] = $this->finding('info', "Block · {$name}", "Item group “{$g['prefix']}” carries {$machine} machine field(s) (icon/css) — hidden from wireframes, editable as raw values.");
                    }
                }
            }
        }
        if ($totalBlocks > 0) {
            $f[] = $this->finding('info', 'Blocks', "{$totalBlocks} blocks across ".count($pages).' page(s).');
        }

        // ── Theme tokens ──
        $tokens = $manifest['theme'] ?? [];
        $colorTokens = collect($tokens)->keys()->filter(fn ($k) => str_starts_with($k, 'color-'))->count();
        if ($colorTokens === 0) {
            $f[] = $this->finding('error', 'Theme', 'No :root color custom properties found — the CMS Theme tab cannot re-skin this template. Define --color-* variables in the stylesheet.');
        } else {
            $hasPrimary = collect($tokens)->keys()->contains(fn ($k) => str_contains($k, 'primary') || str_contains($k, 'accent'));
            $f[] = $hasPrimary
                ? $this->finding('info', 'Theme', "{$colorTokens} color token(s); accent mapping available.")
                : $this->finding('warning', 'Theme', "{$colorTokens} color token(s) but none named *primary*/*accent* — the Theme tab's Accent won't map.");
        }
        $fontTokens = collect($tokens)->keys()->filter(fn ($k) => str_starts_with($k, 'font-') && ! str_contains($k, 'heading'))->count();
        if ($fontTokens === 0) {
            $f[] = $this->finding('warning', 'Fonts', 'No --font-* body tokens — the Theme tab font switch cannot apply.');
        }

        // ── Behaviours & assets ──
        $behaviours = $manifest['behaviours'] ?? [];
        if ($behaviours !== []) {
            $f[] = $this->finding('info', 'Behaviours', implode(', ', $behaviours).' — preserved as shipped.');
        }
        if ($appDir && File::isDirectory($appDir)) {
            $external = 0;
            foreach ($pages as $page) {
                foreach ($page['blocks'] ?? [] as $block) {
                    foreach ($block['nodes'] ?? [] as $n) {
                        if (($n['type'] ?? '') === 'image' && preg_match('#^https?://#', (string) $n['value'])) {
                            $external++;
                        }
                    }
                }
            }
            if ($external > 0) {
                $f[] = $this->finding('warning', 'Assets', "{$external} image(s) hotlinked from external domains — they break if the remote host removes them; bundle them under public/assets.");
            }
        }

        // ── Source fidelity: what the rewrite would silently drop ──
        if ($appDir && File::isDirectory("$appDir/app/pages")) {
            $f = array_merge($f, $this->sourceFindings($appDir));
        }

        // Blocks shared across pages (header/footer) repeat per page — report once.
        $f = collect($f)->unique(fn ($x) => $x['level'].$x['area'].$x['message'])->values()->all();

        // ── Score: 100 − 25/error − 8/warning (floor 0) ──
        $errors = collect($f)->where('level', 'error')->count();
        $warnings = collect($f)->where('level', 'warning')->count();
        $score = max(0, 100 - $errors * 25 - $warnings * 8);

        return ['score' => $score, 'findings' => $f];
    }

    /**
     * Source-aware fidelity checks over app/pages/*.vue — the failure modes
     * graceway hit by hand: slot children and inline page markup are DROPPED
     * by the wireframe rewrite, literal content props never reach the CMS,
     * root-absolute asset dirs must exist under public/ for the preview
     * rebase, and Google-CDN fonts get self-hosted at publish.
     *
     * @return array<int,array{level:string,area:string,message:string}>
     */
    private function sourceFindings(string $appDir): array
    {
        $f = [];
        $propAllow = ['class', 'id', 'to', 'href', 'src', 'alt', 'type', 'style', 'key', 'name', 'target', 'rel', 'width', 'height', 'loading', 'variant'];

        foreach (File::allFiles("$appDir/app/pages") as $pageFile) {
            $file = $pageFile->getPathname();
            $page = ltrim(str_replace('\\', '/', \Illuminate\Support\Str::after($file, '/app/pages/')), '/');
            if ($pageFile->getExtension() !== 'vue' || str_contains($page, '[')) {
                continue; // dynamic routes are not extracted (same rule as TemplateExtractor)
            }
            if (! preg_match('#<template>(.*)</template>#s', File::get($file), $tm)) {
                continue;
            }
            if (str_contains($tm[1], '<component :is') || str_contains($tm[1], 'oluxBlocks')) {
                continue; // already rewritten into a wireframe loop — not authored source
            }
            $tpl = preg_replace('#<!--.*?-->#s', '', $tm[1]);

            // 1. Slot content: <Component>…children…</Component> — dropped by the rewrite.
            if (preg_match_all('#<([A-Z][A-Za-z0-9]*)(\s[^>]*)?(?<!/)>(.*?)</\1>#s', $tpl, $slots, PREG_SET_ORDER)) {
                foreach ($slots as $s) {
                    if (trim($s[3]) !== '') {
                        $f[] = $this->finding('error', 'Fidelity', "{$page}: <{$s[1]}> receives slot content — the CMS rewrite discards it. Move the content inside the component and author pages as flat block lists.");
                    }
                }
            }

            // 2. Inline page markup between blocks (beyond one root wrapper) — also dropped.
            $stripped = preg_replace('#<([A-Z][A-Za-z0-9]*)(\s[^>]*)?(?<!/)>.*?</\1>#s', '', $tpl);
            $stripped = preg_replace('#<[A-Z][A-Za-z0-9]*(\s[^>]*)?/?>#s', '', (string) $stripped);
            $stripped = preg_replace('#^<(div|main|section)([^>]*)>#', '', trim((string) $stripped), 1); // one root wrapper allowed
            $stripped = preg_replace('#</(div|main|section)>$#', '', trim((string) $stripped), 1);
            if (preg_match('#<(section|div|h[1-6]|p|ul|ol|img|form|article|figure)\b#', (string) $stripped, $tag)) {
                $f[] = $this->finding('error', 'Fidelity', "{$page}: inline <{$tag[1]}> markup between block components — the CMS rewrite drops it. Wrap it in its own *Block.vue component.");
            }

            // 3. Literal content props — survive hardcoded, never CMS-editable.
            $literal = 0;
            if (preg_match_all('#<[A-Z][A-Za-z0-9]*\s([^>]+?)/?>#s', $tpl, $tags)) {
                foreach ($tags[1] as $attrs) {
                    if (preg_match_all('#(?<=\s)([a-z][a-z0-9-]*)="([^"]{3,})"#', ' '.$attrs, $props, PREG_SET_ORDER)) {
                        foreach ($props as $pr) {
                            $n = $pr[1];
                            if (! in_array($n, $propAllow, true) && ! str_starts_with($n, 'data-') && ! str_starts_with($n, 'aria-') && ! str_starts_with($n, 'v-')) {
                                $literal++;
                            }
                        }
                    }
                }
            }
            if ($literal > 0) {
                $f[] = $this->finding('warning', 'Fidelity', "{$page}: {$literal} literal content prop(s) on components — prop-passed copy isn't CMS-editable; move it inside the component with data-olx-field markers.");
            }
        }

        // 4. Root-absolute asset dirs: preview-rebased only when they exist under public/.
        $dirRefs = [];
        $scan = array_merge(
            File::glob("$appDir/app/{pages,components,layouts,composables}/*.{vue,ts,js}", GLOB_BRACE) ?: [],
            File::glob("$appDir/public/assets/stylesheets/*.css") ?: []
        );
        foreach ($scan as $file) {
            if (preg_match_all('#["\'(]/(fonts|videos|video|audio|media|img|images|files|downloads)/#', File::get($file), $m)) {
                foreach ($m[1] as $d) {
                    $dirRefs[$d] = true;
                }
            }
        }
        foreach (array_keys($dirRefs) as $d) {
            $f[] = File::isDirectory("$appDir/public/{$d}")
                ? $this->finding('info', 'Assets', "/{$d}/ refs found — the preview build rebases this directory automatically.")
                : $this->finding('error', 'Assets', "/{$d}/ paths referenced but public/{$d} doesn't exist — they 404 in previews. Ship the files under public/{$d} (or public/assets).");
        }

        // 5. Google-CDN fonts — handled automatically at publish.
        foreach (['nuxt.config.ts', 'nuxt.config.js'] as $cfg) {
            if (File::exists("$appDir/$cfg") && str_contains(File::get("$appDir/$cfg"), 'fonts.googleapis.com/css2')) {
                $f[] = $this->finding('info', 'Fonts', 'Google-CDN fonts detected — they will be self-hosted into public/assets/fonts at publish.');
                break;
            }
        }

        // 6. Data-source heuristics: uniform object arrays shaped like
        //    profiles or media galleries — advisory; mark to make editable.
        foreach (File::glob("$appDir/app/{components,composables}/*.{vue,ts,js}", GLOB_BRACE) ?: [] as $file) {
            $code = File::get($file);
            if (str_contains($code, '@olux-collection')) {
                continue; // already marked — the extractor handles it
            }
            if (! preg_match_all('#=\s*\[\s*\{(.*?)\}\s*,\s*\{#s', $code, $arr)) {
                continue;
            }
            foreach ($arr[1] as $row) {
                preg_match_all('#(?<=[{,\n])\s*([a-zA-Z_]\w*)\s*:#', '{'.$row, $km);
                $keys = $km[1] ?? [];
                $isProfile = in_array('name', $keys, true) && array_intersect(['role', 'title', 'bio', 'img'], $keys) !== [];
                $isMedia = array_intersect(['type', 'src', 'img'], $keys) !== [] && in_array('title', $keys, true);
                if (count($keys) >= 2 && ($isProfile || $isMedia)) {
                    $kind = $isProfile ? 'profiles' : 'media-gallery';
                    $f[] = $this->finding('info', 'Data sources', basename($file).": array looks like a {$kind} data source — add a /** @olux-collection Name */ docblock to turn it into an editable CMS collection.");
                    break; // one advisory per file
                }
            }
        }

        return $f;
    }

    private function finding(string $level, string $area, string $message): array
    {
        return ['level' => $level, 'area' => $area, 'message' => $message];
    }
}
