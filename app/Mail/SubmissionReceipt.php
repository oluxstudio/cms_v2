<?php

namespace App\Mail;

use App\Models\Form;
use App\Models\Site;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one branded "we received your submission" email sent to the VISITOR for
 * every submission type (forms, contact, interest, bookings). The subject and
 * an ordered set of reorderable/toggleable sections (greeting, message,
 * submission summary, footer, logo) are edited by the site admin on the Emails
 * page (site attributes email.receipt_subject / email.receipt_sections) with
 * {placeholders}. The admin's logo brands it, falling back to the app logo.
 */
class SubmissionReceipt extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $type  human label for the submission, e.g. "message", "booking", "Contact form"
     * @param  array<string,mixed>  $summary  submitted key => value pairs (optional)
     * @param  ?Form  $form  the source form — used to pick its own template (null → site default)
     */
    public function __construct(
        public Site $site,
        public string $type,
        public ?string $recipientName = null,
        public array $summary = [],
        public ?Form $form = null,
    ) {}

    public static function defaultSubject(): string
    {
        return EmailTemplate::defaultSubject();
    }

    /** @deprecated body copy now lives in EmailTemplate sections; kept for back-compat. */
    public static function defaultBody(): string
    {
        return "Hi {name},\n\nThanks for reaching out to {site}. We've received your {type} and someone will get back to you shortly.";
    }

    private function ctx(): array
    {
        return [
            'name' => $this->recipientName,
            'site' => ucwords(str_replace('-', ' ', $this->site->name)),
            'type' => $this->type,
        ];
    }

    public function envelope(): Envelope
    {
        $subject = EmailTemplate::forForm($this->form, $this->site)['subject'];

        return new Envelope(subject: EmailTemplate::fill($subject, $this->ctx(), $this->summary));
    }

    public function content(): Content
    {
        // The form's own template when it has one, else the site default.
        // Fill placeholders in the editable sections so the view just renders.
        $sections = collect(EmailTemplate::forForm($this->form, $this->site)['sections'])
            ->filter(fn ($s) => $s['enabled'])
            ->map(function ($s) {
                if ($s['text'] !== null) {
                    $s['text'] = EmailTemplate::fill($s['text'], $this->ctx(), $this->summary);
                }

                return $s;
            })
            ->values()
            ->all();

        // Admin's uploaded logo (a URL) wins; otherwise fall back to the app
        // brand mark, served from a stable public URL so every mail client
        // loads it like any other image.
        $logo = $this->site->getAttr('email.logo') ?: asset('images/olux-logo.png');

        return new Content(view: 'emails.branded-receipt', with: [
            'site' => $this->site,
            'logo' => $logo,
            'sections' => $sections,
            'summary' => $this->summary,
        ]);
    }
}
