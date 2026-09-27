<?php

namespace App\Services;

use App\Services\Vue\SfcParser;
use Illuminate\Support\Str;

/**
 * Scalar COPY groups authored in composables (useSiteContent()-style):
 * `{ donate: { title: '…', cta: { label: '…', to: '/donate' } } }`.
 *
 * The array halves of such composables are collections' business
 * (CollectionSourceExtractor); this index maps the STRING leaves so that
 * components rendering `{{ donate.title }}` can be (a) extracted into real
 * block nodes and (b) rewritten CMS-first at publish time — the pipeline
 * twin of the hand-authored DonateCta/PageHeroContent pattern.
 */
class ComposableCopyIndex
{
    /** @var array<string, array<string, array<string, string>>> appDir → accessor → [dotted.path => value] */
    private array $cache = [];

    /** Accessor name → flat map of string leaves ("donate.title" => "Give…"). */
    public function build(string $appDir): array
    {
        if (isset($this->cache[$appDir])) {
            return $this->cache[$appDir];
        }
        $index = [];
        foreach (glob("$appDir/composables/*.{ts,js}", GLOB_BRACE) ?: [] as $file) {
            $code = (string) file_get_contents($file);
            $parts = preg_split('#export (?:const|function) (use\w+)#', $code, -1, PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 1; $i < count($parts) - 1; $i += 2) {
                $flat = $this->flatStrings($parts[$i + 1]);
                if ($flat !== []) {
                    $index[$parts[$i]] = $flat;
                }
            }
        }

        return $this->cache[$appDir] = $index;
    }

    /**
     * The copy bindings one component template renders from indexed
     * composables: [['expr','group','label','fieldKey','value','binding']].
     * Labels are deduped (existing extractor letter-suffix convention)
     * against $takenLabels AND each other — deterministically, so the
     * extractor (nodes) and rewriter (t() calls) always agree.
     */
    public function componentCopy(string $appDir, string $sfc, array $takenLabels = []): array
    {
        $index = $this->build($appDir);
        if ($index === []) {
            return [];
        }
        $sections = SfcParser::sections($sfc);
        $script = $sections['script'] ?? '';
        $template = $sections['template'] ?? '';
        if ($template === '' || $template === null || $script === '' || $script === null) {
            return [];
        }

        // Local alias → group map from `const { donate, mediaMinistry: mm } = useSiteContent()`.
        $aliases = [];
        if (preg_match_all('#const\s*\{([^}]+)\}\s*=\s*(use\w+)\s*\(#', $script, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                if (! isset($index[$m[2]])) {
                    continue;
                }
                foreach (explode(',', $m[1]) as $entry) {
                    $bits = array_map('trim', explode(':', $entry));
                    $group = $bits[0];
                    $alias = $bits[1] ?? $bits[0];
                    if ($group !== '') {
                        $aliases[$alias] = [$m[2], $group];
                    }
                }
            }
        }
        if ($aliases === []) {
            return [];
        }

        // Commented-out markup must not extract (same rule as page intake).
        $template = preg_replace('/<!--.*?-->/s', '', $template);

        // Every `{{ alias.path }}` mustache and `:prop="alias.path"` binding
        // whose path lands on a string leaf, in template order.
        $found = [];
        $pattern = '#(?:\{\{\s*|:[\w-]+=")([A-Za-z_$][\w$]*)((?:\.[A-Za-z_$][\w$]*)+)(?:\s*\}\}|")#';
        if (preg_match_all($pattern, $template, $ms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($ms as $m) {
                $alias = $m[1][0];
                $path = ltrim($m[2][0], '.');
                if (! isset($aliases[$alias])) {
                    continue;
                }
                [$accessor, $group] = $aliases[$alias];
                $value = $index[$accessor]["$group.$path"] ?? null;
                if ($value === null) {
                    continue;
                }
                $expr = $alias.'.'.$path;
                $found[$expr] ??= [
                    'expr' => $expr,
                    'group' => $group,
                    'path' => $path,
                    'value' => $value,
                    'binding' => str_starts_with($m[0][0], ':'),
                    'pos' => $m[0][1],
                ];
            }
        }
        $found = array_values($found);
        usort($found, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        // Deterministic labels: headline of the path ('title' → Title,
        // 'cta.label' → Cta Label), letter-suffixed on collision.
        foreach ($found as &$f) {
            $base = trim(implode(' ', array_map([Str::class, 'headline'], explode('.', $f['path'])))) ?: 'Text';
            $label = $base;
            $suffix = 'B';
            while (in_array($label, $takenLabels, true)) {
                $label = $base.' '.$suffix;
                $suffix++; // B → C → …
            }
            $takenLabels[] = $label;
            $f['label'] = $label;
            $f['fieldKey'] = Str::camel(Str::slug($label));
        }

        return $found;
    }

    /** Flatten the segment's returned object literal to string leaves. */
    private function flatStrings(string $segment): array
    {
        // Comments first: an apostrophe in a `// God's people` comment would
        // desync the string-aware balance walk and truncate the slice.
        $segment = $this->stripComments($segment);
        // Candidate object openings: direct arrow `=> ({` and `return {`.
        $offsets = [];
        if (preg_match_all('#(?:=>\s*\(|return)\s*\{#', $segment, $ms, PREG_OFFSET_CAPTURE)) {
            foreach ($ms[0] as $m) {
                $offsets[] = $m[1] + strlen($m[0]) - 1;
            }
        }
        // Most string leaves wins — helper closures return small objects.
        $best = [];
        foreach ($offsets as $off) {
            $obj = $this->parseObject($segment, $off);
            if ($obj === null) {
                continue;
            }
            $flat = [];
            $this->flatten($obj, '', $flat);
            if (count($flat) > count($best)) {
                $best = $flat;
            }
        }

        return $best;
    }

    private function flatten(array $node, string $prefix, array &$out, int $depth = 0): void
    {
        if ($depth > 3) {
            return;
        }
        foreach ($node as $k => $v) {
            if (! is_string($k) || $k === '' || str_starts_with($k, '/')) {
                continue; // route-keyed maps (useHeroCopy) are hand-wired
            }
            $key = $prefix === '' ? $k : "$prefix.$k";
            if (is_string($v) && trim($v) !== '' && ! str_contains($v, '${')) {
                // (interpolated template literals stay code-owned — a CMS
                // node would show the raw ${…} placeholder)
                $out[$key] = $v;
            } elseif (is_array($v) && ! array_is_list($v)) {
                $this->flatten($v, $key, $out, $depth + 1);
            }
            // Lists are collection territory — skipped on purpose.
        }
    }

    /** Balanced `{…}` slice at $offset → decoded assoc array (JS literal). */
    private function parseObject(string $code, int $offset): ?array
    {
        $depth = 0;
        $end = null;
        $in = null;
        for ($i = $offset, $len = strlen($code); $i < $len; $i++) {
            $ch = $code[$i];
            if ($in !== null) {
                if ($ch === '\\') {
                    $i++;
                } elseif ($ch === $in) {
                    $in = null;
                }

                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $in = $ch;
            } elseif ($ch === '{' || $ch === '[') {
                $depth++;
            } elseif ($ch === '}' || $ch === ']') {
                if (--$depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        if ($end === null) {
            return null;
        }
        $js = $this->stripComments(substr($code, $offset, $end - $offset + 1));

        // Same string-aware JS→JSON conversion as CollectionSourceExtractor:
        // a regex would pair apostrophes inside double-quoted strings.
        $out = '';
        for ($i = 0, $len = strlen($js); $i < $len; $i++) {
            $ch = $js[$i];
            if ($ch === '"' || $ch === '`') {
                $q = $ch;
                $out .= '"';
                for ($i++; $i < $len && $js[$i] !== $q; $i++) {
                    if ($js[$i] === '\\') {
                        $out .= $js[$i].($js[$i + 1] ?? '');
                        $i++;

                        continue;
                    }
                    $out .= $js[$i] === '"' ? '\\"' : $js[$i];
                }
                $out .= '"';
            } elseif ($ch === "'") {
                $buf = '';
                for ($i++; $i < $len && $js[$i] !== "'"; $i++) {
                    if ($js[$i] === '\\') {
                        $buf .= stripcslashes($js[$i].($js[$i + 1] ?? ''));
                        $i++;

                        continue;
                    }
                    $buf .= $js[$i];
                }
                $out .= json_encode($buf, JSON_UNESCAPED_SLASHES);
            } else {
                $out .= $ch;
            }
        }
        // JS object → strict JSON with a context-aware walk: quotes keys,
        // handles ES shorthand (`{ events, }`), spreads, and degrades every
        // non-literal value (helper calls, identifiers, ternaries) to null
        // with balanced consumption — a call's inner commas can't shred keys.
        $js = $this->normalizeJson($out);
        $js = preg_replace('#,\s*([}\]])#', '$1', (string) $js);
        $decoded = json_decode((string) $js, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Context-aware JS-literal → JSON (strings already double-quoted). */
    private function normalizeJson(string $js): string
    {
        $out = '';
        $stack = [];               // '{' object / '[' array contexts
        $expectKey = false;        // in an object, before the next key
        $len = strlen($js);
        $copyString = function (int $i) use ($js, $len, &$out): int {
            $out .= '"';
            for ($i++; $i < $len; $i++) {
                $out .= $js[$i];
                if ($js[$i] === '\\') {
                    $out .= $js[++$i];
                } elseif ($js[$i] === '"') {
                    break;
                }
            }

            return $i;
        };
        for ($i = 0; $i < $len; $i++) {
            $c = $js[$i];
            if ($c === ' ' || $c === "\n" || $c === "\t" || $c === "\r") {
                $out .= $c;

                continue;
            }
            if ($c === '{') {
                $out .= $c;
                $stack[] = '{';
                $expectKey = true;

                continue;
            }
            if ($c === '[') {
                $out .= $c;
                $stack[] = '[';
                $expectKey = false;

                continue;
            }
            if ($c === '}' || $c === ']') {
                $out .= $c;
                array_pop($stack);
                $expectKey = false;

                continue;
            }
            if ($c === ',') {
                $out .= $c;
                $expectKey = end($stack) === '{';

                continue;
            }
            if ($c === ':') {
                $out .= $c;
                $expectKey = false;

                continue;
            }
            if ($expectKey) {
                if ($c === '"') {
                    $i = $copyString($i);

                    continue;
                }
                if ($c === '.') { // spread `...x` — drop it and its expression
                    for ($j = $i; $j < $len && ! in_array($js[$j], [',', '}', "\n"], true); $j++) {
                        if ($js[$j] === '(' || $js[$j] === '[') {
                            for ($d = 0; $j < $len; $j++) {
                                if ($js[$j] === '(' || $js[$j] === '[') {
                                    $d++;
                                } elseif ($js[$j] === ')' || $js[$j] === ']') {
                                    if (--$d === 0) {
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if (($js[$j] ?? '') === ',') {
                        $j++;
                    }
                    $i = $j - 1;

                    continue;
                }
                if (preg_match('/[A-Za-z_$]/', $c)) {
                    $j = $i;
                    while ($j < $len && preg_match('/[\w$]/', $js[$j])) {
                        $j++;
                    }
                    $word = substr($js, $i, $j - $i);
                    $k = $j;
                    while ($k < $len && ctype_space($js[$k])) {
                        $k++;
                    }
                    if (($js[$k] ?? '') === ':') {
                        $out .= '"'.$word.'"';   // key: value
                        $i = $j - 1;
                    } else {
                        $out .= '"'.$word.'": null'; // ES shorthand `{ events, }`
                        $i = $j - 1;
                        $expectKey = false;
                    }

                    continue;
                }
                $out .= $c; // computed keys etc. — let the decode fail softly

                continue;
            }
            // Value position.
            if ($c === '"') {
                $i = $copyString($i);

                continue;
            }
            if (ctype_digit($c) || $c === '-') {
                for (; $i < $len && preg_match('/[\d.eE+-]/', $js[$i]); $i++) {
                    $out .= $js[$i];
                }
                $i--;

                continue;
            }
            $head = substr($js, $i, 5);
            $isConst = null;
            foreach (['true', 'false', 'null'] as $kw) {
                if (str_starts_with($head, $kw) && ! preg_match('/[\w$]/', $js[$i + strlen($kw)] ?? '')) {
                    $isConst = $kw;
                    break;
                }
            }
            if ($isConst !== null) {
                $out .= $isConst;
                $i += strlen($isConst) - 1;

                continue;
            }
            // Anything else (identifier, call, ternary, arrow, spread-in-array)
            // → null, consuming the balanced expression.
            $depth = 0;
            for ($j = $i; $j < $len; $j++) {
                $ch = $js[$j];
                if ($ch === '"') {
                    for ($j++; $j < $len; $j++) {
                        if ($js[$j] === '\\') {
                            $j++;
                        } elseif ($js[$j] === '"') {
                            break;
                        }
                    }
                } elseif ($ch === '(' || $ch === '[' || $ch === '{') {
                    $depth++;
                } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
                    if ($depth === 0) {
                        break;
                    }
                    $depth--;
                } elseif ($ch === ',' && $depth === 0) {
                    break;
                }
            }
            $out .= 'null';
            $i = $j - 1;
        }

        return $out;
    }

    private function stripComments(string $js): string
    {
        $out = '';
        $len = strlen($js);
        $in = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $js[$i];
            if ($in !== null) {
                $out .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $out .= $js[++$i];
                } elseif ($c === $in) {
                    $in = null;
                }

                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $in = $c;
                $out .= $c;

                continue;
            }
            if ($c === '/' && ($js[$i + 1] ?? '') === '/') {
                while ($i < $len && $js[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";

                continue;
            }
            if ($c === '/' && ($js[$i + 1] ?? '') === '*') {
                $i += 2;
                while ($i < $len - 1 && ! ($js[$i] === '*' && $js[$i + 1] === '/')) {
                    $i++;
                }
                $i++;

                continue;
            }
            $out .= $c;
        }

        return $out;
    }
}
