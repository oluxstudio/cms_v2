<?php

namespace App\Modules\Reviews\Http;

use App\Exceptions\PlanLimitReached;
use App\Http\Controllers\Controller;
use App\Modules\Reviews\Models\ReviewRequest;
use App\Modules\Reviews\ReviewService;
use App\Services\MediaStore;
use App\Support\SiteProperties;
use Illuminate\Http\Request;

/**
 * The hosted page a review-request email links to: /review/{token}.
 * One review per token; afterwards the link shows the thank-you state.
 */
class ReviewRequestController extends Controller
{
    private function find(string $token): ReviewRequest
    {
        $request = ReviewRequest::where('token', $token)->with('site')->firstOrFail();
        abort_unless($request->site && $request->site->hasFeature(ReviewService::FEATURE), 404);

        return $request;
    }

    public function show(string $token)
    {
        $req = $this->find($token);
        if (! $req->opened_at) {
            $req->forceFill(['opened_at' => now()])->save();
        }

        return $this->page($req);
    }

    public function store(Request $request, string $token, MediaStore $media)
    {
        $req = $this->find($token);
        if ($req->completed_at) {
            return redirect()->route('reviews.request.show', $token);
        }
        if (filled($request->input('_hp'))) { // honeypot: look successful, keep nothing
            return redirect()->route('reviews.request.show', $token);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:3', 'max:3000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:6144'],
        ], [
            'rating.required' => 'Please choose a star rating.',
            'body.required' => 'Please write a few words about your experience.',
        ]);

        $site = $req->site;
        $photo = null;
        if ($request->hasFile('photo')) {
            try {
                $photo = $media->store($site, $request->file('photo'))->ref();
            } catch (PlanLimitReached) {
                $photo = null; // the review still counts without the picture
            }
        }

        // Claim the token atomically: a double-click must not make two reviews.
        if (ReviewRequest::whereKey($req->id)->whereNull('completed_at')->update(['completed_at' => now()]) === 1) {
            ReviewService::submit($site, ['photo' => $photo] + $data, 'request', $req, $request->ip());
        }

        return redirect()->route('reviews.request.show', $token)->with('reviews.thanks', true);
    }

    private function page(ReviewRequest $req)
    {
        $site = $req->site;

        return view('review-request', [
            'req' => $req,
            'site' => $site,
            'siteName' => ReviewService::siteName($site),
            'logo' => SiteProperties::imageUrl($site, SiteProperties::value($site, 'logo')),
            'googleUrl' => SiteProperties::value($site, 'google_review_url') ?: SiteProperties::value($site, 'review_google'),
            'review' => $req->completed_at ? $req->review : null,
        ]);
    }
}
