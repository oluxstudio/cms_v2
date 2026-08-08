<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Livewire\Concerns\InteractsWithCuratedTemplates;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\Template;
use App\Models\TemplateRating;
use App\Services\StripeConnect;
use App\Services\TemplateCatalog;
use App\Services\TemplateCommerce;
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
    public bool $showPayments = false;

    public string $pubKey = '';

    public string $secretKey = '';

    public string $webhookSecret = '';

    public bool $hasSecret = false;

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

        if ($this->site->hasFeature($key)) {
            $this->site->disableFeature($key);
            $this->successMessage = FeatureRegistry::get($key)['name'].' disabled.';
        } else {
            $this->site->enableFeature($key);
            $this->successMessage = FeatureRegistry::get($key)['name'].' enabled.';
        }
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

    public string $siteCurrency = 'gbp';

    public function openPayments(): void
    {
        $ps = $this->site->paymentSettings;
        $this->siteCurrency = $this->site->currency ?? 'gbp';
        $this->pubKey = $ps->stripe_publishable ?? '';
        $this->hasSecret = (bool) ($ps && filled($ps->stripe_secret));
        $this->secretKey = '';
        $this->webhookSecret = '';
        $this->showPayments = true;
    }

    public function savePayments(): void
    {
        if (! $this->guard()) {
            return;
        }

        $this->validate([
            'pubKey' => ['nullable', 'string', 'max:255'],
            'secretKey' => ['nullable', 'string', 'max:255'],
            'webhookSecret' => ['nullable', 'string', 'max:255'],
        ]);

        $ps = $this->site->paymentSettings ?: new SitePaymentSettings(['site_id' => $this->site->id]);
        $ps->site_id = $this->site->id;
        $ps->stripe_publishable = $this->pubKey ?: null;

        // Only overwrite secrets when a new value is entered (blank = keep existing).
        if (filled($this->secretKey)) {
            $ps->stripe_secret = $this->secretKey;
        }
        if (filled($this->webhookSecret)) {
            $ps->stripe_webhook_secret = $this->webhookSecret;
        }
        $ps->livemode = str_starts_with($this->pubKey, 'pk_live_');
        $ps->save();

        // Site currency (default POUND) — drives every money string + checkout.
        if (array_key_exists(strtolower($this->siteCurrency), config('currencies'))) {
            $this->site->update(['currency' => strtolower($this->siteCurrency)]);
        }

        $this->showPayments = false;
        $this->successMessage = 'Stripe payment settings saved.';
    }

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

        $user = Auth::user();
        $commerce = app(TemplateCommerce::class);

        // Paid + not entitled → send the buyer to Stripe Checkout.
        if (! $commerce->entitled($user, $tpl)) {
            $connect = app(StripeConnect::class);
            if (! $connect->configured()) {
                $this->errorMessage = 'Paid templates require marketplace payments, which aren\'t set up yet.';

                return;
            }
            try {
                $url = $connect->checkout(
                    $user, $tpl,
                    route('templates.checkout.success').'?site='.urlencode($this->site->name),
                    route('templates.checkout.cancel').'?site='.urlencode($this->site->name),
                );
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();

                return;
            }

            return $this->redirect($url); // off to Stripe
        }

        // Entitled (free or already purchased) → record the free grant + install.
        $commerce->grantFree($user, $tpl);

        $this->site->installedTemplates()->create([
            'template_id' => $tpl->id,
            'template_version_id' => $tpl->latest_version_id,
            'source' => 'catalog',
            'builtin_key' => $tpl->builtin_key,
            'name' => $tpl->name,
            'description' => $tpl->description,
            'category' => $tpl->category,
            'accent_color' => $tpl->accent_color,
            'gradient_class' => $tpl->gradient_class,
        ]);
        $tpl->increment('installs_count');

        $this->successMessage = $tpl->name.' installed to this site.';
    }

    /** Uninstall a template from this site (un-applying it first if it's in use). */
    public function uninstallTemplate(string $id): void
    {
        if (! $this->guard()) {
            return;
        }
        $st = $this->site->installedTemplates()->whereKey($id)->first();
        if (! $st) {
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

        $this->reset('templateZip');
        $this->successMessage = '“'.$tpl->name.'” uploaded and installed.';
    }

    public function render()
    {
        return view('livewire.marketplace-page');
    }
}
