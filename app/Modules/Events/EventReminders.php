<?php

namespace App\Modules\Events;

use App\Modules\Events\Mail\EventReminder;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\TicketOrder;
use Illuminate\Support\Facades\Mail;

/**
 * The hourly `events:remind` run: emails every paid order a reminder
 * `reminder_hours` (Events add-on setting, 0 = off) before its event starts,
 * once per event, and releases checkout holds that never completed.
 */
class EventReminders
{
    /** Longest reminder lead time considered (one week). */
    private const MAX_HOURS = 168;

    public function __construct(private EventTickets $engine) {}

    /** @return array{events:int, emails:int, released:int} */
    public function run(): array
    {
        $released = $this->engine->sweepStaleHolds();
        $events = 0;
        $emails = 0;

        $due = Event::with('site')->where('status', 'published')->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addHours(self::MAX_HOURS)])
            ->get();

        foreach ($due as $event) {
            $site = $event->site;
            if (! $site || ! $site->hasFeature('events')) {
                continue;
            }
            $hours = (int) ($site->feature('events')['reminder_hours'] ?? 24);
            if ($hours <= 0 || $event->starts_at->gt(now()->addHours($hours))) {
                continue;
            }

            // Claim the event first so overlapping runs never double-send.
            if (! Event::whereKey($event->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()])) {
                continue;
            }
            $events++;
            TicketOrder::where('event_id', $event->id)->where('status', 'paid')->with('event')
                ->each(function (TicketOrder $order) use ($site, &$emails) {
                    try {
                        Mail::to($order->buyer_email, $order->buyer_name)->queue(new EventReminder($order, $site));
                        $emails++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
        }

        return ['events' => $events, 'emails' => $emails, 'released' => $released];
    }
}
