<?php

namespace App\Mail;

use App\Models\Site;
use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You've been added to {site}" — the account already exists; the email
 * carries the login address (plus a temporary password for brand-new users)
 * and a button to the login page. No registration step.
 */
class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TeamInvitation $invitation,
        public Site $site,
        public ?string $tempPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been added to {$this->site->getAttr('business_name', $this->site->name)} on ".config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.team-invitation', with: [
            'siteName' => $this->site->getAttr('business_name') ?: $this->site->name,
            'inviterName' => $this->invitation->inviter?->name,
            'roleName' => $this->invitation->role->name,
            'email' => $this->invitation->email,
            'tempPassword' => $this->tempPassword,
            'loginUrl' => route('login'),
        ]);
    }
}
