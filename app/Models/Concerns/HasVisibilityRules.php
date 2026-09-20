<?php

namespace App\Models\Concerns;

use Carbon\Carbon;

/**
 * Optional scheduled-visibility rules for content models (components and
 * collections). The nullable `visibility` json holds any of:
 *
 *   from / until          ISO datetimes — a date range
 *   days                  ISO weekday numbers (1 = Mon … 7 = Sun), empty = all
 *   time_from / time_until "HH:MM" daily window (overnight windows supported)
 *   requires_content      hide when the linked collection has no published items
 *   promo                 CLIENT-only: visible when the visitor carried ?promo={tag}
 *
 * The server NEVER drops content over these rules — the connect editor reads
 * the same payloads and `promo` can only be judged in the browser. Instead the
 * rules ship to the client (useOluxContent.hidden() enforces them) together
 * with a serialize-time `visible_now` hint for the server-checkable parts.
 */
trait HasVisibilityRules
{
    /** Server-checkable rules pass right now? (promo is ignored here) */
    public function visibleNow(?Carbon $now = null): bool
    {
        $rules = $this->visibility;
        if (! is_array($rules) || $rules === []) {
            return true;
        }
        $now ??= now();

        if (! empty($rules['from']) && $now->lt(Carbon::parse($rules['from']))) {
            return false;
        }
        if (! empty($rules['until']) && $now->gt(Carbon::parse($rules['until']))) {
            return false;
        }
        if (! empty($rules['days']) && ! in_array($now->isoWeekday(), array_map('intval', (array) $rules['days']), true)) {
            return false;
        }
        if (! empty($rules['time_from']) && ! empty($rules['time_until'])) {
            $time = $now->format('H:i');
            [$fromT, $untilT] = [$rules['time_from'], $rules['time_until']];
            $inWindow = $fromT <= $untilT
                ? ($time >= $fromT && $time <= $untilT)
                : ($time >= $fromT || $time <= $untilT); // overnight, e.g. 22:00–02:00
            if (! $inWindow) {
                return false;
            }
        }
        if (! empty($rules['requires_content']) && ! $this->hasVisibilityContent()) {
            return false;
        }

        return true;
    }

    /** What ships in payloads: the raw rules + the server-side hint. */
    public function visibilityPayload(): ?array
    {
        if (! is_array($this->visibility) || $this->visibility === []) {
            return null;
        }

        return ['rules' => $this->visibility, 'visible_now' => $this->visibleNow()];
    }

    /** Does this model currently HAVE content? (the requires_content check) */
    protected function hasVisibilityContent(): bool
    {
        // Collections: published items. Components: their linked collection's
        // published items (a component without a collection always "has content").
        if (method_exists($this, 'items')) {
            return $this->items()->where('status', 'published')->exists();
        }
        if (isset($this->collection_id) && $this->collection_id) {
            return \App\Models\CollectionItem::where('collection_id', $this->collection_id)
                ->where('status', 'published')->exists();
        }

        return true;
    }
}
