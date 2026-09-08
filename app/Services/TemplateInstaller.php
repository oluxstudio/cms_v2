<?php

namespace App\Services;

use App\Jobs\InstallTemplateJob;
use App\Models\Node;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\User;
use App\Services\SiteConnect\AssetImporter;
use App\Support\CuratedTemplates;
use App\Templates\TemplateAppRegistry;
use App\Templates\TemplatePackage;
use App\Templates\TemplateRegistry;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The one seam for "save a design to a site" and "make a saved design the
 * site's active look" — shared by the public gallery, the marketplace tab
 * and the My Designs page.
 */
class TemplateInstaller
{
    public function __construct(
        private TemplateCommerce $commerce,
        private StripeConnect $connect,
        private TemplateScaffolder $scaffolder,
    ) {}

    /**
     * Create a brand-new site to receive a design — "Use template" never
     * overwrites an existing site. Name = the template's slug, uniquified
     * (-2, -3, …) past reserved subdomains and taken names.
     */
    public function createSiteFrom(User $user, array $card): Site
    {
        $base = Str::slug((string) ($card['name'] ?? 'site')) ?: 'site';
        $label = $base;
        for ($i = 2; ! Site::validSubdomainLabel($label) || Site::where('name', $label)->exists(); $i++) {
            $label = "{$base}-{$i}";
        }

        $site = Site::create([
            'name' => $label,
            'domain' => $label.'.'.(config('publishing.subdomain_base') ?: 'test'),
            'owner' => $user->name,
            'description' => Str::limit((string) ($card['description'] ?? ''), 250),
            'user_id' => $user->id,
        ]);
        $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
        AccountActivity::siteCreated($site);

        return $site;
    }

    /**
     * Save a catalog template to a site. Returns the SiteTemplate, or a
     * Stripe Checkout URL (string) when the buyer must pay first.
     */
    public function saveCatalogToSite(User $user, Site $site, Template $tpl): SiteTemplate|string
    {
        if ($existing = $site->installedTemplates()->where('template_id', $tpl->id)->first()) {
            return $existing;
        }

        if (! $this->commerce->entitled($user, $tpl)) {
            if (! $this->connect->configured()) {
                throw new RuntimeException("Paid templates require marketplace payments, which aren't set up yet.");
            }

            return $this->connect->checkout(
                $user, $tpl,
                route('templates.checkout.success').'?site='.urlencode($site->name).'&template='.urlencode($tpl->uuid),
                route('templates.checkout.cancel').'?site='.urlencode($site->name),
            );
        }

        $this->commerce->grantFree($user, $tpl);

        $row = $site->installedTemplates()->create([
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

        return $row;
    }

    /** Save a first-party curated app (always free, no entitlement needed). */
    public function saveCuratedToSite(Site $site, string $key): SiteTemplate
    {
        $t = CuratedTemplates::find($key);
        if (! $t) {
            throw new RuntimeException('Unknown template.');
        }
        if ($existing = $site->installedTemplates()->where('builtin_key', $key)->first()) {
            return $existing;
        }
        $m = $t['manifest'];

        return $site->installedTemplates()->create([
            'source' => 'builtin',
            'builtin_key' => $key,
            'name' => $t['name'],
            'description' => $t['description'],
            'category' => $t['category'],
            'accent_color' => $t['accent'],
            'gradient_class' => (string) ($m['gradientClass'] ?? 'from-slate-400 to-slate-600'),
            'payload' => [
                'theme' => $m['theme'] ?? [],
                'css' => (string) ($m['css'] ?? ''),
                'pages' => $m['pages'] ?? [],
                'forms' => $m['forms'] ?? [],
                'booking' => $m['booking'] ?? [],
                'collections' => $m['collections'] ?? [],
                'version' => (string) ($m['version'] ?? '1.0.0'),
                'author' => (string) ($m['author'] ?? 'Curated'),
            ],
        ]);
    }

    /**
     * Make this saved design the site's active look: bind the renderer,
     * mark it applied (exclusively) and scaffold any missing pages —
     * existing pages and content are never touched.
     */
    public function apply(Site $site, SiteTemplate $row): void
    {
        $this->bind($site, $row);
        $this->install($site, $row);
    }

    /**
     * Same as apply(), but the heavy content scaffolding runs in a queued
     * job — the caller can redirect to /connect immediately while the
     * pages/forms/modules are created in the background.
     */
    public function applyAsync(Site $site, SiteTemplate $row): void
    {
        $this->bind($site, $row);
        InstallTemplateJob::dispatch($site->id, $row->id);
    }

    /**
     * The cheap, instant half: point the site at this design's renderer and
     * mark the row applied (exclusively). Also flags the install as running
     * so /connect can show a "setting up" state until install() completes.
     */
    public function bind(Site $site, SiteTemplate $row): void
    {
        $appKey = $this->resolveAppKey($row);
        $site->update(['template' => $appKey]);

        $site->installedTemplates()->whereKeyNot($row->id)->update(['applied_at' => null]);
        $row->update(['applied_at' => now()]);
        $site->setAttr('template_install', 'installing');

        // An external client URL would override the preview on /connect, so
        // the user would never see the design they just applied. Stash it
        // (recoverable by re-entering it) and switch to renderer mode.
        if ($clientUrl = $site->getAttr('client_url')) {
            $site->setAttr('client_url_previous', $clientUrl);
            $site->setAttr('client_url', '');
        }
    }

    /**
     * The heavy half: scaffold pages/components, switch on commerce modules,
     * create the template's forms and apply its theme.
     */
    public function install(Site $site, SiteTemplate $row): void
    {
        // bind() just stamped applied_at, so "was it applied before this
        // run?" means: was the previous theme already stashed for this row?
        $wasApplied = $row->previous_theme !== null;
        $appKey = $this->resolveAppKey($row);

        // 1. Content: pages + components (skips existing URLs — safe re-apply).
        $contract = $row->toContract();
        if ($contract && ($pages = $contract->pages())) {
            $this->scaffolder->applyPages($site, $pages);
        }

        // 1a. Chrome: the layout's header/nav + footer blocks wrap every page
        //     — without them the applied site is missing its navbar.
        $layout = [];
        if ($contract instanceof TemplatePackage) {
            $layouts = $contract->layouts(); // keyed by slug; 'default' preferred
            $layout = ($layouts['default'] ?? (reset($layouts) ?: []))['blocks'] ?? [];
        }
        $layout = $layout ?: (array) ($row->payload['layout'] ?? []);
        if ($layout !== []) {
            $this->scaffolder->applyChrome($site, $layout);
        }

        // 1b. Assets: the template's shipped images become the site's default
        //     media library, and top-level image nodes point at the copies.
        $this->importAssets($site, $appKey);

        // 2. Modules: every commerce feature switches on (idempotent), so the
        //    template's booking/store/estimator pages have working backends —
        //    the owner can still toggle any of them off in the Marketplace.
        $site->enableCommerceSuite();

        // 3. Forms the template declares (manifest-driven, firstOrCreate).
        $this->applyForms($site, $this->formsFor($row, $appKey));

        // 3a. Collections the template declares (repeatable lists the owner
        //     can add/remove items on) — seeded with the template's rows.
        $manifest = TemplateAppRegistry::find($appKey)['manifest'] ?? [];
        $this->applyCollections($site, (array) ($manifest['collections'] ?? $row->payload['collections'] ?? []));

        // 3b. Booking: seed the template's services + availability so the
        //     template's appointment flow is bookable out of the box.
        $this->applyBooking($site, $row, $appKey);

        // 4. The template's colour tokens become the site theme; the outgoing
        //    theme is stashed on the row so "stop using" can restore it.
        $this->applyTheme($site, $row, $appKey, $wasApplied);

        $site->setAttr('template_install', 'done');
    }

    /**
     * Re-run the (idempotent) install on every site whose APPLIED design
     * renders with this template key — new pages/blocks/chrome/collections/
     * forms/services/assets appear, owner content is never touched. Used by
     * template:deploy and the repo-push auto-deploy.
     *
     * @param  callable(string):void|null  $onSite  progress callback per site name
     */
    public function refreshAppliedSites(string $appKey, ?callable $onSite = null): int
    {
        $count = 0;
        foreach (Site::whereNotNull('template')->where('template', $appKey)->get() as $site) {
            $row = $site->installedTemplates()->whereNotNull('applied_at')->first();
            if (! $row || $this->resolveAppKey($row) !== $appKey) {
                continue;
            }
            try {
                $this->install($site, $row);
                $count++;
                if ($onSite) {
                    $onSite($site->name);
                }
            } catch (\Throwable $e) {
                report($e); // one broken site must not stop the fleet refresh
            }
        }

        return $count;
    }

    /**
     * Seed the manifest's collections: name + field schema + starter items.
     * firstOrCreate by name; items seed only on first creation, so owner
     * edits/removals survive re-applies.
     */
    private function applyCollections(Site $site, array $collections): void
    {
        foreach ($collections as $def) {
            if (empty($def['name'])) {
                continue;
            }
            $existing = $site->collections()->where('name', $def['name'])->exists();
            if ($existing) {
                continue;
            }
            $col = $site->collections()->create([
                'name' => $def['name'],
                'slug' => '', // mutator derives from name → matches data-olx-key markers
                'type' => (string) ($def['type'] ?? 'grid'),
                'fields' => collect((array) ($def['fields'] ?? []))->map(fn ($f) => [
                    'key' => $f['key'] ?? $f['name'] ?? '',
                    'name' => $f['key'] ?? $f['name'] ?? '',
                    'label' => $f['label'] ?? ucfirst((string) ($f['key'] ?? '')),
                    'type' => $f['type'] ?? 'text',
                ])->filter(fn ($f) => $f['key'] !== '')->values()->all(),
                'is_public' => true,
            ]);
            foreach ((array) ($def['items'] ?? []) as $i => $data) {
                $col->items()->create([
                    'site_id' => $site->id,
                    'data' => (array) $data,
                    'position' => $i,
                    'status' => 'published',
                ]);
            }
        }
    }

    /** @param  list<array{name:string,title?:string,fields?:array}>  $forms */
    private function applyForms(Site $site, array $forms): void
    {
        foreach ($forms as $form) {
            if (empty($form['name'])) {
                continue;
            }
            $site->forms()->firstOrCreate(['name' => $form['name']], [
                'title' => (string) ($form['title'] ?? ucfirst($form['name'])),
                'fields' => (array) ($form['fields'] ?? []),
                'is_active' => true,
            ]);
        }
    }

    /**
     * Copy the template's shipped image assets into the site's own media
     * library (defaults the owner can browse/replace), and rewrite the
     * scaffolded TOP-LEVEL image nodes to @media refs. Repeatable-row image
     * nodes ("Member 1 Image") keep their raw /assets paths — the shells'
     * items() prefix-interpolation would break on resolved /storage URLs.
     */
    private function importAssets(Site $site, string $appKey): void
    {
        foreach ([public_path("nuxt-preview/{$appKey}/assets"), base_path("templates/{$appKey}/public/assets")] as $dir) {
            if (is_dir($dir)) {
                break;
            }
            $dir = null;
        }
        if (! $dir) {
            return;
        }

        // Every image the template ships, wherever it keeps it — recursive.
        $importer = app(AssetImporter::class);
        $refs = []; // basename → @media ref
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && ($ref = $importer->importLocal($site, $file->getPathname()))) {
                $refs[strtolower($file->getFilename())] = $ref;
            }
        }
        if ($refs === []) {
            return;
        }

        // Top-level image nodes only (no "… 1 …" row labels) → the media copy.
        Node::whereHas('component', fn ($q) => $q->where('site_id', $site->id))
            ->where('type', 'image')->where('value', 'like', '/assets/%')
            ->get()
            ->each(function ($node) use ($refs) {
                if (preg_match('/\s\d+\s/', ' '.$node->label.' ')) {
                    return; // repeatable-row node — leave the raw path
                }
                $base = strtolower(basename((string) $node->value));
                if (isset($refs[$base])) {
                    $node->update(['value' => $refs[$base]]);
                }
            });
    }

    /**
     * Seed the manifest's booking block: services (firstOrCreate by name) and
     * site-wide availability, written exactly the way ServiceApiController
     * does (bookings feature config) so the admin editors read it back.
     */
    private function applyBooking(Site $site, SiteTemplate $row, string $appKey): void
    {
        $manifest = TemplateAppRegistry::find($appKey)['manifest'] ?? [];
        $booking = (array) ($manifest['booking'] ?? $row->payload['booking'] ?? []);
        if ($booking === []) {
            return;
        }

        foreach ((array) ($booking['services'] ?? []) as $svc) {
            if (empty($svc['name'])) {
                continue;
            }
            $site->services()->firstOrCreate(['name' => $svc['name']], [
                'kind' => (string) ($svc['kind'] ?? 'slot'),
                'duration_min' => $svc['duration_min'] ?? null,
                'price_cents' => $svc['price_cents'] ?? null,
                'description' => $svc['description'] ?? null,
                'is_active' => true,
                'slug' => '',
            ]);
        }

        $availability = array_intersect_key(
            (array) ($booking['availability'] ?? $booking['settings'] ?? []),
            array_flip(['days', 'open_time', 'close_time', 'slot_minutes', 'lead_hours', 'horizon_days', 'day_hours']),
        );
        if ($availability !== []) {
            $stored = (array) ($site->siteFeatures()->where('key', 'bookings')->value('config') ?? []);
            // Template defaults never clobber values the owner already set.
            $site->saveFeatureConfig('bookings', array_merge($availability, $stored));
        }
    }

    /** Forms declared for this design: registry manifest first, else the stored payload. */
    private function formsFor(SiteTemplate $row, string $appKey): array
    {
        $manifest = TemplateAppRegistry::find($appKey)['manifest'] ?? [];

        return (array) ($manifest['forms'] ?? $row->payload['forms'] ?? []);
    }

    private function applyTheme(Site $site, SiteTemplate $row, string $appKey, bool $wasApplied): void
    {
        $contract = $row->toContract();
        $theme = ($contract && method_exists($contract, 'theme')) ? (array) $contract->theme() : [];
        if ($theme === []) {
            $theme = (array) (TemplateRegistry::find($appKey)?->theme() ?? []);
        }
        if ($theme === []) {
            return;
        }
        if (! $wasApplied) {
            $row->update(['previous_theme' => $site->theme]);
        }
        $site->update(['theme' => $theme]);
    }

    /** Which built renderer app draws this design. */
    public function resolveAppKey(SiteTemplate $row): string
    {
        foreach ([$row->builtin_key, Str::slug((string) $row->name)] as $candidate) {
            if ($candidate && TemplateAppRegistry::exists($candidate)) {
                return $candidate;
            }
        }

        return TemplateAppRegistry::BLANK;
    }
}
