<?php

namespace App\Services;

use App\Services\Vue\SfcParser;
use Illuminate\Support\Facades\File;

/**
 * Rewrites a published template app's block components to consume
 * useOluxContent(blockKey), keeping every original value as the fallback:
 * an unedited site renders byte-identical to the source app, and CMS edits
 * (text, images, repeatable items, hide/show) inject at runtime.
 *
 * The rewrite is bounded and deterministic: it re-parses each component with
 * the same SfcParser + label assignment used at extraction time and only
 * touches the spans the extractor identified.
 */
class SfcRewriter
{
    /** Rewrite every block component referenced by the manifest + ship the composable. */
    public function rewriteApp(string $appDir, array $manifest): void
    {
        $key = $manifest['key'];
        // Chrome components (header/footer) appear on every page — rewrite each
        // component file exactly once or the root guard/bindings duplicate.
        $done = [];
        foreach ($manifest['pages'] as $page) {
            foreach ($page['blocks'] as $block) {
                $file = "$appDir/app/components/{$block['component']}.vue";
                if (File::exists($file) && ! isset($done[$file])) {
                    File::put($file, $this->rewriteComponent(File::get($file), $block));
                    $done[$file] = true;
                }
            }

            // The page itself becomes a dynamic renderer: blocks render in the
            // CMS wireframe order, so canvas reordering drives the real page.
            $pageFile = "$appDir/{$page['file']}";
            if (File::exists($pageFile)) {
                File::put($pageFile, $this->rewritePage(File::get($pageFile), $page));
            }
        }

        File::ensureDirectoryExists("$appDir/app/composables");
        File::copy(base_path('stubs/olux/useOluxContent.ts'), "$appDir/app/composables/useOluxContent.ts");
        File::ensureDirectoryExists("$appDir/app/plugins");
        File::copy(base_path('stubs/olux/olux-nav.client.ts'), "$appDir/app/plugins/olux-nav.client.ts");
        File::copy(base_path('stubs/olux/olux-design.client.ts'), "$appDir/app/plugins/olux-design.client.ts");

        // CMS pages beyond the ones the template shipped (e.g. About Us created
        // in the CMS) need a route too: a catch-all renders ANY page from its
        // wireframe using the template's own block components.
        File::ensureDirectoryExists("$appDir/app/pages");
        File::put("$appDir/app/pages/[...slug].vue", $this->catchAllPage($manifest));
    }

    /**
     * A catch-all page importing the UNION of every block component in the
     * template, rendering whatever the CMS wireframe holds for the current URL.
     * Unknown URLs with no CMS page render nothing (the block map is empty for
     * them), which reads as an empty page rather than a hard 404.
     */
    private function catchAllPage(array $manifest): string
    {
        $imports = '';
        $mapEntries = [];
        $seen = [];
        foreach ($manifest['pages'] as $page) {
            foreach ($page['blocks'] as $block) {
                if (isset($seen[$block['blockKey']])) {
                    continue;
                }
                $seen[$block['blockKey']] = true;
                $imports .= "import {$block['component']} from '~/components/{$block['component']}.vue'\n";
                $mapEntries[] = var_export($block['blockKey'], true).': '.$block['component'];
            }
        }

        $script = "\n".$imports
            ."\nconst route = useRoute()\n"
            .'const oluxBlocks: Record<string, any> = { '.implode(', ', $mapEntries)." }\n"
            ."// Reactive URL getter — this component is reused across navigations.\n"
            ."const oluxPage = useOluxPageOrder(() => route.path, oluxBlocks)\n";

        $template = "\n  <div>\n"
            ."    <div id=\"preloader\"></div>\n"
            ."    <component :is=\"b.comp\" v-for=\"(b, i) in oluxPage\" :key=\"`\${b.key}-\${i}`\" />\n"
            ."  </div>\n";

        return "<script setup lang=\"ts\">{$script}</script>\n\n<template>{$template}</template>\n";
    }

    /**
     * Replace a page's static block composition with a `<component :is>` loop
     * driven by useOluxPageOrder(). Explicit imports keep every block component
     * resolvable; the original order is the fallback (pristine app unchanged).
     */
    private function rewritePage(string $source, array $pageDef): string
    {
        $sections = SfcParser::sections($source);
        if (empty($pageDef['blocks'])) {
            return $source;
        }

        $imports = '';
        $mapEntries = [];
        foreach ($pageDef['blocks'] as $block) {
            $imports .= "import {$block['component']} from '~/components/{$block['component']}.vue'\n";
            $mapEntries[] = var_export($block['blockKey'], true).': '.$block['component'];
        }

        // Preserve the original script (useHead etc.), append imports + the map.
        $script = trim((string) ($sections['script'] ?? ''));
        $script = "\n".$imports
            ."\n".($script !== '' ? $script."\n" : '')
            ."\n// Blocks render in the CMS-configured order (original order as fallback).\n"
            .'const oluxBlocks: Record<string, any> = { '.implode(', ', $mapEntries)." }\n"
            .'const oluxPage = useOluxPageOrder('.var_export($pageDef['url'], true).", oluxBlocks)\n";

        $template = "\n  <div>\n"
            ."    <div id=\"preloader\"></div>\n"
            ."    <component :is=\"b.comp\" v-for=\"(b, i) in oluxPage\" :key=\"`\${b.key}-\${i}`\" />\n"
            ."  </div>\n";

        return "<script setup lang=\"ts\">{$script}</script>\n\n<template>{$template}</template>\n";
    }

    /** Rewrite one block component's SFC source. */
    public function rewriteComponent(string $source, array $blockDef): string
    {
        $sections = SfcParser::sections($source);
        $template = $sections['template'];
        if ($template === null) {
            return $source;
        }

        // Re-parse with the exact extraction logic → labels aligned to offsets.
        $parsed = SfcParser::templateFields($template);
        $fields = TemplateExtractor::labelFixedFields($parsed['fixed']);

        // Fallback map for the generated script (original values, JSON-encoded).
        $fallbacks = [];
        foreach ($fields as $f) {
            $fallbacks[$f['label']] = $f['value'];
            if ($f['kind'] === 'cta' && ($f['href'] ?? '') !== '') {
                $fallbacks[$f['linkLabel']] = $f['href'];
            }
        }

        $newTemplate = $this->rewriteTemplate($template, $fields);
        $newTemplate = $this->addRootHiddenGuard($newTemplate);

        $newScript = $this->rewriteScript($sections['script'], $blockDef, $fallbacks);

        // Reassemble: replace the template body; replace/insert the script block.
        $out = str_replace($template, $newTemplate, $source);
        if ($sections['script'] !== null) {
            $out = str_replace($sections['script'], $newScript, $out);
        } else {
            $out = "<script setup lang=\"ts\">\n{$newScript}</script>\n\n".$out;
        }

        return $out;
    }

    // ─────────────────────────────────────────────── template

    /** Apply span replacements from the end backwards so offsets stay valid. */
    private function rewriteTemplate(string $template, array $fields): string
    {
        usort($fields, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($fields as $f) {
            $span = substr($template, $f['start'], $f['end'] - $f['start']);
            $label = var_export($f['label'], true); // 'Headline' (single-quoted)

            switch ($f['kind']) {
                case 'image':
                    // <img src="/assets/x.png" …> → :src="olux.t('Image', oluxFb['Image'])"
                    $new = preg_replace(
                        '/\ssrc="[^"]*"/',
                        ' :src="olux.t('.$label.', oluxFb['.$label.'])"',
                        $span,
                        1
                    );
                    break;

                case 'cta':
                    $new = $this->replaceInner($span, '{{ olux.t('.$label.', oluxFb['.$label.']) }}');
                    if (($f['href'] ?? '') !== '') {
                        $link = var_export($f['linkLabel'], true);
                        $new = preg_replace(
                            '/\shref="[^"]*"/',
                            ' :href="olux.t('.$link.', oluxFb['.$link.'])"',
                            $new,
                            1
                        );
                    }
                    break;

                case 'text':
                    $new = $this->replaceInner($span, '{{ olux.t('.$label.', oluxFb['.$label.']) }}');
                    break;

                case 'html':
                    // Nested markup: bind via v-html; empty the element's inner.
                    $new = $this->replaceInner($span, '');
                    $new = preg_replace(
                        '/>/',
                        ' v-html="olux.t('.$label.', oluxFb['.$label.'])">',
                        $new,
                        1
                    );
                    break;

                default:
                    continue 2;
            }

            $template = substr($template, 0, $f['start']).$new.substr($template, $f['end']);
        }

        return $template;
    }

    /** Replace the inner content of "<tag …>inner</" span (span ends at the close tag). */
    private function replaceInner(string $span, string $replacement): string
    {
        $gt = strpos($span, '>');
        if ($gt === false) {
            return $span;
        }

        return substr($span, 0, $gt + 1).$replacement;
    }

    /**
     * Root-element bindings: v-if for hide/show plus the CMS Style/Motion
     * settings (inline style string + olux-anim/olux-hover effect classes —
     * both empty until the user configures something in the inspector).
     */
    private function addRootHiddenGuard(string $template): string
    {
        return preg_replace(
            '/<([a-zA-Z][\w-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/',
            '<$1$2 v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">',
            $template,
            1
        );
    }

    // ─────────────────────────────────────────────── script

    /** Inject the olux composable + fallbacks; wrap the items data array. */
    private function rewriteScript(?string $script, array $blockDef, array $fallbacks): string
    {
        $blockKey = var_export($blockDef['blockKey'], true);
        $fbJson = json_encode($fallbacks ?: new \stdClass, JSON_UNESCAPED_SLASHES);

        $inject = "const olux = useOluxContent({$blockKey})\n"
            ."const oluxFb: Record<string, string> = {$fbJson}\n";

        $script ??= '';

        // Wrap standalone string consts (e.g. a shared FAQ answer):
        //   const answer = '…' → const answer = olux.tRef('Answer', '…')
        foreach (SfcParser::scalarStrings($script) as $var => $value) {
            $label = var_export(\Illuminate\Support\Str::headline($var), true);
            $script = preg_replace(
                '/(const\s+'.preg_quote($var, '/').'\s*=\s*)(\'(?:[^\'\\\\]|\\\\.)+\'|"(?:[^"\\\\]|\\\\.)+")/',
                '$1olux.tRef('.$label.', $2)',
                $script,
                1
            );
        }

        // Wrap every extracted items array: const nav = […] →
        //   const nav = olux.items('Nav', {label→key}, […original…], {key→stripPrefix})
        // String arrays (FAQ questions) wrap with olux.list('Faq', […original…]).
        $items = $blockDef['items'] ?? null;
        $groups = $items ? (array_is_list($items) ? $items : [$items]) : [];
        foreach ($groups as $g) {
            if (! preg_match('/const\s+'.preg_quote($g['var'], '/').'\s*=\s*\[/', $script, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $openPos = $m[0][1] + strlen($m[0][0]) - 1;
            $body = $this->balancedArray($script, $openPos);
            if ($body === null) {
                continue;
            }
            $original = '['.$body.']';

            if (! empty($g['scalar'])) {
                $wrapped = 'olux.list('.var_export($g['prefix'], true).', '.$original.')';
            } else {
                $fieldsMap = [];
                $stripMap = [];
                foreach ($g['fields'] as $f) {
                    $fieldsMap[$f['label']] = $f['key'];
                    if ($f['label'] === 'Image' && ! empty($g['imagePrefix'])) {
                        $stripMap[$f['key']] = $g['imagePrefix'];
                    }
                }
                $wrapped = 'olux.items('.var_export($g['prefix'], true).', '
                    .json_encode($fieldsMap, JSON_UNESCAPED_SLASHES).', '
                    .$original.', '
                    .json_encode($stripMap ?: new \stdClass, JSON_UNESCAPED_SLASHES).')';
            }
            $script = substr($script, 0, $openPos).$wrapped.substr($script, $openPos + strlen($original));
        }

        return "\n".$inject.ltrim($script, "\n");
    }

    /** The body between balanced [ ] starting at $openPos, or null. */
    private function balancedArray(string $src, int $openPos): ?string
    {
        $depth = 0;
        $len = strlen($src);
        for ($i = $openPos; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                do {
                    $i++;
                } while ($i < $len && ($src[$i] !== $ch || $src[$i - 1] === '\\'));
                continue;
            }
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $openPos + 1, $i - $openPos - 1);
                }
            }
        }

        return null;
    }
}
