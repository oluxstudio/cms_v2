<?php

namespace App\Services;

use App\Jobs\InstallTemplateJob;
use App\Models\Collection;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\TemplateUpload;
use App\Models\User;
use App\Support\CollectionAutoFields;
use App\Support\CuratedTemplates;
use App\Support\TemplatePaths;
use App\Templates\TemplateAppRegistry;
use App\Templates\TemplatePackage;
use App\Templates\TemplateRegistry;
use Illuminate\Support\Facades\DB;
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
        for ($i = 2; ! Site::validSubdomainLabel($label) || Site::nameTaken($label); $i++) {
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
                'products' => $m['products'] ?? [],
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
        InstallTemplateJob::dispatch($site->id, $row->id, auth()->id());
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
        $site->setAttr(InstallProgress::ATTR, json_encode(['percent' => 1, 'label' => 'Getting started', 'step' => 'start', 'done' => 0, 'total' => 0]));

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
     *
     * SITE DATA BELONGS TO THE SITE. A template brings its layout, pages and
     * default content; it adds a form or (empty) collection only when the site
     * has none by that name, and NEVER changes what the site already has —
     * components, collections and their entries, posts, forms. Only the owner
     * edits those. $refresh = an update of the template the site already uses
     * (template:deploy / repo push): then its own sections, forms and
     * collections may be topped up with fields the template has gained.
     */
    public function install(Site $site, SiteTemplate $row, bool $refresh = false): void
    {
        // What the site had before this run — a switch/apply never edits these.
        $existingComponentIds = $refresh ? null : Component::where('site_id', $site->id)->pluck('id')->all();

        // bind() just stamped applied_at, so "was it applied before this
        // run?" means: was the previous theme already stashed for this row?
        $wasApplied = $row->previous_theme !== null;
        $appKey = $this->resolveAppKey($row);
        $progress = new InstallProgress($site);

        // A copy of the TEMPLATE: pages, their sections + sample text, the
        // header/footer layout, theme, forms (definitions) and its collections —
        // those marked "reset" (Admin › Templates: e.g. Bible Studies, Sermons)
        // start empty, the rest carry the template's entries. Never the
        // template's posts, products, booking services, assets, contacts, tasks
        // or messages.

        // 1. Content: pages + components (skips existing URLs — safe re-apply).
        $contract = $row->toContract();
        $pages = $contract ? $contract->pages() : [];
        $progress->step('pages', 'Creating pages', 0, count($pages));
        if ($pages) {
            $this->scaffolder->applyPages($site, $pages,
                fn (int $done, string $name) => $progress->step('pages', 'Creating “'.$name.'” and its sections', $done, count($pages)),
                topUp: $refresh);
        }

        // Pages a template update just added are published straight away, or the
        // live site's menu links to pages it cannot load yet.
        if ($refresh) {
            $this->publishNewPages($site);
        }

        // 1a. Chrome: the layout's header/nav + footer blocks wrap every page
        //     — without them the applied site is missing its navbar.
        $layout = [];
        if ($contract instanceof TemplatePackage) {
            $layouts = $contract->layouts(); // keyed by slug; 'default' preferred
            $layout = ($layouts['default'] ?? (reset($layouts) ?: []))['blocks'] ?? [];
        }
        $layout = $layout ?: (array) ($row->payload['layout'] ?? []);
        // Catalog/marketplace rows resolve to version contracts, not
        // TemplatePackage — fall back to the published package's layout so
        // their sites still get the header/footer chrome.
        if ($layout === [] && $appKey !== TemplateAppRegistry::BLANK) {
            $dir = TemplatePaths::packageDir($appKey);
            if (is_dir($dir)) {
                $layouts = (new TemplatePackage($dir))->layouts();
                $layout = ($layouts['default'] ?? (reset($layouts) ?: []))['blocks'] ?? [];
            }
        }
        $progress->step('layout', 'Building the header and footer');
        if ($layout !== []) {
            $this->scaffolder->applyChrome($site, $layout);
        }

        // 2. Modules: every commerce feature switches on (idempotent), so the
        //    template's booking/store/estimator pages have working backends —
        //    the owner can still toggle any of them off in the Marketplace.
        $progress->step('features', 'Switching on features');
        $site->enableCommerceSuite();

        // 3. Forms the template declares (manifest-driven, firstOrCreate).
        $progress->step('forms', 'Setting up forms');
        $this->applyForms($site, $this->formsFor($row, $appKey), $refresh);

        // 3a. Collections the template declares (repeatable lists the owner
        //     can add/remove items on). Matched by name: a collection the site
        //     already has (e.g. from its previous template) is REUSED with its
        //     entries; only missing ones are created, empty. Collections the
        //     new template doesn't use are left alone and still served.
        $progress->step('collections', 'Setting up collections');
        $manifest = TemplateAppRegistry::find($appKey)['manifest'] ?? [];
        $this->applyCollections($site, (array) ($manifest['collections'] ?? $row->payload['collections'] ?? []), $refresh, $this->resetCollections($row, $appKey));

        // 3a1b. Wire components to the collection that feeds them (static scan
        //       of the published app) — the connect editor then shows the
        //       data-source grid whenever such a component is selected.
        $progress->step('linking', 'Connecting sections to collections');
        $this->linkComponentCollections($site, $appKey, $existingComponentIds);

        // 3b. Booking: seed the template's services + availability so the
        //     template's appointment flow is bookable out of the box.
        $progress->step('booking', 'Applying booking settings');
        $this->applyBooking($site, $row, $appKey);

        // 4. The template's colour tokens become the site theme; the outgoing
        //    theme is stashed on the row so "stop using" can restore it.
        $progress->step('theme', 'Applying colours and fonts');
        $this->applyTheme($site, $row, $appKey, $wasApplied);

        // 5. Only the CURRENT template's pages, sections and forms are active;
        //    any other template's are parked (never deleted) until it's used again.
        $this->syncActivation($site);

        $progress->finish();
        $site->setAttr('template_install', 'done');
    }

    /** A template's display name for admin labels ("Hairco"), from its key. */
    public static function templateName(string $key): string
    {
        $name = TemplateAppRegistry::find($key)['name'] ?? null;
        $name ??= TemplateUpload::where('key', $key)->value('name');

        return $name ?: Str::headline($key);
    }

    /** Mark that keeps an item active whatever the template (the owner switched it back on). */
    public const OWNER_KEEP = '@owner';

    /**
     * Template-owned structure is active only under its own template:
     *   · every page / form / page-section a template of this site declares is
     *     tagged with that template's key (template_keys) — this also back-fills
     *     what earlier templates created;
     *   · it is ACTIVE when one of its keys is the site's current template, or
     *     it carries no keys (the owner's own) or the OWNER_KEEP mark;
     *   · inactive items stay in the database and come back on switching back.
     * Collections, entries, posts and submissions are data — never touched.
     * A site on no template (blank) has everything active.
     */
    public function syncActivation(Site $site): void
    {
        $current = $site->renderTemplateKey();
        $current = $current === TemplateAppRegistry::BLANK ? null : $current;

        // What each template this site has installed declares.
        $declared = [];   // key => ['pages' => [url => [block names]], 'forms' => [names]]
        foreach ($site->installedTemplates()->get() as $row) {
            $key = $this->resolveAppKey($row);
            if ($key === TemplateAppRegistry::BLANK) {
                continue;
            }
            try {
                $defs = $row->toContract()?->pages() ?? [];
            } catch (\Throwable $e) {
                report($e);
                $defs = [];
            }
            foreach ($defs as $def) {
                $url = $def['url'] ?? '/';
                $names = collect((array) ($def['blocks'] ?? []))->pluck('name')->filter()->all();
                $declared[$key]['pages'][$url] = array_values(array_unique(array_merge($declared[$key]['pages'][$url] ?? [], $names)));
            }
            foreach ($this->formsFor($row, $key) as $form) {
                if (! empty($form['name'])) {
                    $declared[$key]['forms'][] = $form['name'];
                }
            }
        }

        $isActive = fn (array $keys) => $current === null || $keys === [] || in_array(self::OWNER_KEEP, $keys, true) || in_array($current, $keys, true);
        $merge = function (?array $have, callable $declares) use ($declared): array {
            $keys = (array) ($have ?? []);
            if (in_array(self::OWNER_KEEP, $keys, true)) {
                return $keys; // the owner's call — never re-tagged
            }
            foreach ($declared as $key => $d) {
                if ($declares($d)) {
                    $keys[] = $key;
                }
            }

            return array_values(array_unique($keys));
        };

        foreach ($site->pages()->get() as $page) {
            $keys = $merge($page->template_keys, fn ($d) => array_key_exists($page->url, $d['pages'] ?? []));
            $active = $isActive($keys);
            if ($keys !== (array) ($page->template_keys ?? []) || $active !== (bool) $page->template_active) {
                $page->forceFill(['template_keys' => $keys ?: null, 'template_active' => $active])->saveQuietly();
            }

            // Its sections: a block the current template declares on this page shows; others are parked.
            foreach ($page->components()->get() as $component) {
                $pivotKeys = json_decode((string) ($component->pivot->template_keys ?? ''), true) ?: [];
                $keys = $merge($pivotKeys, fn ($d) => in_array($component->name, $d['pages'][$page->url] ?? [], true));
                $active = $isActive($keys);
                if ($keys !== $pivotKeys || $active !== (bool) $component->pivot->active) {
                    DB::table('page_component')
                        ->where('page_id', $page->id)->where('component_id', $component->id)
                        ->update(['template_keys' => $keys ? json_encode($keys) : null, 'active' => $active]);
                }
            }
        }

        foreach ($site->forms()->get() as $form) {
            $keys = $merge($form->template_keys, fn ($d) => in_array($form->name, $d['forms'] ?? [], true));
            $active = $isActive($keys);
            if ($keys !== (array) ($form->template_keys ?? []) || $active !== (bool) $form->template_active) {
                $form->forceFill(['template_keys' => $keys ?: null, 'template_active' => $active])->saveQuietly();
            }
        }
    }

    /**
     * Publish the site's never-published pages (no page.json yet) — only on a
     * site that already publishes, so nothing goes out on a site that never
     * has; a page never published holds no owner drafts to leak.
     */
    public function publishNewPages(Site $site): int
    {
        if (! $site->pages()->whereNotNull('page_json_path')->exists()) {
            return 0;
        }
        $pages = $site->pages()->whereNull('page_json_path')->where('is_published', true)->where('template_active', true)->get();
        foreach ($pages as $page) {
            app(\App\Services\SiteConnect\PageJsonPublisher::class)->publish($page);
        }

        return $pages->count();
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
                $this->install($site, $row, refresh: true);
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
     * The manifest's collections: name + field schema, never entries.
     * Matched by name — the site's existing collections (and their data) are
     * kept and their declared field types synced; missing ones are created empty.
     */
    /**
     * The template's collections that RESET for each site (start empty — the
     * site's own data), as set in Admin › Templates › Edit. Lower-cased names.
     *
     * @return list<string>
     */
    private function resetCollections(SiteTemplate $row, string $appKey): array
    {
        $tpl = $row->template_id ? Template::find($row->template_id) : null;
        $tpl ??= Template::where('builtin_key', $appKey)->orWhere('slug', $appKey)->first();

        return array_map(fn ($n) => mb_strtolower(trim((string) $n)), (array) ($tpl?->reset_collections ?? []));
    }

    /**
     * Template content: a collection that is NOT reset gets the template's
     * entries — but only while it has never held any (soft-deleted ones count),
     * so nothing the owner removed comes back and their own entries are never touched.
     */
    private function seedTemplateEntries(Collection $col, array $def, array $reset): void
    {
        if (in_array(mb_strtolower($col->name), $reset, true) || empty($def['items'])
            || $col->items()->withTrashed()->exists()) {
            return;
        }
        foreach (array_values((array) $def['items']) as $i => $data) {
            $col->items()->create(['site_id' => $col->site_id, 'data' => (array) $data, 'position' => $i, 'status' => 'published']);
        }
    }

    private function applyCollections(Site $site, array $collections, bool $refresh = false, array $reset = []): void
    {
        foreach ($collections as $def) {
            if (empty($def['name'])) {
                continue;
            }
            $existing = $site->collections()->where('name', $def['name'])->first();
            if ($existing && ! $refresh) {
                // The site's collection — its fields and entries are the owner's;
                // a never-used one still gets the template's content.
                $this->seedTemplateEntries($existing, $def, $reset);

                continue;
            }
            if ($existing) {
                // Fields the template renamed (@olux-field … was=old) — key and values move first.
                $this->renameDeclaredFields($existing, $def);
                $existing->refresh();
                // Template gained fields → merge them in additively so the
                // editor renders the new keys (owner fields/order untouched).
                $have = collect($existing->fields ?? [])->pluck('key')->filter()->all();
                $missing = collect((array) ($def['fields'] ?? []))
                    ->map(fn ($f) => ['key' => $f['key'] ?? $f['name'] ?? '', 'name' => $f['key'] ?? $f['name'] ?? '',
                        'label' => $f['label'] ?? ucfirst((string) ($f['key'] ?? '')), 'type' => $f['type'] ?? 'text'] + array_intersect_key($f, array_flip(['fields', 'options', 'required', 'auto', 'hidden', 'show', 'optionsFrom'])))
                    ->filter(fn ($f) => $f['key'] !== '' && ! in_array($f['key'], $have, true))
                    ->values()->all();
                // Fields the template DECLARES (@olux-field) keep their type,
                // required flag and options in step with the template; the
                // owner's label stays unless the template names one.
                $declared = collect((array) ($def['fields'] ?? []))->filter(fn ($f) => ! empty($f['declared']))->keyBy('key');
                $synced = collect((array) $existing->fields)->map(function ($f) use ($declared) {
                    $d = $declared->get($f['key'] ?? '');
                    if (! $d) {
                        return $f;
                    }
                    $f['type'] = $d['type'] ?? ($f['type'] ?? 'text');
                    $f['required'] = (bool) ($d['required'] ?? false);
                    // Options belong to choice fields only (drop a guessed select's).
                    if (in_array($f['type'], ['select', 'radio'], true) && isset($d['options'])) {
                        $f['options'] = $d['options'];
                    } elseif (! in_array($f['type'], ['select', 'radio'], true)) {
                        unset($f['options']);
                    }
                    isset($d['label']) ? $f['label'] = $d['label'] : null;
                    // A declared system source (auto=created_at) is template intent; an
                    // owner-set source on a field the template leaves manual stays.
                    isset($d['auto']) ? $f['auto'] = $d['auto'] : null;
                    // Declared visibility and conditions follow the template.
                    ! empty($d['hidden']) ? $f['hidden'] = true : null;
                    isset($d['show']) ? $f['show'] = $d['show'] : null;
                    if (isset($d['optionsFrom'])) {
                        $f['optionsFrom'] = $d['optionsFrom'];
                        unset($f['options']); // choices come from that collection, not a fixed list
                    }
                    // A declared rows/group schema (typed sub-fields) replaces the guessed one.
                    if (! empty($d['fields']) && collect($d['fields'])->contains(fn ($s) => ! empty($s['declared']))) {
                        $f['fields'] = $d['fields'];
                    }

                    return $f;
                })->all();
                if ($missing !== [] || $synced !== (array) $existing->fields) {
                    $oldAuto = CollectionAutoFields::autoFields($existing);
                    $oldTypes = collect((array) $existing->fields)->mapWithKeys(fn ($f) => [(string) ($f['key'] ?? '') => $f['type'] ?? 'text']);
                    $existing->update(['fields' => array_merge($synced, $missing)]);
                    // A field whose type changed: stored values follow ("yes" → on for a toggle, "12" → 12).
                    $retyped = collect($synced)->filter(fn ($f) => isset($oldTypes[$f['key'] ?? '']) && $oldTypes[$f['key']] !== ($f['type'] ?? 'text'))->values()->all();
                    if ($retyped !== []) {
                        \App\Support\CollectionFieldShape::coerceEntries($existing, $retyped);
                    }
                    // Fields that just got a system source are filled on the existing entries.
                    if (CollectionAutoFields::autoFields($existing) !== $oldAuto) {
                        CollectionAutoFields::backfill($existing->fresh());
                    }
                }
                $this->seedTemplateEntries($existing->fresh(), $def, $reset);

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
                ] + array_intersect_key($f, array_flip(['fields', 'options', 'required', 'auto', 'hidden', 'show', 'optionsFrom'])))->filter(fn ($f) => $f['key'] !== '')->values()->all(),
                'is_public' => true,
            ]);
            // Reset collections start empty (the site's own data); the rest load
            // with the template's entries, as in its preview.
            $this->seedTemplateEntries($col, $def, $reset);
        }
    }

    /**
     * `@olux-field slug slug was=id`: the site's `id` field becomes `slug` —
     * same place in the form, the template's definition — and every entry's
     * value moves with it (kept where the new key already has a value).
     */
    private function renameDeclaredFields(\App\Models\Collection $collection, array $def): void
    {
        $fields = (array) ($collection->fields ?? []);
        $keys = collect($fields)->pluck('key')->filter()->all();
        $moves = [];
        foreach ((array) ($def['fields'] ?? []) as $d) {
            $new = (string) ($d['key'] ?? '');
            $was = (string) ($d['was'] ?? '');
            if ($new === '' || $was === '' || ! in_array($was, $keys, true)) {
                continue;
            }
            if (in_array($new, $keys, true)) {
                // Both keys exist (e.g. an earlier update added the new one alongside):
                // rename only while no entry holds a value under the new key.
                $used = $collection->items()->withTrashed()->get()->contains(fn ($i) => ! in_array(data_get($i->data, $new), [null, '', []], true));
                if ($used) {
                    continue;
                }
                $fields = array_values(array_filter($fields, fn ($f) => ($f['key'] ?? '') !== $new));
            }
            foreach ($fields as $i => $f) {
                if (($f['key'] ?? '') === $was) {
                    $fields[$i] = ['key' => $new, 'name' => $new, 'label' => $d['label'] ?? \Illuminate\Support\Str::headline($new), 'type' => $d['type'] ?? 'text']
                        + array_intersect_key($d, array_flip(['fields', 'options', 'required', 'auto', 'hidden', 'show', 'optionsFrom']));
                }
            }
            $moves[$was] = $new;
        }
        if ($moves === []) {
            return;
        }
        $collection->update(['fields' => $fields]);
        $collection->items()->withTrashed()->get()->each(function ($item) use ($moves) {
            $data = (array) ($item->data ?? []);
            foreach ($moves as $was => $new) {
                if (! array_key_exists($was, $data)) {
                    continue;
                }
                if (($data[$new] ?? '') === '' || ($data[$new] ?? null) === null) {
                    $data[$new] = $data[$was];
                }
                unset($data[$was]);
            }
            if ($data !== $item->data) {
                $item->data = $data;
                $item->saveQuietly();
            }
        });
        // A renamed field that is system-filled (a slug) is filled on every entry now.
        if (collect($fields)->contains(fn ($f) => in_array($f['key'] ?? '', $moves, true) && CollectionAutoFields::valid($f['auto'] ?? null))) {
            CollectionAutoFields::backfill($collection->fresh());
        }
    }

    /** @param  list<array{name:string,title?:string,fields?:array}>  $forms */
    /**
     * Component → collection linkage, derived from the published sources:
     * a component reads items('slug') directly or through a composable
     * accessor (useMembers → items('leadership')). Sets collection_id on the
     * site's matching components so editors can jump to the data source.
     */
    private function linkComponentCollections(Site $site, string $appKey, ?array $protectedIds = null): void
    {
        // $protectedIds: components the site already had — a switch/apply links
        // only the sections it just created, never re-wiring or trimming the owner's.
        $own = fn ($q) => $protectedIds === null ? $q : $q->whereNotIn('components.id', $protectedIds);
        // The published app (first-party or uploaded) keeps its source under app/.
        $appDir = TemplatePaths::appDir($appKey).'/app';
        if (! is_dir($appDir)) {
            return;
        }

        // Accessor name → collection slug: each exported use* accessor maps to
        // the FIRST items('slug') inside its own export segment, so a file
        // exporting several accessors (useSermons + useSermonSeries) or a
        // multi-source helper never smears one slug across everything.
        $byAccessor = [];
        foreach (glob("$appDir/composables/*.{ts,js}", GLOB_BRACE) ?: [] as $file) {
            $code = (string) file_get_contents($file);
            $parts = preg_split('#export (?:const|function) (use\w+)#', $code, -1, PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 1; $i < count($parts) - 1; $i += 2) {
                if (preg_match_all("#items\(['\"]([\w-]+)['\"]#", $parts[$i + 1], $m) && count(array_unique($m[1])) === 1) {
                    $byAccessor[$parts[$i]] = $m[1][0];
                }
            }
        }

        // Component file → slug. Priority: explicit @olux-source marker,
        // then a direct items() call, then accessor usage.
        $links = [];
        foreach (glob("$appDir/components/*.vue") ?: [] as $file) {
            $code = (string) file_get_contents($file);
            $slug = null;
            $explicit = false;
            if (preg_match('#@olux-source\s+([\w-]+)#', $code, $m)) {
                $slug = $m[1];
                $explicit = true;
            } elseif (preg_match("#items\(['\"]([\w-]+)['\"]#", $code, $m)) {
                $slug = $m[1];
            } else {
                // Several accessors may appear (useSermons + useSermonSeries):
                // the one used EARLIEST is the component's primary source.
                $best = PHP_INT_MAX;
                foreach ($byAccessor as $fn => $s) {
                    $pos = strpos($code, $fn.'(');
                    if ($pos !== false && $pos < $best) {
                        [$best, $slug] = [$pos, $s];
                    }
                }
            }
            if ($slug) {
                // Every list the block reads (direct items() calls + accessors) —
                // the extra ones become collection fields inside the component.
                preg_match_all("#items\(['\"]([\w-]+)['\"]#", $code, $direct);
                $all = $direct[1];
                foreach ($byAccessor as $fn => $s) {
                    if (str_contains($code, $fn.'(')) {
                        $all[] = $s;
                    }
                }
                $name = Str::headline(preg_replace('#Block$#', '', basename($file, '.vue')));
                $links[$name] = ['slug' => $slug, 'explicit' => $explicit, 'all' => array_values(array_unique($all))];
            }
        }
        if ($links === []) {
            return;
        }

        // Blocks the CURRENT package declares node-less: every field a legacy
        // install scaffolded for them is dead — the rewritten component renders
        // only its collection's rows, never those nodes.
        $nodeless = [];
        $packageDir = TemplatePaths::packageDir($appKey);
        if (is_dir($packageDir)) {
            foreach ((new TemplatePackage($packageDir))->pages() as $p) {
                foreach ((array) ($p['blocks'] ?? []) as $b) {
                    if ($n = $b['name'] ?? null) {
                        $nodeless[$n] = ($nodeless[$n] ?? true) && empty($b['nodes']);
                    }
                }
            }
        }

        $collections = $site->collections()->get()->keyBy(fn ($c) => Str::slug($c->slug ?: $c->name));
        foreach ($links as $name => $link) {
            // camelCase slugs in code (items('bibleStudies')) → kebab collection slugs
            $col = $collections->get(Str::slug(Str::kebab($link['slug'])));
            if ($col) {
                // An explicit @olux-source marker is author intent — it
                // ALWAYS wins (never freeze on an old heuristic guess);
                // heuristic links only fill gaps.
                $q = $own($site->contentComponents()->where('name', $name));
                if (! $link['explicit']) {
                    $q->whereNull('collection_id');
                }
                $q->update(['collection_id' => $col->id]);

                // Explicitly sourced + node-less in the manifest → shed stale
                // scaffolded fields, so the connect editor opens the collection
                // grid instead of a dead fields form.
                if ($link['explicit'] && ($nodeless[$name] ?? false)) {
                    foreach ($own($site->contentComponents()->where('name', $name))->get() as $component) {
                        $component->nodes()->delete();
                    }
                }
            }

            // The block's other lists (Hero → Hero Words, Contact Info) become
            // collection fields, so the editor shows each as a grid inside it.
            foreach ($link['all'] ?? [] as $slug) {
                $other = $collections->get(Str::slug(Str::kebab($slug)));
                if (! $other || ($col && $other->id === $col->id)) {
                    continue;
                }
                foreach ($own($site->contentComponents()->where('name', $name))->get() as $component) {
                    if ($component->collection_id === $other->id
                        || $component->nodes()->where('type', 'collection')->where('value', $other->id)->exists()) {
                        continue;
                    }
                    $component->nodes()->create([
                        'label' => $other->name, 'type' => 'collection', 'value' => $other->id,
                        'parent' => '0', 'order' => (int) $component->nodes()->max('order') + 1,
                    ]);
                }
            }
        }
    }

    private function applyForms(Site $site, array $forms, bool $refresh = false): void
    {
        foreach ($forms as $form) {
            if (empty($form['name'])) {
                continue;
            }
            $existing = $site->forms()->firstOrCreate(['name' => $form['name']], [
                'title' => (string) ($form['title'] ?? ucfirst($form['name'])),
                'fields' => (array) ($form['fields'] ?? []),
                'is_active' => true,
            ]);

            // Template gained fields since this form was created → merge them
            // in ADDITIVELY (owner-edited fields and order are never touched).
            $have = collect((array) $existing->fields)->pluck('key')->filter()->all();
            $missing = collect((array) ($form['fields'] ?? []))
                ->filter(fn ($fl) => ! empty($fl['key']) && ! in_array($fl['key'], $have, true))
                ->values()->all();
            // Only on a refresh of the same template — a switch never edits the site's forms.
            if ($refresh && $missing !== [] && ! $existing->wasRecentlyCreated) {
                $existing->update(['fields' => array_merge((array) $existing->fields, $missing)]);
            }
        }
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

        // Fresh copy: the template's sample services are NOT created (that's data);
        // only its booking availability settings apply.

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
        // The owner's picked colours (Properties → Colours, keyed by the template's
        // own --color-* names) survive every re-apply and sync.
        $picked = collect(is_array($site->theme) ? $site->theme : [])
            ->filter(fn ($v, $k) => str_starts_with((string) $k, 'color-'))->all();
        $site->update(['theme' => array_merge($theme, $picked)]);
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
