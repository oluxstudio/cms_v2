<?php

namespace App\Livewire\Concerns;

/**
 * Structured editing for NESTED values inside collection entries — scalar
 * lists (tags, questions), row lists (facts: [{label,value}]) and plain
 * groups (cta: {label,to}). The blade partial `partials.nested-field` binds
 * straight into the nested property path; these methods mutate sub-rows by
 * that same dotted path. Paths are whitelisted per component via
 * $nestedRoots so a crafted request can't touch arbitrary properties.
 */
trait WithNestedFields
{
    /** A nested value the structured editor can handle (depth ≤ 2, uniform). */
    public static function nestedEditable(mixed $v): bool
    {
        if (! is_array($v)) {
            return false;
        }
        $rows = array_is_list($v) ? $v : [$v];
        foreach ($rows as $row) {
            if (is_array($row)) {
                if (array_is_list($row)) {
                    return false; // list-of-lists — too deep/irregular
                }
                foreach ($row as $leaf) {
                    if (is_array($leaf)) {
                        return false; // depth 3+
                    }
                }
            }
        }
        // Uniform member kind: all rows scalar, or all rows assoc.
        $kinds = array_unique(array_map(fn ($r) => is_array($r) ? 'row' : 'scalar', $rows));

        return count($kinds) === 1;
    }

    private function nestedGuardPath(string $path): void
    {
        $roots = property_exists($this, 'nestedRoots') ? $this->nestedRoots : [];
        foreach ($roots as $root) {
            if (str_starts_with($path, $root)) {
                return;
            }
        }
        abort(403, 'Path not editable.');
    }

    /** @param  array<int,string>  $keys  sub-fields for a new row when the list is empty (schema) */
    public function nestedAdd(string $path, array $keys = []): void
    {
        $this->nestedGuardPath($path);
        $list = (array) data_get($this, $path, []);
        if (! array_is_list($list)) {
            return; // groups have a fixed shape
        }
        $first = $list[0] ?? null;
        $keys = array_values(array_filter($keys, fn ($k) => is_string($k) && preg_match('/^[A-Za-z0-9_-]{1,60}$/', $k)));
        // a new row gets the first row's keys plus any declared sub-field keys
        // (a list starting with an image row must still offer a video's src)
        $list[] = is_array($first) ? array_fill_keys(array_values(array_unique(array_merge(array_keys($first), $keys))), '')
            : ($keys !== [] ? array_fill_keys($keys, '') : '');
        data_set($this, $path, $list);
    }

    /**
     * Media picked for a NESTED value (gallery "+ Add", or a picker next to a
     * leaf inside a list/group). Returns true when handled. Context:
     * {scope:'nested-media', path:'itemForm.images', append:true|false}
     */
    protected function nestedMediaPicked(array $context, string $url): bool
    {
        if (($context['scope'] ?? '') !== 'nested-media' || ! is_string($path = $context['path'] ?? null) || $url === '') {
            return false;
        }
        $this->nestedGuardPath($path);
        if (! empty($context['append'])) {
            $list = (array) data_get($this, $path, []);
            if (! array_is_list($list)) {
                return true;
            }
            $list[] = $url;
            data_set($this, $path, $list);
        } else {
            data_set($this, $path, $url);
        }

        return true;
    }

    public function nestedRemove(string $path, int $j): void
    {
        $this->nestedGuardPath($path);
        $list = (array) data_get($this, $path, []);
        if (array_is_list($list) && array_key_exists($j, $list)) {
            array_splice($list, $j, 1);
            data_set($this, $path, $list);
        }
    }

    public function nestedMove(string $path, int $j, int $dir): void
    {
        $this->nestedGuardPath($path);
        $list = (array) data_get($this, $path, []);
        $k = $j + ($dir < 0 ? -1 : 1);
        if (array_is_list($list) && isset($list[$j], $list[$k])) {
            [$list[$j], $list[$k]] = [$list[$k], $list[$j]];
            data_set($this, $path, $list);
        }
    }
}
