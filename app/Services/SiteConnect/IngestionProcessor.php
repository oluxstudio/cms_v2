<?php

namespace App\Services\SiteConnect;

use App\Models\IngestedSection;
use App\Models\Page;
use App\Models\PageIngestion;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Turns a received PageIngestion into classified IngestedSection rows: sanitise
 * → split into sections → classify each → persist with a confidence + a
 * needs_review flag. Pure staging; nothing touches live content until commit().
 */
class IngestionProcessor
{
    public function __construct(
        private HtmlSanitizer $sanitizer,
        private PageExtractor $extractor,
        private ContentClassifier $classifier,
        private SectionCommitter $committer,
    ) {}

    public function process(PageIngestion $ingestion): PageIngestion
    {
        $ingestion->update(['status' => PageIngestion::STATUS_EXTRACTING]);

        try {
            $cleanHtml = $this->sanitizer->html((string) $ingestion->raw_html);
            $ingestion->sections()->delete(); // re-process is idempotent

            foreach ($this->extractor->sections($cleanHtml) as $raw) {
                $result = $this->classifier->classify($raw['crawler'], $ingestion->source_url);

                IngestedSection::create([
                    'page_ingestion_id' => $ingestion->id,
                    'site_id' => $ingestion->site_id,
                    'position' => $raw['position'],
                    'tag' => $raw['tag'],
                    'html' => $this->sanitizer->html($raw['html']),
                    'classification' => $result['classification'],
                    'confidence' => $result['confidence'],
                    'needs_review' => $result['confidence'] < IngestedSection::REVIEW_THRESHOLD,
                    'fields' => $result['fields'],
                ]);
            }

            $ingestion->update(['status' => PageIngestion::STATUS_CLASSIFIED]);
        } catch (\Throwable $e) {
            $ingestion->update(['status' => PageIngestion::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }

        return $ingestion->fresh();
    }

    /**
     * Materialise the ingestion's confident sections onto a resolved Page.
     * needs_review sections are left for the CMS review queue (Stage 4).
     */
    public function commit(PageIngestion $ingestion, bool $includeReview = false): Page
    {
        $page = $this->resolvePage($ingestion);

        foreach ($ingestion->sections as $section) {
            if ($section->needs_review && ! $includeReview) {
                continue;
            }
            $this->committer->commit($section, $page);
        }

        $ingestion->update(['status' => PageIngestion::STATUS_COMMITTED, 'page_id' => $page->id]);

        return $page;
    }

    /**
     * Human reclassification from the review queue: force a section to a chosen
     * type, re-extract its fields as that type, and clear the review flag
     * (confidence 1.0 = a person decided).
     */
    public function reclassify(IngestedSection $section, string $type): IngestedSection
    {
        $crawler = new Crawler('<div>'.$section->html.'</div>');
        $section->update([
            'classification' => $type,
            'fields' => $this->classifier->extractAs($crawler, $type),
            'confidence' => 1.0,
            'needs_review' => false,
        ]);

        return $section;
    }

    /** Commit a single reviewed section onto its ingestion's resolved page. */
    public function commitSection(IngestedSection $section): Page
    {
        $page = $this->resolvePage($section->ingestion);
        $this->committer->commit($section, $page);

        return $page;
    }

    /** Find or create the CMS page for this ingestion's source URL. */
    private function resolvePage(PageIngestion $ingestion): Page
    {
        $path = parse_url($ingestion->source_url, PHP_URL_PATH) ?: '/';
        $url = '/'.ltrim(rtrim($path, '/'), '/');
        $url = $url === '/' ? '/' : $url;

        return Page::firstOrCreate(
            ['site_id' => $ingestion->site_id, 'url' => $url],
            [
                'name' => $ingestion->meta['title'] ?? Str::headline(trim($url, '/') ?: 'Home'),
                'keywords' => '',
            ]
        );
    }
}
