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

/** Kind-aware booking notification — admin-editable template `booking_confirmed`. */
class BookingConfirmed extends Mailable implements ShouldQueue
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
            'status_verb' => $this->booking->status === 'confirmed' ? 'is confirmed' : 'was received',
        ];
    }

    /** Structured payload for the booking_summary dynamic section. */
    public function summaryData(): array
    {
        $b = $this->booking;

        return [
            'summary' => $this->summaryLine(),
            'reference' => $b->reference,
            'total' => $b->total_cents > 0 ? $b->formattedTotal() : null,
            'paid' => $b->paid_cents > 0 ? $b->formattedPaid() : null,
            'balance' => ($b->paid_cents > 0 && $b->balanceCents() > 0) ? $b->formattedBalance() : null,
            'notes' => $b->notes ?: null,
        ];
    }

    public function envelope(): Envelope
    {
        $tpl = EmailTemplate::forKey($this->site, 'booking_confirmed');

        return new Envelope(subject: EmailTemplate::fill($tpl['subject'], $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'booking_confirmed');

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => (string) $this->site->getAttr('email.logo', ''),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['booking_summary' => $this->summaryData()],
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
            default => sprintf('%s — %s', $svc, $b->starts_at?->format('l, F j, Y \\a\\t g:i A') ?? '?'),
        };
    }
}
