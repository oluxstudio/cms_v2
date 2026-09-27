<?php

namespace App\Services;

use App\Features\FeatureRegistry;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\User;
use App\Templates\TemplateAppRegistry;

/**
 * Site › Design: apply a LIBRARY template to a site (restore point first,
 * required features switched on) and revert to the restore point. Library
 * authorisation is the single gate — one account can never apply another
 * account's library item.
 */
class DesignService
{
    public function __construct(
        private TemplateCommerce $commerce,
        private TemplateInstaller $installer,
    ) {}

    /**
     * @return array{applied: string, features_enabled: list<string>, restore_point: bool, pages: int}
     */
    public function apply(User $user, Site $site, Template $template, ?string $versionId = null): array
    {
        abort_unless($site->allows($user, 'addons.manage') || $site->canManageTeam($user), 403);
        abort_unless($this->commerce->inLibrary($user, $template), 403, 'This template is not in your library.');

        // ── Restore point BEFORE anything changes ──
        $restore = [
            'template' => $site->template,
            'theme' => $site->theme,
            'applied_row_id' => $site->installedTemplates()->whereNotNull('applied_at')->value('id'),
            'captured_at' => now()->toIso8601String(),
        ];

        // Install (creates/reuses the site_templates row) then bind + scaffold.
        $row = $this->installer->saveCatalogToSite($user, $site, $template);
        abort_unless($row instanceof SiteTemplate, 422, 'This template could not be added to the site.');
        if ($versionId) {
            $row->update(['template_version_id' => $versionId]);
        }
        $this->installer->apply($site, $row);
        $row->update(['previous_state' => $restore]);

        // Switch on any missing required features.
        $enabled = [];
        foreach ((array) $template->required_features as $key) {
            if (FeatureRegistry::exists($key) && ! $site->hasFeature($key)) {
                $site->enableFeature($key);
                $enabled[] = $key;
            }
        }

        return [
            'applied' => $template->name,
            'features_enabled' => $enabled,
            'restore_point' => true,
            'pages' => $site->pages()->count(),
        ];
    }

    /** Undo the last apply: restore renderer binding + theme from the restore point. */
    public function revert(User $user, Site $site): array
    {
        abort_unless($site->allows($user, 'addons.manage') || $site->canManageTeam($user), 403);

        $applied = $site->installedTemplates()->whereNotNull('applied_at')->first();
        $state = (array) ($applied?->previous_state ?? []);
        abort_if($state === [], 422, 'No restore point to go back to.');

        $site->update([
            'template' => $state['template'] ?? TemplateAppRegistry::BLANK,
            'theme' => $state['theme'] ?? $site->theme,
        ]);
        $site->installedTemplates()->update(['applied_at' => null]);
        if (! empty($state['applied_row_id'])) {
            SiteTemplate::where('site_id', $site->id)->whereKey($state['applied_row_id'])
                ->update(['applied_at' => now()]);
        }
        $applied->update(['previous_state' => null]);

        return ['reverted_to' => $state['template'] ?? TemplateAppRegistry::BLANK];
    }
}
