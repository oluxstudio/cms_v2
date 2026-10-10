<?php

namespace App\Modules\Events\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\Events\EventsException;
use App\Modules\Events\EventTickets;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\TicketType;
use App\Payments\CheckoutLine;
use App\Payments\CheckoutRequest;
use App\Payments\PaymentManager;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Events public API for template sites (stateless JSON):
 *
 *   GET  /api/sites/{site}/events[?past=1&limit=n]  → upcoming (or past) published events
 *   GET  /api/sites/{site}/events/{slug}            → detail + ticket types + availability
 *   POST /api/sites/{site}/events/{slug}/order      → free: tickets now · paid: checkout_url
 */
class EventsApiController extends Controller
{
    public function __construct(private EventTickets $engine, private PaymentManager $payments) {}

    private function site(Request $request, string $siteName): Site
    {
        return $request->attributes->get('resolvedSite') ?? Site::where('name', $siteName)->firstOrFail();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        $site = $this->site($request, $siteName);
        $past = $request->boolean('past');
        $limit = max(1, min(100, (int) $request->query('limit', 50)));

        $events = Event::where('site_id', $site->id)->where('status', 'published')
            ->when($past,
                fn ($q) => $q->where('starts_at', '<', now())->orderByDesc('starts_at'),
                fn ($q) => $q->where(fn ($w) => $w->where('starts_at', '>=', now())->orWhere('ends_at', '>=', now()))->orderBy('starts_at'))
            ->with('ticketTypes')
            ->limit($limit)->get();

        $counts = EventTickets::countsFor($events->pluck('id')->all());
        $currency = EventTickets::currency($site);

        return response()->json([
            'data' => $events->map(fn (Event $e) => $this->summary($e, $site, $currency, $counts[$e->id] ?? []))->values(),
            'currency' => $currency,
        ]);
    }

    public function show(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->site($request, $siteName);
        $event = Event::where('site_id', $site->id)->where('slug', $slug)->whereIn('status', ['published', 'cancelled'])->firstOrFail();
        $types = $event->ticketTypes()->get();
        $currency = EventTickets::currency($site);
        $avail = EventTickets::availability($event, $types);
        $counts = EventTickets::countsFor([$event->id])[$event->id];
        $paymentsAvailable = $this->payments->for($site)->available($site);

        return response()->json(['data' => $this->summary($event, $site, $currency, $counts) + [
            'description' => (string) $event->description,
            'payments_available' => $paymentsAvailable,
            'ticket_types' => $types->map(fn (TicketType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'price_cents' => (int) $t->price_cents,
                'price' => Money::format((int) $t->price_cents, $currency, free: true),
                'currency' => $currency,
                'is_free' => $t->isFree(),
                'quantity' => $t->quantity,
                'remaining' => $avail['types'][$t->id]['remaining'],
                'max_per_order' => (int) $t->max_per_order,
                'sales_start' => $t->sales_start?->toIso8601String(),
                'sales_end' => $t->sales_end?->toIso8601String(),
                'on_sale' => $avail['types'][$t->id]['on_sale'],
                'available' => $avail['types'][$t->id]['available'] && ($t->isFree() || $paymentsAvailable),
                'sold_out' => $avail['types'][$t->id]['remaining'] === 0,
            ])->values(),
        ]]);
    }

    public function order(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->site($request, $siteName);
        $event = Event::where('site_id', $site->id)->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tickets' => ['required_without:ticket_type_id', 'array', 'max:20'],
            'tickets.*.ticket_type_id' => ['required', 'string', 'max:40'],
            'tickets.*.quantity' => ['required', 'integer', 'min:0', 'max:100'],
            'ticket_type_id' => ['required_without:tickets', 'string', 'max:40'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'attendees' => ['nullable', 'array', 'max:200'],
            'attendees.*' => ['nullable', 'string', 'max:255'],
            'return_url' => ['nullable', 'url', 'max:2000'],
        ]);

        $items = [];
        if (! empty($data['tickets'])) {
            foreach ($data['tickets'] as $row) {
                $items[$row['ticket_type_id']] = ($items[$row['ticket_type_id']] ?? 0) + (int) $row['quantity'];
            }
        } else {
            $items[$data['ticket_type_id']] = (int) ($data['quantity'] ?? 1);
        }

        // Paid tickets need a connected payment account — say so clearly.
        $paid = TicketType::where('event_id', $event->id)->whereIn('id', array_keys($items))->where('price_cents', '>', 0)->exists();
        $gateway = $this->payments->for($site);
        if ($paid && ! $gateway->available($site)) {
            return response()->json([
                'message' => 'Paid tickets are not available right now — this site is not accepting online payments yet.',
                'reason' => 'payments_unavailable',
            ], 422);
        }

        try {
            $order = $this->engine->placeOrder($site, $event, $items,
                ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null],
                array_values($data['attendees'] ?? []));
        } catch (EventsException $e) {
            return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason], 422);
        }

        if ($order->status === 'paid') {
            return response()->json([
                'status' => 'paid',
                'order_id' => $order->id,
                'reference' => $order->reference,
                'total_cents' => 0,
                'tickets' => $order->tickets()->with('ticketType')->get()->map(fn ($t) => [
                    'code' => $t->code,
                    'attendee' => $t->attendee_name,
                    'type' => $t->ticketType?->name,
                    'url' => $t->url($site->name),
                ])->values(),
            ], 201);
        }

        // Paid → hosted checkout on the site's own (Connect) account.
        $types = TicketType::whereIn('id', array_keys($items))->get()->keyBy('id');
        $lines = [];
        foreach ($items as $typeId => $qty) {
            $t = $types[$typeId];
            if ($t->price_cents > 0 && $qty > 0) {
                $lines[] = new CheckoutLine($event->title.' — '.$t->name, (int) $t->price_cents, $order->currency, $qty);
            }
        }

        try {
            $session = $gateway->createCheckout($site, new CheckoutRequest(
                lines: $lines,
                successUrl: URL::signedRoute('public.events.success', ['siteName' => $site->name, 'order' => $order->id]).'&session_id={CHECKOUT_SESSION_ID}',
                cancelUrl: (string) ($data['return_url'] ?? url('preview/'.$site->name)),
                metadata: ['ticket_order_id' => $order->id, 'site_id' => $site->id],
                customerEmail: $data['email'],
            ));
        } catch (\Throwable $e) {
            Log::error('Event ticket checkout failed', ['site' => $site->id, 'order' => $order->id, 'msg' => $e->getMessage()]);
            $this->engine->release($order);

            return response()->json(['message' => 'Could not start the payment. Please try again later.'], 502);
        }

        $order->update(['checkout_session_id' => $session->id]);

        return response()->json([
            'status' => 'pending',
            'order_id' => $order->id,
            'reference' => $order->reference,
            'total_cents' => (int) $order->total_cents,
            'checkout_url' => $session->url,
        ], 201);
    }

    /** List/detail shape shared by both endpoints. */
    private function summary(Event $e, Site $site, string $currency, array $counts): array
    {
        $types = $e->relationLoaded('ticketTypes') ? $e->ticketTypes : $e->ticketTypes()->get();
        $prices = $types->pluck('price_cents')->map(fn ($c) => (int) $c);
        $taken = ($counts['paid'] ?? 0) + ($counts['pending'] ?? 0);
        $remaining = $e->capacity === null ? null : max(0, $e->capacity - $taken);
        $min = $prices->min();

        return [
            'id' => $e->id,
            'slug' => $e->slug,
            'title' => $e->title,
            'summary' => $e->summary,
            'image' => $e->imageUrl() ?: null,
            'starts_at' => $e->localStart()->toIso8601String(),
            'ends_at' => $e->localEnd()?->toIso8601String(),
            'timezone' => $e->safeTimezone(),
            'when' => $e->whenLabel(),
            'venue' => ['name' => $e->venue_name, 'address' => $e->venue_address],
            'online_url' => $e->online_url,
            'is_online' => filled($e->online_url) && blank($e->venue_name) && blank($e->venue_address),
            'status' => $e->status,
            'is_free' => $types->isNotEmpty() && $prices->max() === 0,
            'price_from_cents' => $min,
            'price_from' => $min === null ? null : Money::format($min, $currency, free: true),
            'currency' => $currency,
            'capacity' => $e->capacity,
            'remaining' => $remaining,
            'sold_out' => $remaining === 0,
            'has_tickets' => $types->isNotEmpty(),
            'api' => url('api/sites/'.$site->name.'/events/'.$e->slug),
        ];
    }
}
