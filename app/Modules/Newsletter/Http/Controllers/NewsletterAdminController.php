<?php

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;

class NewsletterAdminController extends Controller
{
    /** GET /{siteID}/newsletter — the Newsletter admin page (feature + perm gated in the route). */
    public function show(string $siteID)
    {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->accessibleBy(Auth::user()), 403);

        return view('newsletter', ['site' => $site]);
    }
}
