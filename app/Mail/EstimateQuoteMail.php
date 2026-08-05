<?php

namespace App\Mail;

use App\Models\Estimate;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The visitor's estimate email — subject/body are DRAFTED BY THE ADMIN on
 * the Estimates page (site attrs estimator.email_subject / .email_body) with
 * {placeholders}; the calculated results table is appended automatically.
 */
class EstimateQuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Site $site,
        public Estimate $estimate,
        public array $results = [],
    ) {}

    /** Placeholder map available to the admin's draft. */
    public function placeholders(): array
    {
        return [
            '{name}' => $this->estimate->customer_name,
            '{reference}' => $this->estimate->reference,
            '{service}' => $this->estimate->estimator?->name
                ?? ($this->estimate->trade ? ucfirst(str_replace('-', ' ', $this->estimate->trade)) : 'your request'),
            '{cost}' => $this->estimate->cost_high_cents > 0 ? $this->estimate->costLabel() : ($this->results[0]['formatted'] ?? '—'),
            '{completion}' => (string) ($this->estimate->completion ?: '—'),
            '{site}' => ucwords(str_replace('-', ' ', $this->site->name)),
        ];
    }

    private function fill(string $text): string
    {
        return strtr($text, $this->placeholders());
    }

    /** The estimator's OWN draft wins; site-level attrs and defaults back it up. */
    public function envelope(): Envelope
    {
        $subject = $this->estimate->estimator?->email_subject
            ?: (string) $this->site->getAttr('estimator.email_subject', 'Your {service} estimate {reference} from {site}');

        return new Envelope(subject: $this->fill($subject));
    }

    public function content(): Content
    {
        $body = $this->estimate->estimator?->email_body
            ?: (string) $this->site->getAttr('estimator.email_body',
                "Hi {name},\n\nThanks for requesting a {service} estimate from {site}. Here is what we calculated for you — reference {reference}.\n\nWe'll be in touch shortly to talk it through.");

        return new Content(markdown: 'emails.estimate-quote', with: [
            'bodyText' => $this->fill($body),
            'results' => $this->results,
            'estimate' => $this->estimate,
        ]);
    }
}
