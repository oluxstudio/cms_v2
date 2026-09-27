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

/** "Time for your next visit?" — sent N weeks after the last booking. Template `rebook_prompt`. */
class RebookPrompt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Site $site,
        public int $weeks,
        public ?string $bookingUrl = null,
    ) {}

    private function ctx(): array
    {
        return [
            'name' => $this->booking->customer_name,
            'site' => ucwords(str_replace('-', ' ', $this->site->name)),
            'service' => strtolower($this->booking->service?->name ?? 'visit'),
            'weeks' => (string) $this->weeks,
        ];
    }

    public function envelope(): Envelope
    {
        $tpl = EmailTemplate::forKey($this->site, 'rebook_prompt');

        return new Envelope(subject: EmailTemplate::fill($tpl['subject'], $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'rebook_prompt');

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => (string) $this->site->getAttr('email.logo', ''),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['book_button' => ['url' => $this->bookingUrl, 'label' => 'Book now']],
        ]);
    }
}
