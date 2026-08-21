<?php

namespace App\Http\Middleware;

use App\Services\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the platform /admin area: the user must be a super admin AND have
 * passed an authenticator-app (TOTP) check recently. Without enrollment or a
 * fresh check they are sent to the verify page — never straight in.
 * Usage: ->middleware('super')
 */
class EnsureSuperAdmin
{
    /** How long one successful code entry is trusted for. */
    public const REMEMBER_MINUTES = 12 * 60;

    public const SESSION_KEY = 'admin_2fa_passed_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->isSuper(), 403);

        $passedAt = $request->session()->get(self::SESSION_KEY);
        $fresh = $passedAt && now()->diffInMinutes($passedAt, true) < self::REMEMBER_MINUTES;

        if (! app(TwoFactor::class)->enrolled($user) || ! $fresh) {
            return redirect()->route('admin.verify', ['to' => $request->fullUrl()]);
        }

        return $next($request);
    }
}
