<?php

use App\Models\AccountSubscription;
use App\Models\MembershipPlan;
use App\Support\PlanCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * The October 2026 plan table (Starter £19 · Growth £45 · Pro £79 ·
 * Enterprise from £149; Growth 3 sites; storage 1/5/20/50 GB; mailbox
 * storage 10/25 GB; Pro up to 10 staff). config/plans.php holds the new
 * defaults; admin-edited plans live in membership_plans and override them,
 * so the new prices, copy and limits are written onto those rows too.
 * Names, order, colours and hidden flags an admin set are kept; custom plans
 * are left alone. Mailbox grandfathering is re-checked against the new caps.
 */
return new class extends Migration
{
    private const FROM_LINEUP = ['price_cents', 'annual_price_cents', 'price_prefix', 'tagline', 'features', 'description', 'highlight', 'domain_included'];

    public function up(): void
    {
        $lineup = (require config_path('plans.php'))['tiers'];

        foreach (MembershipPlan::whereIn('key', array_keys($lineup))->get() as $row) {
            $data = is_string($row->data) ? (json_decode($row->data, true) ?: []) : (array) $row->data;
            $new = $lineup[$row->key];
            foreach (self::FROM_LINEUP as $k) {
                if (array_key_exists($k, $new)) {
                    $data[$k] = $new[$k];
                } else {
                    unset($data[$k]);
                }
            }
            $data['limits'] = $new['limits'] + (array) ($data['limits'] ?? []);
            $row->update(['data' => $data]);
        }
        // One recommended plan: Growth. Custom plans lose the flag too.
        foreach (MembershipPlan::whereNotIn('key', ['growth', MembershipPlan::SETTINGS_KEY])->get() as $row) {
            $data = is_string($row->data) ? (json_decode($row->data, true) ?: []) : (array) $row->data;
            if (! empty($data['highlight'])) {
                $data['highlight'] = false;
                $row->update(['data' => $data]);
            }
        }
        PlanCatalog::refresh();

        // Grandfather mailboxes over the new allowance.
        AccountSubscription::query()->each(function (AccountSubscription $sub) {
            $used = $sub->mailboxesUsed();
            if ($used > 0 && $used > $sub->mailboxLimitOn($sub->plan)) {
                $sub->update(['grandfathered_mailboxes' => $used]);
            }
        });
    }

    public function down(): void
    {
        // Prices and limits are business data; restore them from /admin/plans if needed.
    }
};
