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

/** "How did we do?" — sent the day after an appointment. Template `review_request`. */
class ReviewRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Site $site,
        public string $reviewUrl,
    ) {}

    private function ctx(): array
    {
        return [
            'name' => $this->booking->customer_name,
            'site' => ucwords(str_replace('-', ' ', $this->site->name)),
            'service' => strtolower($this->booking->service?->name ?? 'visit'),
        ];
    }

    public function envelope(): Envelope
    {
        $tpl = EmailTemplate::forKey($this->site, 'review_request');

        return new Envelope(subject: EmailTemplate::fill($tpl['subject'], $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'review_request');

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => $this->site->brandLogo(),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['review_button' => ['url' => $this->reviewUrl, 'label' => 'Leave a review']],
        ]);
    }
}
