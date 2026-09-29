<?php

namespace App\Console\Commands;

use App\Support\NuxtShell;
use App\Support\TemplatePaths;
use App\Templates\TemplateAppRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Builds a template APP into a static SPA (API data mode) and publishes it under
 * public/nuxt-preview/. Each template renders the site EXACTLY (its own markup/CSS/
 * animations), so the preview is literally what publishes.
 *
 *   php artisan nuxt:preview-build                    # the built-in "blank" app → /nuxt-preview/
 *   php artisan nuxt:preview-build --template=tekstack  # → /nuxt-preview/tekstack/
 *   php artisan nuxt:preview-build --all              # build every discovered template
 */
class BuildNuxtPreview extends Command
{
    protected $signature = 'nuxt:preview-build
        {--template= : Template key to build (default: blank)}
        {--all : Build every discovered template app}
        {--path= : Build from an arbitrary app directory (staging submissions) → /nuxt-preview/_staging/{basename}/}
        {--skip-install : Reuse existing node_modules}';

    protected $description = 'Build template preview SPA(s) into public/nuxt-preview/';

    public function handle(): int
    {
        if ($path = $this->option('path')) {
            return $this->buildOne(basename($path), rtrim($path, '/'));
        }

        $keys = $this->option('all')
            ? array_keys(TemplateAppRegistry::all())
            : [$this->option('template') ?: TemplateAppRegistry::BLANK];

        foreach ($keys as $key) {
            if ($this->buildOne($key) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function buildOne(string $key, ?string $explicitDir = null): int
    {
        $appDir = $explicitDir ?: TemplateAppRegistry::appDir($key);
        if (! $appDir || ! File::isDirectory($appDir)) {
            $this->error("Template app not found for key '{$key}'.");

            return self::FAILURE;
        }

        // FOUNDATION: the effects engine is defined ONCE in App\Support\Fx —
        // regenerate the app's bk-fx plugin from it so editing Fx.php takes
        // effect everywhere (canvas, exporter AND every built renderer).
        if (NuxtShell::writeFxPlugin($appDir)) {
            $this->line('  · bk-fx plugin regenerated from App\\Support\\Fx');
        }

        // The built-in "blank" app keeps the root path for backward compatibility;
        // every other template builds under its own sub-path. Staging submissions
        // (--path) publish under /nuxt-preview/_staging/{key}/.
        if ($explicitDir) {
            $base = "/nuxt-preview/_staging/{$key}/";
            $dest = public_path("nuxt-preview/_staging/{$key}");
        } else {
            $base = TemplatePaths::shellBase($key);
            $dest = TemplatePaths::shellDir($key);
        }

        $this->info("Building template '{$key}' from {$appDir} → {$base}");

        if (! $this->option('skip-install')) {
            $this->info('Installing dependencies (npm install)…');
            if (! $this->runProcess(['npm', 'install', '--no-audit', '--no-fund'], $appDir)) {
                return self::FAILURE;
            }
        }

        $this->info('Building Nuxt SPA (nuxi generate, API data mode)…');
        $env = [
            'NUXT_PUBLIC_DATA_MODE' => 'api',
            'NUXT_APP_BASE_URL' => $base,
        ];
        if (! $this->runProcess(['npx', 'nuxi', 'generate'], $appDir, $env)) {
            return self::FAILURE;
        }

        $output = "{$appDir}/.output/public";
        if (! File::isDirectory($output)) {
            $this->error("Build output not found at {$output}");

            return self::FAILURE;
        }

        // Atomic publish. A delete-then-copy window serves mixed old/new chunks
        // to any preview iframe that loads mid-publish (renders only some
        // blocks), so builds are staged first and swapped in instants.
        $staging = rtrim($dest, '/').'.staging-'.getmypid();
        File::deleteDirectory($staging);
        File::ensureDirectoryExists(dirname($staging));
        File::copyDirectory($output, $staging);
        $r = NuxtShell::rebase($staging, $base);
        if ($r['stamp']) {
            $this->info('  · Asset paths rebased ('.implode(', ', $r['dirs']).') in '.$r['rewritten'].' file(s)');
            $this->info("  · Stylesheet links cache-busted ({$r['stamp']}) in {$r['stamped']} file(s)");
        }

        if ($key === TemplateAppRegistry::BLANK && ! $explicitDir) {
            // The blank build lives at the nuxt-preview ROOT, which also holds
            // the per-template builds — replacing the whole directory would
            // wipe them (it used to!). Merge instead: add new chunks, swap the
            // entry files, then prune chunks the new build no longer ships.
            $this->mergePublishRoot($staging, $dest);
        } else {
            $retired = rtrim($dest, '/').'.old-'.getmypid();
            if (File::isDirectory($dest)) {
                rename($dest, $retired);
            }
            rename($staging, $dest);
            File::deleteDirectory($retired);
        }

        $this->info("✓ Published '{$key}' to {$dest} (open {$base}?site=YOUR-SITE)");

        return self::SUCCESS;
    }

    /**
     * Merge-publish the blank build into the nuxt-preview root without touching
     * sibling template builds: new hashed chunks are ADDED to _nuxt first (old
     * chunks keep serving the old shell), then the shell files are swapped by
     * instant renames, then stale chunks are pruned.
     */
    private function mergePublishRoot(string $staging, string $dest): void
    {
        File::ensureDirectoryExists("{$dest}/_nuxt");
        $newChunks = [];
        foreach (File::allFiles("{$staging}/_nuxt") as $f) {
            $newChunks[$f->getRelativePathname()] = true;
            $target = "{$dest}/_nuxt/{$f->getRelativePathname()}";
            File::ensureDirectoryExists(dirname($target));
            rename($f->getPathname(), $target);
        }
        // Swap shell files + any non-_nuxt build dirs (never dirs we didn't build).
        foreach (File::files($staging) as $f) {
            rename($f->getPathname(), "{$dest}/{$f->getFilename()}");
        }
        foreach (File::directories($staging) as $d) {
            if (basename($d) === '_nuxt') {
                continue;
            }
            $retired = "{$dest}/".basename($d).'.old-'.getmypid();
            if (File::isDirectory("{$dest}/".basename($d))) {
                rename("{$dest}/".basename($d), $retired);
            }
            rename($d, "{$dest}/".basename($d));
            File::deleteDirectory($retired);
        }
        // Prune chunks the new shell no longer references.
        foreach (File::allFiles("{$dest}/_nuxt") as $f) {
            if (! isset($newChunks[$f->getRelativePathname()])) {
                File::delete($f->getPathname());
            }
        }
        File::deleteDirectory($staging);
    }

    /**
     * Template apps reference their assets root-absolute (/assets/…) — correct for
     * a deployed site, broken under a preview sub-path. Rewrite every reference in
     * the built text files (HTML head links, JS chunks incl. v-for template
     * literals, CSS) to the preview base. Exported site builds are untouched.
     */
    /** Run a process, streaming output; returns true on success. */
    private function runProcess(array $cmd, string $cwd, array $env = []): bool
    {
        $process = new Process($cmd, $cwd, array_merge(getenv() ?: [], $env), null, 1200);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            $this->error('Command failed: '.implode(' ', $cmd));

            return false;
        }

        return true;
    }
}
