<?php

namespace App\Modules\Network\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;

/** /{siteID}/network and /{siteID}/earnings — the Referral Network admin pages (perm-gated by the routes). */
class NetworkAdminController extends Controller
{
    public function index(string $siteID)
    {
        return view('network', ['site' => $this->site($siteID)]);
    }

    public function earnings(string $siteID)
    {
        return view('earnings', ['site' => $this->site($siteID)]);
    }

    private function site(string $siteID): Site
    {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->accessibleBy(Auth::user()), 403);

        return $site;
    }
}
