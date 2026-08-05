<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Site;
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
            if (! $site->hasFeature('invoices')) {
                continue;
            }
            Invoice::sweep($site);
            $swept++;
        }
        $this->info("Swept invoices for {$swept} site(s).");

        return self::SUCCESS;
    }
}
