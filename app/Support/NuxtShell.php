<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Shared steps around a Nuxt template build: the pre-build plugin generation
 * and the post-build asset rebasing + atomic publish. Used by the
 * nuxt:preview-build command and by the sandboxed upload builds.
 */
class NuxtShell
{
    /**
     * Generate app/plugins/bk-fx.client.ts from App\Support\Fx — ONE effects
     * definition for every surface. Skips apps without an app/plugins dir.
     */
    public static function writeFxPlugin(string $appDir): bool
    {
        $pluginsDir = "{$appDir}/app/plugins";
        if (! File::isDirectory("{$appDir}/app")) {
            return false;
        }
        File::ensureDirectoryExists($pluginsDir);

        $css = Fx::css();
        $js = Fx::js();

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

        return true;
    }

    public static function rebase(string $dest, string $base): array
    {
        if ($base === '/') {
            return ['dirs' => [], 'rewritten' => 0, 'stamp' => null, 'stamped' => 0]; // served at root
        }
        $prefix = rtrim($base, '/');
        // Every asset-ish top-level dir the build ships gets rebased — not just
        // /assets/ (fonts, videos, audio, media… are referenced root-absolute too).
        $assetDirs = collect(File::directories($dest))->map(fn ($d) => basename($d))
            ->intersect(['assets', 'fonts', 'videos', 'video', 'audio', 'media', 'images', 'img', 'files', 'downloads'])
            ->values()->all() ?: ['assets'];
        $rewritten = 0;
        foreach (File::allFiles($dest) as $file) {
            if (! in_array($file->getExtension(), ['html', 'js', 'mjs', 'css', 'json'], true)) {
                continue;
            }
            $src = File::get($file->getPathname());
            // Nuxt ≥3.8 wraps static asset srcs in a base-aware helper —
            // `x(`/assets/…`)` — which prepends NUXT_APP_BASE_URL at runtime.
            // Rewriting those too would double the prefix, so shield them.
            $new = $src;
            foreach ($assetDirs as $d) {
                $sentinel = "\x00OLX_BASE_AWARE\x00";
                $new = str_replace('(`/'.$d.'/', $sentinel, $new);
                $new = str_replace(
                    ['"/'.$d.'/', "'/".$d.'/', '`/'.$d.'/', 'url(/'.$d.'/', '(/'.$d.'/'],
                    ['"'.$prefix.'/'.$d.'/', "'".$prefix.'/'.$d.'/', '`'.$prefix.'/'.$d.'/', 'url('.$prefix.'/'.$d.'/', '('.$prefix.'/'.$d.'/'],
                    $new
                );
                $new = str_replace($sentinel, '(`/'.$d.'/', $new);
            }
            if ($new !== $src) {
                File::put($file->getPathname(), $new);
                $rewritten++;
            }
        }

        // Cache-bust the hand-authored stylesheets: they keep a stable URL
        // across deploys and are served without Cache-Control, so browsers
        // hold stale copies (new colour classes "missing" in production).
        $stamp = 'v'.time();
        $stamped = 0;
        foreach (File::allFiles($dest) as $file) {
            if (! in_array($file->getExtension(), ['html', 'js', 'mjs', 'json'], true)) {
                continue;
            }
            $code = File::get($file->getPathname());
            $new = preg_replace('#(/assets/(?:stylesheets|fonts)/[\w.-]+\.css)(?!\?)#', '$1?'.$stamp, $code);
            if ($new !== null && $new !== $code) {
                File::put($file->getPathname(), $new);
                $stamped++;
            }
        }

        return ['dirs' => $assetDirs, 'rewritten' => $rewritten, 'stamp' => $stamp, 'stamped' => $stamped];
    }

    /**
     * Atomic publish of a build into $dest: stage beside it, rebase asset
     * paths, then swap in two renames so an open preview never loads a mix
     * of old and new chunks. Not for the blank root (it merges instead).
     */
    public static function publish(string $output, string $dest, string $base): array
    {
        $staging = rtrim($dest, '/').'.staging-'.getmypid();
        File::deleteDirectory($staging);
        File::ensureDirectoryExists(dirname($staging));
        File::copyDirectory($output, $staging);
        $report = self::rebase($staging, $base);

        $retired = rtrim($dest, '/').'.old-'.getmypid();
        if (File::isDirectory($dest)) {
            rename($dest, $retired);
        }
        rename($staging, $dest);
        File::deleteDirectory($retired);

        return $report;
    }
}
