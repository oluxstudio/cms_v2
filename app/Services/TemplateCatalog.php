<?php

namespace App\Services;

use App\Models\Template;
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

        $q = Template::query()->where('status', 'published');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }
        if ($category = (string) ($filters['category'] ?? '')) {
            $q->where('category', $category);
        }
        if (($filters['price'] ?? '') === 'free') {
            $q->where('price_cents', 0);
        } elseif (($filters['price'] ?? '') === 'paid') {
            $q->where('price_cents', '>', 0);
        }

        match ($sort) {
            'new'        => $q->orderByDesc('published_at')->orderByDesc('id'),
            'price-low'  => $q->orderBy('price_cents')->orderByDesc('id'),
            'price-high' => $q->orderByDesc('price_cents')->orderByDesc('id'),
            'rating'     => $q->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            default      => $q->orderByDesc('installs_count')->orderByDesc('id'),
        };

        return $q->paginate($perPage);
    }

    /** Distinct published categories (cached briefly). */
    public function categories(): array
    {
        return Cache::remember('template_catalog_categories', 300, fn () => Template::query()
            ->where('status', 'published')->whereNotNull('category')
            ->distinct()->orderBy('category')->pluck('category')->all());
    }

    /** Resolve a published template by id, uuid or slug. */
    public function find(int|string $id): ?Template
    {
        return Template::query()->where('status', 'published')
            ->where(function ($q) use ($id) {
                $q->orWhere('uuid', $id)->orWhere('slug', $id);
                if (is_numeric($id)) {
                    $q->orWhere('id', (int) $id);
                }
            })->first();
    }
}
