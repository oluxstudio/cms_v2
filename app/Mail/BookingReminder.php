<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Site;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "See you tomorrow" — sent ~24h before a confirmed booking. Template `booking_reminder`. */
class BookingReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Site $site,
    ) {}

    private function ctx(): array
    {
        return [
            'name' => $this->booking->customer_name,
            'site' => ucwords(str_replace('-', ' ', $this->site->name)),
            'reference' => $this->booking->reference,
            'service' => $this->booking->service?->name ?? 'Appointment',
        ];
    }

    public function envelope(): Envelope
    {
        $tpl = EmailTemplate::forKey($this->site, 'booking_reminder');

        return new Envelope(subject: EmailTemplate::fill($tpl['subject'], $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'booking_reminder');
        $b = $this->booking;
        $line = ($b->service?->name ?? 'Appointment').' — '.($b->starts_at?->format('l, F j, Y \\a\\t g:i A') ?? '?')
            .($b->resource ? ' · with '.$b->resource->name : '');

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => $this->site->brandLogo(),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['booking_summary' => [
                'summary' => $line,
                'reference' => $b->reference,
                'total' => null,
                'paid' => null,
                'balance' => $b->balanceCents() > 0 ? $b->formattedBalance() : null,
                'notes' => null,
            ]],
        ]);
    }
}
