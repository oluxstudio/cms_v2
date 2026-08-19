<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Site;
use App\Services\SiteConnect\PageJsonPublisher;
use Illuminate\Console\Command;

/**
 * Publish page.json for a site (all live pages) or a single page. Stage 1's
 * write path — the CMS "Publish" button (Stage 4) will call the same
 * PageJsonPublisher service.
 *
 *   php artisan connect:publish acme-salon
 *   php artisan connect:publish acme-salon --page=/about
 */
class SiteConnectPublish extends Command
{
    protected $signature = 'connect:publish {site : Site name/slug} {--page= : A single page url (e.g. /about); omit for all live pages}';

    protected $description = 'Generate and publish page.json for a site (Site Connect)';

    public function handle(PageJsonPublisher $publisher): int
    {
        $site = Site::where('name', $this->argument('site'))->first();
        if (! $site) {
            $this->error("Site not found: {$this->argument('site')}");

            return self::FAILURE;
        }

        $pages = $this->option('page')
            ? Page::where('site_id', $site->id)->where('url', '/'.ltrim($this->option('page'), '/'))->get()
            : $site->livePages()->get();

        if ($pages->isEmpty()) {
            $this->warn('No matching pages to publish.');

            return self::SUCCESS;
        }

        foreach ($pages as $page) {
            $result = $publisher->publish($page);
            $this->line("  <info>✓</info> {$page->url}  →  {$result['path']}  (v{$result['version']})");
        }

        $this->info("Published {$pages->count()} page(s) for {$site->name} to the '".config('site_connect.disk')."' disk.");

        return self::SUCCESS;
    }
}
