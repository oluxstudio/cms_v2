<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Mint a Site Connect key for a site — a hashed, site-scoped api_token limited
 * to the connect abilities (connect:ingest, content:read). NEVER grants
 * mutation abilities. Prints the raw token ONCE (it is stored hashed).
 *
 *   php artisan connect:token acme-salon
 */
class SiteConnectToken extends Command
{
    protected $signature = 'connect:token {site : Site name/slug} {--name=Site Connect : A label for the key}';

    protected $description = 'Mint a Site Connect key (connect:ingest + content:read) for a site';

    public function handle(): int
    {
        $site = Site::where('name', $this->argument('site'))->first();
        if (! $site) {
            $this->error("Site not found: {$this->argument('site')}");

            return self::FAILURE;
        }

        $raw = 'olx_live_'.Str::random(48);
        ApiToken::create([
            'user_id' => $site->user_id,
            'site_id' => $site->id, // site-scoped → token.site resolves it automatically
            'name' => $this->option('name'),
            'token' => hash('sha256', $raw),
            'token_preview' => substr($raw, 0, 12),
            'abilities' => config('site_connect.abilities', ['connect:ingest', 'content:read']),
        ]);

        $this->info("Site Connect key for {$site->name} (copy now — it will not be shown again):");
        $this->newLine();
        $this->line("  <comment>{$raw}</comment>");
        $this->newLine();
        $this->line('Embed on the client site:');
        $this->line('  <fg=gray><script src="'.rtrim(config('app.url'), '/').'/connect.js"</>');
        $this->line("  <fg=gray>        data-site-name=\"{$site->name}\"</>");
        $this->line('  <fg=gray>        data-site-token="'.$raw.'" defer></script></>');

        return self::SUCCESS;
    }
}
