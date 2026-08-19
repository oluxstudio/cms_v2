<?php

namespace App\Services\Booking;

use App\Mail\BookingConfirmed;
use App\Mail\NewBookingNotification;
use App\Mail\SubmissionReceipt;
use App\Models\Booking;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use Illuminate\Support\Facades\Mail;

/**
 * Everything that happens when a booking lands:
 *   1. the booking is recorded as a FORM RESPONSE on the site's "booking"
 *      form (auto-created), so it shows up in the CRM with the rest;
 *   2. the CUSTOMER gets an email (receipt while pending, confirmation once
 *      confirmed/paid);
 *   3. the ADMIN gets an email — the recipient is configurable on the booking
 *      form's delivery settings (admin_address), defaulting to the account
 *      owner's email. notify_visitor / notify_admin toggles are honoured.
 * Every step is best-effort: a mail failure must never break the booking.
 */
class BookingNotifications
{
    public function send(Booking $booking, Site $site, bool $confirmed = false): void
    {
        $booking->loadMissing('service');
        $form = $this->formFor($booking, $site);
        $summary = $this->summary($booking);

        // 1. CRM record.
        try {
            FormResponse::create([
                'form_id' => $form->id,
                'fields' => $summary,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $email = $form->deliveryConfig()['channels']['email'] ?? [];
        $enabled = (bool) ($email['enabled'] ?? true);

        // 2. Customer email.
        if ($enabled && ($email['notify_visitor'] ?? true) && $booking->customer_email) {
            try {
                Mail::to($booking->customer_email)->send($confirmed
                    ? new BookingConfirmed($booking->fresh('service'), $site)
                    : new SubmissionReceipt(
                        $site,
                        'booking'.($booking->service ? ' for '.$booking->service->name : ''),
                        $booking->customer_name,
                        array_filter([
                            'reference' => $booking->reference,
                            'service' => $booking->service?->name,
                            'when' => $booking->starts_at?->format('D j M Y, g:i A'),
                        ]),
                        $form,
                    ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // 3. Admin email — configurable recipient, account owner by default.
        $admin = trim((string) ($email['admin_address'] ?? '')) ?: $site->user?->email;
        if ($enabled && ($email['notify_admin'] ?? true) && $admin) {
            try {
                Mail::to($admin)->send(new NewBookingNotification($booking->fresh('service'), $site));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * The form this booking's response belongs to: the form the CLIENT's
     * booking UI was built from (`form` in the booking payload, e.g. the
     * "appointment" form whose schema rendered the fields) — falling back to
     * the site's auto-created "booking" form.
     */
    private function formFor(Booking $booking, Site $site): Form
    {
        $requested = trim((string) ($booking->params['form'] ?? ''));
        if ($requested !== '') {
            $form = Form::where('site_id', $site->id)->where('name', $requested)
                ->where('is_active', true)->first();
            if ($form) {
                return $form;
            }
        }

        return $this->bookingForm($site);
    }

    /** The site's fallback booking form — auto-created. */
    private function bookingForm(Site $site): Form
    {
        return Form::firstOrCreate(
            ['site_id' => $site->id, 'name' => 'booking'],
            [
                'title' => 'Bookings',
                'description' => 'Booking submissions land here. Set the admin notification email in this form\'s delivery settings.',
                'is_active' => true,
                'fields' => collect(['name' => 'text', 'email' => 'email', 'phone' => 'tel', 'service' => 'text',
                    'when' => 'text', 'reference' => 'text', 'status' => 'text', 'notes' => 'textarea'])
                    ->map(fn ($type, $key) => ['key' => $key, 'name' => $key, 'label' => ucfirst($key), 'type' => $type, 'required' => false])
                    ->values()->all(),
            ],
        );
    }

    /** @return array<string,string> */
    private function summary(Booking $booking): array
    {
        // Owner-defined custom booking fields ride along in params['fields'].
        $custom = array_filter((array) ($booking->params['fields'] ?? []), 'is_scalar');

        return $custom + array_filter([
            'name' => $booking->customer_name,
            'email' => $booking->customer_email,
            'phone' => $booking->customer_phone,
            'service' => $booking->service?->name,
            'when' => $booking->starts_at?->format('D j M Y, g:i A'),
            'reference' => $booking->reference,
            'status' => $booking->status,
            'notes' => $booking->notes,
        ]);
    }
}
