<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Extracts CMS form definitions from a template's authored <form> markup so
 * every form the template ships exists as a real, submittable site form —
 * no hand-curation needed. Fields come from the inputs/textareas/selects
 * inside each form (label text, name attribute, html type, required flag).
 *
 * The form's name comes from a `data-olx-form="…"` attribute when the
 * author sets one, else from the component/page file name. Forms that are
 * really module UIs (payment fields, search boxes, single-field widgets
 * without a name/email) are skipped.
 */
class FormSourceExtractor
{
    private const TYPE_MAP = [
        'email' => 'email', 'tel' => 'tel', 'number' => 'number', 'date' => 'date',
        'checkbox' => 'checkbox', 'text' => 'text', 'url' => 'text',
    ];

    /** @return array<int,array{name:string,title:string,fields:array}> */
    public function fromSources(string $root): array
    {
        $forms = [];
        $files = array_merge(
            File::glob("$root/app/components/*.vue") ?: [],
            File::glob("$root/app/pages/*.vue") ?: [],
        );

        foreach ($files as $file) {
            $code = File::get($file);
            if (! preg_match_all('#<form\b([^>]*)>(.*?)</form>#s', $code, $m, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $i => $hit) {
                [, $attrs, $body] = $hit;
                $component = preg_replace('#Block$#', '', basename($file, '.vue'));
                $name = preg_match('#data-olx-form="([\w -]+)"#', $attrs, $fm)
                    ? Str::slug($fm[1])
                    : Str::kebab($component).($i > 0 ? '-'.($i + 1) : '');

                $fields = $this->fields($body);
                $keys = array_column($fields, 'key');
                // Only real lead/contact-style forms: needs a way to reach the
                // sender back. Payment/search/widget forms have neither.
                if (! array_intersect($keys, ['email', 'phone', 'name'])
                    || preg_match('#cc-number|card|cvc#i', $body)) {
                    continue;
                }

                $forms[$name] = [
                    'name' => $name,
                    'title' => Str::headline($name),
                    'fields' => $fields,
                ];
            }
        }

        return array_values($forms);
    }

    private function fields(string $body): array
    {
        $fields = [];
        preg_match_all('#<(input|textarea|select)\b([^>]*)/?>#', $body, $m, PREG_SET_ORDER);
        foreach ($m as [$tag, $el, $attrs]) {
            $type = strtolower(preg_match('#\btype="([\w-]+)"#', $attrs, $t) ? $t[1] : 'text');
            if (in_array($type, ['submit', 'button', 'hidden', 'password', 'search'], true)) {
                continue;
            }
            // Key: the name attribute, else v-model tail, else the html type.
            $key = preg_match('#\bname="([\w-]+)"#', $attrs, $n) ? $n[1]
                : (preg_match('#v-model(?:\.\w+)*="[\w.]*?(\w+)"#', $attrs, $v) ? $v[1] : $type);
            $key = Str::slug($key, '_');
            if ($key === '' || isset($fields[$key])) {
                continue;
            }
            // Label: the placeholder reads best; fall back to the key.
            $label = preg_match('#placeholder="([^"]+)"#', $attrs, $p)
                ? Str::headline(Str::limit($p[1], 40, ''))
                : Str::headline($key);

            $fields[$key] = [
                'key' => $key,
                'label' => $el === 'textarea' ? $label : ($key === 'name' ? 'Name' : $label),
                'type' => $el === 'textarea' ? 'textarea'
                    : ($el === 'select' ? 'select' : (self::TYPE_MAP[$type] ?? 'text')),
                'required' => str_contains($attrs, 'required'),
            ];
        }

        return array_values($fields);
    }
}
