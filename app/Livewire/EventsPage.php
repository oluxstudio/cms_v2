<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Site;
use App\Modules\Events\EventTickets;
use App\Modules\Events\Mail\AttendeeMessage;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\Ticket;
use App\Modules\Events\Models\TicketOrder;
use App\Modules\Events\Models\TicketType;
use App\Services\SiteConnect\HtmlSanitizer;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Events & Tickets admin (/{siteID}/events): the event list (grid by default)
 * with an event's own panel — details, ticket types, attendees + door
 * check-in, orders + refunds, CSV export, messaging attendees.
 */
class EventsPage extends Component
{
    use WithLayoutMode;

    public Site $site;

    public string $search = '';

    /** upcoming | past | drafts | cancelled */
    #[Url(except: 'upcoming')]
    public string $filter = 'upcoming';

    /** date | title | sales */
    #[Url(except: 'date')]
    public string $sort = 'date';

    /** The event whose panel is open. */
    #[Url(as: 'event', except: '')]
    public string $eventId = '';

    /** Panel tab: details | tickets | attendees | orders */
    public string $tab = 'attendees';

    // ── Event form ──
    public bool $editing = false;

    public array $form = [];

    // ── Ticket type form (null = closed, '' = new) ──
    public ?string $typeId = null;

    public array $typeForm = [];

    // ── Attendees / check-in ──
    public string $attendeeSearch = '';

    public string $checkinCode = '';

    // ── Email attendees ──
    public bool $composing = false;

    public string $mailSubject = '';

    public string $mailBody = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('events', 'grid');
        if ($this->eventId !== '' && ! Event::where('site_id', $site->id)->whereKey($this->eventId)->exists()) {
            $this->eventId = '';
        }
    }

    public function canManage(): bool
    {
        return $this->site->allows(Auth::user(), 'events.manage');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function event(?string $id = null): Event
    {
        return Event::where('site_id', $this->site->id)->findOrFail($id ?? $this->eventId);
    }

    // ── List ────────────────────────────────────────────────────────────

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['upcoming', 'past', 'drafts', 'cancelled'], true) ? $filter : 'upcoming';
    }

    public function openEvent(string $id): void
    {
        $this->event($id);
        $this->eventId = $id;
        $this->tab = 'attendees';
        $this->reset(['editing', 'typeId', 'typeForm', 'attendeeSearch', 'checkinCode', 'composing']);
    }

    public function closeEvent(): void
    {
        $this->reset(['eventId', 'editing', 'typeId', 'typeForm', 'attendeeSearch', 'checkinCode', 'composing']);
    }

    // ── Event create / edit ─────────────────────────────────────────────

    public function newEvent(): void
    {
        $this->authorizeManage();
        $tz = config('app.timezone', 'UTC');
        $start = now($tz)->addWeek()->setTime(19, 0);
        $this->form = [
            'title' => '', 'slug' => '', 'summary' => '', 'description' => '', 'image' => '',
            'starts_at' => $start->format('Y-m-d\TH:i'), 'ends_at' => $start->copy()->addHours(2)->format('Y-m-d\TH:i'),
            'timezone' => $this->defaultTimezone(), 'venue_name' => '', 'venue_address' => '', 'online_url' => '',
            'capacity' => '', 'status' => 'draft',
        ];
        $this->eventId = '';
        $this->editing = true;
    }

    public function editEvent(?string $id = null): void
    {
        $this->authorizeManage();
        $e = $this->event($id);
        $this->eventId = $e->id;
        $this->form = [
            'title' => $e->title, 'slug' => $e->slug, 'summary' => (string) $e->summary, 'description' => (string) $e->description,
            'image' => (string) $e->image,
            'starts_at' => $e->localStart()->format('Y-m-d\TH:i'),
            'ends_at' => $e->localEnd()?->format('Y-m-d\TH:i') ?? '',
            'timezone' => $e->safeTimezone(), 'venue_name' => (string) $e->venue_name, 'venue_address' => (string) $e->venue_address,
            'online_url' => (string) $e->online_url, 'capacity' => $e->capacity === null ? '' : (string) $e->capacity, 'status' => $e->status,
        ];
        $this->editing = true;
        $this->tab = 'details';
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->form = [];
        $this->resetErrorBag();
    }

    public function saveEvent(): void
    {
        $this->authorizeManage();
        $this->validate([
            'form.title' => 'required|string|max:255',
            'form.slug' => 'nullable|string|max:120',
            'form.summary' => 'nullable|string|max:500',
            'form.description' => 'nullable|string|max:60000',
            'form.image' => 'nullable|string|max:255',
            'form.starts_at' => 'required|date',
            'form.ends_at' => 'nullable|date|after:form.starts_at',
            'form.timezone' => 'required|timezone',
            'form.venue_name' => 'nullable|string|max:255',
            'form.venue_address' => 'nullable|string|max:500',
            'form.online_url' => ['nullable', 'url:http,https', 'max:500'],
            'form.capacity' => 'nullable|integer|min:1|max:1000000',
            'form.status' => 'required|in:draft,published,cancelled',
        ], [], ['form.title' => 'title', 'form.starts_at' => 'start', 'form.ends_at' => 'end', 'form.online_url' => 'online link', 'form.capacity' => 'capacity']);

        $f = $this->form;
        $tz = $f['timezone'];
        $existing = $this->eventId !== '' ? $this->event() : null;
        $data = [
            'title' => trim($f['title']),
            'slug' => Event::uniqueSlug($this->site->id, trim((string) $f['slug']) ?: $f['title'], $existing?->id),
            'summary' => trim((string) $f['summary']) ?: null,
            'description' => app(HtmlSanitizer::class)->html((string) $f['description']) ?: null,
            'image' => trim((string) $f['image']) ?: null,
            'starts_at' => Carbon::parse($f['starts_at'], $tz)->setTimezone(config('app.timezone')),
            'ends_at' => filled($f['ends_at'] ?? null) ? Carbon::parse($f['ends_at'], $tz)->setTimezone(config('app.timezone')) : null,
            'timezone' => $tz,
            'venue_name' => trim((string) $f['venue_name']) ?: null,
            'venue_address' => trim((string) $f['venue_address']) ?: null,
            'online_url' => trim((string) $f['online_url']) ?: null,
            'capacity' => filled($f['capacity']) ? (int) $f['capacity'] : null,
            'status' => $f['status'],
        ];

        if ($existing) {
            // A changed start time re-arms the reminder.
            if (! $existing->starts_at->equalTo($data['starts_at'])) {
                $data['reminder_sent_at'] = null;
            }
            $existing->update($data);
            $event = $existing;
        } else {
            $event = Event::create($data + ['site_id' => $this->site->id]);
            // Every new event starts with a free RSVP ticket — edit or add paid ones.
            $event->ticketTypes()->create(['name' => 'General admission', 'price_cents' => 0, 'max_per_order' => 10]);
        }

        $this->editing = false;
        $this->form = [];
        $this->eventId = $event->id;
        $this->tab = $existing ? 'details' : 'tickets';
        $this->dispatch('toast', level: 'success', title: 'Saved', message: '“'.$event->title.'” saved.');
    }

    public function setStatus(string $id, string $status): void
    {
        $this->authorizeManage();
        abort_unless(in_array($status, ['draft', 'published'], true), 422);
        $e = $this->event($id);
        abort_if($e->status === 'cancelled', 422);
        $e->update(['status' => $status]);
    }

    /** Cancel the event: closes sales and emails every ticket holder. */
    public function cancelEvent(string $id): void
    {
        $this->authorizeManage();
        $e = $this->event($id);
        if ($e->status === 'cancelled') {
            return;
        }
        $e->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        TicketOrder::where('event_id', $e->id)->where('status', 'pending')->update(['status' => 'cancelled']);

        $sent = 0;
        foreach (TicketOrder::where('event_id', $e->id)->where('status', 'paid')->get() as $order) {
            Mail::to($order->buyer_email, $order->buyer_name)->queue(new AttendeeMessage($e, $this->site,
                'Cancelled: '.$e->title,
                "We're sorry — {$e->title} ({$e->whenLabel()}) has been cancelled.".($order->isFree() ? '' : "\n\nWe'll be in touch about your refund for order {$order->reference}."),
                $order->buyer_name));
            $sent++;
        }
        $this->dispatch('toast', level: 'success', title: 'Event cancelled', message: $sent ? "{$sent} ticket ".($sent === 1 ? 'holder' : 'holders').' notified.' : 'Sales closed.');
    }

    public function deleteEvent(string $id): void
    {
        $this->authorizeManage();
        $e = $this->event($id);
        if (TicketOrder::where('event_id', $e->id)->where('status', 'paid')->where('total_cents', '>', 0)->exists()) {
            $this->dispatch('toast', level: 'error', title: 'Can’t delete', message: 'This event has paid orders — cancel it instead.');

            return;
        }
        $e->delete();
        if ($this->eventId === $id) {
            $this->closeEvent();
        }
    }

    // ── Ticket types ────────────────────────────────────────────────────

    public function newType(): void
    {
        $this->authorizeManage();
        $this->typeId = '';
        $this->typeForm = ['name' => '', 'description' => '', 'price' => '0', 'quantity' => '', 'sales_start' => '', 'sales_end' => '', 'max_per_order' => '10'];
    }

    public function editType(string $id): void
    {
        $this->authorizeManage();
        $t = TicketType::where('event_id', $this->event()->id)->findOrFail($id);
        $tz = $this->event()->safeTimezone();
        $this->typeId = $t->id;
        $this->typeForm = [
            'name' => $t->name, 'description' => (string) $t->description,
            'price' => number_format($t->price_cents / 100, 2, '.', ''),
            'quantity' => $t->quantity === null ? '' : (string) $t->quantity,
            'sales_start' => $t->sales_start?->setTimezone($tz)->format('Y-m-d\TH:i') ?? '',
            'sales_end' => $t->sales_end?->setTimezone($tz)->format('Y-m-d\TH:i') ?? '',
            'max_per_order' => (string) $t->max_per_order,
        ];
    }

    public function cancelType(): void
    {
        $this->reset(['typeId', 'typeForm']);
        $this->resetErrorBag();
    }

    public function saveType(): void
    {
        $this->authorizeManage();
        $event = $this->event();
        $this->validate([
            'typeForm.name' => 'required|string|max:255',
            'typeForm.description' => 'nullable|string|max:500',
            'typeForm.price' => 'required|numeric|min:0|max:100000',
            'typeForm.quantity' => 'nullable|integer|min:1|max:1000000',
            'typeForm.sales_start' => 'nullable|date',
            'typeForm.sales_end' => 'nullable|date',
            'typeForm.max_per_order' => 'required|integer|min:1|max:100',
        ], [], ['typeForm.name' => 'name', 'typeForm.price' => 'price', 'typeForm.quantity' => 'quantity', 'typeForm.max_per_order' => 'max per order']);

        $f = $this->typeForm;
        $price = (int) round(((float) $f['price']) * 100);
        if ($price > 0 && $price < 50) {
            $this->addError('typeForm.price', 'Paid tickets must cost at least 0.50.');

            return;
        }
        $tz = $event->safeTimezone();
        $data = [
            'name' => trim($f['name']),
            'description' => trim((string) $f['description']) ?: null,
            'price_cents' => $price,
            'quantity' => filled($f['quantity']) ? (int) $f['quantity'] : null,
            'sales_start' => filled($f['sales_start']) ? Carbon::parse($f['sales_start'], $tz)->setTimezone(config('app.timezone')) : null,
            'sales_end' => filled($f['sales_end']) ? Carbon::parse($f['sales_end'], $tz)->setTimezone(config('app.timezone')) : null,
            'max_per_order' => (int) $f['max_per_order'],
        ];

        if ($this->typeId) {
            TicketType::where('event_id', $event->id)->findOrFail($this->typeId)->update($data);
        } else {
            $event->ticketTypes()->create($data + ['sort' => (int) TicketType::where('event_id', $event->id)->max('sort') + 1]);
        }
        $this->cancelType();
    }

    public function deleteType(string $id): void
    {
        $this->authorizeManage();
        $t = TicketType::where('event_id', $this->event()->id)->findOrFail($id);
        if (Ticket::where('ticket_type_id', $t->id)->exists()) {
            $this->dispatch('toast', level: 'error', title: 'In use', message: 'Tickets of this type have been issued — set its sales end date instead.');

            return;
        }
        $t->delete();
    }

    // ── Attendees & check-in ────────────────────────────────────────────

    public function toggleCheckIn(string $ticketId): void
    {
        $this->authorizeManage();
        $t = Ticket::where('event_id', $this->event()->id)->whereHas('order', fn ($q) => $q->where('status', 'paid'))->findOrFail($ticketId);
        $t->update(['checked_in_at' => $t->checked_in_at ? null : now()]);
    }

    /** Door check-in by code (exact match, case-insensitive). */
    public function checkInByCode(): void
    {
        $this->authorizeManage();
        $code = strtoupper(preg_replace('/\s+/', '', $this->checkinCode));
        if ($code === '') {
            return;
        }
        $t = Ticket::where('event_id', $this->event()->id)->where('code', $code)->with('order')->first();
        if (! $t) {
            // Not a code: fall back to the attendee search.
            $this->attendeeSearch = $this->checkinCode;
            $this->dispatch('toast', level: 'warning', title: 'No ticket with that code', message: 'Showing name matches instead.');

            return;
        }
        if ($t->order?->status !== 'paid') {
            $this->dispatch('toast', level: 'error', title: 'Not valid', message: 'Ticket '.$code.' is '.($t->order?->status ?? 'unknown').'.');

            return;
        }
        if ($t->checked_in_at) {
            $this->dispatch('toast', level: 'warning', title: 'Already checked in', message: $t->attendee_name.' · '.$t->checked_in_at->diffForHumans());
        } else {
            $t->update(['checked_in_at' => now()]);
            $this->dispatch('toast', level: 'success', title: 'Checked in', message: $t->attendee_name.' ('.$code.')');
        }
        $this->checkinCode = '';
    }

    public function exportCsv(): StreamedResponse
    {
        $event = $this->event();
        $rows = Ticket::where('event_id', $event->id)->with(['order', 'ticketType'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'refunded', 'cancelled', 'pending']))
            ->orderBy('attendee_name')->get();
        $tz = $event->safeTimezone();

        return response()->streamDownload(function () use ($rows, $tz) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Attendee', 'Ticket type', 'Order', 'Order status', 'Buyer', 'Buyer email', 'Buyer phone', 'Paid', 'Checked in']);
            foreach ($rows as $t) {
                fputcsv($out, array_map([self::class, 'csvSafe'], [
                    $t->code, $t->attendee_name, $t->ticketType?->name, $t->order?->reference, $t->order?->status,
                    $t->order?->buyer_name, $t->order?->buyer_email, $t->order?->buyer_phone,
                    $t->order?->formattedTotal(),
                    $t->checked_in_at?->setTimezone($tz)->format('Y-m-d H:i') ?? '',
                ]));
            }
            fclose($out);
        }, 'attendees-'.$event->slug.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Neutralise spreadsheet formula injection. */
    public static function csvSafe(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }

    // ── Email attendees ─────────────────────────────────────────────────

    public function sendMessage(): void
    {
        $this->authorizeManage();
        $event = $this->event();
        $this->validate(['mailSubject' => 'required|string|max:150', 'mailBody' => 'required|string|max:5000'],
            [], ['mailSubject' => 'subject', 'mailBody' => 'message']);

        $orders = TicketOrder::where('event_id', $event->id)->where('status', 'paid')->get()->unique(fn ($o) => mb_strtolower($o->buyer_email));
        foreach ($orders as $o) {
            Mail::to($o->buyer_email, $o->buyer_name)->queue(new AttendeeMessage($event, $this->site, $this->mailSubject, $this->mailBody, $o->buyer_name));
        }
        $this->reset(['composing', 'mailSubject', 'mailBody']);
        $this->dispatch('toast', level: 'success', title: 'Message sent', message: $orders->count().' '.($orders->count() === 1 ? 'recipient' : 'recipients').'.');
    }

    // ── Orders ──────────────────────────────────────────────────────────

    public function refundOrder(string $orderId): void
    {
        $this->authorizeManage();
        $order = TicketOrder::where('site_id', $this->site->id)->where('event_id', $this->event()->id)->findOrFail($orderId);
        try {
            app(EventTickets::class)->refund($this->site, $order);
            $this->dispatch('toast', level: 'success', title: $order->isFree() ? 'Order cancelled' : 'Refunded', message: $order->reference.' · '.$order->formattedTotal());
        } catch (HttpException $e) {
            $this->dispatch('toast', level: 'error', title: 'Can’t refund', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', level: 'error', title: 'Refund failed', message: 'The payment provider rejected the refund — try again or refund it in Stripe.');
        }
    }

    public function resendTickets(string $orderId): void
    {
        $this->authorizeManage();
        $order = TicketOrder::where('site_id', $this->site->id)->where('event_id', $this->event()->id)->where('status', 'paid')->findOrFail($orderId);
        app(EventTickets::class)->sendTickets($this->site, $order);
        $this->dispatch('toast', level: 'success', title: 'Tickets re-sent', message: 'to '.$order->buyer_email);
    }

    // ── Render ──────────────────────────────────────────────────────────

    private function defaultTimezone(): string
    {
        $last = Event::where('site_id', $this->site->id)->latest()->value('timezone');

        return $last ?: 'Europe/London';
    }

    public function render()
    {
        $siteId = $this->site->id;
        $currency = EventTickets::currency($this->site);
        $all = Event::where('site_id', $siteId)->withCount('ticketTypes')->get();
        $ids = $all->pluck('id')->all();
        $counts = EventTickets::countsFor($ids);
        $revenue = $ids ? TicketOrder::whereIn('event_id', $ids)->where('status', 'paid')
            ->selectRaw('event_id, SUM(total_cents) as cents')->groupBy('event_id')->pluck('cents', 'event_id')->all() : [];
        $paidTypeEvents = $ids ? TicketType::whereIn('event_id', $ids)->where('price_cents', '>', 0)->distinct()->pluck('event_id')->flip()->all() : [];

        $all->each(function (Event $e) use ($counts, $revenue, $paidTypeEvents) {
            $c = $counts[$e->id] ?? ['paid' => 0, 'pending' => 0, 'checked_in' => 0];
            $e->sold = $c['paid'];
            $e->held = $c['pending'];
            $e->checked_in = $c['checked_in'];
            $e->revenue_cents = (int) ($revenue[$e->id] ?? 0);
            $e->has_paid_types = isset($paidTypeEvents[$e->id]);
            $e->fill_pct = $e->capacity ? min(100, (int) round(($e->sold + $e->held) / $e->capacity * 100)) : null;
            $e->upcoming = ! $e->isPast();
        });

        $needle = mb_strtolower(trim($this->search));
        $events = $all
            ->when($needle !== '', fn ($c) => $c->filter(fn ($e) => str_contains(mb_strtolower($e->title.' '.$e->venue_name.' '.$e->venue_address.' '.$e->summary), $needle)))
            ->filter(fn ($e) => match ($this->filter) {
                'past' => $e->status !== 'cancelled' && $e->status !== 'draft' && ! $e->upcoming,
                'drafts' => $e->status === 'draft',
                'cancelled' => $e->status === 'cancelled',
                default => $e->status === 'published' && $e->upcoming,
            })
            ->sortBy(fn ($e) => match ($this->sort) {
                'title' => mb_strtolower($e->title),
                'sales' => -$e->sold,
                default => $this->filter === 'past' ? -$e->starts_at->getTimestamp() : $e->starts_at->getTimestamp(),
            })->values();

        $monthStart = now()->startOfMonth();
        $paymentsReady = app(EventTickets::class)->paymentsReady($this->site);
        $upcoming = $all->where('status', 'published')->where('upcoming', true);
        $stats = [
            'upcoming' => $upcoming->count(),
            'nextEvent' => $upcoming->sortBy(fn ($e) => $e->starts_at->getTimestamp())->first(),
            'soldMonth' => Ticket::query()->join('event_ticket_orders as o', 'o.id', '=', 'event_tickets.order_id')
                ->where('o.site_id', $siteId)->where('o.status', 'paid')->where('o.paid_at', '>=', $monthStart)->count(),
            'revenueMonth' => (int) TicketOrder::where('site_id', $siteId)->where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('total_cents'),
            'checkinsToday' => Ticket::query()->join('event_ticket_orders as o', 'o.id', '=', 'event_tickets.order_id')
                ->where('o.site_id', $siteId)->where('event_tickets.checked_in_at', '>=', now()->startOfDay())->count(),
            'nearCapacity' => $upcoming->filter(fn ($e) => $e->fill_pct !== null && $e->fill_pct >= 80)->values(),
            'refundsMonth' => TicketOrder::where('site_id', $siteId)->where('status', 'refunded')->where('refunded_at', '>=', $monthStart)->count(),
            'pending' => TicketOrder::where('site_id', $siteId)->where('status', 'pending')->count(),
            'drafts' => $all->where('status', 'draft')->values(),
            'today' => $all->where('status', 'published')->filter(fn ($e) => $e->localStart()->isToday() || $e->starts_at->isToday())->values(),
            'paymentsMissing' => ! $paymentsReady && $all->contains(fn ($e) => $e->has_paid_types && $e->status !== 'cancelled'),
            'salesByEvent' => $all->filter(fn ($e) => $e->sold > 0 || $e->upcoming)->where('status', '!=', 'cancelled')
                ->sortByDesc('sold')->take(6)->values(),
        ];
        $filterCounts = [
            'upcoming' => $upcoming->count(),
            'past' => $all->filter(fn ($e) => ! in_array($e->status, ['draft', 'cancelled'], true) && ! $e->upcoming)->count(),
            'drafts' => $all->where('status', 'draft')->count(),
            'cancelled' => $all->where('status', 'cancelled')->count(),
        ];

        // ── The open event's panel ──
        $open = $this->eventId !== '' ? $all->firstWhere('id', $this->eventId) : null;
        $types = collect();
        $avail = null;
        $attendees = collect();
        $orders = collect();
        if ($open) {
            $types = $open->ticketTypes()->get();
            $avail = EventTickets::availability($open, $types);
            $q = mb_strtolower(trim($this->attendeeSearch));
            $attendees = Ticket::where('event_id', $open->id)
                ->whereHas('order', fn ($o) => $o->where('status', 'paid'))
                ->with(['order', 'ticketType'])
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('attendee_name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', '%'.strtoupper($q).'%')
                    ->orWhereHas('order', fn ($o) => $o->where('buyer_email', 'like', "%{$q}%")->orWhere('buyer_name', 'like', "%{$q}%"))))
                ->orderBy('attendee_name')->limit(500)->get();
            $orders = TicketOrder::where('event_id', $open->id)->latest()->limit(200)->get();
        }

        return view('livewire.events-page', [
            'events' => $events,
            'all' => $all,
            'stats' => $stats,
            'filterCounts' => $filterCounts,
            'currency' => $currency,
            'money' => fn (int $cents) => Money::format($cents, $currency),
            'paymentsReady' => $paymentsReady,
            'canManage' => $this->canManage(),
            'open' => $open,
            'types' => $types,
            'avail' => $avail,
            'attendees' => $attendees,
            'orders' => $orders,
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
