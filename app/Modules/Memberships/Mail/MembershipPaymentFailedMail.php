<?php

namespace App\Modules\Memberships\Mail;

use App\Modules\Memberships\MembershipUrls;

/** A renewal payment failed — Stripe will retry; the member can update their card. */
class MembershipPaymentFailedMail extends MembershipMail
{
    protected function subjectLine(): string
    {
        return 'Your '.MembershipUrls::siteTitle($this->member->site).' membership payment failed';
    }

    protected function viewName(): string
    {
        return 'emails.memberships.payment-failed';
    }

    protected function data(): array
    {
        return ['manageUrl' => route('memberships.public.manage', $this->member->site->name)];
    }
}
