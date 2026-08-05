<?php

namespace App\Console\Commands;

use App\Services\TemplateExtractor;
use Illuminate\Console\Command;

/**
 * Run the convention-based extractor against a staging template app and
 * print a summary. Dev tool — the Submissions UI runs the same service.
 */
class ExtractTemplate extends Command
{
    protected $signature = 'templates:extract {key : Staging folder name, e.g. tekstack}';

    protected $description = 'Extract pages/blocks/theme/fonts/behaviours from a staging Nuxt template app';

    public function handle(TemplateExtractor $extractor): int
    {
        $key = $this->argument('key');
        try {
            $m = $extractor->extract($key);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Extracted “{$m['name']}” ({$key})");
        $this->line('  Theme tokens: '.count($m['theme']).' · Fonts: '.implode(', ', array_column($m['fonts'], 'family')));
        $this->line('  Behaviours: '.implode(', ', $m['behaviours']));
        $this->line('  Assets: '.$m['assets']['count'].' files ('.round($m['assets']['bytes'] / 1024 / 1024, 1).' MB)');
        foreach ($m['pages'] as $p) {
            $this->line("  Page {$p['name']} ({$p['url']}) — ".count($p['blocks']).' blocks'
                .($p['layout']['header'] ? " · header: {$p['layout']['header']}" : '')
                .($p['layout']['footer'] ? " · footer: {$p['layout']['footer']}" : ''));
            foreach ($p['blocks'] as $b) {
                $groups = $b['items'] ? (array_is_list($b['items']) ? $b['items'] : [$b['items']]) : [];
                $items = implode('', array_map(fn ($g) => " · {$g['count']}× {$g['prefix']} items", $groups));
                $this->line("    · {$b['name']} ({$b['blockKey']}) — ".count($b['nodes'])." nodes{$items}");
            }
        }
        $this->comment('  Manifest → .olux/extraction.json');

        return self::SUCCESS;
    }
}
