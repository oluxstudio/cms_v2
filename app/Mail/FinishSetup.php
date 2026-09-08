<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "Your site is one step from live" — the abandoned-signup recovery nudge. */
class FinishSetup extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $business,
        public int $step,
    ) {}

    public function envelope(): Envelope
    {
        $what = $this->business ? "{$this->business} is" : 'Your site is';

        return new Envelope(subject: "{$what} one step from ready");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.finish-setup');
    }
}
