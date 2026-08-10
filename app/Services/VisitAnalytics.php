<?php

namespace App\Services;

use App\Models\Site;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-side aggregation over the visits table for the analytics dashboard.
 * Everything is scoped to one site + a date range and excludes bots.
 */
class VisitAnalytics
{
    private \DateTimeInterface $since;

    public function __construct(private Site $site, string $range = '30d')
    {
        $this->since = match ($range) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            'all' => now()->subYears(50),
            default => now()->subDays(30),
        };
    }

    private function base(): Builder
    {
        return Visit::query()->humans()
            ->where('site_id', $this->site->id)
            ->where('created_at', '>=', $this->since);
    }

    /** @return array{visits:int,unique_visitors:int,unique_sources:int} */
    public function totals(): array
    {
        return [
            'visits' => (clone $this->base())->count(),
            'unique_visitors' => (clone $this->base())->distinct('visitor_hash')->count('visitor_hash'),
            'unique_sources' => (clone $this->base())->whereNotNull('referrer_host')->distinct('referrer_host')->count('referrer_host'),
        ];
    }

    /** country_code => count (for the world map). */
    public function byCountry(): array
    {
        return (clone $this->base())->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) c')->groupBy('country_code')
            ->pluck('c', 'country_code')->all();
    }

    /** Named breakdown for a column, e.g. device_type / os / browser / source. */
    public function breakdown(string $column, int $limit = 12): array
    {
        return (clone $this->base())->whereNotNull($column)
            ->selectRaw("$column as label, COUNT(*) c")->groupBy($column)
            ->orderByDesc('c')->limit($limit)
            ->pluck('c', 'label')->all();
    }

    public function byDevice(): array
    {
        return $this->breakdown('device_type');
    }

    public function byOs(): array
    {
        return $this->breakdown('os');
    }

    public function byBrowser(): array
    {
        return $this->breakdown('browser');
    }

    public function bySource(): array
    {
        return $this->breakdown('source', 8);
    }

    /** Top referrer hosts (traffic sources) with counts. */
    public function topReferrers(int $limit = 20): array
    {
        return (clone $this->base())->whereNotNull('referrer_host')
            ->selectRaw('referrer_host, COUNT(*) c')->groupBy('referrer_host')
            ->orderByDesc('c')->limit($limit)
            ->pluck('c', 'referrer_host')->all();
    }

    /** Top landing pages. */
    public function topPages(int $limit = 10): array
    {
        return (clone $this->base())->whereNotNull('path')
            ->selectRaw('path, COUNT(*) c')->groupBy('path')
            ->orderByDesc('c')->limit($limit)
            ->pluck('c', 'path')->all();
    }

    /**
     * Geographic "demographics" — top countries / regions / cities.
     *
     * @return array{countries:array,cities:array}
     */
    public function geoBreakdown(int $limit = 10): array
    {
        $cities = (clone $this->base())->whereNotNull('city')
            ->selectRaw("CONCAT(city, ', ', COALESCE(country, '')) as label, COUNT(*) c")
            ->groupBy('city', 'country')->orderByDesc('c')->limit($limit)
            ->pluck('c', 'label')->all();

        $countries = (clone $this->base())->whereNotNull('country')
            ->selectRaw('country as label, COUNT(*) c')->groupBy('country')
            ->orderByDesc('c')->limit($limit)
            ->pluck('c', 'label')->all();

        return ['countries' => $countries, 'cities' => $cities];
    }

    /** Visits per day (YYYY-MM-DD => count) for a trend line. */
    public function timeseries(): array
    {
        return (clone $this->base())
            ->selectRaw('DATE(created_at) d, COUNT(*) c')->groupBy('d')->orderBy('d')
            ->pluck('c', 'd')->all();
    }
}
