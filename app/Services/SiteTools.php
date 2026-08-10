<?php

namespace App\Services;

use App\Mail\ModuleCreatedNotification;
use App\Models\Collection;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Services\Modules\DeclarativeModuleEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The single action surface for "prompting a site".
 *
 * Both the deterministic command parser (SitePrompt) and the LLM agent
 * (SiteAgent) call execute() — so every capability is defined once.
 * definitions() returns Anthropic tool-use schemas for the LLM.
 */
class SiteTools
{
    /** Node value types the content editor understands. */
    private const NODE_TYPES = ['text', 'number', 'boolean', 'color', 'url', 'image'];

    /**
     * Anthropic tool definitions, filtered to what THIS user may do on THIS site.
     *
     * @return array<int,array<string,mixed>>
     */
    public function definitions(Site $site, User $user): array
    {
        $canManage = $site->canManageTeam($user);
        $hasStore = $site->hasFeature('store');

        $tools = [
            $this->tool('site_info', 'Get detailed information about the site: name, slug, description, creation date, URL, enabled features, team size, and overall counts.', []),

            $this->tool('site_status', 'Get a quick count summary of the site: pages, components, forms, contacts (and products if the store is on).', []),

            $this->tool('list_pages', 'List the names, URLs, and publish status of all pages on the site.', []),

            $this->tool('list_forms', 'List the forms on the site with their field counts and active status.', []),

            $this->tool('create_page', 'Create a new draft page.', [
                'name' => ['type' => 'string', 'description' => 'Human page name, e.g. "About Us".'],
            ], ['name']),

            $this->tool('publish_page', 'Publish or unpublish a page.', [
                'page' => ['type' => 'string', 'description' => 'Page name or URL.'],
                'published' => ['type' => 'boolean', 'description' => 'true to publish, false to unpublish.'],
            ], ['page', 'published']),

            $this->tool('create_form', 'Create a form for collecting submissions.', [
                'name' => ['type' => 'string'],
                'title' => ['type' => 'string', 'description' => 'Optional public title.'],
            ], ['name']),

            $this->tool('add_form_field', 'Add a field to an existing form.', [
                'form' => ['type' => 'string', 'description' => 'Form name or title.'],
                'label' => ['type' => 'string', 'description' => 'Human-readable field label, e.g. "Email address".'],
                'type' => ['type' => 'string', 'enum' => ['text', 'email', 'textarea', 'number', 'tel', 'url', 'date', 'select', 'checkbox', 'radio'], 'description' => 'Field input type.'],
                'required' => ['type' => 'boolean', 'description' => 'Whether the field is required. Defaults to false.'],
                'options' => ['type' => 'array',  'items' => ['type' => 'string'], 'description' => 'Allowed values for select/radio fields.'],
            ], ['form', 'label', 'type']),

            $this->tool('create_service', 'Add a bookable service to the appointment booking system. Enable the "bookings" feature first (toggle_feature). Visitors book these at /book.', [
                'name' => ['type' => 'string', 'description' => 'Service name, e.g. "Dental Checkup".'],
                'duration_min' => ['type' => 'integer', 'description' => 'Appointment length in minutes (default 30).'],
                'price' => ['type' => 'number', 'description' => 'Price in major units; 0 for free (default 0).'],
                'description' => ['type' => 'string', 'description' => 'Optional short description.'],
            ], ['name']),

            $this->tool('add_booking_page', 'Create a "Book" page on the site with the appointment-booking calendar embedded (also enables the bookings feature). This is the in-site booking interface visitors use. Create services with create_service too.', [
                'service' => ['type' => 'string', 'description' => 'Optional single service slug to book on this page; blank lets visitors choose.'],
            ], []),

            $this->tool('list_modules', 'List every capability/module available on this site (built-in features like store/bookings/donations/forms AND any AI-created declarative modules), whether each is installed, and the user needs each covers. ALWAYS call this before deciding a capability is missing, so you reuse an existing module instead of creating a duplicate.', []),

        ];

        if ($hasStore) {
            $tools[] = $this->tool('create_product', 'Add a product to the store.', [
                'name' => ['type' => 'string'],
                'price' => ['type' => 'number', 'description' => 'Price in major units, e.g. 19.99.'],
            ], ['name', 'price']);

            $tools[] = $this->tool('list_products', 'List the store products.', []);
        }

        if ($canManage) {
            $tools[] = $this->tool('toggle_feature', 'Enable or disable a site feature (Marketplace app). Use "bookings" to turn on the appointment booking system.', [
                'feature' => ['type' => 'string', 'enum' => array_keys(config('features', []))],
                'enabled' => ['type' => 'boolean'],
            ], ['feature', 'enabled']);

            $tools[] = $this->tool('create_module', 'Create a NEW declarative module ONLY when no built-in feature (store/bookings/donations), no existing module, and no plain form covers the need (check list_modules first). A module = an entity with fields + a public page where visitors submit entries and optionally browse them — use for list+submit entities like job applications, event RSVPs, testimonials, member directories. Adds an editable page and emails the site admins. You declare entities and fields only; you never write code.', [
                'name' => ['type' => 'string', 'description' => 'Module / entity name, e.g. "Job Applications".'],
                'fields' => [
                    'type' => 'array',
                    'description' => 'The fields visitors fill in (1–12).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => Collection::FIELD_TYPES],
                            'required' => ['type' => 'boolean'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'For select/radio only.'],
                        ],
                        'required' => ['label', 'type'],
                    ],
                ],
                'public_list' => ['type' => 'boolean', 'description' => 'Show submitted entries publicly (default false).'],
                'public_submit' => ['type' => 'boolean', 'description' => 'Let visitors submit entries (default true).'],
            ], ['name', 'fields']);
        }

        return $tools;
    }

    /**
     * Run a tool against the site. Returns a normalized result.
     *
     * @return array{ok:bool,message:string}
     */
    /** Tools that change state — only these are recorded as performed tasks. */
    private const MUTATING = [
        'create_page', 'publish_page',
        'create_form', 'add_form_field',
        'create_product', 'toggle_feature',

        'create_service', 'add_booking_page', 'create_module',
    ];

    /** Max fields an AI-created declarative module may define. */
    private const MAX_MODULE_FIELDS = 12;

    public function execute(Site $site, User $user, string $name, array $input): array
    {
        try {
            $result = match ($name) {
                'site_info' => $this->siteInfo($site),
                'site_status' => $this->siteStatus($site),
                'list_pages' => $this->listPages($site),
                'list_forms' => $this->listForms($site),
                'create_page' => $this->createPage($site, $input),
                'publish_page' => $this->publishPage($site, $input),
                'create_form' => $this->createForm($site, $input),
                'add_form_field' => $this->addFormField($site, $input),
                'create_product' => $this->createProduct($site, $user, $input),
                'list_products' => $this->listProducts($site, $user),
                'toggle_feature' => $this->toggleFeature($site, $user, $input),
                'create_service' => $this->createService($site, $input),
                'add_booking_page' => $this->addBookingPage($site, $input),
                'list_modules' => $this->listModules($site),
                'create_module' => $this->createModule($site, $user, $input),
                default => $this->err("Unknown action: {$name}."),
            };
        } catch (\Throwable $e) {
            $result = $this->err('That action failed: '.$e->getMessage());
        }

        // Persist every performed mutating task (drives the toast + the activity feed).
        if (in_array($name, self::MUTATING, true)) {
            app(TaskLogger::class)->record(
                site: $site,
                actor: $user,
                title: Str::headline($name),
                level: $result['ok'] ? 'success' : 'error',
                type: str_replace('_', '.', $name),
                message: ltrim($result['message'], "\xe2\x9c\x93 \t"),
                // template apply is a milestone — alert the whole team
                alertTeam: false,
            );
        }

        return $result;
    }

    // ── Handlers ───────────────────────────────────────────────────────

    private function siteInfo(Site $site): array
    {
        $features = collect(array_keys(config('features', [])))
            ->filter(fn ($f) => $site->hasFeature($f))->values();

        $lines = [
            "Site name: {$site->name}",
            'Slug / handle: '.($site->slug ?? Str::slug($site->name)),
            'Created: '.$site->created_at?->format('M j, Y').' ('.$site->created_at?->diffForHumans().')',
            'Pages: '.$site->pages()->count().' ('.$site->pages()->where('is_published', true)->count().' published)',
            'Forms: '.$site->forms()->count(),
            'Team members: '.$site->members()->count(),
            'Contacts / leads: '.$site->contacts()->count(),
            'Enabled features: '.($features->join(', ') ?: 'none'),
        ];

        if ($site->description) {
            array_splice($lines, 1, 0, ['Description: '.$site->description]);
        }

        return $this->ok(implode("\n", $lines));
    }

    private function siteStatus(Site $site): array
    {
        $parts = [
            $site->pages()->count().' pages',
            $site->contacts()->count().' contacts',
        ];
        if ($site->hasFeature('store')) {
            $parts[] = $site->products()->count().' products';
        }

        return $this->ok('📊 '.Str::headline($site->name).': '.implode(' · ', $parts).'.');
    }

    private function listPages(Site $site): array
    {
        $pages = $site->pages()->latest()->take(20)->get();
        if ($pages->isEmpty()) {
            return $this->ok('No pages yet. Try: create page Home.');
        }
        $list = $pages->map(fn ($p) => $p->name.' ('.$p->url.', '.($p->is_published ? 'live' : 'draft').')')->join('; ');

        return $this->ok('Pages: '.$list.'.');
    }

    private function listForms(Site $site): array
    {
        $forms = $site->forms()->withCount('responses')->latest()->take(20)->get();
        if ($forms->isEmpty()) {
            return $this->ok('No forms yet. Try: create a contact form with name, email, and message fields.');
        }

        $list = $forms->map(function ($f) {
            $fields = count($f->fields ?? []);
            $active = $f->is_active ? 'active' : 'inactive';
            $resps = $f->responses_count ?? 0;

            return "{$f->title} ({$fields} fields, {$resps} responses, {$active})";
        })->join('; ');

        return $this->ok("Forms: {$list}.");
    }

    private function createPage(Site $site, array $in): array
    {
        $name = trim((string) ($in['name'] ?? ''), " \"'");
        if ($name === '') {
            return $this->err('A page name is required.');
        }
        $url = '/'.Str::slug($name);

        // Dedupe by name AND url so the agent can't create a second "Home"/"About"
        // (e.g. /home alongside the existing / ) — edit the existing page instead.
        $existing = $site->pages()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first()
            ?? $site->pages()->where('url', $url)->first();
        if ($existing) {
            return $this->err("A \"{$existing->name}\" page already exists at {$existing->url} — edit that one instead of creating a duplicate.");
        }

        $site->pages()->create(['name' => $name, 'url' => $url, 'keywords' => '', 'is_published' => false]);

        return $this->ok("✓ Created page \"{$name}\" at {$url} (draft).");
    }

    private function publishPage(Site $site, array $in): array
    {
        $page = $this->findPage($site, (string) ($in['page'] ?? ''));
        if (! $page) {
            return $this->err('No matching page found.');
        }
        $published = filter_var($in['published'] ?? true, FILTER_VALIDATE_BOOL);
        $page->update(['is_published' => $published]);

        return $this->ok("✓ \"{$page->name}\" is now ".($published ? 'published (live)' : 'a draft').'.');
    }

    private function createForm(Site $site, array $in): array
    {
        $name = trim((string) ($in['name'] ?? ''), " \"'");
        if ($name === '') {
            return $this->err('A form name is required.');
        }
        $site->forms()->create([
            'name' => $name,
            'title' => $in['title'] ?? $name,
            'description' => '',
            'fields' => [],
            'is_active' => true,
        ]);

        return $this->ok("✓ Created form \"{$name}\".");
    }

    private function addFormField(Site $site, array $in): array
    {
        $needle = Str::lower(trim((string) ($in['form'] ?? ''), " \"'"));
        $form = $site->forms()
            ->whereRaw('LOWER(name) = ? OR LOWER(title) = ?', [$needle, $needle])
            ->first();

        if (! $form) {
            return $this->err("No form named \"{$in['form']}\" found on this site.");
        }

        $allowed = ['text', 'email', 'textarea', 'number', 'tel', 'url', 'date', 'select', 'checkbox', 'radio'];
        $type = Str::lower(trim((string) ($in['type'] ?? 'text')));
        if (! in_array($type, $allowed, true)) {
            $type = 'text';
        }

        $label = trim((string) ($in['label'] ?? 'Field'));
        $key = Str::slug($label, '_') ?: 'field_'.Str::random(4);
        $fields = $form->fields ?? [];

        // Prevent duplicate keys
        if (collect($fields)->pluck('key')->contains($key)) {
            $key .= '_'.count($fields);
        }

        $fields[] = array_filter([
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => (bool) ($in['required'] ?? false),
            'options' => (! empty($in['options']) && is_array($in['options'])) ? $in['options'] : null,
        ], fn ($v) => $v !== null);

        $form->update(['fields' => $fields]);

        return $this->ok("✓ Added \"{$label}\" ({$type}) to form \"{$form->displayTitle()}\".");
    }

    private function createProduct(Site $site, User $user, array $in): array
    {
        if (! $site->hasFeature('store')) {
            return $this->err("The Store feature isn't enabled. Enable it from the Marketplace first.");
        }
        $name = trim((string) ($in['name'] ?? ''), " \"'");
        if ($name === '') {
            return $this->err('A product name is required.');
        }
        $cents = (int) round(((float) ($in['price'] ?? 0)) * 100);
        $slug = Str::slug($name) ?: 'product-'.Str::random(5);
        if ($site->products()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }
        $site->products()->create([
            'name' => $name, 'slug' => $slug, 'price_cents' => $cents,
            'currency' => $site->feature('store')['currency'] ?? 'usd',
            'is_active' => true, 'sort' => 0,
        ]);

        return $this->ok("✓ Added product \"{$name}\" ($".number_format($cents / 100, 2).').');
    }

    private function listProducts(Site $site, User $user): array
    {
        if (! $site->hasFeature('store')) {
            return $this->err("The Store feature isn't enabled.");
        }
        $names = $site->products()->latest()->take(20)->pluck('name');

        return $names->isEmpty()
            ? $this->ok('No products yet. Try: create product Mug for $15.')
            : $this->ok('Products: '.$names->join(', ').'.');
    }

    private function toggleFeature(Site $site, User $user, array $in): array
    {
        if (! $site->canManageTeam($user)) {
            return $this->err('Only the site owner or an admin can change features.');
        }
        $key = Str::lower((string) ($in['feature'] ?? ''));
        $valid = array_keys(config('features', []));
        if (! in_array($key, $valid, true)) {
            return $this->err('Feature must be one of: '.implode(', ', $valid).'.');
        }
        $enabled = filter_var($in['enabled'] ?? true, FILTER_VALIDATE_BOOL);
        $enabled ? $site->enableFeature($key) : $site->disableFeature($key);

        return $this->ok('✓ '.($enabled ? 'Enabled' : 'Disabled')." the \"{$key}\" feature. Refresh to see the nav update.");
    }

    private function createService(Site $site, array $in): array
    {
        $name = trim((string) ($in['name'] ?? ''), " \"'");
        if ($name === '') {
            return $this->err('A service name is required.');
        }

        $svc = $site->services()->create([
            'name' => $name,
            'slug' => $name,
            'duration_min' => max(5, (int) ($in['duration_min'] ?? 30)),
            'price_cents' => (int) round(((float) ($in['price'] ?? 0)) * 100),
            'description' => $in['description'] ?? null,
            'is_active' => true,
        ]);

        $note = $site->hasFeature('bookings') ? '' : ' (enable the "bookings" feature so visitors can book it).';

        return $this->ok("✓ Added bookable service \"{$name}\" ({$svc->duration_min} min){$note}");
    }

    /**
     * Create a real, editable "Book" page (url /booking — /book is reserved for the
     * feature route) holding a booking calendar block, and enable the feature. The
     * page uses the site's shared layout for nav/footer like any other page.
     */
    private function addBookingPage(Site $site, array $in): array
    {
        $site->enableFeature('bookings');

        $page = $site->pages()->where('url', '/booking')->first()
            ?? $site->pages()->create([
                'name' => 'Book',
                'url' => '/booking',
                'keywords' => 'book appointment booking',
                'is_published' => true,
            ]);

        // Page content is now built with blocks in the builder.

        Cache::forget("page_render:{$site->id}:{$page->url}");

        return $this->ok('✓ Added a "Book" page at /booking with the appointment calendar embedded. Add at least one service with create_service so visitors have something to book.');
    }

    /** Capability map: every module on the site, installed or available, with intents. */
    private function listModules(Site $site): array
    {
        $lines = [];
        foreach (ModuleRegistry::capabilityMap($site) as $m) {
            $state = $m['enabled'] ? 'installed' : 'available';
            $intents = $m['intents'] ? ' — for: '.implode(', ', array_slice($m['intents'], 0, 6)) : '';
            $lines[] = "• {$m['key']} ({$m['kind']}, {$state}) — {$m['name']}{$intents}";
        }

        return $this->ok("Modules on this site:\n".implode("\n", $lines));
    }

    /**
     * Create a declarative module (entity + fields + public page) when nothing
     * existing covers the need, then email the site admins. No code is generated.
     */
    private function createModule(Site $site, User $user, array $in): array
    {
        if (! $site->canManageTeam($user)) {
            return $this->err('Only the site owner or an admin can create a module.');
        }

        $name = trim((string) ($in['name'] ?? ''), " \"'");
        $slug = Str::slug($name);
        if ($slug === '') {
            return $this->err('A clear module name is required.');
        }
        if (ModuleRegistry::existsForSite($site, $slug)) {
            return $this->err("A \"{$name}\" capability already exists — use it instead (call list_modules to confirm).");
        }

        // Normalize + bound the fields.
        $fields = [];
        $usedKeys = [];
        foreach ((is_array($in['fields'] ?? null) ? $in['fields'] : []) as $f) {
            if (! is_array($f)) {
                continue;
            }
            $label = trim((string) ($f['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = in_array($f['type'] ?? '', Collection::FIELD_TYPES, true) ? $f['type'] : 'text';
            $key = Str::slug($label, '_') ?: ('field_'.(count($fields) + 1));
            $b = $key;
            $n = 2;
            while (in_array($key, $usedKeys, true)) {
                $key = $b.'_'.$n;
                $n++;
            }
            $usedKeys[] = $key;
            $opts = [];
            if (in_array($type, ['select', 'radio'], true) && is_array($f['options'] ?? null)) {
                $opts = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $f['options'])));
            }
            $fields[] = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => (bool) ($f['required'] ?? false), 'options' => $opts];
            if (count($fields) >= self::MAX_MODULE_FIELDS) {
                break;
            }
        }
        if ($fields === []) {
            return $this->err('A module needs at least one field (e.g. Name, Email).');
        }

        $caps = [
            'public_list' => (bool) ($in['public_list'] ?? false),
            'public_submit' => array_key_exists('public_submit', $in) ? (bool) $in['public_submit'] : true,
        ];

        $module = app(DeclarativeModuleEngine::class)->provision($site, $name, $fields, $caps, $user);

        // Editable site page with the module block (mirror addBookingPage).
        $page = $site->pages()->where('url', '/'.$slug)->first()
            ?? $site->pages()->create(['name' => $name, 'url' => '/'.$slug, 'keywords' => $slug, 'is_published' => true]);
        // Page content is now built with blocks in the builder.

        Cache::forget("page_render:{$site->id}:{$page->url}");

        $this->notifyModuleCreated($site, $module, $page);

        $count = count($fields);

        return $this->ok("✓ Created a \"{$name}\" module ({$count} fields) and added a \"{$name}\" page at /{$slug}. Emailed your site admins. Manage entries under Collections.");
    }

    /** Email the site owner + admins that a module was created (best-effort, queued). */
    private function notifyModuleCreated(Site $site, $module, $page): void
    {
        try {
            $recipients = collect([$site->user])
                ->merge($site->members()->wherePivotIn('role', ['owner', 'admin'])->get())
                ->filter()->unique('id')->pluck('email')->filter()->unique()->values();

            if ($recipients->isEmpty()) {
                return;
            }

            $pageUrl = url('/preview/'.$site->name.$page->url);
            $adminUrl = url($site->name.'/collections');

            foreach ($recipients as $email) {
                Mail::to($email)->queue(new ModuleCreatedNotification($site, $module->name, $module->description, $pageUrl, $adminUrl));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function findPage(Site $site, string $needle): ?Page
    {
        $needle = trim($needle, " \"'");
        $url = '/'.ltrim(Str::slug($needle), '/');

        return $site->pages()->where('url', $url)
            ->orWhere(fn ($q) => $q->where('site_id', $site->id)->whereRaw('LOWER(name) = ?', [Str::lower($needle)]))
            ->first();
    }

    private function tool(string $name, string $desc, array $props, array $required = []): array
    {
        return [
            'name' => $name,
            'description' => $desc,
            'input_schema' => [
                'type' => 'object',
                'properties' => (object) $props,
                'required' => $required,
            ],
        ];
    }

    private function ok(string $msg): array
    {
        return ['ok' => true, 'message' => $msg];
    }

    private function err(string $msg): array
    {
        return ['ok' => false, 'message' => $msg];
    }
}
