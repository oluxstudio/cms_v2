<?php

namespace App\Services;

use App\Support\CollectionAutoFields;
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
            // NEXT array literal in the source. The block may also declare
            // field types, one per line (see declaredFields()):
            //   /** @olux-collection Projects
            //    * @olux-field images images
            //    * @olux-field title text required
            //    */
            if (preg_match_all('#/\*\*\s*@olux-collection\s+([A-Za-z][\w ]*?)[ \t]*(\r?\n(?:(?!\*/).)*?)?\s*\*/(.*?)=\s*\[#s', $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $hit) {
                    $name = trim($hit[1][0]);
                    $offset = $hit[0][1] + strlen($hit[0][0]) - 1; // position of '['
                    if ($rows = $this->parseArray($code, $offset)) {
                        $collections[$name] = $this->definition($name, $rows, basename($file), $this->declaredFields((string) ($hit[2][0] ?? '')));
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

        // UNMARKED data-source arrays the publisher can auto-wire become
        // collections too — zero authoring for the common cases.
        foreach ($this->autoCandidates($root) as $cand) {
            if (! isset($collections[$cand['name']])) {
                $collections[$cand['name']] = $cand['definition'];
            }
        }

        return array_values($collections);
    }

    /**
     * Auto-detectable data sources: content-shaped arrays (≥3 uniform object
     * rows) that the rewriter can SAFELY wire to the CMS at publish —
     *   · a const inside a component's <script setup> (per-instance scope), or
     *   · a composable array exposed via a trivial `export const useX = () => rows`
     *     accessor (call-time scope).
     * Files that already touch useCms are the author's own wiring — skipped.
     *
     * @return array<int,array{name:string,slug:string,file:string,const:string,kind:string,accessor:?string,definition:array}>
     */
    public function autoCandidates(string $root): array
    {
        $out = [];
        $taken = [];
        $scan = array_merge(
            array_map(fn ($f) => ['kind' => 'component', 'file' => $f], File::glob("$root/app/components/*.vue") ?: []),
            array_map(fn ($f) => ['kind' => 'composable', 'file' => $f], File::glob("$root/app/composables/*.{ts,js}", GLOB_BRACE) ?: []),
        );
        foreach ($scan as $entry) {
            $file = $entry['file'];
            $code = (string) File::get($file);
            // `// @olux-rows`: the block's arrays are its OWN editable rows
            // (e.g. a carousel's slides) — never auto-collections.
            if (str_contains($code, 'useCms') || str_contains($code, '@olux-collection') || str_contains($code, '@olux-rows')) {
                continue;
            }
            $section = $entry['kind'] === 'component'
                ? (preg_match('#<script[^>]*setup[^>]*>(.*?)</script>#s', $code, $sm) ? $sm[1] : '')
                : $code;
            if ($section === '' || ! preg_match_all('#(?<=\n)(?:export )?const (\w+)\s*(?::[^=\n]+)?=\s*\[#', $section, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m as $hit) {
                $const = $hit[1][0];
                $offset = strpos($section, '[', $hit[0][1] + strlen($hit[0][0]) - 1);
                $rows = $this->parseArray($section, $offset);
                if (! $rows || count($rows) < 3) {
                    continue;
                }
                // Content-shaped: uniform object rows with ≥2 keys and at
                // least one substantial text-ish value.
                $keys = array_keys($rows[0]);
                if (count($keys) < 2) {
                    continue;
                }
                $uniform = collect($rows)->every(fn ($r) => count(array_intersect(array_keys($r), $keys)) >= max(1, count($keys) - 2));
                $texty = collect($rows)->contains(fn ($r) => collect($r)->contains(fn ($v) => is_string($v) && mb_strlen($v) >= 15));
                if (! $uniform || ! $texty) {
                    continue;
                }
                $accessor = null;
                if ($entry['kind'] === 'composable') {
                    if (! preg_match('#export const (use\w+)\s*=\s*\(\)\s*=>\s*'.$const.'\b#', $code, $am)) {
                        continue; // no trivial accessor — cannot wire safely
                    }
                    $accessor = $am[1];
                }
                $name = Str::headline($const);
                if (isset($taken[$name])) {
                    continue;
                }
                $taken[$name] = true;
                $def = $this->definition($name, $rows, basename($file));
                $def['description'] = 'Data source auto-detected from '.basename($file).' — edits here render on the site.';
                $out[] = [
                    'name' => $name,
                    'slug' => Str::camel($name),
                    'file' => $file,
                    'const' => $const,
                    'kind' => $entry['kind'],
                    'accessor' => $accessor,
                    'definition' => $def,
                ];
            }
        }

        return $out;
    }

    /**
     * Which collection slug (kebab) feeds this source file? Priority:
     * explicit `@olux-source <slug>` marker, direct items('slug') call, then
     * a use* accessor resolved through the app's composables.
     */
    public function sourceSlugForFile(string $appDir, string $file): ?string
    {
        return $this->sourceSlugsForFile($appDir, $file)[0] ?? null;
    }

    /** All candidate collection slugs feeding a source file, best-first. */
    public function sourceSlugsForFile(string $appDir, string $file): array
    {
        $code = (string) File::get($file);
        $out = [];
        if (preg_match('#@olux-source\s+([\w-]+)#', $code, $m)) {
            $out[] = $m[1];
        }
        if (preg_match_all("#items\(['\"]([\w-]+)['\"]#", $code, $m)) {
            foreach ($m[1] as $s) {
                $out[] = Str::kebab($s);
            }
        }
        foreach (File::glob("$appDir/app/composables/*.{ts,js}", GLOB_BRACE) ?: [] as $comp) {
            $c = (string) File::get($comp);
            $parts = preg_split('#export (?:const|function) (use\w+)#', $c, -1, PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 1; $i < count($parts) - 1; $i += 2) {
                if (str_contains($code, $parts[$i].'(')
                    && preg_match("#items\(['\"]([\w-]+)['\"]#", $parts[$i + 1], $m)) {
                    $out[] = Str::kebab($m[1]);
                }
            }
        }

        return array_values(array_unique($out));
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
        // Single-quoted strings → JSON strings, STRING-AWARE: a regex here
        // would pair an apostrophe inside a double-quoted string ("someone's")
        // with the next real quote and shred the row.
        $out = '';
        for ($i = 0, $len = strlen((string) $js); $i < $len; $i++) {
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
        $js = $out;
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

    /** Field types a template may declare with @olux-field. */
    public const DECLARED_TYPES = ['text', 'textarea', 'textarea2', 'textarea6', 'textarea10', 'textarea15', 'url', 'email', 'tel', 'number', 'date', 'datetime', 'select',
        'checkbox', 'toggle', 'image', 'images', 'media', 'tags', 'list', 'rows', 'slug'];

    /**
     * `@olux-field <key> <type> [required] [options=a|b|c] [label="Shown label"] [auto=created_at]`
     *   auto=  a system-filled source (created_at, updated_at, created_by, number, …)
     *   slug   a web-address slug built from other fields: `slug slug from=title+date`
     *          (filled automatically, unique in the collection — see CollectionAutoFields)
     *   was=   the field's previous key: installs rename it on existing sites, data included
     * lines from a collection's docblock → [key => partial field definition].
     *   images  a gallery (list of asset paths)    tags  a list of short words
     *   image   one asset                          textarea / url / number / date / select / …
     *
     * @return array<string, array<string, mixed>>
     */
    private function declaredFields(string $doc): array
    {
        $out = [];
        // Type = letters then digits (textarea10, textarea2 …), so a size suffix is never split off.
        // A dotted key ("media.type") declares a sub-field of a rows field.
        preg_match_all('#@olux-field\s+([A-Za-z][\w-]*(?:\.[A-Za-z][\w-]*)?)\s+([a-z]+\d*)\b([^\n]*)#', $doc, $m, PREG_SET_ORDER);
        $sub = [];
        foreach ($m as [$_, $key, $type, $rest]) {
            if (! in_array($type, self::DECLARED_TYPES, true)) {
                continue;
            }
            $field = ['type' => $type, 'required' => (bool) preg_match('#\brequired\b#', $rest)];
            // Kept out of the edit form (still stored and sent to the website).
            if (preg_match('#\bhidden\b#', $rest)) {
                $field['hidden'] = true;
            }
            // Shown only when a sibling field has one of these values: show=type:audio|video
            if (preg_match('#\bshow=([A-Za-z][\w-]*):([^\s]+)#', $rest, $sm)) {
                $field['show'] = ['field' => $sm[1], 'in' => array_values(array_filter(explode('|', $sm[2]), 'strlen'))];
            }
            if (preg_match('#label="([^"]{1,60})"#', $rest, $lm)) {
                $field['label'] = $lm[1];
            }
            if (preg_match('#options=([^\s]+)#', $rest, $om)) {
                $field['options'] = array_values(array_filter(array_map('trim', explode('|', $om[1])), 'strlen'));
            }
            if ($type === 'slug') {
                // Built from other fields: from=title+date (default: the title).
                $sources = preg_match('#\bfrom=([\w+-]+)#', $rest, $fm) ? explode('+', $fm[1]) : ['title'];
                $field['auto'] = CollectionAutoFields::slugSource($sources) ?? 'slug:title';
            } elseif (preg_match('#\bfrom=([\w:-]+)#', $rest, $fm) && ($from = \App\Support\CollectionFieldOptions::parseFrom($fm[1]))) {
                // Choices from another collection: from=sermon-series:slug:name
                $field['optionsFrom'] = $from;
            }
            // System-filled source (auto=created_at, auto=number, auto=slug:title+date …) — see CollectionAutoFields.
            if (preg_match('#auto=([\w:.+-]+)#', $rest, $am) && CollectionAutoFields::valid($am[1])) {
                $field['auto'] = $am[1];
            }
            // Renamed field: was=id — existing sites move the old key's values here.
            if (preg_match('#\bwas=([A-Za-z][\w-]*)#', $rest, $wm) && $wm[1] !== $key) {
                $field['was'] = $wm[1];
            }
            if (str_contains($key, '.')) {
                [$parent, $child] = explode('.', $key, 2);
                $sub[$parent][] = ['key' => $child, 'label' => $field['label'] ?? Str::headline($child)] + $field;

                continue;
            }
            $out[$key] = $field;
        }
        foreach ($sub as $parent => $fields) {
            $out[$parent] = ($out[$parent] ?? []) + ['subFields' => $fields];
        }

        return $out;
    }

    /**
     * Manifest collections entry: typed field schema + items verbatim.
     * Declared fields (@olux-field) win over inferred types and are flagged
     * `declared`, so installs keep existing sites' schemas in step.
     */
    private function definition(string $name, array $rows, string $sourceFile, array $declared = []): array
    {
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $k) {
                $keys[$k] = true;
            }
        }

        $fields = [];
        foreach (array_keys($keys) as $key) {
            // Nested values get STRUCTURED types so editors render real
            // sub-editors instead of raw JSON.
            $samples = collect($rows)->pluck($key)->filter(fn ($v) => is_array($v));
            if ($samples->isNotEmpty()) {
                $first = $samples->first();
                if (array_is_list($first)) {
                    // every sub key used by ANY row (image rows may lack a video's src)
                    $subKeys = $samples->flatten(1)->filter(fn ($r) => is_array($r) && ! array_is_list($r))
                        ->flatMap(fn ($r) => array_keys($r))->unique()->values()->all();
                    $fields[] = $subKeys === []
                        ? ['key' => $key, 'label' => Str::headline($key), 'type' => 'list']
                        : ['key' => $key, 'label' => Str::headline($key), 'type' => 'rows',
                            'fields' => array_map(fn ($k) => ['key' => $k, 'label' => Str::headline($k), 'type' => 'text'], $subKeys)];
                } else {
                    $fields[] = ['key' => $key, 'label' => Str::headline($key), 'type' => 'group',
                        'fields' => array_map(fn ($k) => ['key' => $k, 'label' => Str::headline($k), 'type' => 'text'], array_keys($first))];
                }

                continue;
            }
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

        // Declared types override the guesses (and add declared-only keys).
        foreach ($declared as $key => $decl) {
            $at = collect($fields)->search(fn ($f) => $f['key'] === $key);
            $base = $at === false ? ['key' => $key, 'label' => Str::headline($key)] : $fields[$at];
            $subFields = $decl['subFields'] ?? null;
            unset($decl['subFields']);
            $field = array_merge(array_diff_key($base, array_flip(['fields', 'options'])), $decl, ['declared' => true]);
            if ($subFields !== null) {
                // declared sub-fields first (in declared order), then the inferred rest
                $inferred = collect($base['fields'] ?? [])->keyBy('key');
                $field['type'] = $decl['type'] ?? 'rows';
                $field['fields'] = collect($subFields)->map(fn ($f) => $f + ($inferred[$f['key']] ?? []) + ['declared' => true])
                    ->concat($inferred->except(collect($subFields)->pluck('key')->all())->values())
                    ->values()->all();
            } elseif (isset($base['fields']) && in_array($field['type'] ?? '', ['rows', 'group'], true)) {
                $field['fields'] = $base['fields'];
            }
            if ($at === false) {
                $fields[] = $field;
            } else {
                $fields[$at] = $field;
            }
        }

        // Declared fields lead, in declaration order (the template decides how
        // its editor reads); everything inferred follows in source order.
        if ($declared !== []) {
            $order = array_flip(array_keys($declared));
            usort($fields, fn ($a, $b) => [! isset($order[$a['key']]), $order[$a['key']] ?? 0] <=> [! isset($order[$b['key']]), $order[$b['key']] ?? 0]);
        }

        return [
            'name' => $name,
            'description' => "Data source extracted from {$sourceFile} — components read it live via useCms().items().",
            'fields' => $fields,
            'items' => $rows,
        ];
    }
}
