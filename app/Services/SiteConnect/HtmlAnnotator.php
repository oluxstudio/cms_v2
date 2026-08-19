<?php

namespace App\Services\SiteConnect;

use App\Models\IngestedSection;

/**
 * Annotates an ingested section's HTML with data-olx-* attributes so the
 * exported client page can be hydrated by connect.js: the section root gets a
 * data-olx-id (matching its committed content id), editable nodes get
 * data-olx-field, repeating collection items get data-olx-item, and forms get
 * data-olx-id so their action is rewired.
 *
 * v1 annotates the primary text/image fields + collection items + forms. Nested
 * CTA objects stay baked (not live-editable yet) — documented in
 * docs/site-connect.md.
 */
class HtmlAnnotator
{
    /** Return the section HTML wrapped + annotated, or '' if not committed. */
    public function annotate(IngestedSection $section): string
    {
        if (! $section->committed_ref_id) {
            return '';
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="__olx_wrap__" data-olx-id="'
            .htmlspecialchars($section->committed_ref_id, ENT_QUOTES).'" data-olx-type="'
            .$section->classification.'">'.$section->html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $wrap = $dom->getElementById('__olx_wrap__');

        match ($section->classification) {
            IngestedSection::COMPONENT => $this->annotateComponent($xpath, $wrap),
            IngestedSection::COLLECTION => $this->annotateCollection($xpath, $wrap),
            default => null, // forms just need the data-olx-id on the wrapper
        };

        return $dom->saveHTML($wrap);
    }

    private function annotateComponent(\DOMXPath $xpath, \DOMElement $wrap): void
    {
        $headings = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $wrap);
        if ($headings->length > 0) {
            $this->field($headings->item(0), 'heading');
        }
        if ($headings->length > 1) {
            $this->field($headings->item(1), 'subheading');
        }
        $paras = $xpath->query('.//p', $wrap);
        if ($paras->length > 0) {
            $this->field($paras->item(0), 'body');
        }
        $imgs = $xpath->query('.//img', $wrap);
        if ($imgs->length > 0) {
            $this->field($imgs->item(0), 'image');
        }
    }

    private function annotateCollection(\DOMXPath $xpath, \DOMElement $wrap): void
    {
        // Re-find the repeating group (same rule as the classifier): the parent
        // whose children mostly share a tag+class signature.
        $best = null;
        $bestCount = 0;
        foreach ($xpath->query('.//*', $wrap) as $container) {
            $children = $this->elementChildren($container);
            if (count($children) < 3) {
                continue;
            }
            $sigs = array_map(fn ($c) => $c->nodeName.'.'.($c->getAttribute('class') ?: ''), $children);
            $counts = array_count_values($sigs);
            arsort($counts);
            $top = array_key_first($counts);
            $n = $counts[$top];
            if ($n >= 3 && $n > $bestCount) {
                $bestCount = $n;
                $best = array_values(array_filter($children, fn ($c) => $c->nodeName.'.'.($c->getAttribute('class') ?: '') === $top));
            }
        }
        if (! $best) {
            return;
        }

        foreach ($best as $item) {
            $item->setAttribute('data-olx-item', '');
            $ix = new \DOMXPath($item->ownerDocument);
            if (($h = $ix->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $item))->length) {
                $this->field($h->item(0), 'title');
            }
            if (($p = $ix->query('.//p', $item))->length) {
                $this->field($p->item(0), 'description');
            }
            if (($img = $ix->query('.//img', $item))->length) {
                $this->field($img->item(0), 'image');
            }
        }
    }

    private function field(\DOMNode $node, string $name): void
    {
        if ($node instanceof \DOMElement && ! $node->hasAttribute('data-olx-field')) {
            $node->setAttribute('data-olx-field', $name);
        }
    }

    /** @return array<int,\DOMElement> */
    private function elementChildren(\DOMNode $node): array
    {
        $out = [];
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMElement) {
                $out[] = $c;
            }
        }

        return $out;
    }
}
