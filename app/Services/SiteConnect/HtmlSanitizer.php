<?php

namespace App\Services\SiteConnect;

/**
 * Defensive sanitiser for INGESTED third-party HTML/CSS. Ingested markup is
 * untrusted — it is stored, re-rendered in the CMS preview iframe, and baked
 * into exports, so every path that stores it goes through here first.
 *
 * Strips: <script>/<noscript>, event-handler attributes (on*), javascript:
 * URLs, <iframe>/<object>/<embed>. CSS is stripped of @import to foreign
 * origins and expression().
 */
class HtmlSanitizer
{
    private const STRIP_TAGS = ['script', 'noscript', 'iframe', 'object', 'embed', 'template', 'base', 'link'];

    private const URL_ATTRS = ['href', 'src', 'xlink:href', 'action', 'formaction', 'srcset'];

    public function html(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        // Force UTF-8 and parse as a fragment-tolerant full document.
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="__olx_root__">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Remove dangerous elements.
        foreach (self::STRIP_TAGS as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        // <meta http-equiv> (refresh redirects, CSP overrides, …) — charset/name
        // metas are harmless, http-equiv is an attack vector in published pages.
        foreach (iterator_to_array($dom->getElementsByTagName('meta')) as $node) {
            if ($node instanceof \DOMElement && $node->hasAttribute('http-equiv')) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Strip event handlers + javascript: URLs from every element.
        foreach ($xpath->query('//*') as $el) {
            if (! $el instanceof \DOMElement) {
                continue;
            }
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $name = strtolower($attr->name);
                $value = trim($attr->value);
                if (str_starts_with($name, 'on')) {
                    $el->removeAttribute($attr->name);

                    continue;
                }
                if (in_array($name, self::URL_ATTRS, true)) {
                    // javascript: always; data: unless it's an inline image —
                    // data:text/html and friends execute in the page's origin.
                    if (preg_match('/^\s*javascript:/i', $value)
                        || (preg_match('/^\s*data:/i', $value) && ! preg_match('/^\s*data:image\//i', $value))) {
                        $el->removeAttribute($attr->name);

                        continue;
                    }
                }
                if ($name === 'formtarget' || $name === 'target' && strtolower($value) === '_top') {
                    $el->removeAttribute($attr->name);

                    continue;
                }
                if ($name === 'style') {
                    $el->setAttribute('style', $this->css($value));
                }
            }
        }

        $root = $dom->getElementById('__olx_root__');
        $out = '';
        foreach ($root?->childNodes ?? [] as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /** Strip @import and expression() from a CSS string; re-serialise as-is. */
    public function css(string $css): string
    {
        $css = preg_replace('/@import\b[^;]+;/i', '', $css) ?? '';
        $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css) ?? '';
        // Neutralise javascript: inside url(...).
        $css = preg_replace('/url\(\s*["\']?\s*javascript:[^)]*\)/i', 'none', $css) ?? '';

        return trim($css);
    }
}
