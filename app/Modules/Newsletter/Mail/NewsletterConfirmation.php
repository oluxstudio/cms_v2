<?php

namespace App\Modules\Newsletter\Mail;

use App\Models\Subscription;
use App\Modules\Newsletter\Newsletter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Double opt-in: "Please confirm your subscription" with the subscriber's confirm link. */
class NewsletterConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    private function brand(): array
    {
        return Newsletter::brand($this->subscription->site);
    }

    public function envelope(): Envelope
    {
        $brand = $this->brand();

        return new Envelope(...Newsletter::envelope($brand), subject: 'Please confirm your subscription to '.$brand['name']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter.confirm', with: [
            'brand' => $this->brand(),
            'confirmUrl' => route('newsletter.confirm', $this->subscription->token),
            'name' => $this->subscription->name,
        ]);
    }
}
