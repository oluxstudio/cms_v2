<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\SiteProperties;
use Illuminate\Console\Command;

/**
 * One-off: give every site its "Site Properties" component, folding in the
 * values the first (attribute-based) version stored. Safe to re-run — an
 * existing component only gets missing schema fields added.
 */
class MigrateSiteProperties extends Command
{
    protected $signature = 'site-properties:migrate {--site= : Only this site (name)}';

    protected $description = 'Create each site\'s Site Properties component (and move legacy site.* attributes into it)';

    public function handle(): int
    {
        $sites = Site::query()->when($this->option('site'), fn ($q, $name) => $q->where('name', $name))->get();
        $moved = 0;
        foreach ($sites as $site) {
            $hadLegacy = $site->siteAttributes()->whereIn('key', ['site.display_name', 'site.logo', 'site.email', 'site.phones', 'site.emails', 'site.variables'])->exists();
            $existed = SiteProperties::find($site) !== null;
            SiteProperties::component($site);
            if ($hadLegacy && ! $existed) {
                $moved++;
            }
        }
        $this->info("Site Properties ready on {$sites->count()} site(s); {$moved} had earlier values moved in.");

        return self::SUCCESS;
    }
}
