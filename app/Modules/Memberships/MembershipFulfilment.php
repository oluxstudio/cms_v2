<?php

namespace App\Modules\Memberships;

use App\Models\Site;
use App\Payments\WebhookEvent;

/**
 * config/payments.php 'fulfilment' handler for checkout metadata
 * `membership_id`: a completed subscription checkout activates the member;
 * an expired one is noted in their history. Renewals / failures arrive as
 * invoice events through MembershipBilling.
 */
class MembershipFulfilment
{
    public function __construct(private MembershipService $memberships) {}

    public function handle(Site $site, WebhookEvent $event, bool $completed): void
    {
        $member = Member::where('site_id', $site->id)->find($event->metadata['membership_id'] ?? null);
        if (! $member) {
            return;
        }

        if ($completed) {
            // Async methods can complete checkout unpaid — invoice.paid activates those later.
            if ($event->isPaid && in_array($member->status, ['pending', 'cancelled'], true)) {
                $this->memberships->activate($member, null);
            }

            return;
        }

        if ($member->status === 'pending') {
            $member->log('checkout_expired');
        }
    }
}
