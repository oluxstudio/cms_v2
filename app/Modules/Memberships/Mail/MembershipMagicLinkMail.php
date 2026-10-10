<?php

namespace App\Modules\Memberships\Mail;

use App\Modules\Memberships\Member;
use App\Modules\Memberships\MembershipService;
use App\Modules\Memberships\MembershipUrls;

/** A one-click sign-in link (expires after MembershipService::MAGIC_TTL_MINUTES). */
class MembershipMagicLinkMail extends MembershipMail
{
    public function __construct(Member $member, public string $loginUrl)
    {
        parent::__construct($member);
    }

    protected function subjectLine(): string
    {
        return 'Your sign-in link for '.MembershipUrls::siteTitle($this->member->site);
    }

    protected function viewName(): string
    {
        return 'emails.memberships.magic-link';
    }

    protected function data(): array
    {
        return ['loginUrl' => $this->loginUrl, 'minutes' => MembershipService::MAGIC_TTL_MINUTES];
    }
}
