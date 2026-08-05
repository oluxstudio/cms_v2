<?php

namespace App\Mail;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You've been invited to join {account}'s team" — carries the one-time
 * accept link. Opening that link is the email verification.
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TeamInvitation $invitation,
        public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->invitation->account->name}'s team on ".config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.team-invitation', with: [
            'accountName' => $this->invitation->account->name,
            'inviterName' => $this->invitation->inviter?->name,
            'roleName' => $this->invitation->role->name,
            'acceptUrl' => route('invite.accept', $this->plainToken),
            'expiresAt' => $this->invitation->expires_at,
        ]);
    }
}
