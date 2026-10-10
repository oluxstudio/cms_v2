<?php

namespace App\Modules\Reviews\Mail;

use App\Models\Site;
use App\Modules\Reviews\Models\ReviewRequest;
use App\Modules\Reviews\ReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "Would you leave a quick review?" — the first ask, or the one 5-day reminder. */
class ReviewRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Site $site,
        public ReviewRequest $reviewRequest,
        public bool $reminder = false,
    ) {}

    public function envelope(): Envelope
    {
        $name = ReviewService::siteName($this->site);

        return new Envelope(...ReviewService::sender($this->site), subject: $this->reminder
            ? "A quick reminder from {$name} — how did we do?"
            : "How did we do? A quick review for {$name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reviews.request', with: [
            'siteName' => ReviewService::siteName($this->site),
            'logo' => $this->site->brandLogo(),
            'recipient' => $this->reviewRequest->name,
            'ask' => ReviewService::requestMessage($this->site),
            'url' => $this->reviewRequest->url(),
            'reminder' => $this->reminder,
        ]);
    }
}
