<?php

namespace App\Contracts;

/**
 * Provider-agnostic domain reseller seam (Phase 1: availability + cost price).
 *
 * Deliberately separate from App\Services\Domains\Registrar (the purchase
 * flow's register/DNS seam) — later phases can have one driver implement
 * both. Keep this interface free of any provider-specific shapes so the
 * reseller can be swapped without touching callers.
 */
interface DomainRegistrar
{
    /**
     * Availability + our COST price for each domain.
     *
     * @param  list<string>  $domains  e.g. ['mysalon.co.uk', 'mysalon.com'] —
     *                                 scheme/www noise is tolerated and stripped.
     * @return list<array{domain: string, available: bool, status: string, price: ?float, currency: ?string}>
     *
     * @throws \InvalidArgumentException on un-parseable domain input
     * @throws \RuntimeException on API/auth failure
     */
    public function checkAvailability(array $domains): array;
}
