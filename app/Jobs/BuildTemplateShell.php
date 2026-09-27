<?php

namespace App\Jobs;

use App\Models\Site;
use App\Templates\TemplateAppRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Build a template's Nuxt shell (the "hosting space" every site on that
 * template serves from) in the background. Shells are PER TEMPLATE, so the
 * job is keyed by template — one build fixes every site using it.
 *
 * State is tracked in cache (shell-build:{key}) so the Go-live checklist can
 * show "being prepared" and flip to ready without a page reload.
 */
class BuildTemplateShell implements ShouldQueue
{
    use Queueable;

    /** A full npm install + nuxi generate — minutes, never retried blindly. */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public string $templateKey) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('shell-build:'.$this->templateKey))->expireAfter(3600)];
    }

    public function handle(): void
    {
        Cache::put(self::cacheKey($this->templateKey), 'building', now()->addHour());

        try {
            $exit = Artisan::call('nuxt:preview-build', $this->templateKey === TemplateAppRegistry::BLANK
                ? []
                : ['--template' => $this->templateKey]);

            Cache::put(self::cacheKey($this->templateKey), $exit === 0 ? 'done' : 'failed', now()->addMinutes(10));
            if ($exit !== 0) {
                report(new \RuntimeException("Shell build for {$this->templateKey} exited with {$exit}."));
            }
        } catch (Throwable $e) {
            Cache::put(self::cacheKey($this->templateKey), 'failed', now()->addMinutes(10));
            report($e); // a failed build must never crash the flow that queued it
        }
    }

    /**
     * Make sure this site's hosting space exists, queueing a build when it
     * doesn't. Idempotent and cheap when the shell is already on disk.
     *
     * @return string ready | building | queued | failed
     */
    public static function ensure(Site $site): string
    {
        $state = self::state($site);

        if ($state === 'missing') {
            Cache::put(self::cacheKey($site->renderTemplateKey()), 'queued', now()->addHour());
            self::dispatch($site->renderTemplateKey());

            return 'queued';
        }

        return $state;
    }

    /** Current hosting-space state for a site: ready | building | queued | failed | missing. */
    public static function state(Site $site): string
    {
        if ($site->liveShell() !== null) {
            return 'ready';
        }

        return match (Cache::get(self::cacheKey($site->renderTemplateKey()))) {
            'building' => 'building',
            'queued' => 'queued',
            'failed' => 'failed',
            default => 'missing',
        };
    }

    private static function cacheKey(string $templateKey): string
    {
        return 'shell-build:'.$templateKey;
    }
}
