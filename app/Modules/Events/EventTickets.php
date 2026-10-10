<?php

namespace App\Modules\Events;

use App\Models\Site;
use App\Modules\Events\Mail\TicketConfirmation;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\Ticket;
use App\Modules\Events\Models\TicketOrder;
use App\Modules\Events\Models\TicketType;
use App\Payments\PaymentManager;
use App\Services\TaskLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The events engine: availability, atomic ordering (no overselling), paying,
 * releasing holds, refunds and check-in. Controllers, the admin page, the
 * fulfilment handler and the scheduled command all go through here.
 */
class EventTickets
{
    /** Pending (unpaid) checkouts older than this are released by the sweep. */
    public const HOLD_HOURS = 25;

    public function __construct(private PaymentManager $payments) {}

    public static function currency(Site $site): string
    {
        $c = strtolower((string) ($site->feature('events')['currency'] ?? ''));

        return $c !== '' ? $c : strtolower((string) ($site->currency ?: 'gbp'));
    }

    // ── Availability ────────────────────────────────────────────────────

    /**
     * Ticket counts for many events in ONE query:
     * event_id => ['paid' => n, 'pending' => n, 'checked_in' => n].
     */
    public static function countsFor(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }
        $rows = Ticket::query()
            ->join('event_ticket_orders as o', 'o.id', '=', 'event_tickets.order_id')
            ->whereIn('event_tickets.event_id', $eventIds)
            ->whereIn('o.status', TicketOrder::HOLDING)
            ->selectRaw('event_tickets.event_id, o.status, COUNT(*) as n, SUM(CASE WHEN event_tickets.checked_in_at IS NULL THEN 0 ELSE 1 END) as ci')
            ->groupBy('event_tickets.event_id', 'o.status')
            ->get();

        $out = [];
        foreach ($eventIds as $id) {
            $out[$id] = ['paid' => 0, 'pending' => 0, 'checked_in' => 0];
        }
        foreach ($rows as $r) {
            $out[$r->event_id][$r->status] = (int) $r->n;
            $out[$r->event_id]['checked_in'] += (int) $r->ci;
        }

        return $out;
    }

    /** ticket_type_id => tickets holding capacity (pending + paid), for one event. */
    public static function typeCounts(string $eventId): array
    {
        return Ticket::query()
            ->join('event_ticket_orders as o', 'o.id', '=', 'event_tickets.order_id')
            ->where('event_tickets.event_id', $eventId)
            ->whereIn('o.status', TicketOrder::HOLDING)
            ->selectRaw('event_tickets.ticket_type_id, COUNT(*) as n')
            ->groupBy('event_tickets.ticket_type_id')
            ->pluck('n', 'ticket_type_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Public availability for an event: remaining overall + per ticket type.
     *
     * @return array{taken:int, remaining:?int, types: array<string, array{taken:int, remaining:?int, on_sale:bool, available:bool}>}
     */
    public static function availability(Event $event, ?Collection $types = null): array
    {
        $types ??= $event->ticketTypes()->get();
        $byType = static::typeCounts($event->id);
        $taken = array_sum($byType);
        $eventLeft = $event->capacity === null ? null : max(0, $event->capacity - $taken);

        $out = ['taken' => $taken, 'remaining' => $eventLeft, 'types' => []];
        foreach ($types as $t) {
            $tTaken = $byType[$t->id] ?? 0;
            $tLeft = $t->quantity === null ? null : max(0, $t->quantity - $tTaken);
            $left = match (true) {
                $tLeft === null => $eventLeft,
                $eventLeft === null => $tLeft,
                default => min($tLeft, $eventLeft),
            };
            $out['types'][$t->id] = [
                'taken' => $tTaken,
                'remaining' => $left,
                'on_sale' => $t->onSale(),
                'available' => $t->onSale() && ($left === null || $left > 0) && $event->status === 'published' && ! $event->isPast(),
            ];
        }

        return $out;
    }

    // ── Ordering ────────────────────────────────────────────────────────

    /**
     * Place an order atomically. The event row is locked FOR UPDATE, so
     * concurrent orders for the same event serialise and capacity / per-type
     * quantity can never be oversold. Free orders are paid immediately;
     * paid ones are left `pending` (holding their seats) for checkout.
     *
     * @param  array<string,int>  $items  ticket_type_id => quantity
     * @param  array{name:string,email:string,phone?:?string}  $buyer
     * @param  list<string>  $attendees  optional attendee names, in ticket order
     *
     * @throws EventsException
     */
    public function placeOrder(Site $site, Event $event, array $items, array $buyer, array $attendees = []): TicketOrder
    {
        $items = array_filter(array_map('intval', $items), fn ($q) => $q > 0);
        if ($items === []) {
            throw new EventsException('Choose at least one ticket.', 'empty');
        }

        $order = DB::transaction(function () use ($site, $event, $items, $buyer, $attendees) {
            /** @var Event $locked */
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'published') {
                throw new EventsException($locked->status === 'cancelled' ? 'This event has been cancelled.' : 'This event is not open for bookings.', 'closed');
            }
            if ($locked->isPast()) {
                throw new EventsException('This event has already taken place.', 'closed');
            }

            $types = TicketType::where('event_id', $locked->id)->whereIn('id', array_keys($items))->lockForUpdate()->get()->keyBy('id');
            if ($types->count() !== count($items)) {
                throw new EventsException('That ticket type does not exist.', 'invalid');
            }

            $byType = static::typeCounts($locked->id);
            $total = array_sum($items);
            if ($locked->capacity !== null && array_sum($byType) + $total > $locked->capacity) {
                $left = max(0, $locked->capacity - array_sum($byType));
                throw new EventsException($left === 0 ? 'Sorry — this event is sold out.' : "Only {$left} ".($left === 1 ? 'place' : 'places').' left.', 'sold_out');
            }

            $totalCents = 0;
            foreach ($items as $typeId => $qty) {
                $t = $types[$typeId];
                if (! $t->onSale()) {
                    throw new EventsException("“{$t->name}” tickets are not on sale right now.", 'off_sale');
                }
                if ($t->max_per_order && $qty > $t->max_per_order) {
                    throw new EventsException("You can book at most {$t->max_per_order} “{$t->name}” tickets per order.", 'too_many');
                }
                if ($t->quantity !== null && ($byType[$typeId] ?? 0) + $qty > $t->quantity) {
                    $left = max(0, $t->quantity - ($byType[$typeId] ?? 0));
                    throw new EventsException($left === 0 ? "“{$t->name}” is sold out." : "Only {$left} “{$t->name}” left.", 'sold_out');
                }
                $totalCents += $t->price_cents * $qty;
            }

            $order = TicketOrder::create([
                'site_id' => $site->id,
                'event_id' => $locked->id,
                'buyer_name' => $buyer['name'],
                'buyer_email' => $buyer['email'],
                'buyer_phone' => $buyer['phone'] ?? null,
                'quantity' => $total,
                'total_cents' => $totalCents,
                'currency' => static::currency($site),
                'status' => 'pending',
            ]);

            $i = 0;
            foreach ($items as $typeId => $qty) {
                for ($n = 0; $n < $qty; $n++) {
                    Ticket::create([
                        'order_id' => $order->id,
                        'event_id' => $locked->id,
                        'ticket_type_id' => $typeId,
                        'attendee_name' => trim((string) ($attendees[$i] ?? '')) ?: $buyer['name'],
                        'attendee_email' => $i === 0 ? $buyer['email'] : null,
                        'code' => Ticket::newCode(),
                    ]);
                    $i++;
                }
            }

            return $order;
        });

        if ($order->isFree()) {
            $this->markPaid($site, $order);
        }

        return $order->fresh();
    }

    /** Mark paid (idempotent), email the tickets and alert the owner. */
    public function markPaid(Site $site, TicketOrder $order, ?string $paymentRef = null): void
    {
        $changed = DB::transaction(function () use ($order, $paymentRef) {
            $fresh = TicketOrder::whereKey($order->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status === 'paid' || $fresh->status === 'refunded') {
                return false;
            }
            $fresh->update(['status' => 'paid', 'paid_at' => now(), 'payment_ref' => $paymentRef ?: $fresh->payment_ref]);

            return true;
        });
        if (! $changed) {
            return;
        }
        $order->refresh();

        $this->sendTickets($site, $order);

        try {
            $event = $order->event;
            app(TaskLogger::class)->alert($site,
                ($order->isFree() ? 'New RSVP' : 'Tickets sold — '.$order->formattedTotal()).' · '.$order->quantity.' × '.($event?->title ?? 'event'),
                'event', 'success', 'From '.($order->buyer_name ?: $order->buyer_email),
                null, 'all', url($site->name.'/events'));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function sendTickets(Site $site, TicketOrder $order): void
    {
        try {
            Mail::to($order->buyer_email, $order->buyer_name)->queue(new TicketConfirmation($order, $site));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** An expired / abandoned checkout: release its held seats. */
    public function release(TicketOrder $order): bool
    {
        return (bool) TicketOrder::whereKey($order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
    }

    /** Release every pending order older than the hold window. */
    public function sweepStaleHolds(): int
    {
        return TicketOrder::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(self::HOLD_HOURS))
            ->update(['status' => 'cancelled']);
    }

    /**
     * Refund a paid order through the site's gateway (free orders are simply
     * cancelled). Its tickets stop counting against capacity.
     *
     * @throws \Throwable when the provider rejects the refund
     */
    public function refund(Site $site, TicketOrder $order): void
    {
        if ($order->status === 'pending') {
            $this->release($order);

            return;
        }
        abort_unless($order->status === 'paid', 422, 'Only paid orders can be refunded.');

        if (! $order->isFree()) {
            abort_unless(filled($order->payment_ref), 422, 'This order has no payment reference to refund.');
            $this->payments->for($site)->refund($site, (string) $order->payment_ref);
        }
        $order->update(['status' => $order->isFree() ? 'cancelled' : 'refunded', 'refunded_at' => now()]);
    }

    /** Whether the site's paid ticket types can actually be sold. */
    public function paymentsReady(Site $site): bool
    {
        return $this->payments->for($site)->available($site);
    }
}
