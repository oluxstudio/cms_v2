<?php

namespace App\Modules\Reviews\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;

/** /{siteID}/reviews — the Reviews admin page (feature + reviews.view gated by the route). */
class ReviewsAdminController extends Controller
{
    public function index(string $siteID)
    {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->accessibleBy(Auth::user()), 403);

        return view('reviews', ['site' => $site]);
    }
}
