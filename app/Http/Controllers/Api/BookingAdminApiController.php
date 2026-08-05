<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmed;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Token-authenticated booking management (Authorization: Bearer <api token>).
 * The token's user must be a manager of the site.
 */
class BookingAdminApiController extends Controller
{
    private function site(Request $request, string $siteName): Site
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        abort_unless($site->hasFeature('bookings'), 404);

        $user = $request->attributes->get('api_token_user');
        abort_unless($user && $site->canManageTeam($user), 403, 'This token cannot manage this site.');

        return $site;
    }

    /** Paginated bookings list with filters: status, kind, from, to. */
    public function index(Request $request, string $siteName): JsonResponse
    {
        $site = $this->site($request, $siteName);

        $q = $site->bookings()->with('service:id,name,slug,kind')->latest('starts_at');

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($kind = $request->query('kind')) {
            $q->whereHas('service', fn ($s) => $s->where('kind', $kind));
        }
        if ($from = $request->query('from')) {
            $q->where('starts_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->where('starts_at', '<=', $to);
        }

        $page = $q->paginate(min(100, (int) $request->query('per_page', 25)));

        return response()->json([
            'data' => collect($page->items())->map(fn ($b) => [
                'id'          => $b->id,
                'reference'   => $b->reference,
                'status'      => $b->status,
                'kind'        => $b->service?->kind,
                'service'     => $b->service?->name,
                'customer'    => ['name' => $b->customer_name, 'email' => $b->customer_email, 'phone' => $b->customer_phone],
                'starts_at'   => $b->starts_at?->toIso8601String(),
                'ends_at'     => $b->ends_at?->toIso8601String(),
                'params'      => (object) ($b->params ?? []),
                'quantity'    => $b->quantity,
                'total_cents' => $b->total_cents,
                'currency'    => $b->currency,
                'created_at'  => $b->created_at->toIso8601String(),
            ]),
            'total'        => $page->total(),
            'per_page'     => $page->perPage(),
            'current_page' => $page->currentPage(),
        ]);
    }

    /** Confirm or cancel a booking. */
    public function update(Request $request, string $siteName, int $id): JsonResponse
    {
        $site = $this->site($request, $siteName);
        $data = $request->validate(['status' => ['required', 'in:confirmed,cancelled']]);

        $booking = $site->bookings()->with('service')->findOrFail($id);
        $was = $booking->status;
        $booking->update(['status' => $data['status']]);

        if ($was !== $data['status']) {
            try {
                \App\Services\ActivityLogger::bookingEvent($booking, $data['status']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($data['status'] === 'confirmed' && $was !== 'confirmed') {
            try {
                Mail::to($booking->customer_email)->send(new BookingConfirmed($booking, $site));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($data['status'] === 'cancelled' && $was !== 'cancelled') {
            try {
                Mail::to($booking->customer_email)->send(new \App\Mail\BookingCancelled($booking, $site));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'id' => $booking->id, 'status' => $booking->status]);
    }
}
