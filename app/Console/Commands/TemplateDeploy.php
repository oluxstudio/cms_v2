<?php

namespace App\Console\Commands;

use App\Models\TemplateSubmission;
use App\Services\TemplateInstaller;
use App\Services\TemplateRepoIngest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * ONE command to ship a template update end-to-end:
 *   refresh source (repo pull / local path / zip) → publish → catalog sync
 *   → rebuild the renderer shell → refresh every site using the template.
 *
 *   php artisan template:deploy hairco                       # pull from its repo
 *   php artisan template:deploy hairco --from=/path/to/app   # local folder/zip/git url
 *   php artisan template:deploy hairco --no-sites --no-build # republish only
 */
class TemplateDeploy extends Command
{
    protected $signature = 'template:deploy {key : Template key}
        {--from= : Source dir, zip or git URL (default: the submission\'s repo, else skip refresh)}
        {--no-build : Skip rebuilding the renderer shell}
        {--no-sites : Skip refreshing sites that use the template}';

    protected $description = 'Update a template everywhere: import/republish, rebuild its shell, refresh applied sites';

    public function handle(TemplateRepoIngest $ingest, TemplateInstaller $installer): int
    {
        $key = (string) $this->argument('key');
        $started = time();
        $manifestPath = resource_path("templates/{$key}/template.json");
        $before = is_file($manifestPath) ? (array) json_decode((string) file_get_contents($manifestPath), true) : [];

        // 1. Source refresh → publish + catalog sync.
        if ($from = $this->option('from')) {
            $this->line("→ Importing from {$from}…");
            $code = Artisan::call('template:import', ['source' => $from, '--key' => $key, '--replace' => true, '--accept' => true], $this->output);
            if ($code !== self::SUCCESS) {
                $this->error('✗ Import failed — nothing deployed.');

                return self::FAILURE;
            }
        } elseif (($submission = TemplateSubmission::where('key', $key)->first())?->repo_url) {
            $this->line("→ Pulling latest from {$submission->repo_url}…");
            try {
                $ingest->pull($submission); // accepted submissions republish + resync inside
            } catch (\Throwable $e) {
                $this->error('✗ Repo pull failed: '.$e->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->warn('→ No --from and no repo on file — deploying the already-published version.');
        }
        $this->info('✓ Published + catalog in sync');

        // Curated manifest blocks must survive republishes.
        $after = is_file($manifestPath) ? (array) json_decode((string) file_get_contents($manifestPath), true) : [];
        foreach (['forms', 'booking', 'collections'] as $k) {
            if (! empty($before[$k]) && empty($after[$k])) {
                $this->warn("⚠ manifest key '{$k}' disappeared from template.json — re-add it before sites re-apply.");
            }
        }

        // 2. Rebuild the renderer shell (what previews + live sites serve).
        if (! $this->option('no-build')) {
            $this->line('→ Building renderer shell…');
            if (Artisan::call('nuxt:preview-build', ['--template' => $key]) !== self::SUCCESS) {
                $this->error('✗ Shell build failed — sites still serve the previous build.');

                return self::FAILURE;
            }
            $index = public_path("nuxt-preview/{$key}/index.html");
            if (! is_file($index) || filemtime($index) < $started) {
                $this->error('✗ Shell build did not produce a fresh index.html.');

                return self::FAILURE;
            }
            $this->info('✓ Shell rebuilt');
        }

        // 3. Refresh every site running this template (idempotent installs).
        $refreshed = 0;
        if (! $this->option('no-sites')) {
            $this->line('→ Refreshing sites using this template…');
            $refreshed = $installer->refreshAppliedSites($key, fn (string $name) => $this->line("   · {$name}"));
            $this->info("✓ {$refreshed} site(s) refreshed");
        }

        if (! File::exists($manifestPath)) {
            $this->error('✗ Published manifest missing — deploy is incomplete.');

            return self::FAILURE;
        }

        $this->info("Deployed '{$key}': published ✓".($this->option('no-build') ? '' : ', shell built ✓').", {$refreshed} site(s) refreshed.");

        return self::SUCCESS;
    }
}
