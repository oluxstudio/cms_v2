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

        // Blocks shared across pages (header/footer) repeat per page — report once.
        $f = collect($f)->unique(fn ($x) => $x['level'].$x['area'].$x['message'])->values()->all();

        // ── Score: 100 − 25/error − 8/warning (floor 0) ──
        $errors = collect($f)->where('level', 'error')->count();
        $warnings = collect($f)->where('level', 'warning')->count();
        $score = max(0, 100 - $errors * 25 - $warnings * 8);

        return ['score' => $score, 'findings' => $f];
    }

    private function finding(string $level, string $area, string $message): array
    {
        return ['level' => $level, 'area' => $area, 'message' => $message];
    }
}
