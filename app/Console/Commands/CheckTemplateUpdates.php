<?php

namespace App\Console\Commands;

use App\Services\BuiltinTemplateUpdates;
use Illuminate\Console\Command;

/**
 * Run after every deploy (deploy.sh / scripts/apply-local.sh): flags built-in
 * templates whose repo copy changed, so Admin › Templates shows
 * "Update available" on them. Never changes a template by itself.
 */
class CheckTemplateUpdates extends Command
{
    protected $signature = 'templates:check-updates';

    protected $description = 'Flag built-in templates whose repo copy changed since their last published version';

    public function handle(BuiltinTemplateUpdates $updates): int
    {
        foreach ($updates->check() as $key => $state) {
            $this->line(match ($state) {
                'update' => "  ↑ {$key}: update available — Admin › Templates › Update template",
                'baseline' => "  · {$key}: up to date (baseline recorded)",
                'not-in-catalog' => "  ? {$key}: not in the catalog yet (run templates:sync to add it)",
                default => "  ✓ {$key}: up to date",
            });
        }

        return self::SUCCESS;
    }
}
