<?php

namespace App\Modules\Newsletter\Mail;

use App\Modules\Newsletter\Newsletter;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * One campaign message for one recipient (never BCC): the site's logo/name
 * header, the body, and a footer with the address + this recipient's own
 * unsubscribe link (also offered as RFC 8058 one-click List-Unsubscribe).
 * Sent synchronously from the SendCampaign job, so not itself queued.
 */
class NewsletterCampaignMail extends Mailable
{
    public function __construct(
        public array $brand,
        public string $subjectLine,
        public ?string $preheader,
        public string $bodyHtml,
        public ?string $unsubscribeUrl,
        public bool $isTest = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(...Newsletter::envelope($this->brand), subject: ($this->isTest ? '[Test] ' : '').$this->subjectLine);
    }

    public function headers(): Headers
    {
        if (! $this->unsubscribeUrl) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter.campaign', with: [
            'brand' => $this->brand,
            'preheader' => $this->preheader,
            'body' => $this->bodyHtml,
            'unsubscribeUrl' => $this->unsubscribeUrl,
            'isTest' => $this->isTest,
        ]);
    }
}
