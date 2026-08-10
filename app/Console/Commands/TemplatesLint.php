<?php

namespace App\Console\Commands;

use App\Services\TemplateExtractor;
use App\Services\TemplateLint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Lint a staging template app against the ingestion conventions. Runs the
 * extractor fresh (so the report always reflects the current source), then
 * scores what the pipeline can faithfully edit.
 */
class TemplatesLint extends Command
{
    protected $signature = 'templates:lint {key : Staging app folder name}';

    protected $description = 'Score a staging template against the CMS ingestion conventions';

    public function handle(TemplateExtractor $extractor, TemplateLint $lint): int
    {
        $key = $this->argument('key');
        $dir = rtrim(config('templates.staging_path'), '/')."/{$key}";
        if (! File::isDirectory($dir)) {
            $this->error("No staging app at {$dir}");

            return self::FAILURE;
        }

        $manifest = $extractor->extract($key);
        $result = $lint->analyze($manifest, $dir);

        $this->info("Template “{$key}” — quality score: {$result['score']}/100");
        $this->newLine();
        foreach ($result['findings'] as $f) {
            $tag = match ($f['level']) {
                'error' => '<fg=red>ERROR  </>',
                'warning' => '<fg=yellow>WARN   </>',
                default => '<fg=green>INFO   </>',
            };
            $this->line("  {$tag} {$f['area']} — {$f['message']}");
        }

        return collect($result['findings'])->contains(fn ($f) => $f['level'] === 'error')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
