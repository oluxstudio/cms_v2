<?php

namespace App\Mail;

use App\Models\Estimate;
use App\Models\Site;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The visitor's estimate email — template `estimate_quote` on the Emails page.
 * Precedence: the estimator's OWN draft (Estimates page) > the site's template
 * customisation > legacy estimator.email_* attrs > catalog default.
 */
class EstimateQuoteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Site $site,
        public Estimate $estimate,
        public array $results = [],
    ) {}

    /** Placeholder ctx available to the admin's draft. */
    public function ctx(): array
    {
        return [
            'name' => $this->estimate->customer_name,
            'reference' => $this->estimate->reference,
            'service' => $this->estimate->estimator?->name
                ?? ($this->estimate->trade ? ucfirst(str_replace('-', ' ', $this->estimate->trade)) : 'your request'),
            'cost' => $this->estimate->cost_high_cents > 0 ? $this->estimate->costLabel() : ($this->results[0]['formatted'] ?? '—'),
            'completion' => (string) ($this->estimate->completion ?: '—'),
            'site' => ucwords(str_replace('-', ' ', $this->site->name)),
        ];
    }

    /** Legacy shape kept for the Estimates-page editor and its tests. */
    public function placeholders(): array
    {
        return collect($this->ctx())->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => $v])->all();
    }

    public function envelope(): Envelope
    {
        $subject = $this->estimate->estimator?->email_subject
            ?: EmailTemplate::forKey($this->site, 'estimate_quote')['subject'];

        return new Envelope(subject: EmailTemplate::fill($subject, $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'estimate_quote');

        // The estimator's own draft replaces the intro text wholesale.
        if ($draft = $this->estimate->estimator?->email_body) {
            $tpl['sections'] = collect($tpl['sections'])->map(function ($s) use ($draft) {
                if ($s['key'] === 'intro') {
                    $s['text'] = $draft;
                }

                return $s;
            })->all();
        }

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => $this->site->brandLogo(),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['quote_summary' => [
                'results' => collect($this->results)->map(fn ($r) => [
                    'label' => $r['label'] ?? ($r['name'] ?? 'Estimate'),
                    'formatted' => $r['formatted'] ?? '',
                ])->all(),
                'reference' => $this->estimate->reference,
            ]],
        ]);
    }
}
