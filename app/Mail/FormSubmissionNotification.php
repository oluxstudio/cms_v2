<?php

namespace App\Mail;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Owner alert: a form on their site was just submitted (contact or custom form). */
class FormSubmissionNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string,mixed>  $fields  submitted key => value pairs
     * @param  array<string,string>  $labels  field key => the form's label for it (form order)
     */
    public function __construct(
        public Site $site,
        public string $formLabel,
        public array $fields,
        public string $adminUrl,
        public array $labels = [],
    ) {}

    public function envelope(): Envelope
    {
        // A submission that carries its own subject (e.g. "Message for Pastor …") says so up front.
        $about = trim((string) ($this->fields['subject'] ?? ''));

        return new Envelope(
            subject: 'New '.$this->formLabel.' submission on '.ucwords(str_replace('-', ' ', $this->site->name))
                .($about !== '' ? ' — '.\Illuminate\Support\Str::limit($about, 80) : ''),
        );
    }

    /** The submitted values as label => value, in the form's field order, empty ones left out. @return array<string,string> */
    public function rows(): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($this->labels), array_keys($this->fields))));
        $rows = [];
        foreach ($keys as $key) {
            $value = $this->fields[$key] ?? null;
            $value = is_array($value) ? implode(', ', array_map('strval', $value)) : (is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value);
            if (trim($value) === '') {
                continue;
            }
            $rows[$this->labels[$key] ?? \Illuminate\Support\Str::headline($key)] = $value;
        }

        return $rows;
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.form-submission-owner', with: ['rows' => $this->rows()]);
    }
}
