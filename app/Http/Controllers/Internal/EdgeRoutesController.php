<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Traefik HTTP-provider feed: one TLS router per verified custom domain, so
 * the edge proxy routes it to the CMS and issues its Let's Encrypt cert.
 * Traefik polls this over the internal docker network; the shared token
 * keeps it from being read through the public edge.
 */
class EdgeRoutesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('domains.edge_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->header('X-Edge-Token')), 404);

        $base = strtolower((string) config('publishing.subdomain_base'));
        $routers = [];

        $domains = Site::whereNotNull('domain')->whereNotNull('domain_verified_at')
            ->pluck('domain')->map(fn ($d) => strtolower(trim((string) $d)))
            ->filter(fn ($d) => $d !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $d)
                && ($base === '' || ($d !== $base && ! str_ends_with($d, '.'.$base))))
            ->unique();

        foreach ($domains as $domain) {
            $hosts = [$domain];
            // www only once it resolves — a cert request for a name with no
            // DNS fails validation and burns Let's Encrypt's failure quota.
            if (self::resolves('www.'.$domain)) {
                $hosts[] = 'www.'.$domain;
            }
            foreach ($hosts as $host) {
                $routers['cd-'.substr(md5($host), 0, 12)] = [
                    'rule' => 'Host(`'.$host.'`)',
                    'entryPoints' => ['websecure'],
                    'service' => (string) config('domains.edge_service', 'cms@docker'),
                    'tls' => ['certResolver' => (string) config('domains.edge_cert_resolver', 'le')],
                    'priority' => 50,
                ];
            }
        }

        return response()->json($routers ? ['http' => ['routers' => $routers]] : new \stdClass);
    }

    private static function resolves(string $host): bool
    {
        return Cache::remember('edge-resolves:'.$host, now()->addMinutes(10),
            fn () => gethostbyname($host) !== $host);
    }
}
