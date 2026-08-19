<?php

namespace App\Services\SiteConnect;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Splits an ingested page's HTML into top-level SECTIONS — the units that get
 * classified. A section is `header`/`nav`/`footer`, or a direct element child of
 * `<main>` (falling back to `<body>`). Each section is returned with its tag and
 * its own HTML for downstream classification + preview fidelity.
 */
class PageExtractor
{
    /**
     * @return array<int,array{tag:string, html:string, crawler:Crawler}>
     */
    public function sections(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $crawler = new Crawler;
        $crawler->addHtmlContent('<div id="__olx_doc__">'.$html.'</div>', 'UTF-8');

        // Prefer <main>'s children; else <body>'s; else the wrapper's.
        $container = $this->firstNode($crawler, '//main')
            ?? $this->firstNode($crawler, '//body')
            ?? $this->firstNode($crawler, '//*[@id="__olx_doc__"]');

        if (! $container) {
            return [];
        }

        $sections = [];
        $position = 0;

        // Landmark regions anywhere in the doc, in document order, plus the
        // content container's own element children.
        $landmarks = $crawler->filterXPath('//header | //nav | //footer');
        foreach ($landmarks as $node) {
            $sections[] = $this->section($node, $position++);
        }

        foreach ($container->childNodes as $node) {
            if ($node instanceof \DOMElement && ! in_array(strtolower($node->tagName), ['header', 'nav', 'footer'], true)) {
                $sections[] = $this->section($node, $position++);
            }
        }

        // Drop empty shells (whitespace-only).
        return array_values(array_filter($sections, fn ($s) => trim(strip_tags($s['html'])) !== '' || str_contains($s['html'], '<img')));
    }

    private function section(\DOMNode $node, int $position): array
    {
        $html = $node->ownerDocument->saveHTML($node);

        return [
            'position' => $position,
            'tag' => strtolower($node->nodeName),
            'html' => trim((string) $html),
            'crawler' => new Crawler($node),
        ];
    }

    private function firstNode(Crawler $crawler, string $xpath): ?\DOMNode
    {
        $found = $crawler->filterXPath($xpath);

        return $found->count() ? $found->getNode(0) : null;
    }
}
