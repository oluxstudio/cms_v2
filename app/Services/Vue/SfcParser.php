<?php

namespace App\Services\Vue;

/**
 * DOM-lite parser for Vue SFCs (no JS runtime). Extracts, by convention:
 *  - the ordered component tags used by a page template
 *  - local data arrays from <script setup> (repeatable content items)
 *  - static editable fields from <template>: headings, paragraphs, CTAs,
 *    captions and images that sit OUTSIDE any v-for region.
 *
 * Elements whose inner content nests markup (e.g. <h2>… <span>x</span></h2>)
 * are extracted with kind "html" so the rewriter can keep fidelity via v-html.
 */
class SfcParser
{
    /** Tags whose inner content we consider editable. */
    private const TEXT_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'p', 'a', 'button', 'span'];

    /** Tags allowed to carry nested markup and still be extracted (kind=html). */
    private const HTML_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'p'];

    /** Void elements — never pushed on the open-tag stack. */
    private const VOID_TAGS = ['img', 'br', 'hr', 'input', 'meta', 'link', 'source', 'track', 'wbr'];

    /** Split an SFC into its script-setup and template source. */
    public static function sections(string $source): array
    {
        $script = null;
        $template = null;
        if (preg_match('/<script\s+setup[^>]*>(.*?)<\/script>/s', $source, $m)) {
            $script = $m[1];
        }
        // Greedy: the template block ends at the LAST </template> in the file.
        if (preg_match('/<template>(.*)<\/template>/s', $source, $m)) {
            $template = $m[1];
        }

        return ['script' => $script, 'template' => $template];
    }

    /**
     * Ordered PascalCase component tags in a page template.
     *
     * @return string[] e.g. ['AppHeader','HeroBlock',…]
     */
    public static function pageComponents(string $template): array
    {
        preg_match_all('/<([A-Z][A-Za-z0-9]*)\b[^>]*\/?>/', $template, $m);

        return $m[1];
    }

    /** The page title from a useHead({ title: '…' }) call, if present. */
    public static function useHeadTitle(?string $script): ?string
    {
        if ($script && preg_match('/useHead\s*\(\s*\{[^}]*title:\s*([\'"`])(.*?)\1/s', $script, $m)) {
            return $m[2];
        }

        return null;
    }

    /**
     * Local data arrays of object literals in <script setup>:
     *   const items = [ { img: 'x.jpg', name: 'Jane' }, … ]
     *
     * @return array<string,array<int,array<string,string>>> varName => rows
     */
    public static function dataArrays(?string $script): array
    {
        if (! $script) {
            return [];
        }
        $out = [];
        if (! preg_match_all('/const\s+(\w+)\s*=\s*\[/', $script, $decls, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        foreach ($decls[1] as $i => [$var, $varPos]) {
            $open = $decls[0][$i][1] + strlen($decls[0][$i][0]) - 1; // position of '['
            $body = self::balancedSlice($script, $open, '[', ']');
            if ($body === null) {
                continue;
            }
            $rows = self::parseObjectRows($body);
            if ($rows === []) {
                // Plain string array (e.g. FAQ questions) → single-field rows.
                $rows = self::parseStringRows($body);
            }
            if ($rows !== []) {
                $out[$var] = $rows;
            }
        }

        return $out;
    }

    /**
     * Rows for an array of plain string literals: ['q1', 'q2'] → [['_value'=>'q1'],…].
     * Returns [] when the array holds anything else (objects, numbers).
     *
     * @return array<int,array<string,string>>
     */
    private static function parseStringRows(string $body): array
    {
        // Reject if anything other than strings/commas/whitespace is present.
        $stripped = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|`(?:[^`\\\\]|\\\\.)*`/s', '', $body);
        if (trim(str_replace(',', '', (string) $stripped)) !== '') {
            return [];
        }
        if (! preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"|`((?:[^`\\\\]|\\\\.)*)`/s', $body, $m, PREG_SET_ORDER)) {
            return [];
        }

        return array_map(fn ($s) => ['_value' => stripcslashes($s[1] !== '' ? $s[1] : ($s[2] !== '' ? $s[2] : ($s[3] ?? '')))], $m);
    }

    /**
     * Standalone string consts in <script setup> (e.g. a shared FAQ answer):
     * const answer = '…' → ['answer' => '…']. Short strings are ignored.
     *
     * @return array<string,string>
     */
    public static function scalarStrings(?string $script): array
    {
        if (! $script) {
            return [];
        }
        $out = [];
        if (preg_match_all('/const\s+(\w+)\s*=\s*(?:\'((?:[^\'\\\\]|\\\\.)+)\'|"((?:[^"\\\\]|\\\\.)+)")\s*$/m', $script, $m, PREG_SET_ORDER)) {
            foreach ($m as $p) {
                $val = stripcslashes($p[2] !== '' ? $p[2] : $p[3]);
                if (mb_strlen($val) >= 10) {
                    $out[$p[1]] = $val;
                }
            }
        }

        return $out;
    }

    /** Substring inside balanced delimiters starting at $openPos (exclusive). */
    private static function balancedSlice(string $src, int $openPos, string $open, string $close): ?string
    {
        $depth = 0;
        $len = strlen($src);
        for ($i = $openPos; $i < $len; $i++) {
            $ch = $src[$i];
            // Skip string literals so brackets inside them don't count.
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $i = self::skipString($src, $i);
                continue;
            }
            if ($ch === $open) {
                $depth++;
            } elseif ($ch === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $openPos + 1, $i - $openPos - 1);
                }
            }
        }

        return null;
    }

    /** Index of the closing quote matching the one at $pos (handles \-escapes). */
    private static function skipString(string $src, int $pos): int
    {
        $q = $src[$pos];
        $len = strlen($src);
        for ($i = $pos + 1; $i < $len; $i++) {
            if ($src[$i] === '\\') {
                $i++;
                continue;
            }
            if ($src[$i] === $q) {
                return $i;
            }
        }

        return $len;
    }

    /**
     * Parse `{ key: 'value', … }, { … }` rows tolerantly. Only TOP-LEVEL scalar
     * keys are captured — nested arrays/objects (e.g. dropdown `children`) are
     * skipped so their inner keys don't pollute the row.
     *
     * @return array<int,array<string,string>>
     */
    private static function parseObjectRows(string $body): array
    {
        $rows = [];
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            if ($body[$i] !== '{') {
                continue;
            }
            $obj = self::balancedSlice($body, $i, '{', '}');
            if ($obj === null) {
                break;
            }
            $i += strlen($obj) + 1;
            $row = self::parseObjectTopLevel($obj);
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** Scalar key/value pairs at depth 0 of one object body. */
    private static function parseObjectTopLevel(string $obj): array
    {
        $row = [];
        $len = strlen($obj);
        for ($i = 0; $i < $len; $i++) {
            // Key at depth 0: identifier followed by ':'
            if (! preg_match('/\G\s*(\w+)\s*:/A', $obj, $m, 0, $i)) {
                continue;
            }
            $i += strlen($m[0]);
            // Skip whitespace to the value.
            while ($i < $len && ctype_space($obj[$i])) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }
            $ch = $obj[$i];
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $end = self::skipString($obj, $i);
                $row[$m[1]] = stripcslashes(substr($obj, $i + 1, $end - $i - 1));
                $i = $end;
            } elseif ($ch === '[' || $ch === '{') {
                // Nested structure (children/config) → skip its whole span.
                $close = $ch === '[' ? ']' : '}';
                $inner = self::balancedSlice($obj, $i, $ch, $close);
                $i += ($inner === null) ? 0 : strlen($inner) + 1;
            } elseif (preg_match('/\G(true|false|[\d.]+)/A', $obj, $v, 0, $i)) {
                $row[$m[1]] = $v[1];
                $i += strlen($v[1]) - 1;
            }
            // Advance to the next comma at depth 0 (loop's regex anchors handle it).
        }

        return $row;
    }

    /**
     * Static editable fields in a block template, outside v-for regions.
     *
     * @return array{fixed: array<int,array<string,string>>, itemImagePrefix: ?string}
     *  fixed rows: [tag, kind(text|html|image|cta), label-hint, value, attrs…]
     */
    public static function templateFields(string $template): array
    {
        $fixed = [];
        $itemImagePrefix = null;

        // Record the image-path prefix used inside v-for interpolations:
        //   :src="`/assets/images/testimonials/${t.img}`"
        if (preg_match('/:src="`([^`$]*)\$\{/', $template, $m)) {
            $itemImagePrefix = $m[1];
        }

        // Tokenize tags; track open-element stack and v-for suppression depth.
        $re = '/<(\/?)([a-zA-Z][\w-]*)((?:"[^"]*"|\'[^\']*\'|`[^`]*`|[^>"\'`])*)(\/?)>/';
        if (! preg_match_all($re, $template, $tokens, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return ['fixed' => [], 'itemImagePrefix' => $itemImagePrefix];
        }

        $stack = [];        // [tag, attrs, contentStart, vfor(bool), tokenStart]
        $vforDepth = 0;
        $ranges = [];       // extracted [start, end] ranges for containment dedup

        foreach ($tokens as $t) {
            [$full, $pos] = [$t[0][0], $t[0][1]];
            $closing = $t[1][0] === '/';
            $tag = strtolower($t[2][0]);
            $attrs = $t[3][0];
            $selfClose = ($t[4][0] ?? '') === '/';

            if (! $closing && $tag === 'img') {
                if ($vforDepth === 0 && preg_match('/\ssrc="(\/assets\/[^"]+)"/', $attrs, $m)) {
                    $fixed[] = ['tag' => 'img', 'kind' => 'image', 'value' => $m[1], 'start' => $pos, 'end' => $pos + strlen($full)];
                }
                continue;
            }
            if (in_array($tag, self::VOID_TAGS, true)) {
                continue;
            }

            if (! $closing && ! $selfClose) {
                $isVfor = (bool) preg_match('/\sv-for=/', $attrs);
                if ($isVfor) {
                    $vforDepth++;
                }
                $stack[] = ['tag' => $tag, 'attrs' => $attrs, 'content' => $pos + strlen($full), 'vfor' => $isVfor, 'start' => $pos];
                continue;
            }
            if ($closing) {
                // Unwind to the matching open tag (tolerate minor imbalance).
                for ($k = count($stack) - 1; $k >= 0; $k--) {
                    if ($stack[$k]['tag'] === $tag) {
                        $el = $stack[$k];
                        $popped = array_splice($stack, $k);
                        foreach ($popped as $p) {
                            if ($p['vfor']) {
                                $vforDepth--;
                            }
                        }
                        self::collect($el, $tag, substr($template, $el['content'], $pos - $el['content']), $pos, $vforDepth, $fixed, $ranges);
                        break;
                    }
                }
            }
        }

        return ['fixed' => array_values($fixed), 'itemImagePrefix' => $itemImagePrefix];
    }

    /** Consider one closed element for extraction; dedup nested captures. */
    private static function collect(array $el, string $tag, string $inner, int $endPos, int $vforDepth, array &$fixed, array &$ranges): void
    {
        if ($vforDepth > 0 || ! in_array($tag, self::TEXT_TAGS, true)) {
            return;
        }
        $trimmed = trim($inner);
        if ($trimmed === '' || str_contains($trimmed, '{{')) {
            return; // empty or already dynamic
        }
        $pure = ! str_contains($trimmed, '<');
        $text = trim(html_entity_decode(strip_tags($trimmed)));
        if (mb_strlen($text) < 2) {
            return;
        }

        if ($pure) {
            // Decoded text: mustache output is not HTML, so "&amp;" must become "&".
            if ($tag === 'a') {
                $href = preg_match('/\shref="([^"]*)"/', $el['attrs'], $m) ? $m[1] : '';
                $entry = ['tag' => 'a', 'kind' => 'cta', 'value' => $text, 'href' => $href];
            } else {
                $entry = ['tag' => $tag, 'kind' => 'text', 'value' => $text];
            }
        } elseif (in_array($tag, self::HTML_TAGS, true)) {
            // Nested markup (e.g. styled span inside a heading) → html node.
            $entry = ['tag' => $tag, 'kind' => 'html', 'value' => $trimmed];
            // Drop any pure-text nodes captured inside this element.
            $fixed = array_filter($fixed, fn ($f) => ! (($f['start'] ?? -1) >= $el['start'] && ($f['end'] ?? PHP_INT_MAX) <= $endPos));
        } else {
            return; // a/button/span with nested markup → let inner spans win
        }

        $entry['start'] = $el['start'];
        $entry['end'] = $endPos;
        $fixed[] = $entry;
        $ranges[] = [$el['start'], $endPos];
    }
}
