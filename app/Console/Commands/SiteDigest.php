<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigest;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Site;
use App\Models\SiteActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * "Your week at {site}" — one Monday-morning email per site to its owner:
 * bookings held & coming up, money collected, new leads, overdue invoices and
 * the week's highlights. Sites with a quiet week (or the weekly_digest attr
 * switched off) are skipped.
 *
 *   php artisan site:digest [--dry-run]
 */
class SiteDigest extends Command
{
    protected $signature = 'site:digest {--dry-run : List what would be sent without mailing}';

    protected $description = 'Email each site owner a summary of the last 7 days';

    public function handle(): int
    {
        $sent = 0;
        $since = now()->subDays(7);

        foreach (Site::query()->with('user')->cursor() as $site) {
            if (! $site->user?->email || $site->getAttr('weekly_digest', '1') === '0') {
                continue;
            }

            $stats = $this->stats($site, $since);
            $quiet = $stats['bookings_held'] === 0 && $stats['bookings_upcoming'] === 0
                && $stats['revenue_cents'] === 0 && $stats['new_leads'] === 0 && $stats['overdue_count'] === 0;
            if ($quiet) {
                continue;
            }

            $this->line(" → {$site->name} ({$site->user->email}): "
                ."{$stats['bookings_held']} held / {$stats['bookings_upcoming']} upcoming, "
                .'£'.number_format($stats['revenue_cents'] / 100, 2).' collected, '
                ."{$stats['new_leads']} leads, {$stats['overdue_count']} overdue");
            if (! $this->option('dry-run')) {
                Mail::to($site->user->email)->send(new WeeklyDigest($site, $stats));
            }
            $sent++;
        }

        $this->info(($this->option('dry-run') ? 'Would send ' : 'Sent ').$sent.' digest(s).');

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function stats(Site $site, $since): array
    {
        $revenue = 0;
        $overdueCount = 0;
        $overdueCents = 0;
        if ($site->hasFeature('invoices')) {
            $revenue += (int) Invoice::where('site_id', $site->id)->where('status', 'paid')->where('paid_at', '>=', $since)->sum('total_cents');
            $overdue = Invoice::where('site_id', $site->id)->where('status', 'overdue')->get();
            $overdueCount = $overdue->count();
            $overdueCents = (int) $overdue->sum('total_cents');
        }
        if ($site->hasFeature('store')) {
            $revenue += (int) Order::where('site_id', $site->id)->whereIn('status', ['paid', 'fulfilled'])->where('created_at', '>=', $since)->sum('total_cents');
        }

        return [
            'since' => $since,
            'bookings_held' => $site->hasFeature('bookings')
                ? Booking::where('site_id', $site->id)->active()->whereBetween('starts_at', [$since, now()])->count() : 0,
            'bookings_upcoming' => $site->hasFeature('bookings')
                ? Booking::where('site_id', $site->id)->upcoming()->count() : 0,
            'revenue_cents' => $revenue,
            'new_leads' => $site->contacts()->where('created_at', '>=', $since)->count(),
            'overdue_count' => $overdueCount,
            'overdue_cents' => $overdueCents,
            'reviews_requested' => $site->hasFeature('bookings')
                ? Booking::where('site_id', $site->id)->where('review_requested_at', '>=', $since)->count() : 0,
            'highlights' => SiteActivityLog::where('site_id', $site->id)->where('created_at', '>=', $since)
                ->latest()->limit(5)->get()->map(fn ($l) => $l->title)->all(),
        ];
    }
}
