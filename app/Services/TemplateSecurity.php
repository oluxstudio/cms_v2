<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Hardening for user-generated template packages (.zip uploads). Nothing from an
 * upload is trusted: archives are bounded + path-checked, CSS is sanitized, pages
 * are validated against the block catalog, and SVG assets are stripped of script.
 */
class TemplateSecurity
{
    private const NODE_TYPES = ['text', 'url', 'image', 'number', 'boolean', 'color'];

    /**
     * Validate an opened archive against the configured limits. Throws on any
     * violation (size, count, path traversal, disallowed file type).
     */
    public function inspectZip(ZipArchive $zip): void
    {
        $lim       = config('templates.limits');
        $allowed   = array_map('strtolower', $lim['allowed_ext']);
        $maxFiles  = (int) $lim['max_files'];
        $maxTotal  = (int) $lim['max_total_mb'] * 1024 * 1024;
        $maxFile   = (int) $lim['max_file_mb'] * 1024 * 1024;

        if ($zip->numFiles > $maxFiles) {
            throw new RuntimeException("Archive has too many files ({$zip->numFiles} > {$maxFiles}).");
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (str_ends_with($name, '/')) {
                continue; // directory entry
            }

            // Path traversal / absolute paths.
            if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
                throw new RuntimeException("Unsafe path in archive: {$name}");
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === '' || ! in_array($ext, $allowed, true)) {
                throw new RuntimeException("Disallowed file type in archive: {$name}");
            }

            $stat = $zip->statIndex($i);
            $size = (int) ($stat['size'] ?? 0); // uncompressed
            if ($size > $maxFile) {
                throw new RuntimeException("File too large in archive: {$name}");
            }
            $total += $size;
            if ($total > $maxTotal) {
                throw new RuntimeException('Archive contents exceed the size limit.');
            }
        }
    }

    /** Neutralise dangerous constructs in template CSS. */
    public function sanitizeCss(?string $css): string
    {
        $css = (string) $css;
        // Remove style-breakout, imports, JS execution vectors.
        $patterns = [
            '#</?\s*style[^>]*>#i',
            '#@import\b[^;]*;?#i',
            '#expression\s*\(#i',
            '#javascript\s*:#i',
            '#behavior\s*:#i',
            '#-moz-binding\b#i',
            '#@charset\b[^;]*;?#i',
        ];
        $css = preg_replace($patterns, '', $css) ?? '';

        return trim($css);
    }

    /**
     * Validate + clean page definitions against the block catalog. Unknown block
     * types/variants and node types are dropped; counts are capped. Returns safe pages.
     *
     * @param  array<int,mixed>  $pages
     * @return array<int,array<string,mixed>>
     */
    public function sanitizePages(array $pages): array
    {
        $maxPages   = (int) config('templates.limits.max_pages');
        $out        = [];

        foreach (array_slice($pages, 0, $maxPages) as $page) {
            if (! is_array($page)) {
                continue;
            }
            $clean = [
                'name'     => $this->str($page['name'] ?? 'Page', 120),
                'url'      => $this->str($page['url'] ?? '/', 200),
                'keywords' => $this->str($page['keywords'] ?? '', 255),
                'blocks'   => [],
            ];

            foreach (array_slice((array) ($page['blocks'] ?? []), 0, 60) as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $type = $this->str((string) ($block['type'] ?? ''), 60);
                if ($type === '') {
                    continue;
                }
                // Catalog validation moved to the block builder; keep the declared variant as-is.
                $variant = $this->str((string) ($block['variant'] ?? 'default'), 60);

                $nodes = [];
                foreach (array_slice((array) ($block['nodes'] ?? []), 0, 60) as $n) {
                    if (! is_array($n)) {
                        continue;
                    }
                    $ntype = (string) ($n['type'] ?? 'text');
                    if (! in_array($ntype, self::NODE_TYPES, true)) {
                        $ntype = 'text';
                    }
                    $nodes[] = [
                        'label' => $this->str($n['label'] ?? 'Field', 120),
                        'type'  => $ntype,
                        'value' => $this->str((string) ($n['value'] ?? ''), 5000),
                        'order' => (int) ($n['order'] ?? 0),
                    ];
                }

                $clean['blocks'][] = [
                    'type'    => $type,
                    'variant' => $variant,
                    'name'    => $this->str($block['name'] ?? '', 120),
                    'nodes'   => $nodes,
                ];
            }

            $out[] = $clean;
        }

        if (! $out) {
            throw new RuntimeException('No valid pages found in the package.');
        }

        return $out;
    }

    /** Strip script/handlers from an SVG asset so it can't execute JS. */
    public function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('#<script[^>]*>.*?</script>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $svg) ?? $svg; // on* handlers (double-quoted)
        $svg = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $svg) ?? $svg;  // on* handlers (single-quoted)
        $svg = preg_replace('#javascript\s*:#i', '', $svg) ?? $svg;
        $svg = preg_replace('#<\s*(iframe|foreignObject)[^>]*>#i', '', $svg) ?? $svg;

        return $svg;
    }

    /** Is a filename an SVG (needs sanitizing before publish)? */
    public function isSvg(string $name): bool
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'svg';
    }

    /** Trim + strip angle brackets from a short plain-text field (name, category…). */
    public function sanitizePlain(mixed $v, int $max = 255): string
    {
        return $this->str(str_replace(['<', '>'], '', (string) $v), $max);
    }

    private function str(mixed $v, int $max): string
    {
        return mb_substr(trim((string) $v), 0, $max);
    }
}
