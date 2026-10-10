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

/** "Coming up soon" — sent `reminder_hours` before the event starts. */
class EventReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, SiteSender;

    public function __construct(public TicketOrder $order, public Site $site) {}

    public function envelope(): Envelope
    {
        return new Envelope(...$this->sender($this->site), subject: 'Reminder: '.$this->order->event->title.' is coming up');
    }

    public function content(): Content
    {
        $event = $this->order->event;

        return new Content(view: 'emails.events.tickets', with: [
            'site' => $this->site,
            'siteLabel' => self::siteLabel($this->site),
            'logo' => $this->site->brandLogo(),
            'order' => $this->order,
            'event' => $event,
            'tickets' => $this->order->tickets()->with('ticketType')->get(),
            'heading' => 'See you '.($event->starts_at->isToday() ? 'today' : ($event->starts_at->isTomorrow() ? 'tomorrow' : 'soon')),
            'intro' => 'A quick reminder — '.$event->title.' starts '.$event->starts_at->diffForHumans().'. Bring your ticket code (or the QR on your ticket page) to the door.',
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
