<?php

namespace App\Modules\Events\Mail;

use App\Models\Site;
use App\Modules\Events\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A message from the organiser to an event's attendees (also used for cancellation notices). */
class AttendeeMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, SiteSender;

    public function __construct(
        public Event $event,
        public Site $site,
        public string $subjectLine,
        public string $body,
        public ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(...$this->sender($this->site), subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.events.message', with: [
            'site' => $this->site,
            'siteLabel' => self::siteLabel($this->site),
            'logo' => $this->site->brandLogo(),
            'event' => $this->event,
            'body' => $this->body,
            'name' => $this->recipientName,
        ]);
    }
}
