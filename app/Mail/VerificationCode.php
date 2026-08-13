<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The 6-digit code that verifies a new signup's email before the account is created. */
class VerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes = 15,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your Olux verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verification-code', with: [
            'code' => $this->code,
            'ttl' => $this->ttlMinutes,
        ]);
    }
}
