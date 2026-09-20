<?php

namespace App\Livewire\Concerns;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;

/**
 * Shared scheduled-visibility form state for the Components and Collections
 * edit modals (partials.visibility-fields binds to these vis* properties).
 */
trait WithVisibilityFields
{
    public string $visFrom = '';

    public string $visUntil = '';

    /** @var array<int|string> ISO weekday numbers 1-7 */
    public array $visDays = [];

    public string $visTimeFrom = '';

    public string $visTimeUntil = '';

    public bool $visRequiresContent = false;

    public string $visPromo = '';

    protected function hydrateVisibilityFields(?array $rules): void
    {
        $rules ??= [];
        $this->visFrom = isset($rules['from']) ? substr((string) $rules['from'], 0, 16) : '';
        $this->visUntil = isset($rules['until']) ? substr((string) $rules['until'], 0, 16) : '';
        $this->visDays = array_map('strval', (array) ($rules['days'] ?? []));
        $this->visTimeFrom = (string) ($rules['time_from'] ?? '');
        $this->visTimeUntil = (string) ($rules['time_until'] ?? '');
        $this->visRequiresContent = (bool) ($rules['requires_content'] ?? false);
        $this->visPromo = (string) ($rules['promo'] ?? '');
    }

    protected function resetVisibilityFields(): void
    {
        $this->reset(['visFrom', 'visUntil', 'visDays', 'visTimeFrom', 'visTimeUntil', 'visRequiresContent', 'visPromo']);
    }

    /** Validate + assemble the rules json; null when everything is blank. */
    protected function assembleVisibility(): ?array
    {
        $this->validate([
            'visFrom' => ['nullable', 'date'],
            'visUntil' => ['nullable', 'date', 'after_or_equal:visFrom'],
            'visDays' => ['array'],
            'visDays.*' => ['integer', 'between:1,7'],
            'visTimeFrom' => ['nullable', 'date_format:H:i', 'required_with:visTimeUntil'],
            'visTimeUntil' => ['nullable', 'date_format:H:i', 'required_with:visTimeFrom'],
            'visPromo' => ['nullable', 'string', 'alpha_dash', 'max:64'],
        ], [
            'visUntil.after_or_equal' => 'The "visible until" date must be after "visible from".',
            'visTimeFrom.required_with' => 'Set both daily times, or neither.',
            'visTimeUntil.required_with' => 'Set both daily times, or neither.',
        ]);

        $rules = array_filter([
            'from' => $this->visFrom ?: null,
            'until' => $this->visUntil ?: null,
            'days' => array_values(array_map('intval', $this->visDays)) ?: null,
            'time_from' => $this->visTimeFrom ?: null,
            'time_until' => $this->visTimeUntil ?: null,
            'requires_content' => $this->visRequiresContent ?: null,
            'promo' => trim($this->visPromo) ?: null,
        ], fn ($v) => $v !== null);

        return $rules === [] ? null : $rules;
    }

    /** Visibility changed → the external page_render cache is stale site-wide. */
    protected function bustRenderCache(Site $site): void
    {
        foreach ($site->pages()->pluck('url') as $url) {
            Cache::forget("page_render:{$site->id}:{$url}");
        }
    }
}
