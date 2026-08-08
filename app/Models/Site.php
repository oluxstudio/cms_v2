<?php

namespace App\Models;

use App\Features\FeatureRegistry;
use App\Templates\TemplateAppRegistry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory;
    use HasUlids;

    /** Roles a member can hold within a site, ordered by privilege. */
    public const ROLES = ['owner', 'admin', 'editor', 'viewer'];

    protected $fillable = ['user_id', 'name', 'domain', 'owner', 'description', 'template', 'theme', 'live', 'domain_verified_at'];

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
            $manifest = resource_path("templates/{$key}/template.json");
            $renderer = is_file($manifest) ? (json_decode((string) file_get_contents($manifest), true)['renderer'] ?? null) : null;
            $key = ($renderer && TemplateAppRegistry::exists($renderer)) ? $renderer : TemplateAppRegistry::BLANK;
        }

        // Wireframes are gone — every build previews through the generic block renderer.
        $key = TemplateAppRegistry::BLANK;

        $dir = $key === TemplateAppRegistry::BLANK ? 'nuxt-preview' : "nuxt-preview/{$key}";
        $index = public_path("{$dir}/index.html");
        if (! is_file($index)) {
            return null;
        }

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
            $dir = $candidate === TemplateAppRegistry::BLANK ? 'nuxt-preview' : "nuxt-preview/{$candidate}";
            if (is_file(public_path("{$dir}/index.html"))) {
                return [public_path("{$dir}/index.html"), "/{$dir}/"];
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

    /** The pivot role for a given user, or null if they're not a member. */
    public function roleFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $member = $this->members()->where('users.id', $user->id)->first();

        return $member?->pivot->role;
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
        if ($this->user_id !== null && $user->canInAccount($this->user_id, $permission)) {
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
            || ($this->user_id !== null && $user->membershipIn($this->user_id) !== null)
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
            ->where('url', 'not like', '/\_layout-%'); // layout shadow pages hold fixed-region blocks
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
    public function stripeReady(): bool
    {
        return (bool) $this->paymentSettings?->isConfigured();
    }
}
