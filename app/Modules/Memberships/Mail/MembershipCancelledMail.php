<?php

namespace App\Modules\Memberships\Mail;

use App\Modules\Memberships\MembershipUrls;

/** The membership has ended. */
class MembershipCancelledMail extends MembershipMail
{
    protected function subjectLine(): string
    {
        return 'Your '.MembershipUrls::siteTitle($this->member->site).' membership has ended';
    }

    protected function viewName(): string
    {
        return 'emails.memberships.cancelled';
    }

    protected function data(): array
    {
        return ['manageUrl' => route('memberships.public.manage', $this->member->site->name)];
    }
}
