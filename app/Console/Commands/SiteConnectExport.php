<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\SiteConnect\SiteExporter;
use Illuminate\Console\Command;

/**
 * Build the transformed-site export (attributed HTML + baked content +
 * connect.js) as a zip on disk.
 *
 *   php artisan connect:export acme-salon
 */
class SiteConnectExport extends Command
{
    protected $signature = 'connect:export {site : Site name/slug}';

    protected $description = 'Export a site as transformed, hydration-ready HTML (Site Connect)';

    public function handle(SiteExporter $exporter): int
    {
        $site = Site::where('name', $this->argument('site'))->first();
        if (! $site) {
            $this->error("Site not found: {$this->argument('site')}");

            return self::FAILURE;
        }

        $result = $exporter->export($site);
        if ($result['pages'] === 0) {
            $this->warn('Nothing committed to export yet — commit some ingested pages first.');

            return self::SUCCESS;
        }

        $this->info("Exported {$result['pages']} page(s) → {$result['path']}");

        return self::SUCCESS;
    }
}
