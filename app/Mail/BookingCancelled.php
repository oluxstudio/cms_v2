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

/** Sent to the customer when the owner cancels their booking — template `booking_cancelled`. */
class BookingCancelled extends Mailable implements ShouldQueue
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
            'service' => $this->booking->service?->name ?? 'Service',
        ];
    }

    public function envelope(): Envelope
    {
        $tpl = EmailTemplate::forKey($this->site, 'booking_cancelled');

        return new Envelope(subject: EmailTemplate::fill($tpl['subject'], $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'booking_cancelled');
        $helper = new BookingConfirmed($this->booking, $this->site);
        $data = $helper->summaryData();
        // Cancellation context: surface what was paid, not a balance due.
        $data['balance'] = null;
        $data['notes'] = $this->booking->paid_cents > 0
            ? 'You paid '.$this->booking->formattedPaid().' on this booking — we will be in touch about a refund if applicable.'
            : null;

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => (string) $this->site->getAttr('email.logo', ''),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['booking_summary' => $data],
        ]);
    }
}
