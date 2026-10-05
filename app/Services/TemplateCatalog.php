<?php

namespace App\Services;

use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Support\TemplateAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Read model for the marketplace catalog — searchable, filterable, paginated.
 * Replaces TemplateRegistry::all() for browsing (the registry stays as a seed source).
 */
class TemplateCatalog
{
    /**
     * @param  array{search?:string,category?:string,price?:string}  $filters
     */
    public function browse(array $filters = [], string $sort = 'popular', ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = $perPage ?: (int) config('templates.per_page', 12);

        $q = Template::query()->publiclyListed();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }
        if ($category = (string) ($filters['category'] ?? '')) {
            $q->where('category', $category);
        }
        if (! empty($filters['categories']) && is_array($filters['categories'])) {
            $q->whereIn('category', $filters['categories']);
        }
        if (! empty($filters['creators']) && is_array($filters['creators'])) {
            $q->whereIn('creator_id', $filters['creators']);
        }
        foreach ((array) ($filters['tags'] ?? []) as $tag) {
            $q->whereJsonContains('tags', $tag);
        }
        if (($filters['price'] ?? '') === 'free') {
            $q->where('price_cents', 0);
        } elseif (($filters['price'] ?? '') === 'paid') {
            $q->where('price_cents', '>', 0);
        }

        match ($sort) {
            'new', 'newest' => $q->orderByDesc('published_at')->orderByDesc('id'),
            'price_asc' => $q->orderBy('price_cents')->orderByDesc('id'),
            'price-low' => $q->orderBy('price_cents')->orderByDesc('id'),
            'price-high' => $q->orderByDesc('price_cents')->orderByDesc('id'),
            'rating' => $q->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            default => $q->orderByDesc('installs_count')->orderByDesc('id'),
        };

        return $q->with('creator')->paginate($perPage);
    }

    /** Distinct published categories (cached briefly). */
    public function categories(): array
    {
        return Cache::remember('template_catalog_categories', 300, fn () => Template::query()
            ->publiclyListed()->whereNotNull('category')
            ->distinct()->orderBy('category')->pluck('category')->all());
    }

    /** Resolve a published, PUBLIC template by id, uuid or slug (private ones: findFor()). */
    public function find(int|string $id): ?Template
    {
        return Template::query()->publiclyListed()
            ->where(function ($q) use ($id) {
                $q->orWhere('uuid', $id)->orWhere('slug', $id);
                if (is_numeric($id)) {
                    $q->orWhere('id', (int) $id);
                }
            })->first();
    }

    /**
     * A template this user may see (acting for $site's account): public
     * published ones, plus private ones assigned to that account.
     */
    public function findFor(?User $user, int|string $id, ?Site $site = null): ?Template
    {
        $t = Template::query()->whereIn('status', ['published', 'private'])
            ->where(fn ($q) => $q->orWhere('uuid', $id)->orWhere('slug', $id)->orWhere('id', $id))->first();

        return $t && TemplateAccess::canSee($user, $t, $site) ? $t : null;
    }
}
