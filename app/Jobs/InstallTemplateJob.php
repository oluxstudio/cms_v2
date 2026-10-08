<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\SiteTemplate;
use App\Services\TemplateInstaller;
use App\Support\TaskAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The heavy half of applying a design to a site — scaffolding pages,
 * components and nodes, enabling commerce modules, creating the template's
 * forms and theme — runs here so "Use template" can redirect to /connect
 * instantly. Progress is tracked in the site's `template_install` attribute
 * (installing → done | failed), which /connect polls.
 */
class InstallTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * $refresh: a template UPDATE on a site already using it (Admin ›
     * Templates › Update sites) — syncs declared field types/activation like
     * `template:deploy`, instead of a first-time install.
     */
    public function __construct(public string $siteId, public string $siteTemplateId, public ?string $userId = null, public bool $refresh = false) {}

    /** One install per site at a time — a second click must not double-scaffold. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('template-install:'.$this->siteId))->dontRelease()->expireAfter(600)];
    }

    public function handle(TemplateInstaller $installer): void
    {
        $site = Site::find($this->siteId);
        $row = SiteTemplate::find($this->siteTemplateId);
        if (! $site || ! $row) {
            return;
        }

        $installer->install($site, $row, refresh: $this->refresh);
        TaskAlerts::done($this->userId, $site->id, 'Design applied: '.$row->name,
            Str::headline($site->name).' now uses it — pages, sections and settings are in place.', url($site->name.'/connect'), ['site_template_id' => $row->id]);
    }

    public function failed(?\Throwable $e): void
    {
        Log::warning('Template install failed', [
            'site_id' => $this->siteId,
            'site_template_id' => $this->siteTemplateId,
            'error' => $e?->getMessage(),
        ]);
        $site = Site::find($this->siteId);
        $site?->setAttr('template_install', 'failed');
        if ($site) {
            TaskAlerts::failed($this->userId, $site->id, 'Design couldn\'t be applied',
                'Setting up '.(SiteTemplate::whereKey($this->siteTemplateId)->value('name') ?: 'the design').' on '.Str::headline($site->name).' failed. Try applying it again.',
                url($site->name.'/designs'));
        }
    }
}
