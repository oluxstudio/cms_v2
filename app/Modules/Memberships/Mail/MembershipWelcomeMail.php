<?php

namespace App\Modules\Memberships\Mail;

use App\Modules\Memberships\Member;
use App\Modules\Memberships\MembershipUrls;

/** Sent once, when a membership becomes active — the `welcome_message` setting + a sign-in link. */
class MembershipWelcomeMail extends MembershipMail
{
    public function __construct(Member $member, public string $loginUrl)
    {
        parent::__construct($member);
    }

    protected function subjectLine(): string
    {
        return 'Welcome to '.MembershipUrls::siteTitle($this->member->site);
    }

    protected function viewName(): string
    {
        return 'emails.memberships.welcome';
    }

    protected function data(): array
    {
        return [
            'loginUrl' => $this->loginUrl,
            'welcome' => (string) ($this->member->site->feature('memberships')['welcome_message'] ?? ''),
        ];
    }
}
