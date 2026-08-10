<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Owner alert: a NEW booking just came in — review/confirm it. */
class NewBookingNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Site $site,
    ) {}

    public function envelope(): Envelope
    {
        $status = match (true) {
            $this->booking->status !== 'confirmed' => 'awaiting your confirmation',
            $this->booking->paid_cents > 0 => 'paid & confirmed',
            default => 'confirmed',
        };

        return new Envelope(subject: 'New booking on '.ucwords(str_replace('-', ' ', $this->site->name))." — {$this->booking->reference} ({$status})");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.new-booking', with: [
            'summary' => (new BookingConfirmed($this->booking, $this->site))->summaryLine(),
            'adminUrl' => url("{$this->site->name}/bookings"),
        ]);
    }
}
