<?php

namespace App\Support;

/**
 * A plan's full specification, in plain words — built from the SAME limits the
 * app enforces (config/plans.php tiers.*.limits via AccountSubscription), so
 * what a client reads is exactly what they get. Used by the plan detail
 * "All specs" view and the Compare plans table.
 *
 * Each row: ['label' => …, 'value' => text, 'ok' => true|false|null]
 *   ok=true  → included (✓), ok=false → not included (✕), null → plain value.
 */
class PlanSpecs
{
    /** Rows that describe features rather than limits (text from plans.compare). */
    private const TEXT_ROWS = ['CRM', 'Messaging', 'AI assistant', 'Support'];

    /** @return array<string, array<int, array{label: string, value: string, ok: ?bool}>> section => rows */
    public static function for(string $key, ?array $tier = null): array
    {
        $tier ??= (array) config("plans.tiers.{$key}");
        $l = (array) ($tier['limits'] ?? []);
        $has = fn (string $k) => array_key_exists($k, $l);
        $num = fn ($v, string $unit = '') => $v === null ? 'Unlimited' : number_format((int) $v).$unit;
        $yes = fn (string $label, bool $on, string $onText = 'Included', string $offText = 'Not included') => ['label' => $label, 'value' => $on ? $onText : $offText, 'ok' => $on];
        $row = fn (string $label, string $value) => ['label' => $label, 'value' => $value, 'ok' => null];

        $freeDomain = $l['free_domain'] ?? null;
        $fee = $l['payment_fee_pct'] ?? null;
        $calendars = $has('staff_calendars') ? $l['staff_calendars'] : null;

        $sections = [
            'Websites' => array_values(array_filter([
                $row('Sites', $has('sites') ? $num($l['sites']) : '1'),
                $yes('Your own domain', (bool) ($l['custom_domain'] ?? true), 'Included', 'Not included — free Olux address only'),
                $yes('Free domain for year 1', (bool) $freeDomain, $freeDomain === 'any' ? 'Any domain' : ($freeDomain ? '.'.ltrim((string) $freeDomain, '.').' domain' : ''), 'Not included'),
                $yes('No “Made with Olux” badge', ! ($l['badge'] ?? false), 'Badge removed', 'Badge shown on your site'),
                $row('Asset storage', self::storage($l['storage_mb'] ?? null)),
                $yes('Premium tools (block builder, custom scripts, premium add-ons)', (bool) ($l['premium'] ?? false)),
            ])),
            'Bookings & payments' => array_values(array_filter([
                $row('Online bookings', $has('bookings_month') ? ($l['bookings_month'] === null ? 'Unlimited' : $num($l['bookings_month']).' a month') : 'Unlimited'),
                $row('Staff booking calendars', $calendars === null ? 'Unlimited' : $num($calendars)
                    .(! empty($l['extra_staff_cents']) ? ', then '.Money::format((int) $l['extra_staff_cents'], 'gbp').' a month per extra staff member' : '')),
                $yes('Booking deposits', (bool) ($l['deposits'] ?? false)),
                $row('Invoices', ! $has('invoices_month') || $l['invoices_month'] === null ? 'Unlimited' : ((int) $l['invoices_month'] === 0 ? 'Not included' : $num($l['invoices_month']).' a month')),
                $yes('Recurring invoices', (bool) ($l['recurring_invoices'] ?? false)),
                $row('Olux fee on online payments', $fee === null ? '—' : ((float) $fee <= 0 ? 'None' : rtrim(rtrim(number_format((float) $fee, 1), '0'), '.').'% per payment')),
            ])),
            'Email & marketing' => array_values(array_filter([
                $row('Business mailboxes', ! $has('mailboxes') ? 'Not included' : ($l['mailboxes'] === null ? 'To suit you' : ((int) $l['mailboxes'] === 0 ? 'Not included' : $num($l['mailboxes'])))),
                ! empty($l['mailbox_storage_gb']) ? $row('Storage per mailbox', $l['mailbox_storage_gb'].' GB') : null,
                $row('Newsletter emails', $has('newsletter_sends_month') ? ($l['newsletter_sends_month'] === null ? 'Unlimited' : $num($l['newsletter_sends_month']).' a month') : '—'),
            ])),
            'AI & templates' => array_values(array_filter([
                $row('AI assistant', ! $has('ai_tokens_month') || $l['ai_tokens_month'] === null ? 'Included (fair use)' : self::tokens((int) $l['ai_tokens_month']).' AI tokens a month'),
                $yes('Sell templates on the marketplace', (bool) ($l['marketplace'] ?? false)),
            ])),
        ];

        // Feature rows that aren't numeric limits (CRM level, messaging, support).
        $extra = [];
        foreach (self::TEXT_ROWS as $label) {
            $text = config("plans.compare.{$label}.{$key}");
            if (filled($text) && $label !== 'AI assistant') {
                $extra[] = $row($label, (string) $text);
            }
        }
        if ($extra) {
            $sections['More'] = $extra;
        }

        return $sections;
    }

    /** Flat label => row map (for the compare table). */
    public static function flat(string $key, ?array $tier = null): array
    {
        $out = [];
        foreach (self::for($key, $tier) as $section => $rows) {
            foreach ($rows as $r) {
                $out[$section][$r['label']] = $r;
            }
        }

        return $out;
    }

    private static function storage($mb): string
    {
        if ($mb === null) {
            return 'Unlimited';
        }

        return $mb >= 1024 ? rtrim(rtrim(number_format($mb / 1024, 1), '0'), '.').' GB' : $mb.' MB';
    }

    private static function tokens(int $n): string
    {
        return $n >= 1000000 ? round($n / 1000000, 1).'M' : ($n >= 1000 ? round($n / 1000).'k' : (string) $n);
    }
}
