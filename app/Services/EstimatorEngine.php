<?php

namespace App\Services;

use App\Models\Site;
use App\Support\Money;

/**
 * Estimator module engine — instant cost + completion-time estimates for
 * trade services (cleaner, landscaper, laundry, carpenter, mover, builder,
 * plumber, electrician). The catalog lives in config/estimator.php; a site
 * scales all prices with its feature `rate_multiplier` (%) and sets currency.
 */
class EstimatorEngine
{
    /** The trades + inputs a site offers (all catalog trades unless disabled). */
    public function config(Site $site): array
    {
        $f = (array) $site->feature('estimator');
        $enabled = array_filter(array_map('trim', explode(',', (string) ($f['trades'] ?? ''))));

        $trades = collect(config('estimator.trades'))
            ->when($enabled !== [], fn ($c) => $c->only($enabled))
            ->map(fn ($t, $key) => [
                'key' => $key,
                'name' => $t['name'],
                'icon' => $t['icon'],
                'inputs' => array_map(fn ($in) => collect($in)->except(['per_unit_cents', 'per_unit_hours'])->all(), $t['inputs']),
            ])->values()->all();

        return [
            'trades' => $trades,
            'currency' => strtolower((string) ($f['currency'] ?? 'gbp')),
        ];
    }

    /**
     * Compute an estimate. $inputs is key => value from the widget.
     *
     * @return array{trade:string,cost_low_cents:int,cost_high_cents:int,cost_label:string,
     *               hours:float,completion:string,breakdown:array<int,array{label:string,amount_cents:int}>}|null
     *         null when the trade is unknown.
     */
    public function estimate(Site $site, string $tradeKey, array $inputs): ?array
    {
        $trade = config("estimator.trades.{$tradeKey}");
        if (! $trade) {
            return null;
        }
        $f = (array) $site->feature('estimator');
        $scale = max(1, (int) ($f['rate_multiplier'] ?? 100)) / 100;
        $currency = strtolower((string) ($f['currency'] ?? 'gbp'));

        $cents = (float) $trade['base_cents'];
        $hours = (float) $trade['base_hours'];
        $mult = 1.0;
        $breakdown = [['label' => $trade['name'].' call-out', 'amount_cents' => (int) round($trade['base_cents'] * $scale)]];

        foreach ($trade['inputs'] as $in) {
            $key = $in['key'];
            $val = $inputs[$key] ?? null;

            if ($in['type'] === 'number') {
                $qty = max((int) ($in['min'] ?? 0), min((int) ($in['max'] ?? PHP_INT_MAX), (int) ($val ?? $in['default'] ?? 0)));
                if ($qty > 0 && ! empty($in['per_unit_cents'])) {
                    $line = $in['per_unit_cents'] * $qty;
                    $cents += $line;
                    $breakdown[] = ['label' => $in['label'].' × '.$qty.(isset($in['unit']) ? ' '.$in['unit'] : ''), 'amount_cents' => (int) round($line * $scale)];
                }
                $hours += ($in['per_unit_hours'] ?? 0) * $qty;
            } elseif ($in['type'] === 'select') {
                $opts = collect($in['options']);
                $opt = $opts->firstWhere('key', $val) ?? $opts->first();
                $mult *= (float) ($opt['multiplier'] ?? 1);
                $breakdown[] = ['label' => $in['label'].': '.$opt['label'], 'amount_cents' => 0];
            } elseif ($in['type'] === 'toggle' && $val) {
                if (! empty($in['multiplier'])) {
                    $mult *= (float) $in['multiplier'];
                }
                if (! empty($in['add_cents'])) {
                    $cents += $in['add_cents'];
                }
                $breakdown[] = ['label' => $in['label'], 'amount_cents' => (int) round(($in['add_cents'] ?? 0) * $scale)];
            }
        }

        $cents = $cents * $mult * $scale;
        $hours = round($hours * ($mult > 1 ? 1 + ($mult - 1) / 2 : $mult), 1); // effort grows slower than price
        $pct = (int) config('estimator.range_pct', 12);
        $low = (int) (round($cents * (1 - $pct / 100) / 100) * 100);
        $high = (int) (round($cents * (1 + $pct / 100) / 100) * 100);

        return [
            'trade' => $tradeKey,
            'trade_name' => $trade['name'],
            'cost_low_cents' => $low,
            'cost_high_cents' => $high,
            'cost_label' => Money::format($low, $currency).' – '.Money::format($high, $currency),
            'currency' => $currency,
            'hours' => $hours,
            'completion' => $this->completionLabel($hours, (int) $trade['crew']),
            'breakdown' => $breakdown,
        ];
    }

    /** "About 3 hours (same day)" / "About a full day" / "2–3 days (crew of 3)". */
    private function completionLabel(float $hours, int $crew): string
    {
        $workday = (float) config('estimator.workday_hours', 8);
        $effective = $hours / max(1, $crew);

        if ($effective <= 0.75 * $workday) {
            $h = max(1, (int) ceil($effective));

            return "About {$h} hour".($h === 1 ? '' : 's').' — same day';
        }
        if ($effective <= $workday) {
            return 'About a full day';
        }
        $days = (int) ceil($effective / $workday);
        $span = $days > 1 ? ($days - 1).'–'.$days : (string) $days;

        return "{$span} days".($crew > 1 ? " (crew of {$crew})" : '');
    }
}
