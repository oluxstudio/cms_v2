<?php

namespace App\Console\Commands;

use App\Mail\BookingReminder;
use App\Mail\RebookPrompt;
use App\Mail\ReviewRequest;
use App\Models\Booking;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * The three booking automations, one command with a {kind} argument so the
 * scheduler stays simple and channel logic lives in one place:
 *
 *   bookings:automate reminders  — confirmed bookings starting 20–28h from now
 *   bookings:automate reviews    — bookings that ended 2–26h ago (needs the
 *                                  site's google_review_url attribute)
 *   bookings:automate rebook     — customers whose LAST visit ended N weeks
 *                                  ago (per-site `rebook_weeks`, 0 = off) and
 *                                  who have no future booking
 *
 * Each send stamps the booking (reminder_sent_at / review_requested_at /
 * rebook_prompted_at) so nothing is ever sent twice. Email-only for now —
 * SMS/WhatsApp slots in here when a provider is wired.
 */
class BookingAutomations extends Command
{
    protected $signature = 'bookings:automate {kind : reminders|reviews|rebook}';

    protected $description = 'Send booking reminder / review-request / rebooking emails';

    public function handle(): int
    {
        $kind = $this->argument('kind');
        $sent = 0;

        foreach (Site::all() as $site) {
            if (! $site->hasFeature('bookings')) {
                continue;
            }
            $config = $site->feature('bookings');

            $sent += match ($kind) {
                'reminders' => $this->reminders($site, $config),
                'reviews' => $this->reviews($site, $config),
                'rebook' => $this->rebook($site, $config),
                default => 0,
            };
        }

        $this->info("bookings:automate {$kind} — {$sent} email(s) sent.");

        return self::SUCCESS;
    }

    private function reminders(Site $site, array $config): int
    {
        if (! ($config['remind_visitor'] ?? true)) {
            return 0;
        }

        $due = Booking::where('site_id', $site->id)
            ->where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('customer_email')
            ->whereBetween('starts_at', [now()->addHours(20), now()->addHours(28)])
            ->get();

        foreach ($due as $booking) {
            Mail::to($booking->customer_email)->queue(new BookingReminder($booking, $site));
            $booking->forceFill(['reminder_sent_at' => now()])->save();
        }

        return $due->count();
    }

    private function reviews(Site $site, array $config): int
    {
        $reviewUrl = (string) $site->getAttr('google_review_url', '');
        if (! ($config['review_requests'] ?? true) || $reviewUrl === '') {
            return 0;
        }

        $due = Booking::where('site_id', $site->id)
            ->where('status', 'confirmed')
            ->whereNull('review_requested_at')
            ->whereNotNull('customer_email')
            ->whereBetween('ends_at', [now()->subHours(26), now()->subHours(2)])
            ->get();

        foreach ($due as $booking) {
            Mail::to($booking->customer_email)->queue(new ReviewRequest($booking, $site, $reviewUrl));
            $booking->forceFill(['review_requested_at' => now()])->save();
        }

        return $due->count();
    }

    private function rebook(Site $site, array $config): int
    {
        $weeks = (int) ($config['rebook_weeks'] ?? 5);
        if ($weeks < 1) {
            return 0;
        }

        // Bookings that ended N weeks ago (±half a day around the daily run).
        $window = [now()->subWeeks($weeks)->subHours(12), now()->subWeeks($weeks)->addHours(12)];
        $candidates = Booking::where('site_id', $site->id)
            ->where('status', 'confirmed')
            ->whereNull('rebook_prompted_at')
            ->whereNotNull('customer_email')
            ->whereBetween('ends_at', $window)
            ->get();

        $sent = 0;
        foreach ($candidates as $booking) {
            $sameCustomer = Booking::where('site_id', $site->id)
                ->where('customer_email', $booking->customer_email)
                ->whereIn('status', ['confirmed', 'pending', 'awaiting_payment']);
            // Skip when they came back since, or already have a future booking.
            if ((clone $sameCustomer)->where('starts_at', '>', $booking->ends_at)->exists()) {
                continue;
            }

            $bookingUrl = $this->bookingUrl($site);
            Mail::to($booking->customer_email)->queue(new RebookPrompt($booking, $site, $weeks, $bookingUrl));
            $booking->forceFill(['rebook_prompted_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }

    /** Public booking link: the client site when connected, else nothing. */
    private function bookingUrl(Site $site): ?string
    {
        $client = (string) $site->getAttr('client_url', '');

        return $client !== '' ? rtrim($client, '/').'/appointment' : null;
    }
}
