<?php

namespace App\Modules\Reviews\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\Reviews\Models\Review;
use App\Modules\Reviews\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public reviews API for template sites (stateless):
 *   GET  /api/sites/{siteName}/reviews   published reviews + aggregate + JSON-LD
 *   POST /api/sites/{siteName}/reviews   a visitor's review (honeypot + throttle:leads)
 */
class ReviewApiController extends Controller
{
    private function site(Request $request, string $siteName): Site
    {
        return $request->attributes->get('resolvedSite') ?? Site::where('name', $siteName)->firstOrFail();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        $site = $this->site($request, $siteName);
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
        $sort = $request->query('sort') === 'highest' ? 'highest' : 'newest';

        $q = Review::where('site_id', $site->id)->published();
        if ($request->boolean('featured')) {
            $q->where('featured', true);
        }
        if (in_array((int) $request->query('rating'), [1, 2, 3, 4, 5], true)) {
            $q->where('rating', (int) $request->query('rating'));
        }
        $sort === 'highest'
            ? $q->orderByDesc('rating')->orderByDesc('created_at')
            : $q->orderByDesc('created_at');

        $page = $q->paginate($perPage)->withQueryString();
        $items = collect($page->items());
        $photo = ReviewService::photoResolver($site, $items);
        $aggregate = ReviewService::aggregate($site);
        $ld = ReviewService::jsonLd($site, $aggregate, $items);

        return response()->json([
            'site' => $site->name,
            'aggregate' => $aggregate,
            'reviews' => $items->map(fn ($r) => ReviewService::present($r, $photo))->values(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'sort' => $sort,
            ],
            'json_ld' => $ld,
            'json_ld_script' => ReviewService::jsonLdScript($ld),
        ]);
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->site($request, $siteName);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        // Same visitor, same words, same day → treat as a double submit.
        $hash = ReviewService::ipHash($request->ip());
        $dupe = Review::where('site_id', $site->id)->where('ip_hash', $hash)
            ->where('created_at', '>=', now()->subDay())
            ->where('body', ReviewService::clean($data['body'], 3000))->exists();
        if ($dupe) {
            return response()->json(['message' => 'You have already sent this review — thank you!'], 422);
        }

        $review = ReviewService::submit($site, $data, 'on_site', null, $request->ip());

        return response()->json([
            'ok' => true,
            'status' => $review->status,
            'message' => $review->status === 'published'
                ? 'Thank you — your review is live.'
                : 'Thank you — your review will appear once it has been approved.',
        ], 201);
    }
}
