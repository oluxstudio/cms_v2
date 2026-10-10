<?php

namespace App\Modules\Events\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;

/** The Events admin page wrapper (/{siteID}/events). */
class EventsAdminController extends Controller
{
    public function __invoke(string $siteID)
    {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->accessibleBy(Auth::user()), 403);

        return view('events', ['site' => $site, 'event' => request()->query('event')]);
    }
}
