<?php

namespace App\Http\Controllers\Api;

use App\Contracts\DomainRegistrar;
use App\Http\Controllers\Controller;
use App\Http\Requests\DomainCheckRequest;
use App\Services\Domains\DomainPurchase;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * "Find a domain" availability lookup for the dashboard. A bare label
 * (no extension) fans out to the default UK-first TLD set.
 */
class DomainCheckController extends Controller
{
    /** @var list<string> Extensions tried when the query has no dot. */
    private const DEFAULT_TLDS = ['co.uk', 'uk', 'com'];

    public function __invoke(DomainCheckRequest $request, DomainRegistrar $registrar): JsonResponse
    {
        $query = strtolower(trim($request->validated()['domain']));

        $domains = str_contains($query, '.')
            ? [$query]
            : array_map(fn (string $tld) => "{$query}.{$tld}", self::DEFAULT_TLDS);

        try {
            $results = $registrar->checkAvailability($domains);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Retail pricing only (config/domains.php tlds map) — the reseller COST
        // price from the registrar is never exposed to tenants.
        $purchase = app(DomainPurchase::class);

        return response()->json([
            'results' => array_map(fn (array $r) => [
                'domain' => $r['domain'],
                'available' => $r['available'],
                'status' => $r['status'],
                'price_cents' => $purchase->priceFor($r['domain']),
            ], $results),
        ]);
    }
}
