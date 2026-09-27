<?php

namespace App\Console\Commands;

use App\Contracts\DomainRegistrar;
use Illuminate\Console\Command;
use Throwable;

/**
 * Check domain availability + our cost price against Openprovider.
 *
 *   php artisan openprovider:check mysalon.co.uk mysalon.com
 */
class OpenproviderCheck extends Command
{
    protected $signature = 'openprovider:check {domains* : Domains to check, e.g. mysalon.co.uk}';

    protected $description = 'Check domain availability and cost price via the Openprovider API';

    public function handle(DomainRegistrar $registrar): int
    {
        try {
            $results = $registrar->checkAvailability((array) $this->argument('domains'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Domain', 'Available', 'Status', 'Cost price'],
            array_map(fn (array $r) => [
                $r['domain'],
                $r['available'] ? '<info>yes</info>' : 'no',
                $r['status'],
                $r['price'] !== null ? number_format($r['price'], 2).' '.($r['currency'] ?? '') : '—',
            ], $results),
        );

        return self::SUCCESS;
    }
}
