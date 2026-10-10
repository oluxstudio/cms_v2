<?php

namespace App\Modules\Memberships;

use App\Models\Site;
use App\Modules\Memberships\Mail\MembershipPaymentFailedMail;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Recurring-billing events from the site's connected Stripe account
 * (forwarded raw by SiteConnectWebhookController): first payment, renewals,
 * failed payments, cancellations. Idempotent — Stripe retries webhooks.
 */
class MembershipBilling
{
    public function __construct(private MembershipService $memberships) {}

    public function applyStripeEvent(Site $site, string $type, array $o): void
    {
        match ($type) {
            'checkout.session.completed' => $this->checkoutCompleted($site, $o),
            'invoice.paid' => $this->invoicePaid($site, $o),
            'invoice.payment_failed' => $this->invoiceFailed($site, $o),
            'customer.subscription.updated' => $this->subscriptionUpdated($site, $o),
            'customer.subscription.deleted' => $this->subscriptionDeleted($site, $o),
            default => null,
        };
    }

    private function checkoutCompleted(Site $site, array $o): void
    {
        if (($o['mode'] ?? null) !== 'subscription' || empty($o['metadata']['membership_id'])) {
            return;
        }
        $member = Member::where('site_id', $site->id)->find($o['metadata']['membership_id']);
        if (! $member) {
            return;
        }
        $ids = ['subscription' => self::id($o['subscription'] ?? null), 'customer' => self::id($o['customer'] ?? null)];
        if (in_array($o['payment_status'] ?? null, ['paid', 'no_payment_required'], true)) {
            $this->memberships->activate($member, null, $ids);
        } else {
            $member->update(array_filter(['stripe_subscription_id' => $ids['subscription'], 'stripe_customer_id' => $ids['customer']]));
        }
    }

    private function invoicePaid(Site $site, array $o): void
    {
        $member = $this->memberFor($site, $o);
        if (! $member) {
            return;
        }
        $renews = self::periodEnd($o) ?? MembershipUrls::nextRenewal($member->interval);
        $ids = ['subscription' => self::subscriptionId($o), 'customer' => self::id($o['customer'] ?? null)];

        if ($member->status === 'active') {
            $member->update(array_filter(['stripe_subscription_id' => $ids['subscription'], 'stripe_customer_id' => $ids['customer']]) + ['renews_at' => $renews]);
        } elseif ($member->status === 'cancelled') {
            return; // a late invoice for an ended subscription — nothing to extend
        } elseif ($member->status === 'past_due') {
            // A retried payment went through — recovered, no second welcome.
            $member->update(['status' => 'active', 'renews_at' => $renews]);
        } else {
            $this->memberships->activate($member, $renews, $ids); // first payment
        }
        $member->log('renewed', Money::format((int) ($o['amount_paid'] ?? $member->price_cents), $o['currency'] ?? $member->currency)
            .' · next '.$renews->format('j M Y'));
    }

    private function invoiceFailed(Site $site, array $o): void
    {
        $member = $this->memberFor($site, $o);
        if (! $member || $member->status === 'cancelled') {
            return;
        }
        $first = $member->status !== 'past_due';
        $member->update(['status' => 'past_due']);
        $member->log('payment_failed', isset($o['amount_due']) ? Money::format((int) $o['amount_due'], $o['currency'] ?? $member->currency) : null);
        if ($first) {
            $this->memberships->mail($member, new MembershipPaymentFailedMail($member->fresh(['tier', 'site'])));
        }
    }

    private function subscriptionUpdated(Site $site, array $o): void
    {
        $member = $this->memberFor($site, $o);
        if (! $member || $member->status === 'cancelled') {
            return;
        }
        $updates = ['cancel_at_period_end' => (bool) ($o['cancel_at_period_end'] ?? false)];
        if ($end = self::subscriptionPeriodEnd($o)) {
            $updates['renews_at'] = $end;
        }
        $status = $o['status'] ?? null;
        if (in_array($status, ['past_due', 'unpaid'], true) && $member->status === 'active') {
            $updates['status'] = 'past_due';
        } elseif (in_array($status, ['active', 'trialing'], true) && $member->status === 'past_due') {
            $updates['status'] = 'active';
        }
        $member->update($updates);
    }

    private function subscriptionDeleted(Site $site, array $o): void
    {
        $member = $this->memberFor($site, $o);
        if ($member) {
            $this->memberships->markCancelled($member, 'Subscription ended');
        }
    }

    // ── payload helpers (handle both pre- and post-2025 Stripe shapes) ──

    private function memberFor(Site $site, array $o): ?Member
    {
        $subId = ($o['object'] ?? null) === 'subscription' ? ($o['id'] ?? null) : self::subscriptionId($o);
        $meta = (array) ($o['metadata'] ?? []);
        if (($o['object'] ?? null) === 'invoice' || ! isset($meta['membership_id'])) {
            $meta = (array) ($o['subscription_details']['metadata'] ?? $o['parent']['subscription_details']['metadata'] ?? $meta);
        }

        $q = Member::where('site_id', $site->id);
        if ($subId && ($m = (clone $q)->where('stripe_subscription_id', $subId)->first())) {
            return $m;
        }

        return ! empty($meta['membership_id']) ? $q->find($meta['membership_id']) : null;
    }

    private static function subscriptionId(array $o): ?string
    {
        return self::id($o['subscription'] ?? $o['parent']['subscription_details']['subscription'] ?? null);
    }

    private static function id(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : (is_array($v) ? ($v['id'] ?? null) : null);
    }

    private static function periodEnd(array $o): ?Carbon
    {
        $ends = collect($o['lines']['data'] ?? [])->map(fn ($l) => (int) ($l['period']['end'] ?? 0))->filter();

        return $ends->isNotEmpty() ? Carbon::createFromTimestamp($ends->max()) : (isset($o['period_end']) ? Carbon::createFromTimestamp((int) $o['period_end']) : null);
    }

    private static function subscriptionPeriodEnd(array $o): ?Carbon
    {
        $ts = $o['current_period_end'] ?? ($o['items']['data'][0]['current_period_end'] ?? null);

        return $ts ? Carbon::createFromTimestamp((int) $ts) : null;
    }
}
