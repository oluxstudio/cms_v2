<?php

namespace App\Mail;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one branded "we received your submission" email sent to the VISITOR
 * for every submission type (forms, contact, interest, bookings). Subject +
 * body are edited by the site admin on the Emails page (site attributes
 * email.receipt_subject / email.receipt_body) with {placeholders}, and the
 * site logo + name brand the message. A summary of what was submitted is
 * appended automatically.
 */
class SubmissionReceipt extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $type  human label for the submission, e.g. "message", "booking", "Contact form"
     * @param  array<string,mixed>  $summary  submitted key => value pairs (optional)
     */
    public function __construct(
        public Site $site,
        public string $type,
        public ?string $recipientName = null,
        public array $summary = [],
    ) {}

    public static function defaultSubject(): string
    {
        return 'We received your {type} — {site}';
    }

    public static function defaultBody(): string
    {
        return "Hi {name},\n\nThanks for reaching out to {site}. We've received your {type} and someone will get back to you shortly.\n\n— The {site} team";
    }

    private function placeholders(): array
    {
        return [
            '{name}' => $this->recipientName ?: 'there',
            '{site}' => ucwords(str_replace('-', ' ', $this->site->name)),
            '{type}' => $this->type,
        ];
    }

    private function fill(string $text): string
    {
        return strtr($text, $this->placeholders());
    }

    public function envelope(): Envelope
    {
        $subject = (string) $this->site->getAttr('email.receipt_subject', self::defaultSubject());

        return new Envelope(subject: $this->fill($subject));
    }

    public function content(): Content
    {
        $body = (string) $this->site->getAttr('email.receipt_body', self::defaultBody());

        return new Content(view: 'emails.branded-receipt', with: [
            'site' => $this->site,
            'logo' => $this->site->getAttr('email.logo') ?: null,
            'body' => $this->fill($body),
            'summary' => $this->summary,
        ]);
    }
}
