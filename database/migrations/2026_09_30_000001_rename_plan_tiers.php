<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * New tier line-up: Free · Starter · Growth · Pro · Enterprise. Existing
 * customers keep their price and features: the old Pro (£45) is now Growth
 * and the old Business (£79) is now Pro. Order matters — pro→growth first.
 */
return new class extends Migration
{
    private const MAP = [['pro', 'growth'], ['business', 'pro']];

    public function up(): void
    {
        foreach (self::MAP as [$from, $to]) {
            $this->rename($from, $to);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::MAP) as [$from, $to]) {
            $this->rename($to, $from);
        }
    }

    private function rename(string $from, string $to): void
    {
        DB::table('account_subscriptions')->where('plan', $from)->update(['plan' => $to]);
        DB::table('domain_orders')->where('plan', $from)->update(['plan' => $to]);

        // Per-account price overrides are keyed by plan.
        DB::table('account_subscriptions')->whereNotNull('price_overrides')->orderBy('id')->each(function ($row) use ($from, $to) {
            $o = json_decode((string) $row->price_overrides, true) ?: [];
            if (array_key_exists($from, $o)) {
                $o[$to] = $o[$from];
                unset($o[$from]);
                DB::table('account_subscriptions')->where('id', $row->id)->update(['price_overrides' => json_encode($o)]);
            }
        });

        // Admin edits of the plan move with it; its old display name is dropped
        // so the tier shows its new name.
        if ($row = DB::table('membership_plans')->where('key', $from)->first()) {
            $data = json_decode((string) $row->data, true) ?: [];
            unset($data['name']);
            DB::table('membership_plans')->where('key', $to)->delete();
            DB::table('membership_plans')->where('id', $row->id)->update(['key' => $to, 'data' => json_encode($data)]);
        }
        Cache::forget('membership-plans:v1');
    }
};
