<?php

namespace App\Services;

use App\Services\Vue\SfcParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Extracts the CMS-editable structure from a Nuxt template app (staging folder):
 * pages → ordered blocks → editable nodes (fixed + repeatable items), plus
 * theme tokens, fonts, script behaviours and an asset summary.
 *
 * Convention-based: no manifest required from template authors. The result is
 * written to {app}/.olux/extraction.json and stored on the TemplateSubmission.
 */
class TemplateExtractor
{
    /** CDN keyword → behaviour label, matched against nuxt.config head URLs. */
    private const BEHAVIOURS = [
        'swiper'      => 'carousel',
        'aos'         => 'scroll-animations',
        'glightbox'   => 'lightbox',
        'bootstrap'   => 'bootstrap',
        'isotope'     => 'filterable-grid',
        'purecounter' => 'counters',
        'typed'       => 'typing-effect',
    ];

    /** Item object key → node field label. */
    private const FIELD_LABELS = [
        'img' => 'Image', 'image' => 'Image', 'avatar' => 'Image', 'photo' => 'Image',
        'name' => 'Name', 'role' => 'Role', 'text' => 'Text', 'title' => 'Title',
        'desc' => 'Description', 'description' => 'Description', 'price' => 'Price',
        'question' => 'Question', 'answer' => 'Answer', 'quote' => 'Quote',
        'link' => 'Link', 'url' => 'Link', 'icon' => 'Icon', 'delay' => 'Delay',
    ];

    /** Extract a staging app by folder key. Returns the manifest array. */
    public function extract(string $key): array
    {
        $root = rtrim(config('templates.staging_path'), '/').'/'.$key;
        if (! File::isDirectory("$root/app/pages")) {
            throw new \RuntimeException("Not a Nuxt template app: {$key} (missing app/pages)");
        }

        $manifest = [
            'key'          => $key,
            'name'         => Str::headline($key),
            'extracted_at' => now()->toIso8601String(),
            'theme'        => $this->theme($root),
            'fonts'        => [],
            'behaviours'   => [],
            'assets'       => $this->assets($root),
            'pages'        => [],
        ];

        [$manifest['fonts'], $manifest['behaviours']] = $this->headMeta($root);

        foreach (File::files("$root/app/pages") as $pageFile) {
            if ($pageFile->getExtension() !== 'vue') {
                continue;
            }
            $manifest['pages'][] = $this->page($root, $pageFile->getPathname());
        }

        File::ensureDirectoryExists("$root/.olux");
        File::put("$root/.olux/extraction.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $manifest;
    }

    // ─────────────────────────────────────────────── pages & blocks

    private function page(string $root, string $file): array
    {
        $src = File::get($file);
        $sections = SfcParser::sections($src);
        $slug = basename($file, '.vue');
        $url = $slug === 'index' ? '/' : '/'.Str::slug($slug);

        $page = [
            'name'   => $slug === 'index' ? 'Home' : Str::headline($slug),
            'title'  => SfcParser::useHeadTitle($sections['script']),
            'url'    => $url,
            'file'   => 'app/pages/'.basename($file),
            'layout' => ['header' => null, 'footer' => null],
            'blocks' => [],
        ];

        foreach (SfcParser::pageComponents($sections['template'] ?? '') as $component) {
            $compFile = "$root/app/components/{$component}.vue";
            if (! File::exists($compFile)) {
                continue; // not a local component (e.g. NuxtLink)
            }
            // Header/footer chrome is noted for the manifest AND extracted as an
            // editable block — its menu links, contact info etc. are content too.
            if (preg_match('/^App(Header|Nav)/', $component)) {
                $page['layout']['header'] = $component;
            } elseif (preg_match('/Footer$/', $component)) {
                $page['layout']['footer'] = $component;
            }
            $page['blocks'][] = $this->block($component, File::get($compFile));
        }

        return $page;
    }

    /** Extract one block component into blockKey + nodes (+ repeatable items). */
    private function block(string $component, string $src): array
    {
        $sections = SfcParser::sections($src);
        $base = preg_replace('/^App(?=[A-Z])/', '', preg_replace('/Block$/', '', $component));
        $blockKey = Str::kebab($base);

        $block = [
            'component' => $component,
            'blockKey'  => $blockKey,
            'name'      => Str::headline($base),
            'nodes'     => [],
            'items'     => null,
        ];

        // ── Fixed nodes from the template, with digit-free labels ──
        $fields = SfcParser::templateFields($sections['template'] ?? '');
        $order = 0;
        foreach (self::labelFixedFields($fields['fixed']) as $f) {
            if ($f['kind'] === 'cta') {
                $block['nodes'][] = ['label' => $f['label'], 'type' => 'text', 'value' => $f['value'], 'kind' => 'text', 'order' => $order++];
                if (($f['href'] ?? '') !== '') {
                    $block['nodes'][] = ['label' => $f['linkLabel'], 'type' => 'url', 'value' => $f['href'], 'kind' => 'attr:href', 'order' => $order++];
                }

                continue;
            }
            $block['nodes'][] = [
                'label' => $f['label'],
                'type'  => $f['kind'] === 'image' ? 'image' : 'text',
                'value' => $f['value'],
                'kind'  => $f['kind'] === 'image' ? 'attr:src' : $f['kind'],
                'order' => $order++,
            ];
        }

        // ── Standalone script strings (e.g. a shared FAQ answer) → fixed nodes ──
        foreach (SfcParser::scalarStrings($sections['script']) as $var => $value) {
            $block['nodes'][] = [
                'label' => Str::headline($var),
                'type'  => 'text',
                'value' => $value,
                'kind'  => 'const:'.$var,
                'order' => $order++,
            ];
        }

        // ── Repeatable items: EVERY script data array becomes its own group
        //    (a block can have e.g. both nav links and social links). ──
        $arrays = SfcParser::dataArrays($sections['script']);
        $groups = [];
        $usedPrefixes = [];
        foreach ($arrays as $var => $rows) {
            $prefix = $this->itemPrefix($base, $var);
            // Prefixes must be unique per block — they drive node clustering.
            while (in_array($prefix, $usedPrefixes, true)) {
                $prefix .= ' '.Str::ucfirst($var);
            }
            $usedPrefixes[] = $prefix;
            $fieldKeys = array_keys($rows[0]);
            // A plain string array (['q1','q2']) is a "scalar" group: one field
            // per item, node label is just "{Prefix} {n}".
            $scalar = $fieldKeys === ['_value'];

            $groups[] = [
                'var'         => $var,
                'prefix'      => $prefix,
                'scalar'      => $scalar,
                'fields'      => $scalar
                    ? [['key' => '_value', 'label' => '']]
                    : array_map(fn ($k) => ['key' => $k, 'label' => self::FIELD_LABELS[$k] ?? Str::headline($k)], $fieldKeys),
                'imagePrefix' => $fields['itemImagePrefix'],
                'count'       => count($rows),
                'values'      => $rows,
            ];

            // Emit item nodes in the "{Prefix} {n} {Field}" convention the
            // Configure inspector already clusters into repeatable groups.
            foreach ($rows as $i => $row) {
                foreach ($row as $k => $v) {
                    $fieldLabel = self::FIELD_LABELS[$k] ?? Str::headline($k);
                    $isImg = ! $scalar && $fieldLabel === 'Image';
                    $block['nodes'][] = [
                        'label' => $scalar ? $prefix.' '.($i + 1) : $prefix.' '.($i + 1).' '.$fieldLabel,
                        'type'  => $isImg ? 'image' : 'text',
                        'value' => $isImg && $fields['itemImagePrefix'] ? $fields['itemImagePrefix'].$v : $v,
                        'kind'  => 'item:'.$k,
                        'order' => $order++,
                    ];
                }
            }
        }
        $block['items'] = $groups ?: null;

        return $block;
    }

    /**
     * Assign stable, digit-free labels to parsed fixed fields, in document order.
     * Shared with SfcRewriter so rewrites resolve the exact same labels the
     * extraction manifest recorded. CTA fields get label + linkLabel.
     *
     * @param  array<int,array<string,mixed>>  $fixed  SfcParser::templateFields()['fixed']
     * @return array<int,array<string,mixed>>  same rows + 'label' (and 'linkLabel')
     */
    public static function labelFixedFields(array $fixed): array
    {
        $counters = [];
        $suffix = function (string $label) use (&$counters): string {
            $n = $counters[$label] = ($counters[$label] ?? 0) + 1;
            // Letter suffixes: digits in labels would trigger item clustering.
            return $n === 1 ? $label : $label.' '.chr(64 + $n); // B, C, …
        };

        $out = [];
        foreach ($fixed as $f) {
            switch ($f['kind']) {
                case 'image':
                    $f['label'] = $suffix('Image');
                    break;
                case 'cta':
                    // Contact links read as what they are, not as call-to-actions.
                    $href = strtolower((string) ($f['href'] ?? ''));
                    $base = str_starts_with($href, 'mailto:') ? 'Email'
                        : (str_starts_with($href, 'tel:') ? 'Phone' : null);
                    if ($base !== null) {
                        $lbl = $suffix($base);
                        $f['label'] = $lbl;
                        $f['linkLabel'] = "$lbl Link";
                    } else {
                        $lbl = $suffix('CTA');
                        $f['label'] = "$lbl Label";
                        $f['linkLabel'] = "$lbl Link";
                    }
                    break;
                case 'html':
                case 'text':
                    $f['label'] = $suffix(match ($f['tag']) {
                        'h1', 'h2' => 'Headline',
                        'h3', 'h4', 'h5' => 'Subheadline',
                        'span'     => 'Caption',
                        default    => 'Text',
                    });
                    break;
                default:
                    continue 2;
            }
            $out[] = $f;
        }

        return $out;
    }

    /** Singular, human item prefix: TestimonialsBlock → "Testimonial". */
    private function itemPrefix(string $base, string $var): string
    {
        $fromVar = in_array(strtolower($var), ['items', 'list', 'data', 'rows'], true) ? null : Str::headline($var);
        $candidate = $fromVar ?: Str::headline($base);
        $words = explode(' ', $candidate);
        $last = Str::singular(end($words));
        if (mb_strlen($last) >= 3) {
            $words[count($words) - 1] = $last;

            return implode(' ', $words);
        }

        return $candidate.' Item';
    }

    // ─────────────────────────────────────────────── theme / fonts / assets

    /** CSS custom properties from :root in the app's stylesheets. */
    private function theme(string $root): array
    {
        $vars = [];
        $dir = "$root/public/assets/stylesheets";
        if (! File::isDirectory($dir)) {
            return $vars;
        }
        foreach (File::files($dir) as $css) {
            if ($css->getExtension() !== 'css') {
                continue;
            }
            $src = File::get($css->getPathname());
            // A stylesheet may declare several :root blocks (fonts, colors, …).
            if (preg_match_all('/:root\s*\{([^}]*)\}/s', $src, $blocks)) {
                foreach ($blocks[1] as $body) {
                    if (preg_match_all('/--([\w-]+)\s*:\s*([^;]+);/', $body, $props, PREG_SET_ORDER)) {
                        foreach ($props as $p) {
                            $vars[$p[1]] = trim($p[2]);
                        }
                    }
                }
            }
        }

        return $vars;
    }

    /** [fonts, behaviours] parsed from nuxt.config.ts head links/scripts. */
    private function headMeta(string $root): array
    {
        $fonts = [];
        $behaviours = [];
        $config = File::exists("$root/nuxt.config.ts") ? File::get("$root/nuxt.config.ts") : '';

        if (preg_match_all('/fonts\.googleapis\.com\/css2?\?([^\'"`]+)/', $config, $m)) {
            foreach ($m[1] as $query) {
                preg_match_all('/family=([^:&]+)/', $query, $fams);
                foreach ($fams[1] as $i => $fam) {
                    $fonts[] = ['family' => str_replace('+', ' ', urldecode($fam)), 'role' => $i === 0 ? 'heading' : 'body'];
                }
            }
        }

        foreach (self::BEHAVIOURS as $needle => $label) {
            if (preg_match('/cdn[^\'"`]*'.preg_quote($needle, '/').'/i', $config)) {
                $behaviours[] = $label;
            }
        }

        return [$fonts, array_values(array_unique($behaviours))];
    }

    /** Asset summary: count, bytes and a capped image manifest. */
    private function assets(string $root): array
    {
        $dir = "$root/public/assets";
        if (! File::isDirectory($dir)) {
            return ['count' => 0, 'bytes' => 0, 'images' => []];
        }
        $count = 0;
        $bytes = 0;
        $images = [];
        foreach (File::allFiles($dir) as $f) {
            $count++;
            $bytes += $f->getSize();
            if (count($images) < 200 && in_array(strtolower($f->getExtension()), ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
                $images[] = '/assets/'.str_replace('\\', '/', $f->getRelativePathname());
            }
        }

        return ['count' => $count, 'bytes' => $bytes, 'images' => $images];
    }
}
