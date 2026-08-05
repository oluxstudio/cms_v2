<?php

namespace App\Console\Commands;

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
        $this->writeFxPlugin($appDir);

        // The built-in "blank" app keeps the root path for backward compatibility;
        // every other template builds under its own sub-path. Staging submissions
        // (--path) publish under /nuxt-preview/_staging/{key}/.
        if ($explicitDir) {
            $base = "/nuxt-preview/_staging/{$key}/";
            $dest = public_path("nuxt-preview/_staging/{$key}");
        } else {
            $base = $key === TemplateAppRegistry::BLANK ? '/nuxt-preview/' : "/nuxt-preview/{$key}/";
            $dest = $key === TemplateAppRegistry::BLANK ? public_path('nuxt-preview') : public_path("nuxt-preview/{$key}");
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
        $this->rewriteAssetPaths($staging, $base);

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
    private function rewriteAssetPaths(string $dest, string $base): void
    {
        if ($base === '/') {
            return; // served at root — absolute paths already resolve
        }
        $prefix = rtrim($base, '/');
        $rewritten = 0;
        foreach (File::allFiles($dest) as $file) {
            if (! in_array($file->getExtension(), ['html', 'js', 'mjs', 'css', 'json'], true)) {
                continue;
            }
            $src = File::get($file->getPathname());
            $new = str_replace(
                ['"/assets/', "'/assets/", '`/assets/', 'url(/assets/', '(/assets/'],
                ['"'.$prefix.'/assets/', "'".$prefix.'/assets/', '`'.$prefix.'/assets/', 'url('.$prefix.'/assets/', '('.$prefix.'/assets/'],
                $src
            );
            if ($new !== $src) {
                File::put($file->getPathname(), $new);
                $rewritten++;
            }
        }
        $this->info("  · Asset paths rebased to {$prefix}/assets/ in {$rewritten} file(s)");
    }

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

    /**
     * Generate app/plugins/bk-fx.client.ts from App\Support\Fx — ONE effects
     * definition for every surface. Skips apps without an app/plugins dir.
     */
    private function writeFxPlugin(string $appDir): void
    {
        $pluginsDir = "{$appDir}/app/plugins";
        if (! File::isDirectory("{$appDir}/app")) {
            return;
        }
        File::ensureDirectoryExists($pluginsDir);

        $css = \App\Support\Fx::css();
        $js = \App\Support\Fx::js();

        $content = <<<TS
/**
 * GENERATED from App\Support\Fx by nuxt:preview-build — do not edit here.
 * The effects engine (enter/leave animations, click FX, parallax) is defined
 * ONCE in app/Support/Fx.php; editing it updates every surface on rebuild.
 */
export default defineNuxtPlugin(() => {
  if (typeof window === 'undefined') return
  const style = document.createElement('style');
  style.textContent = FX_CSS;
  document.head.appendChild(style);
  FX_JS
})

const FX_CSS = `{$css}`

function FX_JS_PLACEHOLDER() {}
TS;
        // Inject the JS body (an IIFE) in place of the FX_JS marker, and the
        // css via template literal above (backticks inside are not used by Fx).
        $content = str_replace("  FX_JS\n", $js."\n", $content);
        $content = str_replace('function FX_JS_PLACEHOLDER() {}', '', $content);

        File::put("{$pluginsDir}/bk-fx.client.ts", $content);
        $this->line('  · bk-fx plugin regenerated from App\Support\Fx');
    }
}
