<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the customer when the owner cancels their booking. */
class BookingCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Site $site,
    ) {}

    public function envelope(): Envelope
    {
        $name = ucwords(str_replace('-', ' ', $this->site->name));

        return new Envelope(subject: "Your booking with {$name} was cancelled — {$this->booking->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.booking-cancelled', with: [
            'summary' => (new BookingConfirmed($this->booking, $this->site))->summaryLine(),
        ]);
    }
}
