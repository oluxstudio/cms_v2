<?php

namespace App\Mail;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "Your week at {site}" — the Monday summary sent by site:digest. */
class WeeklyDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Site $site,
        public array $stats,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->site->getAttr('business_name') ?: $this->site->name;

        return new Envelope(subject: "Your week at {$name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.weekly-digest', with: [
            'siteName' => $this->site->getAttr('business_name') ?: $this->site->name,
            'dashboardUrl' => url($this->site->name.'/dashboard'),
        ]);
    }
}
