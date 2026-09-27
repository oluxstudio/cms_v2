<?php

namespace App\Services;

use App\Services\Vue\SfcParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

        // Auto-wire unmarked data-source arrays FIRST (pristine sources):
        // owners then edit those grids as CMS collections with zero authoring.
        $this->wireAutoCollections($appDir);
        // Chrome components (header/footer) appear on every page — rewrite each
        // component file exactly once or the root guard/bindings duplicate.
        $done = [];
        foreach ($manifest['pages'] as $page) {
            foreach ($page['blocks'] as $block) {
                $file = "$appDir/app/components/{$block['component']}.vue";
                if (File::exists($file) && ! isset($done[$file])) {
                    File::put($file, $this->rewriteComponent(File::get($file), $block, "$appDir/app"));
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
        if (! File::exists("$appDir/app/composables/useCmsForm.ts")) {
            // Form bridge: FormData → POST /api/sites/{site}/form/{name}
            File::copy(base_path('stubs/olux/useCmsForm.ts'), "$appDir/app/composables/useCmsForm.ts");
        }
        if (! File::exists("$appDir/app/composables/useCms.ts")) {
            // Data-source layer: CMS collection rows for marked arrays
            // (profiles, media galleries) with id/_cid for beacons.
            File::copy(base_path('stubs/olux/useCms.ts'), "$appDir/app/composables/useCms.ts");
        }
        File::ensureDirectoryExists("$appDir/app/plugins");
        File::copy(base_path('stubs/olux/olux-nav.client.ts'), "$appDir/app/plugins/olux-nav.client.ts");
        File::copy(base_path('stubs/olux/olux-design.client.ts'), "$appDir/app/plugins/olux-design.client.ts");
        File::copy(base_path('stubs/olux/olux-head.client.ts'), "$appDir/app/plugins/olux-head.client.ts");

        // CMS pages beyond the ones the template shipped (e.g. About Us created
        // in the CMS) need a route too: a catch-all renders ANY page from its
        // wireframe using the template's own block components.
        File::ensureDirectoryExists("$appDir/app/pages");
        File::put("$appDir/app/pages/[...slug].vue", $this->catchAllPage($manifest, $appDir));
    }

    /**
     * A catch-all page importing the UNION of every block component in the
     * template, rendering whatever the CMS wireframe holds for the current URL.
     * Unknown URLs with no CMS page render nothing (the block map is empty for
     * them), which reads as an empty page rather than a hard 404.
     */
    private function catchAllPage(array $manifest, string $appDir): string
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

        // Blocks that live in the template but sit on NO shipped page (e.g. a
        // Donate block only used by CMS-scaffolded pages) still need a route:
        // include every *Block.vue component, keyed by its kebab-cased name.
        foreach (File::glob("$appDir/app/components/*Block.vue") as $file) {
            $component = basename($file, '.vue');
            // Same formula as TemplateExtractor::block(): App prefix stripped too.
            $blockKey = Str::kebab(preg_replace('/^App(?=[A-Z])/', '', preg_replace('/Block$/', '', $component)));
            if ($blockKey === '' || isset($seen[$blockKey])) {
                continue;
            }
            $seen[$blockKey] = true;
            $imports .= "import {$component} from '~/components/{$component}.vue'\n";
            $mapEntries[] = var_export($blockKey, true).': '.$component;
        }

        $script = "\n".$imports
            ."\nconst route = useRoute()\n"
            .'const oluxBlocks: Record<string, any> = { '.implode(', ', $mapEntries)." }\n"
            ."// Reactive URL getter — this component is reused across navigations.\n"
            ."const oluxPage = useOluxPageOrder(() => route.path, oluxBlocks)\n";

        $template = "\n  <div>\n"
            ."    <div id=\"preloader\"></div>\n"
            ."    <component :is=\"b.comp\" v-for=\"(b, i) in oluxPage\" :key=\"`\${b.key}-\${i}`\" :data-olx-key=\"b.key\" data-olx-kind=\"component\" />\n"
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
        $propEntries = [];
        foreach ($pageDef['blocks'] as $block) {
            $imports .= "import {$block['component']} from '~/components/{$block['component']}.vue'\n";
            $mapEntries[] = var_export($block['blockKey'], true).': '.$block['component'];
            if (! empty($block['props'])) {
                $propEntries[] = var_export($block['blockKey'], true).': '.json_encode($block['props'], JSON_UNESCAPED_SLASHES);
            }
        }

        // Preserve the original script (useHead etc.), append imports + the map.
        $script = trim((string) ($sections['script'] ?? ''));
        $script = "\n".$imports
            ."\n".($script !== '' ? $script."\n" : '')
            ."\n// Blocks render in the CMS-configured order (original order as fallback).\n"
            .'const oluxBlocks: Record<string, any> = { '.implode(', ', $mapEntries)." }\n"
            .'const oluxPage = useOluxPageOrder('.var_export($pageDef['url'], true).", oluxBlocks)\n"
            .'// Page-level literal props (e.g. :limit="3" show-view-all) survive the rewrite.'."\n"
            .'const oluxProps: Record<string, any> = { '.implode(', ', $propEntries)." }\n";

        $template = "\n  <div>\n"
            ."    <div id=\"preloader\"></div>\n"
            ."    <component :is=\"b.comp\" v-for=\"(b, i) in oluxPage\" :key=\"`\${b.key}-\${i}`\" v-bind=\"oluxProps[b.key] || {}\" :data-olx-key=\"b.key\" data-olx-kind=\"component\" />\n"
            ."  </div>\n";

        return "<script setup lang=\"ts\">{$script}</script>\n\n<template>{$template}</template>\n";
    }

    /**
     * CMS-wire the auto-detected data-source arrays (see
     * CollectionSourceExtractor::autoCandidates): the array keeps its name for
     * every existing usage, but its value becomes "CMS rows if any, else the
     * authored seed". Composables via their trivial accessor (call-time
     * scope); component <script setup> consts in place (per-instance scope).
     */
    private function wireAutoCollections(string $appDir): void
    {
        foreach (app(CollectionSourceExtractor::class)->autoCandidates($appDir) as $cand) {
            $code = (string) File::get($cand['file']);
            $slug = $cand['slug'];

            if ($cand['kind'] === 'composable') {
                $pattern = '#export const '.$cand['accessor'].'\s*=\s*\(\)\s*=>\s*'.$cand['const'].'\b#';
                $replacement = "// CMS-first (auto-wired at publish): the '{$cand['name']}' collection feeds this; authored rows seed it.\n"
                    ."export const {$cand['accessor']} = () => {\n"
                    ."  const rows = useCms().items('{$slug}', []) as any[]\n"
                    ."  return rows.length ? rows : {$cand['const']}\n"
                    .'}';
                $new = preg_replace($pattern, $replacement, $code, 1);
            } else {
                // Rename the declaration to *_seed, overlay under the original
                // name — every later usage in the component stays untouched.
                $pattern = '#((?:export )?const )'.$cand['const'].'(\s*(?::[^=\n]+)?=\s*\[)#';
                if (! preg_match($pattern, $code, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                $declStart = $m[0][1];
                $bracket = strpos($code, '[', $declStart);
                $end = $this->balanced($code, $bracket);
                if ($end === null) {
                    continue;
                }
                $overlay = "\n// CMS-first (auto-wired at publish): the '{$cand['name']}' collection feeds this grid.\n"
                    ."const {$cand['const']} = (() => { const r = useCms().items('{$slug}', []) as any[]; return r.length ? r : {$cand['const']}__seed })()\n";
                $new = substr($code, 0, $declStart)
                    .preg_replace($pattern, '${1}'.$cand['const'].'__seed$2', substr($code, $declStart, $end - $declStart), 1)
                    .$overlay
                    .substr($code, $end);
            }
            if ($new !== null && $new !== $code) {
                File::put($cand['file'], $new);
            }
        }
    }

    /** Index just past the bracket matching the one at $open, string-aware. */
    private function balanced(string $s, int $open): ?int
    {
        $depth = 0;
        $in = null;
        for ($i = $open, $len = strlen($s); $i < $len; $i++) {
            $ch = $s[$i];
            if ($in !== null) {
                if ($ch === '\\') {
                    $i++;
                } elseif ($ch === $in) {
                    $in = null;
                }
            } elseif ($ch === "'" || $ch === '"' || $ch === '`') {
                $in = $ch;
            } elseif ($ch === '[' || $ch === '{') {
                $depth++;
            } elseif ($ch === ']' || $ch === '}') {
                if (--$depth === 0) {
                    return $i + 1;
                }
            }
        }

        return null;
    }

    /** Rewrite one block component's SFC source. */
    public function rewriteComponent(string $source, array $blockDef, ?string $appDir = null): string
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

        // Composable-authored copy ({{ donate.title }}) → CMS-first t() calls
        // + field markers. Labels re-derived with the SAME taken-list order as
        // TemplateExtractor::block() (fixed labels, then script consts) so
        // the shipped nodes and these t() lookups always agree. Hand-authored
        // CMS-native components (they already call useOluxContent) are theirs.
        if ($appDir !== null && ! str_contains($source, 'useOluxContent')) {
            $taken = array_keys($fallbacks);
            foreach (SfcParser::scalarStrings($sections['script'] ?? '') as $var => $v) {
                $taken[] = Str::headline($var);
            }
            $copies = (new ComposableCopyIndex)->componentCopy($appDir, $source, $taken);
            $newTemplate = $this->rewriteCopy($newTemplate, $copies);
        }

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

    /**
     * Composable-copy rewrites: `{{ donate.title }}` → `{{ oluxCms.t('Title',
     * donate.title) }}` (fallback = the original expression, so a pristine
     * render is byte-identical), `:prop="expr"` bindings likewise, and a
     * data-olx-field marker where the mustache is an element's sole text
     * (or on src/label-ish bindings) for click/hover editing.
     */
    private function rewriteCopy(string $template, array $copies): string
    {
        foreach ($copies as $f) {
            $expr = preg_quote($f['expr'], '/');
            $label = var_export($f['label'], true);
            $call = 'oluxCms.t('.$label.', '.$f['expr'].')';

            if ($f['binding']) {
                // Marker only on src/label-ish props — an <img :src :alt> pair
                // must not end up with two data-olx-field attributes.
                $template = preg_replace_callback(
                    '/:([\w-]+)="'.$expr.'"/',
                    function ($m) use ($call, $f) {
                        $marker = in_array(strtolower($m[1]), ['src', 'label'], true)
                            ? ' data-olx-field="'.$f['fieldKey'].'"'
                            : '';

                        return ':'.$m[1].'="'.$call.'"'.$marker;
                    },
                    $template
                );

                continue;
            }
            // Sole-text hosts get the marker (attrs may span lines).
            $template = preg_replace_callback(
                '/<([A-Za-z][\w-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>(\s*)\{\{\s*'.$expr.'\s*\}\}/',
                fn ($m) => '<'.$m[1].$m[2]
                    .(str_contains($m[2], 'data-olx-field') ? '' : ' data-olx-field="'.$f['fieldKey'].'"')
                    .'>'.$m[3].'{{ '.$call.' }}',
                $template
            );
            // Any remaining inline occurrences still go CMS-first (no marker).
            $template = preg_replace('/\{\{\s*'.$expr.'\s*\}\}/', '{{ '.$call.' }}', $template);
        }

        return $template;
    }

    /** Apply span replacements from the end backwards so offsets stay valid. */
    private function rewriteTemplate(string $template, array $fields): string
    {
        usort($fields, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($fields as $f) {
            $span = substr($template, $f['start'], $f['end'] - $f['start']);
            $label = var_export($f['label'], true); // 'Headline' (single-quoted)
            // Marker for /connect click-to-edit: same key shape the editor's
            // nodeByFieldKey() matches ('Cta Label' → ctaLabel).
            $fieldKey = Str::camel(Str::slug($f['label']));

            switch ($f['kind']) {
                case 'image':
                    // <img src="/assets/x.png" …> → :src="oluxCms.t('Image', oluxFb['Image'])"
                    $new = preg_replace(
                        '/\ssrc="[^"]*"/',
                        ' :src="oluxCms.t('.$label.', oluxFb['.$label.'])"',
                        $span,
                        1
                    );
                    $new = $this->addFieldMarker($new, $fieldKey);
                    break;

                case 'cta':
                    $new = $this->replaceInner($span, '{{ oluxCms.t('.$label.', oluxFb['.$label.']) }}');
                    if (($f['href'] ?? '') !== '') {
                        $link = var_export($f['linkLabel'], true);
                        $new = preg_replace(
                            '/\shref="[^"]*"/',
                            ' :href="oluxCms.t('.$link.', oluxFb['.$link.'])"',
                            $new,
                            1
                        );
                    }
                    $new = $this->addFieldMarker($new, $fieldKey);
                    break;

                case 'text':
                    $new = $this->replaceInner($span, '{{ oluxCms.t('.$label.', oluxFb['.$label.']) }}');
                    $new = $this->addFieldMarker($new, $fieldKey);
                    break;

                case 'html':
                    // Nested markup: bind via v-html; empty the element's inner.
                    $new = $this->replaceInner($span, '');
                    $gt = $this->tagEnd($new);
                    if ($gt !== null) {
                        $new = substr($new, 0, $gt).' v-html="oluxCms.t('.$label.', oluxFb['.$label.'])"'.substr($new, $gt);
                    }
                    $new = $this->addFieldMarker($new, $fieldKey);
                    break;

                default:
                    continue 2;
            }

            $template = substr($template, 0, $f['start']).$new.substr($template, $f['end']);
        }

        return $template;
    }

    /** Inject data-olx-field="key" into the span's FIRST open tag (idempotent). */
    private function addFieldMarker(string $span, string $fieldKey): string
    {
        if ($fieldKey === '' || str_contains($span, 'data-olx-field=')) {
            return $span;
        }

        return preg_replace(
            '/<([a-zA-Z][\w-]*)/',
            '<$1 data-olx-field="'.$fieldKey.'"',
            $span,
            1
        );
    }

    /** Replace the inner content of "<tag …>inner</" span (span ends at the close tag). */
    private function replaceInner(string $span, string $replacement): string
    {
        $gt = $this->tagEnd($span);
        if ($gt === null) {
            return $span;
        }

        return substr($span, 0, $gt + 1).$replacement;
    }

    /**
     * Position of the open tag's closing '>' — skips quoted attribute values,
     * so bindings like v-if="step > 0" never truncate the tag early.
     */
    private function tagEnd(string $span): ?int
    {
        $len = strlen($span);
        for ($i = 0; $i < $len; $i++) {
            $ch = $span[$i];
            if ($ch === '"' || $ch === "'") {
                for ($i++; $i < $len && $span[$i] !== $ch; $i++);
            } elseif ($ch === '>') {
                return $i;
            }
        }

        return null;
    }

    /**
     * Root-element bindings: v-if for hide/show plus the CMS Style/Motion
     * settings (inline style string + olux-anim/olux-hover effect classes —
     * both empty until the user configures something in the inspector).
     */
    private function addRootHiddenGuard(string $template): string
    {
        return preg_replace_callback(
            '/<([a-zA-Z][\w-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/',
            function (array $m) {
                $extra = ' v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value"';
                // A root that already binds :class must not get a second one
                // (duplicate attribute) — Motion classes are skipped there.
                if (! str_contains($m[2], ':class=') && ! str_contains($m[2], 'v-bind:class=')) {
                    $extra .= ' :class="oluxCms.rootClass.value"';
                }

                return '<'.$m[1].$m[2].$extra.'>';
            },
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

        // Collision-proof name: template apps may declare their own `olux`
        // (e.g. hairco's `const olux = useOluxSite()`).
        $inject = "const oluxCms = useOluxContent({$blockKey})\n"
            ."const oluxFb: Record<string, string> = {$fbJson}\n";

        $script ??= '';

        // Wrap standalone string consts (e.g. a shared FAQ answer):
        //   const answer = '…' → const answer = olux.tRef('Answer', '…')
        foreach (SfcParser::scalarStrings($script) as $var => $value) {
            // ONLY wrap template-consumed strings. If the SCRIPT itself uses
            // the variable (fetch bodies, keys, math…), a computed ref would
            // silently change its type — e.g. JSON.stringify on a wrapped
            // FORM_NAME threw "circular structure" and broke booking submits.
            $declPattern = '/const\s+'.preg_quote($var, '/').'\s*=/';
            $withoutDecl = preg_replace($declPattern, '', $script, 1);
            if (preg_match('/\b'.preg_quote($var, '/').'\b/', (string) $withoutDecl)) {
                continue;
            }
            $label = var_export(Str::headline($var), true);
            $script = preg_replace(
                '/(const\s+'.preg_quote($var, '/').'\s*=\s*)(\'(?:[^\'\\\\]|\\\\.)+\'|"(?:[^"\\\\]|\\\\.)+")/',
                '$1oluxCms.tRef('.$label.', $2)',
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
                $wrapped = 'oluxCms.list('.var_export($g['prefix'], true).', '.$original.')';
            } else {
                $fieldsMap = [];
                $stripMap = [];
                foreach ($g['fields'] as $f) {
                    $fieldsMap[$f['label']] = $f['key'];
                    if ($f['label'] === 'Image' && ! empty($g['imagePrefix'])) {
                        $stripMap[$f['key']] = $g['imagePrefix'];
                    }
                }
                $wrapped = 'oluxCms.items('.var_export($g['prefix'], true).', '
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
