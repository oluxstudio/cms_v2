<?php

namespace App\Support;

use App\Models\MembershipPlan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Membership plans = config/plans.php overlaid with the plans an admin edited
 * on /admin/plans. apply() runs at boot, so every existing reader of
 * config('plans.tiers') sees the edited plans without code changes.
 */
class PlanCatalog
{
    private const CACHE_KEY = 'membership-plans:v1';

    /** The file defaults, captured before the first overlay. */
    private static ?array $defaults = null;

    public static function apply(): void
    {
        self::$defaults ??= ['tiers' => config('plans.tiers', []), 'trial_days' => config('plans.trial_days', 14)];

        try {
            $rows = Cache::rememberForever(self::CACHE_KEY, fn () => MembershipPlan::pluck('data', 'key')->all());
        } catch (Throwable) {
            return; // no table yet (fresh install, CI build) — file plans stand
        }

        $tiers = self::$defaults['tiers'];
        foreach ($rows as $key => $data) {
            $data = is_string($data) ? (json_decode($data, true) ?: []) : (array) $data;
            if ($key === MembershipPlan::SETTINGS_KEY) {
                if (isset($data['trial_days'])) {
                    config(['plans.trial_days' => (int) $data['trial_days']]);
                }

                continue;
            }
            // A deleted plan (a built-in one keeps its row so it can be restored).
            if (! empty($data['deleted'])) {
                unset($tiers[$key]);

                continue;
            }
            $base = $tiers[$key] ?? [];
            $merged = $data + $base;
            // Limits merge key-by-key: a limit added in code (e.g. mailboxes)
            // still applies to plans an admin edited before it existed.
            $merged['limits'] = ((array) ($data['limits'] ?? [])) + ((array) ($base['limits'] ?? []));
            $tiers[$key] = $merged;
        }
        config(['plans.tiers' => $tiers]);
    }

    /**
     * The rules an account falls back to when nothing is paying (trial over,
     * plan cancelled): the Free plan — as edited by an admin when it's on
     * sale, or the shipped Free plan when it has been removed from sale.
     */
    public static function lapsedTier(): ?array
    {
        self::$defaults ??= ['tiers' => config('plans.tiers', []), 'trial_days' => config('plans.trial_days', 14)];

        return config('plans.tiers.free') ?? self::$defaults['tiers']['free'] ?? null;
    }

    /** Built-in plans a super admin deleted (restorable): key => name. @return array<string,string> */
    public static function deletedBuiltIns(): array
    {
        self::$defaults ??= ['tiers' => config('plans.tiers', []), 'trial_days' => config('plans.trial_days', 14)];

        return MembershipPlan::whereIn('key', array_keys(self::$defaults['tiers']))->get()
            ->filter(fn ($row) => ! empty(((array) $row->data)['deleted']))
            ->mapWithKeys(fn ($row) => [$row->key => (string) (((array) $row->data)['name'] ?? self::$defaults['tiers'][$row->key]['name'] ?? $row->key)])
            ->all();
    }

    /** Is this plan one of the shipped defaults (deleting it keeps a restorable marker)? */
    public static function isBuiltIn(string $key): bool
    {
        self::$defaults ??= ['tiers' => config('plans.tiers', []), 'trial_days' => config('plans.trial_days', 14)];

        return array_key_exists($key, self::$defaults['tiers']);
    }

    public static function save(string $key, array $data): void
    {
        MembershipPlan::updateOrCreate(['key' => $key], ['data' => $data]);
        self::refresh();
    }

    public static function delete(string $key): void
    {
        MembershipPlan::where('key', $key)->delete();
        self::refresh();
    }

    public static function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::apply();
    }

    /** Plans a customer may pick (hidden plans stay for existing subscribers only). */
    public static function publicTiers(?string $keep = null)
    {
        return collect(config('plans.tiers'))
            ->filter(fn ($t, $k) => empty($t['hidden']) || $k === $keep)
            ->sortBy('order');
    }
}
