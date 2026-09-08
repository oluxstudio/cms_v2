<?php

namespace App\Services\Blueprints;

/**
 * "Tradespeople" tenant (electricians, plumbers, builders…): a clean business
 * template, quoting + invoicing first, call-out slots bookable online, and a
 * quote-request form. Data lives in config/blueprints/trades.php.
 */
class TradesBlueprint extends Blueprint
{
    public function key(): string
    {
        return 'trades';
    }
}
