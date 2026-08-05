<?php

namespace App\Mail;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies site admins that the AI assistant created a new declarative module.
 */
class ModuleCreatedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Site $site,
        public string $moduleName,
        public string $moduleDescription,
        public string $pageUrl,
        public string $adminUrl,
    ) {}

    public function envelope(): Envelope
    {
        $name = ucwords(str_replace('-', ' ', $this->site->name));

        return new Envelope(subject: "New \"{$this->moduleName}\" module added to {$name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.module-created');
    }
}
