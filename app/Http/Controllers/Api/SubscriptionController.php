<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Subscription;
use App\Modules\Newsletter\Services\SubscriberService;
use App\Services\TaskLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    /**
     * POST /api/sites/{siteName}/subscribe
     *
     * Accepts: { email, name?, source?, tags? }  (tags: array or "a, b")
     * Responses ({ message, status } — status: subscribed | pending):
     *   201  new subscriber (status "pending" + a confirmation email when the
     *        site's Newsletter add-on has double opt-in on)
     *   200  re-subscribed / confirmation re-sent
     *   409  already subscribed
     *   422  validation failed
     */
    public function store(Request $request, string $siteName, SubscriberService $subscribers): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        try {
            $data = $request->validate([
                'email' => ['required', 'email', 'max:255'],
                'name' => ['nullable', 'string', 'max:255'],
                'source' => ['nullable', 'string', 'max:255'],
                'tags' => ['nullable'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $tags = array_slice(Subscription::cleanTags(is_array($data['tags'] ?? null) || is_string($data['tags'] ?? null) ? $data['tags'] : null), 0, 10);

        [$sub, $outcome] = $subscribers->subscribe(
            $site, $data['email'], $data['name'] ?? null,
            $data['source'] ?? $request->header('Referer'), $request->ip(), $tags,
        );

        if ($outcome === 'already') {
            return response()->json(['message' => 'You are already subscribed.', 'status' => $sub->status], 409);
        }

        if ($outcome === 'pending' || $outcome === 'created') {
            try {
                app(TaskLogger::class)->alert($site,
                    'New subscriber — '.$sub->email, 'subscriber', 'info',
                    $sub->name, null, 'all', null);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $pending = $sub->isPending();
        $message = match (true) {
            $pending => 'Almost there — please check your inbox to confirm your subscription.',
            $outcome === 'resubscribed' => 'Welcome back! You have been re-subscribed.',
            default => 'Thank you for subscribing!',
        };

        return response()->json(['message' => $message, 'status' => $sub->status], in_array($outcome, ['created', 'pending'], true) ? 201 : 200);
    }

    /**
     * POST /api/sites/{siteName}/unsubscribe
     *
     * Accepts: { email }
     */
    public function destroy(Request $request, string $siteName, SubscriberService $subscribers): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Subscription::where('site_id', $site->id)
            ->where('email', $data['email'])
            ->get()
            ->each(fn (Subscription $s) => $subscribers->unsubscribe($s));

        return response()->json(['message' => 'You have been unsubscribed.']);
    }
}
