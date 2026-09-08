<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "How did we do?" — sent the day after an appointment, with the review link. */
class ReviewRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Site $site,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        $name = ucwords(str_replace('-', ' ', $this->site->name));

        return new Envelope(subject: "How did we do? — {$name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.review-request');
    }
}
