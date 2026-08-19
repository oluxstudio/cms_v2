<?php

namespace App\Services\SiteConnect;

use App\Models\IngestedSection;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Classifies one extracted section into component | collection | post | form,
 * with a confidence in [0,1]. Below IngestedSection::REVIEW_THRESHOLD the caller
 * flags it for human review rather than auto-committing — we never silently
 * guess.
 *
 * Identification mirrors the SOURCE shape, as it renders into the DOM:
 *   form       — a <form> tag → a CMS form built from its child fields.
 *   collection — an ARRAY source (v-for / repeated siblings, or a <ul>/<ol>
 *                list) → 2+ items sharing a tag+class shape.
 *   post       — an <article>, or the page URL matches a blog/news pattern.
 *   component  — an OBJECT/variable source (a single, non-repeating block) →
 *                the default.
 */
class ContentClassifier
{
    /**
     * @return array{classification:string, confidence:float, fields:array}
     */
    public function classify(Crawler $section, string $sourceUrl = ''): array
    {
        if ($form = $this->form($section)) {
            return $form;
        }
        if ($collection = $this->collection($section)) {
            return $collection;
        }
        if ($post = $this->post($section, $sourceUrl)) {
            return $post;
        }

        return $this->component($section);
    }

    /**
     * Force-extract a section's fields AS a chosen type — used when a human
     * reclassifies in the review queue. Falls back to component fields if the
     * forced extractor finds nothing to work with.
     *
     * @return array the `fields` payload for that type
     */
    public function extractAs(Crawler $section, string $type): array
    {
        $result = match ($type) {
            IngestedSection::FORM => $this->form($section),
            IngestedSection::COLLECTION => $this->collection($section),
            IngestedSection::POST => $this->post($section, ''),
            default => $this->component($section),
        };

        if ($result === null) {
            // The section doesn't fit the forced type — keep the human's choice
            // but seed it with the generic component fields so nothing is lost.
            $result = $this->component($section);
        }

        return $result['fields'] ?? [];
    }

    // --- form ---------------------------------------------------------------
    private function form(Crawler $section): ?array
    {
        $forms = $section->filter('form');
        if (! $forms->count()) {
            return null;
        }
        $form = $forms->first();

        $fields = [];
        $form->filter('input, textarea, select')->each(function (Crawler $el) use (&$fields) {
            $type = $el->nodeName() === 'textarea' ? 'textarea'
                : ($el->nodeName() === 'select' ? 'select' : ($el->attr('type') ?: 'text'));
            if (in_array($type, ['submit', 'button', 'hidden', 'reset'], true)) {
                return;
            }
            $name = $el->attr('name') ?: $el->attr('id') ?: 'field_'.count($fields);
            $fields[] = array_filter([
                'name' => $name,
                'type' => $type,
                'label' => $this->labelFor($el, $name),
                'required' => $el->attr('required') !== null,
                'options' => $type === 'select'
                    ? $el->filter('option')->each(fn (Crawler $o) => trim($o->text()))
                    : null,
            ], fn ($v) => $v !== null);
        });

        return [
            'classification' => IngestedSection::FORM,
            'confidence' => 0.97,
            'fields' => [
                'intent' => $this->formIntent($fields, $form),
                'action' => $form->attr('action'),
                'fields' => $fields,
            ],
        ];
    }

    private function formIntent(array $fields, Crawler $form): string
    {
        $names = strtolower(implode(' ', array_column($fields, 'name')).' '.$form->text(''));
        if (str_contains($names, 'subscribe') || str_contains($names, 'newsletter')) {
            return 'newsletter';
        }
        if (str_contains($names, 'book') || str_contains($names, 'appointment') || str_contains($names, 'date')) {
            return 'booking';
        }

        return 'contact';
    }

    // --- collection ---------------------------------------------------------
    private function collection(Crawler $section): ?array
    {
        $best = $this->repeatingGroup($section);
        if (! $best) {
            return null;
        }

        [$items, $uniformity] = $best;
        $count = count($items);

        // 2 uniform items → ~0.75, scaling up with count and shape uniformity.
        $confidence = min(0.98, 0.6 + min($count, 8) * 0.03 + $uniformity * 0.18);

        $rows = array_map(fn (Crawler $item) => $this->itemFields($item), $items);
        $schema = collect($rows)->flatMap(fn ($r) => array_keys($r))
            ->countBy()->filter(fn ($n) => $n >= max(1, (int) floor($count / 2)))
            ->keys()->values()->all();

        return [
            'classification' => IngestedSection::COLLECTION,
            'confidence' => round($confidence, 3),
            'fields' => ['schema' => $schema, 'items' => $rows],
        ];
    }

    /**
     * Find the container whose direct children best form a repeating set (3+
     * siblings sharing a tag+class signature). Returns [items, uniformity 0..1].
     *
     * @return array{0:array<int,Crawler>,1:float}|null
     */
    private function repeatingGroup(Crawler $section): ?array
    {
        $bestItems = [];
        $bestUniformity = 0.0;

        $containers = $section->filterXPath('descendant-or-self::*');
        $containers->each(function (Crawler $container) use (&$bestItems, &$bestUniformity) {
            $children = $container->children();
            // An array needs at least two rendered items. For <ul>/<ol> the <li>
            // children ARE the array even without shared classes.
            $isList = in_array(strtolower($container->nodeName()), ['ul', 'ol'], true);
            if ($children->count() < 2) {
                return;
            }
            $signatures = $children->each(fn (Crawler $c) => $isList ? 'li' : $c->nodeName().'.'.($c->attr('class') ?: ''));
            $top = collect($signatures)->countBy()->sortDesc();
            $dominant = $top->keys()->first();
            $matches = $top->first();
            if ($matches < 2) {
                return;
            }
            $uniformity = $matches / max(1, count($signatures));
            if ($matches > count($bestItems) || ($matches === count($bestItems) && $uniformity > $bestUniformity)) {
                // Crawler::reduce is a FILTER (callback returns bool) → keep only
                // the dominant-signature siblings, then realise them as an array.
                $bestItems = $children
                    ->reduce(fn (Crawler $c) => ($isList ? 'li' : $c->nodeName().'.'.($c->attr('class') ?: '')) === $dominant)
                    ->each(fn (Crawler $c) => $c);
                $bestUniformity = $uniformity;
            }
        });

        return count($bestItems) >= 2 ? [$bestItems, $bestUniformity] : null;
    }

    /** Infer a repeating item's fields from its content types. */
    private function itemFields(Crawler $item): array
    {
        $out = [];
        $heading = $item->filter('h1,h2,h3,h4,h5,h6')->first();
        if ($heading->count()) {
            $out['title'] = trim($heading->text(''));
        }
        $para = $item->filter('p')->first();
        if ($para->count()) {
            $out['description'] = trim($para->text(''));
        }
        $img = $item->filter('img')->first();
        if ($img->count()) {
            $out['image'] = $img->attr('src');
        }
        $link = $item->filter('a')->first();
        if ($link->count()) {
            $out['link'] = $link->attr('href');
            // A bare list item (e.g. a nav/menu array) — use the link text as its title.
            if (empty($out['title'])) {
                $out['title'] = trim($link->text(''));
            }
        }
        if (preg_match('/[£$€]\s?\d[\d.,]*/u', $item->text(''), $m)) {
            $out['price'] = trim($m[0]);
        }

        return $out;
    }

    // --- post ---------------------------------------------------------------
    private function post(Crawler $section, string $sourceUrl): ?array
    {
        $path = strtolower(parse_url($sourceUrl, PHP_URL_PATH) ?: '');
        $isBlogUrl = (bool) preg_match('#/(blog|news|article|post)s?/#', $path);
        $hasArticle = $section->filter('article')->count() > 0;
        $hasByline = $section->filter('time, [rel="author"], .byline, .author')->count() > 0;

        if (! $hasArticle && ! ($isBlogUrl && $hasByline)) {
            return null;
        }

        $confidence = ($hasArticle ? 0.7 : 0.0) + ($isBlogUrl ? 0.2 : 0.0) + ($hasByline ? 0.1 : 0.0);
        $heading = $section->filter('h1,h2')->first();
        $time = $section->filter('time')->first();

        return [
            'classification' => IngestedSection::POST,
            'confidence' => round(min(0.98, $confidence), 3),
            'fields' => [
                'title' => $heading->count() ? trim($heading->text('')) : null,
                'body' => $section->html(''),
                'excerpt' => $this->firstParagraph($section),
                'publishedAt' => $time->count() ? ($time->attr('datetime') ?: trim($time->text(''))) : null,
                'image' => $section->filter('img')->count() ? $section->filter('img')->first()->attr('src') : null,
            ],
        ];
    }

    // --- component (default) ------------------------------------------------
    private function component(Crawler $section): array
    {
        $headings = $section->filter('h1,h2,h3');
        $paras = $section->filter('p');
        $img = $section->filter('img')->first();
        $cta = $section->filter('a')->first();

        $fields = array_filter([
            'heading' => $headings->count() ? trim($headings->first()->text('')) : null,
            'subheading' => $headings->count() > 1 ? trim($headings->eq(1)->text('')) : null,
            'body' => $paras->count() ? trim($paras->first()->text('')) : null,
            'image' => $img->count() ? $img->attr('src') : null,
            'cta' => $cta->count() ? ['label' => trim($cta->text('')), 'href' => $cta->attr('href')] : null,
        ], fn ($v) => $v !== null && $v !== '');

        // More recognisable structure → higher confidence, but a component is the
        // fallback so it never claims certainty.
        $signals = ($headings->count() ? 1 : 0) + ($paras->count() ? 1 : 0) + ($img->count() ? 1 : 0);
        $confidence = [0 => 0.4, 1 => 0.6, 2 => 0.75, 3 => 0.82][$signals] ?? 0.82;

        return [
            'classification' => IngestedSection::COMPONENT,
            'confidence' => $confidence,
            'fields' => $fields,
        ];
    }

    // --- helpers ------------------------------------------------------------
    private function labelFor(Crawler $el, string $name): string
    {
        // A <label for="id"> anywhere in the form (the form's child structure).
        if ($id = $el->attr('id')) {
            $doc = $el->getNode(0)?->ownerDocument;
            if ($doc) {
                foreach ((new \DOMXPath($doc))->query('//label[@for="'.$id.'"]') as $label) {
                    $text = trim($label->textContent);
                    if ($text !== '') {
                        return $text;
                    }
                }
            }
        }
        if ($ph = $el->attr('placeholder')) {
            return $ph;
        }
        if ($aria = $el->attr('aria-label')) {
            return $aria;
        }

        return Str::headline($name);
    }

    private function firstParagraph(Crawler $section): ?string
    {
        $p = $section->filter('p')->first();

        return $p->count() ? Str::limit(trim($p->text('')), 160) : null;
    }
}
