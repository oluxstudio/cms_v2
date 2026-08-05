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
 * NOTE: tokens are currently stored in PLAIN TEXT (the settings page shows
 * them back to the user). Before production, store hash('sha256', $token)
 * and look up by hash instead.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        $token = $bearer ? ApiToken::where('token', $bearer)->first() : null;
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated — a valid API token is required.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_token_user', $token->user);

        return $next($request);
    }
}
