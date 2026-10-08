<?php

namespace App\Models;

use App\Features\FeatureRegistry;
use App\Payments\PaymentGateway;
use App\Payments\PaymentManager;
use App\Support\SiteContentCache;
use App\Support\SiteProperties;
use App\Support\SiteSetupTask;
use App\Support\TemplatePaths;
use App\Templates\TemplateAppRegistry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory;
    use HasUlids;

    /** Roles a member can hold within a site, ordered by privilege. */
    public const ROLES = ['owner', 'admin', 'editor', 'viewer'];

    /** The commerce suite every site starts with (nav items show when enabled). */
    public const COMMERCE_FEATURES = ['store', 'invoices', 'donations', 'bookings', 'estimator'];

    protected $fillable = ['user_id', 'name', 'domain', 'owner', 'description', 'template', 'theme', 'live', 'domain_verified_at', 'currency'];

    protected $casts = ['theme' => 'array', 'live' => 'boolean', 'domain_verified_at' => 'datetime'];

    /** Default theme values — mirror the Nuxt template's main.css :root. */
    public const THEME_DEFAULTS = [
        'font' => 'Inter',
        'accent' => '#6366f1',
        'navy' => '#0c1a3e',
        'surface' => '#f8fafc',
        'text' => '#1a1f36',
        'muted' => '#6b7280',
        'radius' => '12px',
        'base_size' => '16px',
    ];

    /** Theme values with defaults filled in for any missing keys. */
    public function themeValues(): array
    {
        return array_merge(self::THEME_DEFAULTS, is_array($this->theme) ? $this->theme : []);
    }

    /**
     * Which template APP renders this site (preview + publish). Defaults to the
     * built-in generic renderer ("blank"). Set when a template is applied.
     */
    public function renderTemplateKey(): string
    {
        return $this->template ?: TemplateAppRegistry::BLANK;
    }

    /**
     * In-page preview URL for a page of this site: the built renderer app with
     * ?site= (live API content) + ?page= (deep link) + ?v= (per-build cache
     * bust). Null when the renderer hasn't been built — callers show a hint
     * instead of a dead iframe.
     */
    public function previewUrl(?string $pageUrl = null): ?string
    {
        $key = $this->renderTemplateKey();
        // A package template (e.g. user "save as template") renders with the
        // app named in its manifest, not with its own key.
        if ($key !== TemplateAppRegistry::BLANK && ! TemplateAppRegistry::exists($key)) {
            $manifest = TemplatePaths::packageDir($key).'/template.json';
            $renderer = is_file($manifest) ? (json_decode((string) file_get_contents($manifest), true)['renderer'] ?? null) : null;
            $key = ($renderer && TemplateAppRegistry::exists($renderer)) ? $renderer : TemplateAppRegistry::BLANK;
        }

        // Preview through the site's ACTIVE renderer; fall back to the generic
        // block renderer whenever the keyed shell hasn't been built.
        if (! TemplatePaths::hasShell($key)) {
            $key = TemplateAppRegistry::BLANK;
        }
        $index = TemplatePaths::shellDir($key).'/index.html';
        if (! is_file($index)) {
            return null;
        }
        $dir = trim(TemplatePaths::shellBase($key), '/');

        $query = http_build_query(array_filter([
            'site' => $this->name,
            'page' => $pageUrl,
            'v' => (string) filemtime($index),
        ], fn ($v) => $v !== null && $v !== ''));

        return url($dir).'/?'.$query;
    }

    /**
     * Normalize a user-entered domain: strip scheme/path/port/www, lowercase.
     * Returns null when nothing valid remains.
     */
    /** Does the site use a real template (not the blank starter)? */
    public function hasTemplate(): bool
    {
        return $this->renderTemplateKey() !== TemplateAppRegistry::BLANK;
    }

    /**
     * The visitor-view preview for "Live preview" buttons — null when the site
     * has no template yet, so the button says there's nothing to preview
     * instead of opening the empty generic renderer.
     */
    public function visitorPreviewUrl(?string $pageUrl = null): ?string
    {
        return $this->hasTemplate() ? $this->templatePreviewUrl($pageUrl) : null;
    }

    /**
     * Preview through the site's OWN template renderer (hairco, verita…) —
     * what a live domain serves — falling back to the generic block renderer.
     * Used where the visitor must see their real site (signup wizard, "view
     * site" links), not the block editor's preview.
     */
    public function templatePreviewUrl(?string $pageUrl = null): ?string
    {
        $found = $this->liveShell();
        if ($found && $this->renderTemplateKey() !== TemplateAppRegistry::BLANK) {
            [$index, $base] = $found;
            if ($base !== TemplatePaths::shellBase(TemplateAppRegistry::BLANK)) {
                $base = trim($base, '/');
                $page = trim((string) $pageUrl, '/');

                // Deep-link by PATH — the static shells route by URL path and
                // ignore a ?page= param. Works when the page was prerendered,
                // or when the SPA fallback can serve it and the CMS knows the
                // page (template catch-all renders its wireframe client-side).
                $path = '';
                if ($page !== '' && (is_file(public_path("{$base}/{$page}/index.html"))
                    || (is_file(public_path("{$base}/200.html")) && $this->pages()->where('url', '/'.$page)->exists()))) {
                    $path = '/'.$page;
                }

                $query = http_build_query([
                    'site' => $this->name,
                    'v' => (string) filemtime($index),
                ]);

                return url($base).$path.'/?'.$query;
            }
        }

        return $this->previewUrl($pageUrl);
    }

    // ── Instant subdomains ({name}.{publishing.subdomain_base}) ─────────────

    /** The site's automatic host, e.g. janes-salon.oluxstudio.com (null when disabled). */
    public function subdomainHost(): ?string
    {
        $base = (string) config('publishing.subdomain_base');

        return $base !== '' ? "{$this->name}.{$base}" : null;
    }

    /** Best public address: verified live custom domain → subdomain → renderer preview. */
    public function publicUrl(): ?string
    {
        if ($this->live && $this->domain && $this->domain_verified_at) {
            return 'https://'.$this->domain;
        }
        if ($host = $this->subdomainHost()) {
            return 'https://'.$host;
        }

        return $this->templatePreviewUrl();
    }

    /** A valid, unreserved DNS label for a site subdomain (also the site name). */
    public static function validSubdomainLabel(string $label): bool
    {
        $label = strtolower(trim($label));

        // 3–63 chars, letters/digits/hyphens, no leading/trailing hyphen.
        return preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $label) === 1
            && ! in_array($label, (array) config('publishing.reserved_subdomains', []), true);
    }

    /** Resolve an incoming Host header to the site it is the subdomain of. */
    public static function forSubdomainHost(string $host): ?self
    {
        $base = (string) config('publishing.subdomain_base');
        $host = strtolower($host);
        if ($base === '' || ! str_ends_with($host, '.'.$base)) {
            return null;
        }
        $label = substr($host, 0, -strlen('.'.$base));
        if (! self::validSubdomainLabel($label)) {
            return null;
        }

        return self::where('name', $label)->first();
    }

    // ── Changing the web address (old addresses live on as SiteAlias) ───────

    public function aliases(): HasMany
    {
        return $this->hasMany(SiteAlias::class);
    }

    /** Is this address in use — by a site, or as another site's old address? */
    public static function nameTaken(string $label, ?string $exceptSiteId = null): bool
    {
        $label = strtolower(trim($label));

        return self::where('name', $label)->when($exceptSiteId, fn ($q) => $q->whereKeyNot($exceptSiteId))->exists()
            || SiteAlias::where('name', $label)->when($exceptSiteId, fn ($q) => $q->where('site_id', '!=', $exceptSiteId))->exists();
    }

    /** The site now behind an old address (null when the name is current or unknown). */
    public static function forOldName(?string $name): ?self
    {
        $name = strtolower(trim((string) $name));
        if ($name === '' || self::where('name', $name)->exists()) {
            return null;
        }

        return SiteAlias::where('name', $name)->first()?->site;
    }

    /** Why $label can't be this site's new address, or null when it can. */
    public function addressError(string $label): ?string
    {
        $label = strtolower(trim($label));

        return match (true) {
            $label === $this->name => 'That is already this site’s address.',
            ! self::validSubdomainLabel($label) => 'Use 3–63 lowercase letters, numbers or hyphens (not at the start or end).',
            self::nameTaken($label, $this->id) => 'That address is taken. Try another.',
            default => null,
        };
    }

    /**
     * Move the site to a new web address ({label}.{base} and /{label}/… in the
     * app). The old address is kept as an alias, so shared links, the old
     * subdomain, Stripe webhooks and Site Connect embeds keep working (they are
     * redirected or resolved to the new one). Files already uploaded stay where
     * they are; their URLs are stored and keep working.
     */
    public function changeAddress(string $label): void
    {
        $label = strtolower(trim($label));
        if ($error = $this->addressError($label)) {
            throw new \InvalidArgumentException($error);
        }

        DB::transaction(function () use ($label) {
            $old = $this->name;
            $base = (string) config('publishing.subdomain_base') ?: 'test';

            // Taking back one of our own old addresses: it stops being an alias.
            SiteAlias::where('site_id', $this->id)->where('name', $label)->delete();
            SiteAlias::firstOrCreate(['name' => $old], ['site_id' => $this->id]);

            // Sites made from a template store their free subdomain as the domain.
            $domain = $this->domain === "{$old}.{$base}" ? "{$label}.{$base}" : $this->domain;

            $this->forceFill(['name' => $label, 'domain' => $domain])->save();
        });
        SiteContentCache::bump($this->id);
    }

    public static function normalizeDomain(?string $input): ?string
    {
        $d = strtolower(trim((string) $input));
        $d = preg_replace('#^https?://#', '', $d);
        $d = explode('/', $d)[0];
        $d = explode(':', $d)[0];
        $d = preg_replace('/^www\./', '', $d);

        return preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $d) ? $d : null;
    }

    /**
     * The built renderer shell served on this site's live domain: the site's
     * template app build if present, else the generic block renderer.
     * Returns [absolute index.html path, public base dir] or null (no build).
     */
    public function liveShell(): ?array
    {
        $key = $this->renderTemplateKey();
        foreach (array_unique([$key, TemplateAppRegistry::BLANK]) as $candidate) {
            if (TemplatePaths::hasShell($candidate)) {
                return [TemplatePaths::shellDir($candidate).'/index.html', TemplatePaths::shellBase($candidate)];
            }
        }

        return null;
    }

    /** Per-request memo of the enabled-feature map. */
    private ?array $featureMap = null;

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::slug(trim($value)),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Team members of this site, with their pivot role. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Everyone who works on this site: the owning account, account-team
     * members whose membership covers it (site-scoped or account-wide),
     * and legacy site_user pivot members. Each entry: user + role label.
     *
     * @return \Illuminate\Support\Collection<int, array{user: User, role: ?string}>
     */
    public function teamUsers(): \Illuminate\Support\Collection
    {
        $team = collect();

        // joined_at: when the person joined this site's team (the owner "joins"
        // when the site is created).
        if ($this->user) {
            $team->push(['user' => $this->user, 'role' => 'owner', 'joined_at' => $this->created_at]);
        }

        AccountMember::with(['user', 'role'])
            ->where('account_id', $this->user_id)
            ->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $this->id))
            ->get()
            ->each(fn ($m) => $m->user && $team->push(['user' => $m->user, 'role' => $m->role?->name, 'joined_at' => $m->created_at]));

        $this->members()->get()
            ->each(fn ($u) => $team->push(['user' => $u, 'role' => $u->pivot->role, 'joined_at' => $u->pivot->created_at]));

        // One entry per person: the first role found, the earliest join time.
        return $team->groupBy(fn ($t) => $t['user']->id)
            ->map(fn ($rows) => ['joined_at' => $rows->pluck('joined_at')->filter()->min()] + $rows->first())
            ->values();
    }

    /** The role label for a given user on this site, or null if they're not on the team. */
    public function roleFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $this->teamUsers()->first(fn ($t) => $t['user']->id === $user->id)['role'] ?? null;
    }

    /** Whether the user may manage the team (permission-gated). */
    public function canManageTeam(?User $user): bool
    {
        return $this->allows($user, 'team.manage');
    }

    /**
     * RBAC check for this site: super admins and the owning account hold every
     * permission; account members are checked against their role. Legacy
     * per-site memberships (site_user pivot) map onto the default role
     * templates so old data keeps working.
     */
    /**
     * Page-level CRUD check: pages.manage plus the role's page scope
     * (owners, supers and unscoped roles pass; pass null to ask about
     * CREATING a page, which scoped roles may not do).
     */
    public function allowsPageEdit(?User $user, ?string $pageId): bool
    {
        if (! $this->allows($user, 'pages.manage')) {
            return false;
        }
        if ($user->isSuper() || ($this->user_id !== null && $this->user_id === $user->id)) {
            return true;
        }
        $role = $user->membershipFor($this)?->role;
        if (! $role) {
            return true; // legacy site_user membership — no page scoping
        }

        return $pageId === null ? $role->allowsPageCreate() : $role->allowsPage($pageId);
    }

    public function allows(?User $user, ?string $permission): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuper() || ($this->user_id !== null && $this->user_id === $user->id)) {
            return true;
        }
        if ($permission === null) { // page open to any member
            return $this->accessibleBy($user);
        }
        if ($this->user_id !== null && $user->canOnSite($this, $permission)) {
            return true;
        }

        // Legacy site_user fallback: owner/admin → everything, else the
        // matching default role template from config/permissions.php.
        return match ($this->roleFor($user)) {
            'owner', 'admin' => true,
            'editor' => in_array($permission, config('permissions.roles.editor.permissions', []), true),
            'viewer' => in_array($permission, config('permissions.roles.viewer.permissions', []), true),
            default => false,
        };
    }

    /** Whether the user can access this site at all (account member or legacy role). */
    public function accessibleBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isSuper()
            || ($this->user_id !== null && $this->user_id === $user->id)
            || ($this->user_id !== null && $user->membershipFor($this) !== null)
            || $this->roleFor($user) !== null;
    }

    public function siteAttributes(): HasMany
    {
        return $this->hasMany(SiteAttribute::class);
    }

    /** Templates installed to this site from the Marketplace (built-in or uploaded). */
    public function installedTemplates(): HasMany
    {
        return $this->hasMany(SiteTemplate::class)->latest('id');
    }

    /** Read a single attribute value by key, falling back to $default. */
    public function getAttr(string $key, mixed $default = null): mixed
    {
        return $this->siteAttributes()->where('key', $key)->value('value') ?? $default;
    }

    /** Create or update an attribute, returning the saved row. */
    public function setAttr(string $key, ?string $value): SiteAttribute
    {
        return $this->siteAttributes()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /** Remove an attribute by key. Returns the number of rows deleted. */
    public function forgetAttr(string $key): int
    {
        return $this->siteAttributes()->where('key', $key)->delete();
    }

    protected static function booted(): void
    {
        // Every new site starts with its "Set up your site" task.
        static::created(fn (Site $site) => SiteSetupTask::sync($site));
    }

    /**
     * Envelope parts for emails sent to this site's customers: the From NAME is
     * the business (the address stays the platform's, so SPF/DKIM pass) and
     * replies go to the business. Spread into `new Envelope(...)`.
     *
     * @return array{from?: Address, replyTo?: list<Address>}
     */
    public function mailSender(): array
    {
        $clean = fn (?string $v) => trim(mb_substr(preg_replace('/[\r\n\t"<>]+/', ' ', (string) $v), 0, 80));
        $name = $clean(SiteProperties::value($this, 'email_sender_name') ?: $this->getAttr('business_name'));
        $reply = trim(SiteProperties::value($this, 'reply_to') ?: SiteProperties::value($this, 'email'));
        $out = [];
        if ($name !== '' && filled(config('mail.from.address'))) {
            $out['from'] = new Address((string) config('mail.from.address'), $name);
        }
        if (filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $out['replyTo'] = [new Address($reply, $name !== '' ? $name : null)];
        }

        return $out;
    }

    /** Logo for branded output: the email-specific one, else the site's property logo. */
    public function brandLogo(): string
    {
        // Mail clients need an absolute URL; @media refs resolve to the file's location.
        return Media::resolveAbsolute($this->id, (string) ($this->getAttr('email.logo') ?: SiteProperties::value($this, 'logo')));
    }

    /** All attributes as a flat [key => value] array. */
    public function attrMap(): array
    {
        return $this->siteAttributes()->pluck('value', 'key')->all();
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    /** Site-level bookable resources (staff / rooms / vehicles). */
    public function resources(): HasMany
    {
        return $this->hasMany(ServiceResource::class);
    }

    /** User-built reusable components (BlockLayout kind=component). */
    public function components()
    {
        return $this->hasMany(BlockLayout::class)->where('kind', 'component');
    }

    /** Classic CONTENT components (named node bags — the Components page). */
    public function contentComponents(): HasMany
    {
        return $this->hasMany(Component::class)->latest('id');
    }

    /**
     * Pages that are part of the live site — excludes pages parked under
     * /_archived-… by TemplateInstaller when a template replaced them.
     * Use for menus, payloads and anything visitor-facing.
     */
    public function livePages()
    {
        return $this->hasMany(Page::class)
            ->where('url', 'not like', '/\_archived-%')
            ->where('url', 'not like', '/\_layout-%') // layout shadow pages hold fixed-region blocks
            ->where('template_active', true);          // a non-current template's pages are parked, not deleted
    }

    public function collections()
    {
        return $this->hasMany(Collection::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function contactSubmissions()
    {
        return $this->hasMany(ContactSubmission::class);
    }

    public function forms()
    {
        return $this->hasMany(Form::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function taskLogs()
    {
        return $this->hasMany(TaskLog::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function todos()
    {
        return $this->hasMany(Todo::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Features / Marketplace
    // ─────────────────────────────────────────────────────────────

    public function siteFeatures(): HasMany
    {
        return $this->hasMany(SiteFeature::class);
    }

    public function paymentSettings(): HasOne
    {
        return $this->hasOne(SitePaymentSettings::class);
    }

    public function githubSettings(): HasOne
    {
        return $this->hasOne(SiteGithubSettings::class);
    }

    /** Site Connect wiring (mode, domain allow-list, ingest/publish times). */
    public function connection(): HasOne
    {
        return $this->hasOne(SiteConnection::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /** The site's named estimators (Cleaner, Mover, …), each with fields + calcs. */
    public function estimators(): HasMany
    {
        return $this->hasMany(Estimator::class)->orderBy('sort')->orderBy('id');
    }

    /** Cached map of enabled feature key => SiteFeature. */
    public function loadedFeatures(): array
    {
        if ($this->featureMap !== null) {
            return $this->featureMap;
        }

        $rows = Cache::remember("site_features:{$this->id}", now()->addHours(6), function () {
            return $this->siteFeatures()->get(['id', 'key', 'enabled', 'config'])->all();
        });

        return $this->featureMap = collect($rows)
            ->keyBy('key')
            ->all();
    }

    public function hasFeature(string $key): bool
    {
        $f = $this->loadedFeatures()[$key] ?? null;

        return $f !== null && (bool) $f->enabled;
    }

    /** Merged config: registry defaults + stored config JSON. */
    public function feature(string $key): array
    {
        $stored = $this->loadedFeatures()[$key]->config ?? [];

        return array_merge(FeatureRegistry::defaults($key), $stored ?: []);
    }

    public function enableFeature(string $key, array $config = []): SiteFeature
    {
        abort_unless(FeatureRegistry::exists($key), 404);

        $feature = $this->siteFeatures()->updateOrCreate(
            ['key' => $key],
            ['enabled' => true] + (empty($config) ? [] : ['config' => $config]),
        );

        $this->flushFeatureCache();

        return $feature;
    }

    /**
     * Switch on the full commerce suite (store, invoices, donations, bookings,
     * estimator) — used at site creation so the Commerce nav is populated from
     * day one. Never re-enables a feature the site has explicitly disabled.
     */
    public function enableCommerceSuite(): void
    {
        $existing = $this->siteFeatures()->pluck('key')->all();
        foreach (self::COMMERCE_FEATURES as $key) {
            if (! in_array($key, $existing, true)) {
                $this->enableFeature($key);
            }
        }
    }

    public function disableFeature(string $key): void
    {
        $this->siteFeatures()->where('key', $key)->update(['enabled' => false]);
        $this->flushFeatureCache();
    }

    public function saveFeatureConfig(string $key, array $config): SiteFeature
    {
        $feature = $this->siteFeatures()->updateOrCreate(
            ['key' => $key],
            ['config' => $config],
        );

        $this->flushFeatureCache();

        return $feature;
    }

    public function flushFeatureCache(): void
    {
        $this->featureMap = null;
        Cache::forget("site_features:{$this->id}");
    }

    /** Whether this site can take payments (Stripe keys present). */
    /** The payment gateway for this site (OFF gateway until the owner enables payments). */
    public function paymentGateway(): PaymentGateway
    {
        return app(PaymentManager::class)->for($this);
    }

    /** Accepting payments = the "Accept payments" switch is on AND the gateway has its keys. */
    public function paymentsEnabled(): bool
    {
        return $this->paymentGateway()->available($this);
    }

    /** @deprecated use paymentsEnabled() — kept for the many blade/checklist call sites. */
    public function stripeReady(): bool
    {
        return $this->paymentsEnabled();
    }
}
