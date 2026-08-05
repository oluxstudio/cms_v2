<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Kind-aware booking notification (slot appointment / stay / trip seats). */
class BookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Site $site,
    ) {}

    public function envelope(): Envelope
    {
        $name = ucwords(str_replace('-', ' ', $this->site->name));
        $verb = $this->booking->status === 'confirmed' ? 'is confirmed' : 'was received';

        return new Envelope(subject: "Your booking with {$name} {$verb} — {$this->booking->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.booking-confirmed', with: [
            'summary' => $this->summaryLine(),
        ]);
    }

    /** One human line describing what was booked, per kind. */
    public function summaryLine(): string
    {
        $b = $this->booking;
        $svc = $b->service?->name ?? 'Service';
        $p = (array) $b->params;

        return match ($b->service?->kind) {
            'stay' => sprintf('%s — %d night(s), %s to %s, %d guest(s), %d unit(s)',
                $svc, $p['nights'] ?? 1, $p['check_in'] ?? '?', $p['check_out'] ?? '?', $p['guests'] ?? 1, $p['units'] ?? 1),
            'trip' => sprintf('%s — %s → %s on %s, %d seat(s)',
                $svc, $p['origin'] ?? '?', $p['destination'] ?? '?',
                $b->starts_at?->format('D, M j · g:i A') ?? '?', $p['qty'] ?? 1),
            default => sprintf('%s — %s', $svc, $b->starts_at?->format('l, F j, Y \a\t g:i A') ?? '?'),
        };
    }
}
