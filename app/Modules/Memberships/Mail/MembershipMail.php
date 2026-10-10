<?php

namespace App\Modules\Memberships\Mail;

use App\Modules\Memberships\Member;
use App\Modules\Memberships\MembershipUrls;
use App\Support\SiteProperties;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Shared shape of every membership email: From name = the site's name
 * (sender name from Site Properties when set), Reply-To = the site's email
 * from Site Properties, so members' replies reach the owner.
 */
abstract class MembershipMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Member $member) {}

    abstract protected function subjectLine(): string;

    abstract protected function viewName(): string;

    protected function data(): array
    {
        return [];
    }

    public function envelope(): Envelope
    {
        $site = $this->member->site;
        $clean = fn (?string $v) => trim(mb_substr(preg_replace('/[\r\n\t"<>]+/', ' ', (string) $v), 0, 80));
        $name = $clean(SiteProperties::value($site, 'email_sender_name')) ?: $clean(MembershipUrls::siteTitle($site));
        $reply = trim(SiteProperties::value($site, 'reply_to') ?: SiteProperties::value($site, 'email'));

        $args = ['subject' => $this->subjectLine()];
        if (filled(config('mail.from.address'))) {
            $args['from'] = new Address((string) config('mail.from.address'), $name);
        }
        if (filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $args['replyTo'] = [new Address($reply, $name)];
        }

        return new Envelope(...$args);
    }

    public function content(): Content
    {
        return new Content(markdown: $this->viewName(), with: [
            'member' => $this->member,
            'site' => $this->member->site,
            'siteTitle' => MembershipUrls::siteTitle($this->member->site),
        ] + $this->data());
    }
}
