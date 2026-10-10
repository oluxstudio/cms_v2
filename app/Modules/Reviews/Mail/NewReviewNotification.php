<?php

namespace App\Modules\Reviews\Mail;

use App\Models\Site;
use App\Modules\Reviews\Models\Review;
use App\Modules\Reviews\ReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the site owner: a new review is waiting for approval. */
class NewReviewNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Site $site,
        public Review $review,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            ...ReviewService::sender($this->site),
            subject: "New {$this->review->rating}★ review awaiting approval — ".ReviewService::siteName($this->site),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reviews.owner-new', with: [
            'siteName' => ReviewService::siteName($this->site),
            'review' => $this->review,
            'url' => route('site.reviews', $this->site->name).'?filter=pending',
        ]);
    }
}
