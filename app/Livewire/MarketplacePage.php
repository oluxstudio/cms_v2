<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Livewire\Concerns\InteractsWithCuratedTemplates;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateRating;
use App\Services\ActivityLogger;
use App\Services\TemplateCatalog;
use App\Services\TemplateInstaller;
use App\Services\TemplatePackageImporter;
use App\Services\TemplateRatings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MarketplacePage extends Component
{
    use InteractsWithCuratedTemplates;
    use WithFileUploads;
    use WithPagination;

    public Site $site;

    public bool $canManage = false;

    /** Uploaded template package (.zip) pending import. */
    public $templateZip = null;

    public string $templateError = '';

    /** Feature key whose settings drawer is open (null = closed). */
    public ?string $settingsKey = null;

    public array $form = [];

    /** Stripe payments panel. */
    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->canManage = $site->canManageTeam(Auth::user());
    }

    public function getFeaturesProperty(): array
    {
        return collect(FeatureRegistry::all())
            ->map(fn ($f) => $f + ['enabled' => $this->site->hasFeature($f['key'])])
            // Delisted features stay hidden unless the site already uses them.
            ->reject(fn ($f) => ($f['hidden'] ?? false) && ! $f['enabled'])
            ->values()
            ->all();
    }

    public function getNeedsPaymentsProperty(): bool
    {
        return collect(FeatureRegistry::all())
            ->contains(fn ($f) => ($f['needs_payments'] ?? false) && $this->site->hasFeature($f['key']));
    }

    private function guard(): bool
    {
        if (! $this->canManage) {
            $this->errorMessage = 'Only the site owner or admins can manage features.';

            return false;
        }

        return true;
    }

    public function toggle(string $key): void
    {
        if (! $this->guard()) {
            return;
        }
        abort_unless(FeatureRegistry::exists($key), 404);

        $feature = FeatureRegistry::get($key);

        if ($this->site->hasFeature($key)) {
            $this->site->disableFeature($key);
            session()->flash('mp-message', $feature['name'].' disabled — its pages were removed from your menu.');
            $this->redirect(url($this->site->name.'/marketplace'));

            return;
        }

        // Premium features require a plan that unlocks them (config/plans.php).
        if (($feature['tier'] ?? 'basic') === 'premium' && ! $this->site->user->currentSubscription()->allowsPremium()) {
            $this->dispatch('upgrade-required',
                reason: $feature['name'].' is a premium module. Your current plan doesn\'t include premium modules — upgrade to Pro or higher to enable it.',
                cta: 'Unlock premium');

            return;
        }

        $this->site->enableFeature($key);
        session()->flash('mp-message', $feature['name'].' enabled — its pages were added to your menu.');
        $this->redirect(url($this->site->name.'/marketplace'));
    }

    public function openSettings(string $key): void
    {
        abort_unless(FeatureRegistry::exists($key), 404);
        $this->settingsKey = $key;
        $this->form = $this->site->feature($key);
    }

    public function closeSettings(): void
    {
        $this->settingsKey = null;
        $this->form = [];
    }

    public function saveSettings(): void
    {
        if (! $this->guard() || ! $this->settingsKey) {
            return;
        }

        $schema = FeatureRegistry::get($this->settingsKey)['settings'] ?? [];

        // Keep only known fields; coerce numbers.
        $clean = [];
        foreach ($schema as $name => $field) {
            $val = $this->form[$name] ?? ($field['default'] ?? null);
            if (($field['type'] ?? 'text') === 'number') {
                $val = (int) $val;
            }
            $clean[$name] = $val;
        }

        $this->site->saveFeatureConfig($this->settingsKey, $clean);
        $this->successMessage = FeatureRegistry::get($this->settingsKey)['name'].' settings saved.';
        $this->closeSettings();
    }

    // Payment settings moved to their own page: /{site}/payments
    // (App\Livewire\SitePaymentsPage).

    // ───────────────────────────────────────────── Templates marketplace (DB catalog)

    /** Catalog browse filters (reset pagination when they change). */
    public string $tplSearch = '';

    public string $tplCategory = '';

    public string $tplPrice = '';

    public string $tplSort = 'popular';

    public function updatedTplSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTplCategory(): void
    {
        $this->resetPage();
    }

    public function updatedTplPrice(): void
    {
        $this->resetPage();
    }

    public function updatedTplSort(): void
    {
        $this->resetPage();
    }

    /** Paginated catalog of published templates. */
    public function getTemplatesProperty()
    {
        return app(TemplateCatalog::class)->browse(
            ['search' => $this->tplSearch, 'category' => $this->tplCategory, 'price' => $this->tplPrice],
            $this->tplSort,
        );
    }

    public function getTemplateCategoriesProperty(): array
    {
        return app(TemplateCatalog::class)->categories();
    }

    /** Template ids already installed on this site (to flag cards). */
    public function getInstalledTemplateIdsProperty(): array
    {
        return $this->site->installedTemplates()->whereNotNull('template_id')->pluck('template_id')->all();
    }

    /** This site's installed templates (catalog + uploaded). */
    public function getInstalledTemplatesProperty()
    {
        return $this->site->installedTemplates()->with('template')->get();
    }

    /** The current user's existing star rating per catalog template id. */
    public function getMyRatingsProperty(): array
    {
        $ids = $this->site->installedTemplates()->whereNotNull('template_id')->pluck('template_id');

        return TemplateRating::where('user_id', Auth::id())
            ->whereIn('template_id', $ids)->pluck('stars', 'template_id')->all();
    }

    /** Rate a template the user has installed (1–5 stars). */
    public function rateTemplate(string $templateId, int $stars): void
    {
        $tpl = Template::find($templateId);
        if (! $tpl) {
            return;
        }
        $ratings = app(TemplateRatings::class);
        if (! $ratings->canRate(Auth::user(), $tpl)) {
            $this->errorMessage = 'You can only rate templates you have installed.';

            return;
        }
        $ratings->rate(Auth::user(), $tpl, $stars);
        $this->successMessage = 'Thanks for rating “'.$tpl->name.'”.';
    }

    /** Install a catalog template (free → grant + install; paid → entitlement-gated checkout). */
    public function installFromCatalog(string $templateId)
    {
        if (! $this->guard()) {
            return;
        }
        $tpl = app(TemplateCatalog::class)->find($templateId);
        if (! $tpl) {
            $this->errorMessage = 'Unknown template.';

            return;
        }
        if ($this->site->installedTemplates()->where('template_id', $tpl->id)->exists()) {
            $this->successMessage = $tpl->name.' is already installed.';

            return;
        }

        try {
            $result = app(TemplateInstaller::class)->saveCatalogToSite(Auth::user(), $this->site, $tpl);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }
        if (is_string($result)) {
            return $this->redirect($result); // off to Stripe
        }

        $this->successMessage = $tpl->name.' installed to this site.';
    }

    /**
     * Uninstall a template from this site. A template that has been APPLIED
     * has scaffolded real pages/components — deleting the install row would
     * strand them looking like an uninstall, so applied templates are kept
     * until the site stops using them.
     */
    public function uninstallTemplate(string $id): void
    {
        if (! $this->guard()) {
            return;
        }
        $st = $this->site->installedTemplates()->whereKey($id)->first();
        if (! $st) {
            return;
        }
        if ($st->isApplied()) {
            $this->templateError = '“'.$st->name.'” is applied to this site — its pages are in use. Switch the site to another template first.';

            return;
        }
        $st->delete();
        $this->successMessage = 'Template removed from this site.';
    }

    /** Import an uploaded .zip template package and install it. */
    public function uploadTemplate(TemplatePackageImporter $importer): void
    {
        $this->templateError = '';
        if (! $this->guard()) {
            return;
        }

        $this->validate(
            ['templateZip' => ['required', 'file', 'max:20480']], // 20 MB
            ['templateZip.required' => 'Choose a .zip file to upload.'],
        );

        if (strtolower($this->templateZip->getClientOriginalExtension()) !== 'zip') {
            $this->templateError = 'The template must be a .zip file.';

            return;
        }

        try {
            $tpl = $importer->import($this->site, $this->templateZip->getRealPath());
        } catch (\Throwable $e) {
            $this->templateError = $e->getMessage();

            return;
        }

        try {
            ActivityLogger::log($this->site->id, 'template', 'uploaded',
                'Template “'.$tpl->name.'” uploaded from a .zip', [
                    'entity_id' => $tpl->id,
                    'description' => 'Direct upload by '.auth()->user()?->name.' — sanitised, not marketplace-reviewed.',
                    'url' => '/marketplace',
                ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->reset('templateZip');
        $this->successMessage = '“'.$tpl->name.'” uploaded and installed.';
    }

    public function render()
    {
        return view('livewire.marketplace-page');
    }
}
