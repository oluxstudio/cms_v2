<?php

namespace App\Modules\Memberships\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;

/** GET /{siteID}/memberships — the admin page wrapper (feature + perm gated by the route). */
class MembershipAdminController extends Controller
{
    public function __invoke(string $siteID)
    {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->accessibleBy(Auth::user()), 403);

        return view('memberships', ['site' => $site]);
    }
}
