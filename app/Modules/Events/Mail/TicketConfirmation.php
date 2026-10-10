<?php

namespace App\Modules\Events\Mail;

use App\Models\Site;
use App\Modules\Events\CalendarInvite;
use App\Modules\Events\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "Your tickets" — every ticket's code + link, and an add-to-calendar .ics. */
class TicketConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, SiteSender;

    public function __construct(public TicketOrder $order, public Site $site) {}

    public function envelope(): Envelope
    {
        return new Envelope(...$this->sender($this->site),
            subject: ($this->order->isFree() ? 'You’re booked: ' : 'Your tickets: ').$this->order->event->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.events.tickets', with: [
            'site' => $this->site,
            'siteLabel' => self::siteLabel($this->site),
            'logo' => $this->site->brandLogo(),
            'order' => $this->order,
            'event' => $this->order->event,
            'tickets' => $this->order->tickets()->with('ticketType')->get(),
            'heading' => $this->order->isFree() ? 'You’re on the list' : 'Here are your tickets',
            'intro' => null,
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => CalendarInvite::make($this->order->event, $this->site), 'event.ics')
                ->withMime('text/calendar'),
        ];
    }
}
