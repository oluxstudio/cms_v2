<?php

namespace App\Console\Commands;

use App\Services\TemplateCatalogWriter;
use App\Templates\TemplateRegistry;
use Illuminate\Console\Command;

/**
 * Seed/refresh the DB catalog (`templates` + `template_versions`) from the built-in
 * registry (class templates + on-disk packages). Idempotent — safe to re-run after
 * editing a package. Publishes package assets to the configured templates disk and
 * bakes absolute asset URLs into the version payload.
 */
class SyncTemplateCatalog extends Command
{
    protected $signature = 'templates:sync {--key= : Sync only this template key}';

    protected $description = 'Seed/refresh the template catalog from built-in templates & packages';

    public function handle(): int
    {
        $only = (string) $this->option('key');
        $writer = app(TemplateCatalogWriter::class);
        foreach (TemplateRegistry::all() as $contract) {
            if ($only !== '' && $contract->key() !== $only) {
                continue;
            }
            $template = $writer->upsert($contract);
            $pages = count($template->versions()->find($template->latest_version_id)?->payload['pages'] ?? []);
            $this->line("  ✓ {$contract->name()}  ({$contract->key()} v{$contract->version()}, {$pages} pages)");
        }

        $this->info('Template catalog synced.');

        return self::SUCCESS;
    }
}
