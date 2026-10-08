<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformTestimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/testimonials?limit=N — published testimonials about Olux, in the
 * order Admin › Testimonials sets. limit is 1–20 (default: the admin's
 * landing count, 5 out of the box). Public: no email, no IP address.
 */
class TestimonialApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:20']]);
        $limit = PlatformTestimonial::landingLimit($request->filled('limit') ? (int) $request->query('limit') : null);
        $items = PlatformTestimonial::forLanding($limit);

        return response()->json([
            'data' => $items->map->toPublicArray()->values(),
            'meta' => ['count' => $items->count(), 'limit' => $limit, 'total_published' => PlatformTestimonial::where('status', 'published')->count()],
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
