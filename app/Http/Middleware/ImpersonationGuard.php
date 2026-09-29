<?php

namespace App\Http\Middleware;

use App\Services\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a super admin is viewing as a client: end the session after 60
 * minutes, and keep the admin area out of reach (the client isn't an admin).
 */
class ImpersonationGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! Impersonation::active()) {
            return $next($request);
        }

        if (Impersonation::expired()) {
            $admin = app(Impersonation::class)->stop('expired');

            return redirect()->to($admin?->isSuper() ? route('admin.accounts') : '/')
                ->with('status', 'Your "view as" session ended after '.Impersonation::MINUTES.' minutes.');
        }

        if ($request->is('admin', 'admin/*')) {
            return redirect()->back(fallback: '/')->with('status', 'Stop viewing as the client to go back to the admin area.');
        }

        return $next($request);
    }
}
