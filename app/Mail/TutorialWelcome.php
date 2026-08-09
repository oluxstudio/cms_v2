<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once a paid plan is activated: welcomes the user and links to the
 * getting-started tutorial page.
 */
class TutorialWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $planName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to Olux Studio — here\'s how to get started');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tutorial-welcome',
            with: [
                'name' => $this->user->name,
                'planName' => $this->planName,
                'tutorialUrl' => route('tutorial'),
                'dashboardUrl' => route('home'),
            ],
        );
    }
}
