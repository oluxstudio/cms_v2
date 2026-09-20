<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Marker-driven data-source extraction: a template author annotates an array
 * in a component/composable with
 *
 *   /** @olux-collection Media *\/
 *   const items = [ { title: 'Sunday recap', type: 'video', src: '/videos/…' }, … ]
 *
 * (or puts data-olx-source="media" on an element whose script holds one
 * array) and the pipeline turns it into a manifest `collections` entry —
 * field schema typed from the row keys, items carried verbatim — which
 * TemplateInstaller::applyCollections seeds into every applied site. The
 * shipped useCms stub then feeds those rows back to the components, so the
 * owner edits real CMS data and engagement beacons work out of the box.
 */
class CollectionSourceExtractor
{
    /** @return array<int,array{name:string,description:string,fields:array,items:array}> */
    public function fromSources(string $root): array
    {
        $collections = [];
        $files = array_merge(
            File::glob("$root/app/components/*.{vue,ts,js}", GLOB_BRACE) ?: [],
            File::glob("$root/app/composables/*.{ts,js}", GLOB_BRACE) ?: [],
        );

        foreach ($files as $file) {
            $code = File::get($file);

            // Docblock marker(s): each names the collection and points at the
            // NEXT array literal in the source.
            if (preg_match_all('#/\*\*\s*@olux-collection\s+([A-Za-z][\w ]*?)\s*\*/(.*?)=\s*\[#s', $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $hit) {
                    $name = trim($hit[1][0]);
                    $offset = $hit[0][1] + strlen($hit[0][0]) - 1; // position of '['
                    if ($rows = $this->parseArray($code, $offset)) {
                        $collections[$name] = $this->definition($name, $rows, basename($file));
                    }
                }
                continue;
            }

            // Element marker: data-olx-source="media" → first array in the script.
            if (preg_match('#data-olx-source="([\w-]+)"#', $code, $em)
                && preg_match('#=\s*\[#s', $code, $am, PREG_OFFSET_CAPTURE)) {
                $name = Str::headline($em[1]);
                $offset = $am[0][1] + strlen($am[0][0]) - 1;
                if (! isset($collections[$name]) && ($rows = $this->parseArray($code, $offset))) {
                    $collections[$name] = $this->definition($name, $rows, basename($file));
                }
            }
        }

        return array_values($collections);
    }

    /** Balanced-bracket slice from '[' at $offset, JS-literal → decoded rows. */
    private function parseArray(string $code, int $offset): ?array
    {
        $depth = 0;
        $end = null;
        for ($i = $offset, $len = strlen($code); $i < $len; $i++) {
            $ch = $code[$i];
            if ($ch === '[' || $ch === '{') {
                $depth++;
            } elseif ($ch === ']' || $ch === '}') {
                if (--$depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        if ($end === null) {
            return null;
        }
        $js = substr($code, $offset, $end - $offset + 1);

        // JS object literal → JSON: strip comments, quote keys, single → double
        // quotes, drop trailing commas. Rows using template literals or
        // computed values won't decode — the author should keep seeds literal.
        $js = $this->stripComments($js);
        $js = preg_replace_callback("#'((?:[^'\\\\]|\\\\.)*)'#s", fn ($q) => json_encode(stripcslashes($q[1]), JSON_UNESCAPED_SLASHES), (string) $js);
        $js = preg_replace('#([{,]\s*)([A-Za-z_]\w*)\s*:#', '$1"$2":', (string) $js);
        $js = preg_replace('#,\s*([}\]])#', '$1', (string) $js);

        $rows = json_decode((string) $js, true);
        if (! is_array($rows)) {
            // Second pass: bare identifiers as values (constants, imported
            // vars) aren't JSON — degrade them to null and keep the rest.
            $js = preg_replace('#:\s*(?!true\b|false\b|null\b|[\d"\[{-])[A-Za-z_$][\w$.]*#', ': null', (string) $js);
            $rows = json_decode((string) $js, true);
        }
        if (! is_array($rows)) {
            return null;
        }
        $rows = array_values(array_filter($rows, fn ($r) => is_array($r) && $r !== []));

        return count($rows) >= 1 ? $rows : null;
    }

    /**
     * Remove // and /* *​/ comments WITHOUT touching string contents — a naive
     * regex eats the tail of any URL value ("https://…"), corrupting rows.
     */
    private function stripComments(string $js): string
    {
        $out = '';
        $len = strlen($js);
        $in = null; // current string delimiter: ' " or `
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
            if ($c === '/' && $i + 1 < $len && $js[$i + 1] === '/') {
                while ($i < $len && $js[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";

                continue;
            }
            if ($c === '/' && $i + 1 < $len && $js[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && ! ($js[$i] === '*' && $js[$i + 1] === '/')) {
                    $i++;
                }
                $i++;

                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    /** Manifest collections entry: typed field schema + items verbatim. */
    private function definition(string $name, array $rows, string $sourceFile): array
    {
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $k) {
                $keys[$k] = true;
            }
        }

        $fields = [];
        foreach (array_keys($keys) as $key) {
            $values = collect($rows)->pluck($key)->filter(fn ($v) => is_scalar($v))->map(fn ($v) => (string) $v);
            $distinct = $values->unique();
            $type = match (true) {
                in_array($key, ['src', 'img', 'image', 'url', 'link', 'href', 'photo', 'avatar'], true) => 'url',
                $key === 'date' || $distinct->every(fn ($v) => (bool) preg_match('#^\d{4}-\d{2}-\d{2}#', $v)) && $distinct->isNotEmpty() => 'date',
                count($rows) >= 3 && $distinct->count() >= 1 && $distinct->count() <= 6 && $distinct->count() < count($rows)
                    && $distinct->every(fn ($v) => strlen($v) <= 24 && ! str_contains($v, '/')) && $values->count() === count($rows) => 'select',
                default => 'text',
            };
            $field = ['key' => $key, 'label' => Str::headline($key), 'type' => $type];
            if ($type === 'select') {
                $field['options'] = $distinct->values()->all();
            }
            $fields[] = $field;
        }

        return [
            'name' => $name,
            'description' => "Data source extracted from {$sourceFile} — components read it live via useCms().items().",
            'fields' => $fields,
            'items' => $rows,
        ];
    }
}
