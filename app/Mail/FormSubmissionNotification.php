<?php

namespace App\Mail;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Owner alert: a form on their site was just submitted (contact or custom form). */
class FormSubmissionNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string,mixed>  $fields  submitted key => value pairs
     */
    public function __construct(
        public Site $site,
        public string $formLabel,
        public array $fields,
        public string $adminUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New '.$this->formLabel.' submission on '.ucwords(str_replace('-', ' ', $this->site->name)),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.form-submission-owner');
    }
}
