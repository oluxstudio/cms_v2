<?php

namespace App\Support;

/**
 * Allow-list sanitizer for INLINE rich text in content blocks (the CMS side
 * of `content`/`header` props). One definition, three consumers: cleaned at
 * SAVE (BlockTreeService), re-cleaned at payload/export time as defense in
 * depth. Everything not explicitly allowed is stripped: unknown tags are
 * unwrapped (their text kept), dangerous elements are removed entirely, and
 * every on* handler / javascript: URL dies here.
 */
class RichText
{
    /** Inline formatting only — block structure comes from blocks, not markup. */
    private const ALLOWED_TAGS = ['b', 'strong', 'i', 'em', 'u', 's', 'span', 'br', 'a', 'mark', 'small', 'sub', 'sup', 'code'];

    /** Elements whose CONTENT is dangerous too — dropped wholesale. */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript'];

    /** span[style]: only these CSS properties survive. */
    private const STYLE_PROPS = '/^(color|font-size|font-weight|text-decoration|letter-spacing)$/i';

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if ($html === '' || ! str_contains($html, '<')) {
            return $html; // plain text: nothing to do
        }

        $doc = new \DOMDocument;
        // Wrap so multiple root nodes + encoding survive; suppress warnings on junk HTML.
        @$doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="rt-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        $root = $doc->getElementById('rt-root');
        if (! $root) {
            return strip_tags($html);
        }

        self::walk($root, $doc);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(\DOMNode $node, \DOMDocument $doc): void
    {
        // Iterate a static list — we mutate the tree as we go.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMCdataSection || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue; // text nodes pass through
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap: keep the (sanitized) children, drop the tag itself.
                self::walk($child, $doc);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            self::scrubAttributes($child, $tag);
            self::walk($child, $doc);
        }
    }

    private static function scrubAttributes(\DOMElement $el, string $tag): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $keep = false;

            if ($name === 'class') {
                $keep = true; // every allowed tag may carry classes (validated below)
            } elseif ($tag === 'a' && in_array($name, ['href', 'target', 'rel'], true)) {
                $keep = true;
            } elseif ($tag === 'span' && $name === 'style') {
                $keep = true;
            }

            if (! $keep) {
                $el->removeAttribute($attr->name);
            }
        }

        // class: plain CSS tokens only — no quotes, braces or escapes.
        if ($el->hasAttribute('class')) {
            $classes = trim(preg_replace('/[^-_a-zA-Z0-9 ]+/', '', $el->getAttribute('class')));
            $classes === '' ? $el->removeAttribute('class') : $el->setAttribute('class', $classes);
        }

        if ($tag === 'a') {
            $href = trim($el->getAttribute('href'));
            // Safe schemes only — javascript:/data:/vbscript: die here.
            if ($href !== '' && ! preg_match('~^(https?://|mailto:|tel:|#|/|\./|\.\./)~i', $href)) {
                $el->removeAttribute('href');
            }
            if (strtolower($el->getAttribute('target')) === '_blank') {
                $el->setAttribute('rel', 'noopener');
            } elseif ($el->hasAttribute('target')) {
                $el->removeAttribute('target');
            }
        }

        if ($tag === 'span' && $el->hasAttribute('style')) {
            $safe = [];
            foreach (explode(';', $el->getAttribute('style')) as $decl) {
                [$prop, $value] = array_pad(explode(':', $decl, 2), 2, '');
                $prop = trim($prop);
                $value = trim($value);
                // No url()/expression()/escapes sneaking through a value.
                if ($prop !== '' && $value !== ''
                    && preg_match(self::STYLE_PROPS, $prop)
                    && ! preg_match('/url\s*\(|expression|javascript|\\\\/i', $value)) {
                    $safe[] = "{$prop}:{$value}";
                }
            }
            $safe === [] ? $el->removeAttribute('style') : $el->setAttribute('style', implode(';', $safe));
        }
    }
}
