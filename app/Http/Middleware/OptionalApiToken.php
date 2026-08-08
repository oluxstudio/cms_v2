<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use GraphQL\Validator\Rules\DisableIntrospection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional Bearer auth for the GraphQL endpoint: a valid token widens what
 * queries may see (drafts, private collections) but its absence is fine —
 * public fields still resolve.
 *
 * Also gates INTROSPECTION: in production the schema is only introspectable
 * with a valid token (the public shape is documented on the api-docs page).
 */
class OptionalApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findByBearer($request->bearerToken());

        if ($token && ! $token->isExpired()) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
            $request->attributes->set('api_token', $token);
            $request->attributes->set('api_token_user', $token->user);
        }

        if (app()->isProduction() && ! $request->attributes->has('api_token')) {
            config(['lighthouse.security.disable_introspection' => DisableIntrospection::ENABLED]);
        }

        return $next($request);
    }
}
