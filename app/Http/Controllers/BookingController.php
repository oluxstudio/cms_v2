<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\Service;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\Booking\BookingNotifications;
use App\Services\BookingService;
use App\Services\Stripe\StripeGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Public blade booking flow — SLOT services only (the block-built renderer
 * widget is the primary UX and covers all kinds: slot, stay, trip).
 * Also hosts the Stripe webhook + checkout success page for ALL kinds.
 */
class BookingController extends Controller
{
    public function __construct(
        private BookingService $booking,
        private StripeGateway $stripe,
    ) {}

    private function site(string $siteName): Site
    {
        return Site::where('name', $siteName)->firstOrFail();
    }

    /** Public booking page — the embed renders ALL kinds via the API. */
    public function index(string $siteName)
    {
        $site = $this->site($siteName);
        abort_unless($site->hasFeature('bookings'), 404);

        return view('public.book.index', compact('site'));
    }

    /** Date + time-slot picker for a single slot service. */
    public function show(string $siteName, string $service, Request $request)
    {
        $site = $this->site($siteName);
        $svc = $site->services()->where('slug', $service)->where('is_active', true)->firstOrFail();
        abort_unless($svc->kind === 'slot', 404); // stay/trip book via the site widget

        $dates = $this->booking->upcomingOpenDates($site, 14, $svc);
        $selected = $request->query('date');
        if (! in_array($selected, $dates, true)) {
            $selected = $dates[0] ?? null;
        }
        $slots = $selected ? $this->booking->slotsFor($site, $svc, Carbon::parse($selected)) : [];

        return view('public.book.show', [
            'site' => $site,
            'service' => $svc,
            'dates' => $dates,
            'selected' => $selected,
            'slots' => $slots,
        ]);
    }

    /** Create the booking (slot only). */
    public function store(string $siteName, Request $request)
    {
        $site = $this->site($siteName);

        $data = $request->validate([
            'service' => ['required', 'string'],
            'start' => ['required', 'date'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $svc = $site->services()->where('slug', $data['service'])->where('is_active', true)->firstOrFail();
        abort_unless($svc->kind === 'slot', 404);

        $result = $this->booking->book($site, $svc, $data);

        if (is_string($result)) {
            return back()->withInput()->with('book_error', $result);
        }

        // CRM funnel: the customer becomes (or updates) a Contact.
        try {
            Contact::capture($site, $result->customer_name, $result->customer_email, $result->customer_phone,
                "Booked {$svc->name} ({$result->reference}).");
        } catch (\Throwable $e) {
            report($e);
        }

        // Form-response record + customer/admin emails (best-effort; the admin
        // recipient is configurable on the "booking" form's delivery settings).
        app(BookingNotifications::class)->send($result->fresh('service'), $site, confirmed: true);

        return redirect()->route('public.book.success', ['siteName' => $site->name, 'ref' => $result->reference]);
    }

    /** Success page — also the Stripe checkout return URL (?ref=REFERENCE). */
    public function success(string $siteName, Request $request)
    {
        $site = $this->site($siteName);
        $appt = $site->bookings()->with('service')
            ->where('reference', strtoupper((string) $request->query('ref')))
            ->first();

        // Belt-and-braces: confirm on return from Stripe even when no webhook is
        // configured (local dev, fresh sites). Payment is verified SERVER-SIDE
        // against the stored session — the query string alone confirms nothing.
        // The status guard keeps this idempotent if the webhook got there first.
        if ($appt && $appt->status === 'awaiting_payment' && $appt->stripe_session_id) {
            try {
                $session = $this->stripe->retrieveCheckoutSession($site, $appt->stripe_session_id);
                if ($session->payment_status === 'paid') {
                    $this->confirmPaid($appt, $site, (int) ($session->metadata->charge_cents ?? $appt->total_cents));
                    $appt->refresh();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // The client site told us where its booking page lives (host-checked at
        // store time): send the customer back there with the reference — its
        // booking widget shows the confirmation. Verification + emails above
        // have already run by this point.
        if ($appt && ($ret = $appt->params['return_url'] ?? null)) {
            return redirect()->away(
                $ret.(str_contains($ret, '?') ? '&' : '?').'booking_ref='.$appt->reference
            );
        }

        return view('public.book.success', compact('site', 'appt'));
    }

    /** Payment landed: confirm the booking and email BOTH the customer and the owner. */
    private function confirmPaid(Booking $booking, Site $site, int $paidCents): void
    {
        $booking->update(['paid_cents' => $paidCents]);
        $booking->markConfirmed();
        try {
            ActivityLogger::bookingEvent($booking->fresh('service'), 'confirmed');
        } catch (\Throwable $e) {
            report($e);
        }
        // Form-response record + customer confirmation + admin alert
        // (recipient configurable on the "booking" form's delivery settings).
        app(BookingNotifications::class)
            ->send($booking->fresh('service'), $site, confirmed: true);
    }

    /** Stripe webhook: payment confirms the booking; expiry frees the hold. */
    public function webhook(string $siteName, Request $request)
    {
        $site = $this->site($siteName);

        try {
            $event = $this->stripe->verifyWebhook($site, $request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if (in_array($event->type, ['checkout.session.completed', 'checkout.session.expired'], true)) {
            $session = $event->data->object;
            $booking = isset($session->metadata->booking_id)
                ? $site->bookings()->find($session->metadata->booking_id)
                : $site->bookings()->where('stripe_session_id', $session->id)->first();

            if ($booking && $booking->status === 'awaiting_payment') {
                if ($event->type === 'checkout.session.completed') {
                    // Record what was actually charged (deposit or full total),
                    // confirm, and email both the customer and the owner.
                    $this->confirmPaid($booking, $site, (int) ($session->metadata->charge_cents ?? $booking->total_cents));
                } else {
                    $booking->markCancelled(); // hold released
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
