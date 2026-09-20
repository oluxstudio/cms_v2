<?php

namespace App\Console\Commands;

use App\Services\Vue\SfcParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Layout-parity guard: for every published page, the extracted block order
 * must be an ordered subset of the component sequence in the ORIGINAL page
 * source — a mismatch means the CMS renders the page in a different order
 * than the template author built. Also flags source *Block components that
 * never made it into any page. Runs as deploy step 4b.
 */
class TemplateVerify extends Command
{
    protected $signature = 'template:verify {key : Template key}';

    protected $description = 'Check published pages against the template sources for layout parity';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $appDir = base_path("templates/{$key}");
        $pagesDir = resource_path("templates/{$key}/pages");
        if (! File::isDirectory($appDir) || ! File::isDirectory($pagesDir)) {
            $this->error("Template '{$key}' has no published app or pages to verify.");

            return self::FAILURE;
        }

        $errors = [];
        $usedBlocks = [];

        foreach (File::files($pagesDir) as $file) {
            $page = (array) json_decode($file->getContents(), true);
            $blockKeys = collect($page['blocks'] ?? [])
                ->map(fn ($b) => Str::afterLast((string) ($b['type'] ?? ''), ':'))
                ->filter()->values()->all();
            foreach ($blockKeys as $bk) {
                $usedBlocks[$bk] = true;
            }

            // The authored order: prefer the original page source (pre-rewrite
            // sources carry literal *Block tags); published apps are usually
            // rewritten into wireframe loops, so fall back to the extraction
            // manifest, which recorded the original sequence at import time.
            $url = trim((string) ($page['url'] ?? ''), '/');
            $candidates = array_unique(array_filter([$url === '' ? 'index' : $url, $url !== '' ? "$url/index" : null, $file->getFilenameWithoutExtension()]));
            $source = collect($candidates)->map(fn ($c) => "$appDir/app/pages/{$c}.vue")->first(fn ($p) => File::exists($p));
            $sourceKeys = [];
            if ($source && preg_match('#<template>(.*)</template>#s', File::get($source), $m)) {
                $sourceKeys = collect(SfcParser::pageComponents($m[1]))
                    ->filter(fn ($c) => str_ends_with($c, 'Block'))
                    ->map(fn ($c) => Str::kebab(substr($c, 0, -5)))
                    ->values()->all();
            }
            if ($sourceKeys === []) {
                $manifestPage = collect($this->manifestPages($appDir))
                    ->first(fn ($p) => trim((string) ($p['url'] ?? ''), '/') === $url);
                $sourceKeys = collect($manifestPage['blocks'] ?? [])
                    ->map(fn ($b) => (string) ($b['blockKey'] ?? ''))
                    ->filter()->values()->all();
            }
            if ($sourceKeys === []) {
                continue; // no authored record to compare against
            }
            $source ??= $file->getFilename();

            // Ordered-subset check: every extracted block appears in the source
            // sequence, in the same relative order (chrome/injected extras allowed).
            $i = 0;
            foreach ($blockKeys as $bk) {
                $pos = array_search($bk, array_slice($sourceKeys, $i), true);
                if ($pos === false) {
                    $errors[] = basename((string) $source).": block '{$bk}' is out of order (or missing) versus the authored page — the CMS would render a different layout. Expected sequence: ".implode(' → ', $sourceKeys);
                    break;
                }
                $i += $pos + 1;
            }
        }

        // Source blocks never used on any page — authored but unreachable.
        foreach (File::glob("$appDir/app/components/*Block.vue") as $comp) {
            $bk = Str::kebab(substr(basename($comp, '.vue'), 0, -5));
            if (! isset($usedBlocks[$bk])) {
                $this->warn("⚠ ".basename($comp).' exists in sources but appears on no published page.');
            }
        }

        if ($errors !== []) {
            foreach (array_unique($errors) as $e) {
                $this->error('✗ '.$e);
            }

            return self::FAILURE;
        }

        $this->info("✓ Layout parity verified for '{$key}'.");

        return self::SUCCESS;
    }

    private ?array $manifestPages = null;

    /** Pages from the published app's extraction manifest (original order). */
    private function manifestPages(string $appDir): array
    {
        if ($this->manifestPages === null) {
            $path = "$appDir/.olux/extraction.json";
            $manifest = File::exists($path) ? (array) json_decode(File::get($path), true) : [];
            $this->manifestPages = (array) ($manifest['pages'] ?? []);
        }

        return $this->manifestPages;
    }
}
