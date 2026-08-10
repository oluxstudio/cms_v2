<?php

namespace App\Livewire;

use App\Models\Site;
use App\Services\VisitAnalytics;
use Livewire\Component;

class AnalyticsDashboard extends Component
{
    public Site $site;

    /** 7d | 30d | 90d | all */
    public string $range = '30d';

    /** Show 20 vs 10 traffic sources. */
    public bool $allSources = false;

    /** Theme palette cycled through the charts. */
    private const PALETTE = ['#4fffdc', '#4fbfff', '#7b5cf6', '#f5697b', '#f5c842', '#d9f068', '#f97316', '#33245c', '#173a5e', '#e6d6c6'];

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    public function setRange(string $range): void
    {
        $this->range = in_array($range, ['7d', '30d', '90d', 'all'], true) ? $range : '30d';
        // Server-rendered tiles/tables refresh via Livewire; the wire:ignore
        // charts update in place from this event.
        $this->dispatch('analytics-updated', charts: $this->chartData());
    }

    public function toggleSources(): void
    {
        $this->allSources = ! $this->allSources;
    }

    /** Map a [label => count] list to {labels, series, colors} for a chart. */
    private function series(array $counts): array
    {
        $labels = array_keys($counts);

        return [
            'labels' => array_map(fn ($l) => (string) $l, $labels),
            'series' => array_map('intval', array_values($counts)),
            'colors' => array_map(fn ($i) => self::PALETTE[$i % count(self::PALETTE)], array_keys($labels)),
        ];
    }

    /** Just the chart datasets (for the live-update event). */
    private function chartData(): array
    {
        $a = new VisitAnalytics($this->site, $this->range);

        return [
            'country' => $a->byCountry(),
            'device' => $this->series($a->byDevice()),
            'os' => $this->series($a->byOs()),
            'channel' => $this->series($a->bySource()),
        ];
    }

    public function render()
    {
        $a = new VisitAnalytics($this->site, $this->range);
        $totals = $a->totals();
        $charts = $this->chartData();
        $referrers = $a->topReferrers($this->allSources ? 20 : 10);
        $geo = $a->geoBreakdown();

        return view('livewire.analytics-dashboard', [
            'totals' => $totals,
            'charts' => $charts,
            'referrers' => $referrers,
            'browsers' => $a->byBrowser(),
            'geo' => $geo,
            'topPages' => $a->topPages(),
            'hasData' => $totals['visits'] > 0,
        ]);
    }
}
