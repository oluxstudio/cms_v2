<?php

namespace App\Console\Commands;

use App\Models\DomainOrder;
use App\Services\TaskLogger;
use Illuminate\Console\Command;

/**
 * Daily sweep: alert site teams about domains expiring within 30 days
 * (warning) and 7 days (urgent). Deduped per domain + expiry, so re-runs
 * never pile up duplicates. Renewing from the Go-live page clears it by
 * moving the expiry forward.
 *
 *   php artisan domains:renewal-sweep
 */
class DomainRenewalSweep extends Command
{
    protected $signature = 'domains:renewal-sweep';

    protected $description = 'Raise alerts for registered domains that are about to expire';

    public function handle(TaskLogger $logger): int
    {
        $expiring = DomainOrder::with('site:id,name,domain')
            ->where('type', 'register')->where('status', 'registered')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->get()
            // Only the domain each site is actually using matters.
            ->filter(fn (DomainOrder $o) => $o->site && $o->site->domain === $o->domain);

        foreach ($expiring as $order) {
            $days = (int) now()->diffInDays($order->expires_at);
            $urgent = $days <= 7;

            $logger->alert(
                $order->site,
                "{$order->domain} expires ".($days === 0 ? 'today' : "in {$days} ".str('day')->plural($days)),
                type: 'domain',
                level: $urgent ? 'error' : 'warning',
                body: 'Renew it from the Go-live page to keep the site online — unrenewed domains drop off the internet at expiry.',
                link: url($order->site->name.'/publish'),
                dedupeKey: 'domain-renewal:'.$order->domain.':'.$order->expires_at->toDateString().':'.($urgent ? 'urgent' : 'notice'),
            );
        }

        $this->info('Checked '.$expiring->count().' expiring domain(s).');

        return self::SUCCESS;
    }
}
