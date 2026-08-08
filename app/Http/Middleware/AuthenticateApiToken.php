<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for third-party API access, backed by the api_tokens
 * table (created/managed on the user settings page).
 *
 * Tokens are stored HASHED (sha256) — the bearer is hashed before lookup,
 * so the database never holds a usable credential. Expired tokens are
 * rejected here; site/ability scoping is enforced by ResolvesApiSite in
 * the controllers.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findByBearer($request->bearerToken());

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated — a valid API token is required.'], 401);
        }
        if ($token->isExpired()) {
            return response()->json(['message' => 'This API token has expired — generate a new one on the settings page.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_token', $token);
        $request->attributes->set('api_token_user', $token->user);

        return $next($request);
    }
}
