<?php

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\Newsletter\Newsletter;
use Illuminate\Http\JsonResponse;

class NewsletterApiController extends Controller
{
    /**
     * GET /api/sites/{siteName}/newsletter — what a template's signup box needs.
     * Signups themselves post to the long-standing /api/sites/{siteName}/subscribe.
     */
    public function show(string $siteName): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        $settings = Newsletter::settings($site);

        return response()->json([
            'enabled' => $site->hasFeature(Newsletter::FEATURE),
            'headline' => (string) ($settings['signup_headline'] ?? ''),
            'double_opt_in' => Newsletter::doubleOptIn($site),
            'subscribe_url' => route('api.subscribe', $site->name),
            'unsubscribe_url' => route('api.unsubscribe', $site->name),
        ]);
    }
}
