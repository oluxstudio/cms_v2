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

/** "Time for your next visit?" — sent N weeks after a customer's last booking. */
class RebookPrompt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Site $site,
        public int $weeks,
        public ?string $bookingUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $name = ucwords(str_replace('-', ' ', $this->site->name));

        return new Envelope(subject: "Time for your next visit? — {$name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.rebook-prompt');
    }
}
