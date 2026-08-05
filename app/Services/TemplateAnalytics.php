<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplatePurchase;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Creator earnings + per-template analytics, derived from purchases (paid) and the
 * denormalised install/rating counters.
 */
class TemplateAnalytics
{
    /** Totals across a creator's templates. */
    public function creatorSummary(User $user): array
    {
        $ids  = Template::where('user_id', $user->id)->pluck('id');
        $paid = TemplatePurchase::whereIn('template_id', $ids)->where('status', 'paid');

        return [
            'templates'   => $ids->count(),
            'installs'    => (int) Template::whereIn('id', $ids)->sum('installs_count'),
            'sales'       => (clone $paid)->count(),
            'gross_cents' => (int) (clone $paid)->sum('price_cents'),
            'fees_cents'  => (int) (clone $paid)->sum('platform_fee_cents'),
            'net_cents'   => (int) (clone $paid)->sum('creator_amount_cents'),
        ];
    }

    /** Per-template breakdown for the creator dashboard. */
    public function perTemplate(User $user): Collection
    {
        return Template::where('user_id', $user->id)->latest('id')->get()->map(function (Template $t) {
            $paid = TemplatePurchase::where('template_id', $t->id)->where('status', 'paid');

            return [
                'template'      => $t,
                'installs'      => (int) $t->installs_count,
                'sales'         => (clone $paid)->count(),
                'revenue_cents' => (int) (clone $paid)->sum('creator_amount_cents'),
                'rating_avg'    => (float) $t->rating_avg,
                'rating_count'  => (int) $t->rating_count,
            ];
        });
    }

    public static function money(int $cents): string
    {
        return \App\Support\Money::format($cents, 'gbp');
    }
}
