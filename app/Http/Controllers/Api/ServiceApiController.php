<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiSite;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Booking services CRUD + availability settings (token context).
 *
 *   GET    /api/site/services            → list services      (Bearer · bookings.manage)
 *   POST   /api/site/services            → create (enables the bookings feature)
 *   PATCH  /api/site/services/{slug}     → update
 *   DELETE /api/site/services/{slug}     → delete
 *   PATCH  /api/site/booking-settings    → merge availability keys into the bookings feature config
 *
 * Lets client-side tooling (cms-seed.mjs) manage the booking catalogue the
 * same way it manages components/collections/forms/posts.
 */
class ServiceApiController extends Controller
{
    use ResolvesApiSite;

    /** Availability keys SlotAvailability::settings understands. */
    private const AVAILABILITY_KEYS = ['days', 'open_time', 'close_time', 'slot_minutes', 'lead_hours', 'horizon_days', 'day_hours'];

    private function record(Service $s): array
    {
        return ['id' => $s->id, 'slug' => $s->slug, 'name' => $s->name, 'kind' => $s->kind,
            'duration_min' => $s->duration_min, 'price_cents' => $s->price_cents,
            'deposit_cents' => $s->deposit_cents, 'capacity' => $s->capacity,
            'requires_payment' => (bool) $s->requires_payment, 'is_active' => (bool) $s->is_active,
            'description' => $s->description, 'config' => $s->config ?? []];
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'kind' => ['sometimes', 'in:'.implode(',', Service::KINDS)],
            'duration_min' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1440'],
            'price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'deposit_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'requires_payment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array', 'max:30'],
        ]);
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'bookings.manage');

        return response()->json(['services' => $site->services()->orderBy('sort')->orderBy('name')->get()
            ->map(fn (Service $s) => $this->record($s))->values()]);
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'bookings.manage');
        $data = $this->validated($request, creating: true);

        // Seeded services must actually be bookable.
        $site->enableFeature('bookings');

        $service = $site->services()->create($data + ['kind' => 'slot', 'is_active' => true, 'slug' => '']);

        return response()->json(['ok' => true, 'service' => $this->record($service)], 201);
    }

    public function update(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'bookings.manage');
        $service = $site->services()->where('slug', $slug)->firstOrFail();
        $service->update($this->validated($request, creating: false));

        return response()->json(['ok' => true, 'service' => $this->record($service->fresh())]);
    }

    public function destroy(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'bookings.manage');
        $site->services()->where('slug', $slug)->firstOrFail()->delete();

        return response()->json(['ok' => true]);
    }

    /** Merge site-wide availability settings into the bookings feature config. */
    public function updateSettings(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'bookings.manage');
        $data = $request->validate([
            'days' => ['sometimes', 'string', 'max:60'],
            'open_time' => ['sometimes', 'date_format:H:i'],
            'close_time' => ['sometimes', 'date_format:H:i'],
            'slot_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'lead_hours' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'horizon_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'day_hours' => ['sometimes', 'array', 'max:7'],
        ]);

        $site->enableFeature('bookings');
        $stored = $site->siteFeatures()->where('key', 'bookings')->value('config') ?? [];
        $site->saveFeatureConfig('bookings', array_merge($stored, array_intersect_key($data, array_flip(self::AVAILABILITY_KEYS))));

        return response()->json(['ok' => true, 'config' => $site->fresh()->feature('bookings')]);
    }
}
