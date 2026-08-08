<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Silent spam trap for visitor form endpoints. Client sites render a hidden
 * `_hp` input that humans never fill; bots that auto-complete every field
 * give themselves away. We answer with a normal-looking success and persist
 * NOTHING — no error for the bot to learn from.
 */
class HoneypotGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (filled($request->input('_hp'))) {
            Log::info('Honeypot tripped — submission dropped.', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => true, 'message' => 'Thank you!'], 201);
        }

        return $next($request);
    }
}
