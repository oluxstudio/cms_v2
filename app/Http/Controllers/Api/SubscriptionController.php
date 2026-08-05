<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    /**
     * POST /api/sites/{siteName}/subscribe
     *
     * Accepts: { email, name? }
     * - Idempotent: re-activates an existing unsubscribed record.
     * - Returns 409 if already actively subscribed.
     */
    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        try {
            $data = $request->validate([
                'email'  => ['required', 'email', 'max:255'],
                'name'   => ['nullable', 'string', 'max:255'],
                'source' => ['nullable', 'string', 'max:255'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        $existing = Subscription::where('site_id', $site->id)
                                ->where('email', $data['email'])
                                ->first();

        if ($existing) {
            if ($existing->isActive()) {
                return response()->json([
                    'message' => 'You are already subscribed.',
                ], 409);
            }

            // Re-activate an unsubscribed record
            $existing->update([
                'status'     => 'active',
                'name'       => $data['name'] ?? $existing->name,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Welcome back! You have been re-subscribed.',
            ]);
        }

        Subscription::create([
            'site_id'    => $site->id,
            'email'      => $data['email'],
            'name'       => $data['name'] ?? null,
            'source'     => $data['source'] ?? $request->header('Referer'),
            'ip_address' => $request->ip(),
            'status'     => 'active',
        ]);

        return response()->json([
            'message' => 'Thank you for subscribing!',
        ], 201);
    }

    /**
     * POST /api/sites/{siteName}/unsubscribe
     *
     * Accepts: { email }
     */
    public function destroy(Request $request, string $siteName): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Subscription::where('site_id', $site->id)
                    ->where('email', $data['email'])
                    ->update(['status' => 'unsubscribed']);

        return response()->json(['message' => 'You have been unsubscribed.']);
    }
}
