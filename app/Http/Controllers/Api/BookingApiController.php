<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmed;
use App\Mail\NewBookingNotification;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Service;
use App\Models\Site;
use App\Services\BookingService;
use App\Services\Stripe\StripeGateway;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Public booking API — powers the in-site BookingWidget AND any external
 * client. One engine, three archetypes (service.kind):
 *
 *   slot — GET availability?service&date            → free start times
 *   stay — GET availability?service&check_in&check_out[&units]  → units/total
 *          GET availability?service&month=Y-m       → per-day units calendar
 *   trip — GET availability?service[&date&origin&destination]   → departures
 *
 * POST /booking creates the booking (kind-dispatched payload). Paid services
 * respond with a Stripe checkout_url; the webhook confirms on payment.
 */
class BookingApiController extends Controller
{
    private const WEEKDAY_INDEX = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

    public function __construct(
        private BookingService $booking,
        private StripeGateway $stripe,
    ) {}

    private function site(string $siteName): Site
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        abort_unless($site->hasFeature('bookings'), 404);

        return $site;
    }

    private function service(Site $site, ?string $slug): ?Service
    {
        return $site->services()->where('slug', $slug)->where('is_active', true)->first();
    }

    /** Services (with kind + public params) so a client can render any flow. */
    public function config(string $siteName): JsonResponse
    {
        $site = $this->site($siteName);
        $s = $this->booking->settings($site);

        return response()->json([
            'services' => $site->services()->where('is_active', true)->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Service $svc) => [
                    'slug' => $svc->slug,
                    'name' => $svc->name,
                    'kind' => $svc->kind,
                    'description' => $svc->description,
                    'duration' => $svc->duration_min,
                    'price' => $svc->formattedPrice(),
                    'price_cents' => $svc->price_cents,
                    'currency' => $svc->currency,
                    'requires_payment' => $svc->requires_payment,
                    'capacity' => $svc->capacity,
                    'config' => (object) ($svc->config ?? []),
                    // Named resources (staff / rooms / vehicles) the customer can pick.
                    'resources' => $svc->activeResources()->get()
                        ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'price_cents' => $r->price_cents])->values(),
                    'resource_noun' => $svc->resourceNoun(),
                    // Owner-defined extra booking-form fields (beyond name/email/phone/message).
                    'form_fields' => $svc->formFields(),
                    // Custom type label/icon + deposit terms.
                    'type' => $svc->typeLabel(),
                    'icon' => $svc->typeIcon(),
                    'deposit_cents' => $svc->hasDeposit() ? ['fixed' => $svc->deposit_cents, 'pct' => $svc->deposit_pct] : null,
                ])->values(),
            // Site-wide slot defaults (kept for slot-widget back-compat).
            'availability' => [
                'weekdays' => array_values(array_map(fn ($d) => self::WEEKDAY_INDEX[$d] ?? null, $s['days'])),
                'horizonDays' => $s['horizon'],
                'slotMinutes' => $s['slot'],
            ],
        ]);
    }

    /** Kind-dispatched availability query. */
    public function availability(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $svc = $this->service($site, $request->query('service'));
        if (! $svc) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        return match ($svc->kind) {
            'stay' => $this->stayAvailability($svc, $request),
            'trip' => response()->json([
                'departures' => $this->booking->tripDepartures(
                    $svc,
                    $request->query('date') ? Carbon::parse($request->query('date')) : null,
                    $request->query('origin'),
                    $request->query('destination'),
                ),
            ]),
            default => response()->json([
                'slots' => $request->query('date')
                    ? $this->booking->slots->slotsFor(
                        $site,
                        $svc,
                        Carbon::parse($request->query('date')),
                        $request->query('resource') ? $svc->activeResources()->whereKey((int) $request->query('resource'))->first() : null,
                    )
                    : [],
                'openDates' => $this->booking->upcomingOpenDates($site, 14, $svc),
            ]),
        };
    }

    private function stayAvailability(Service $svc, Request $request): JsonResponse
    {
        // Month calendar for the range picker.
        if ($request->query('month')) {
            return response()->json([
                'days' => $this->booking->stays->calendar($svc, Carbon::parse($request->query('month').'-01')),
            ]);
        }

        $request->validate(['check_in' => ['required', 'date'], 'check_out' => ['required', 'date']]);
        $in = Carbon::parse($request->query('check_in'))->startOfDay();
        $out = Carbon::parse($request->query('check_out'))->startOfDay();
        $units = max(1, (int) $request->query('units', 1));

        if ($err = $this->booking->stays->validRange($svc, $in, $out, (int) $request->query('guests', 1))) {
            return response()->json(['available' => false, 'message' => $err], 422);
        }

        $nights = $this->booking->stays->nights($in, $out);

        // A SPECIFIC named room/house requested → answer for that room only.
        if ($request->query('resource') && $svc->usesResources()) {
            $room = $svc->activeResources()->whereKey((int) $request->query('resource'))->first();
            $free = $room && $this->booking->stays->resourceFree($room, $in, $out);

            return response()->json([
                'available' => $free,
                'units_left' => $free ? 1 : 0,
                'nights' => $nights,
                'total_cents' => $this->booking->prices->stayTotal($svc, $room, $in, $out, 1),
                'currency' => $svc->currency,
                'resource' => $room?->name,
            ]);
        }

        $left = $this->booking->stayAvailability($svc, $in, $out);

        return response()->json([
            'available' => $left >= $units,
            'units_left' => $left,
            'nights' => $nights,
            'total_cents' => $this->booking->prices->stayTotal($svc, null, $in, $out, $units),
            'currency' => $svc->currency,
        ]);
    }

    /** Create a booking of any kind. */
    public function store(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $svc = $this->service($site, (string) $request->input('service'));
        if (! $svc) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Where the CLIENT site wants the customer back after Stripe checkout
            // (its own appointment/booking page). Host-checked before use.
            'return_url' => ['nullable', 'url', 'max:500'],
        ], match ($svc->kind) {
            'stay' => [
                'check_in' => ['required', 'date'],
                'check_out' => ['required', 'date'],
                'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
                'units' => ['nullable', 'integer', 'min:1', 'max:50'],
                'resource_id' => ['nullable', 'string'],
            ],
            'trip' => [
                'departure_id' => ['required', 'string'],
                'qty' => ['required', 'integer', 'min:1', 'max:50'],
            ],
            default => [
                'start' => ['required', 'date'],
                'resource_id' => ['nullable', 'string'],
            ],
        }));

        // Owner-defined custom fields: validate required ones, keep only known keys.
        $custom = [];
        foreach ($svc->formFields() as $ff) {
            $value = trim((string) $request->input('fields.'.$ff['key'], ''));
            if ($value === '' && ! empty($ff['required'])) {
                return response()->json(['message' => $ff['label'].' is required.', 'errors' => ['fields.'.$ff['key'] => [$ff['label'].' is required.']]], 422);
            }
            if ($value !== '') {
                $custom[$ff['key']] = mb_substr($value, 0, 1000);
            }
        }

        $paid = $svc->requires_payment && $site->stripeReady();
        // Auto-confirm: unpaid bookings skip the owner's manual confirmation.
        // Paid ones confirm via the payment webhook regardless.
        $status = $paid ? 'awaiting_payment'
            : ($svc->configValue('auto_confirm') ? 'confirmed' : 'pending');
        $result = $this->booking->book($site, $svc, $data, $status);

        if (is_string($result)) {
            // Unavailable / policy violation — 409 for race losses, 422 otherwise.
            $race = str_contains($result, 'just taken') || str_contains($result, 'Not enough');

            return response()->json(['message' => $result], $race ? 409 : 422);
        }

        if ($custom) {
            $result->update(['params' => ($result->params ?? []) + ['fields' => $custom]]);
        }

        // CRM funnel: every booking customer becomes (or updates) a Contact.
        try {
            Contact::capture($site, $result->customer_name, $result->customer_email, $result->customer_phone,
                "Booked {$svc->name} ({$result->reference}) — ".($result->starts_at?->format('D, M j · g:i A') ?? 'date tbc').'.');
        } catch (\Throwable $e) {
            report($e);
        }

        // A zero-total booking has nothing to charge — treat as free even
        // when the service requires payment (price/departure not set yet).
        if ($paid && $result->total_cents > 0) {
            if ($ret = $this->safeReturnUrl($site, $request, $data['return_url'] ?? null)) {
                $result->update(['params' => ($result->params ?? []) + ['return_url' => $ret]]);
            }

            return $this->beginCheckout($site, $svc, $result);
        }
        if ($result->status === 'awaiting_payment') {
            $result->update(['status' => 'pending']);
        }

        $this->notify($result, $site);

        return response()->json([
            'ok' => true,
            'message' => $result->status === 'confirmed'
                ? 'Booking confirmed — see you then!'
                : 'Booking received — you will get a confirmation shortly.',
            'reference' => $result->reference,
            'status' => $result->status,
            'service' => $svc->name,
            'resource' => $result->params['resource'] ?? null,
            'total' => $result->formattedTotal(),
        ], 201);
    }

    /**
     * Paid service: hold created — hand the client a Stripe Checkout URL.
     * With a DEPOSIT configured, only the deposit is charged online; the
     * balance is tracked on the booking and due at arrival/appointment.
     */
    private function beginCheckout(Site $site, Service $svc, Booking $booking): JsonResponse
    {
        // Conditional deposit: short-notice bookings (inside the lead window)
        // pay in FULL; only bookings far enough ahead may pay just the deposit.
        $leadHours = (int) $svc->configValue('deposit_min_lead_hours', 0);
        $farEnough = $leadHours === 0
            || ($booking->starts_at && now()->diffInHours($booking->starts_at, false) >= $leadHours);

        $charge = $farEnough ? $svc->depositCentsFor($booking->total_cents) : $booking->total_cents;
        $partial = $charge < $booking->total_cents;
        $label = $partial ? 'deposit' : 'booking';

        try {
            $session = $this->stripe->createCheckoutSession(
                $site,
                [[
                    'price_data' => [
                        'currency' => $booking->currency,
                        'product_data' => ['name' => "{$svc->name} — {$label} {$booking->reference}"],
                        'unit_amount' => max(1, $charge),
                    ],
                    'quantity' => 1,
                ]],
                // Success always lands on the CMS route first: it VERIFIES the payment
                // with Stripe, confirms + emails, then bounces to the client's page.
                url("preview/{$site->name}/book/success").'?ref='.$booking->reference,
                // Cancel goes straight back to the client's booking page when known.
                ($ret = $booking->params['return_url'] ?? null)
                    ? $ret.(str_contains($ret, '?') ? '&' : '?').'booking_cancelled=1'
                    : url("preview/{$site->name}/book"),
                ['booking_id' => $booking->id, 'site_id' => $site->id, 'charge_cents' => $charge],
                $booking->customer_email,
            );
        } catch (\Throwable $e) {
            report($e);
            $booking->markCancelled(); // free the hold

            return response()->json(['message' => 'Payment could not be started. Please try again.'], 502);
        }

        $booking->update(['stripe_session_id' => $session->id]);

        $fmt = fn (int $c) => Money::format($c, $booking->currency);

        return response()->json([
            'ok' => true,
            'message' => $partial
                ? 'Pay the '.$fmt($charge).' deposit to confirm — '.$fmt($booking->total_cents - $charge).' due later.'
                : 'Complete payment to confirm your booking.',
            'reference' => $booking->reference,
            'status' => $booking->status,
            'deposit_cents' => $partial ? $charge : null,
            'balance_cents' => $partial ? $booking->total_cents - $charge : 0,
            'checkout_url' => $session->url,
        ], 201);
    }

    /** Status lookup by reference (no PII beyond what the ref-holder sent). */
    public function show(string $siteName, string $reference): JsonResponse
    {
        $site = $this->site($siteName);
        $b = $site->bookings()->with('service')->where('reference', strtoupper($reference))->first();
        if (! $b) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        return response()->json([
            'reference' => $b->reference,
            'status' => $b->status,
            'kind' => $b->service?->kind,
            'service' => $b->service?->name,
            'starts_at' => $b->starts_at?->toIso8601String(),
            'ends_at' => $b->ends_at?->toIso8601String(),
            'params' => (object) ($b->params ?? []),
            'quantity' => $b->quantity,
            'total_cents' => $b->total_cents,
            'currency' => $b->currency,
        ]);
    }

    /**
     * A client-supplied checkout return URL, accepted only for http(s) URLs whose
     * host is this app's, the current request's, or the site's own domain — so
     * checkout can't be used as an open redirect to arbitrary hosts.
     */
    private function safeReturnUrl(Site $site, Request $request, ?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $allowed = array_filter([
            strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)),
            strtolower($request->getHost()),
            strtolower((string) parse_url((string) $site->domain, PHP_URL_HOST) ?: (string) $site->domain),
        ]);

        return in_array($host, $allowed, true) ? $url : null;
    }

    private function notify(Booking $booking, Site $site): void
    {
        try {
            Mail::to($booking->customer_email)->send(new BookingConfirmed($booking->fresh('service'), $site));
        } catch (\Throwable $e) {
            report($e);
        }
        // The OWNER hears about every new booking too.
        try {
            if ($owner = $site->user?->email) {
                Mail::to($owner)->send(new NewBookingNotification($booking->fresh('service'), $site));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
