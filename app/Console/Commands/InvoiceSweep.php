<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Site;
use App\Services\OpsAlerts;
use Illuminate\Console\Command;

/**
 * Hourly invoice automation: refresh overdue statuses, generate + send
 * recurring invoices, send due/overdue reminders. Each site with the
 * invoices feature gets a sweep.
 */
class InvoiceSweep extends Command
{
    protected $signature = 'invoices:sweep';

    protected $description = 'Refresh overdue invoices, generate recurring ones, send payment reminders';

    public function handle(): int
    {
        $swept = 0;
        foreach (Site::all() as $site) {
            if ($site->hasFeature('invoices')) {
                Invoice::sweep($site);
                $swept++;
            }
            // Generated action items (overdue invoices, stale quotes, pending
            // bookings) — feature-gated internally, cheap for quiet sites.
            OpsAlerts::sweep($site);
        }
        $this->info("Swept invoices for {$swept} site(s).");

        return self::SUCCESS;
    }
}
