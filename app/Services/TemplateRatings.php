<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateRating;
use App\Models\User;

/**
 * Star ratings/reviews. A user may rate a template they're entitled to (installed /
 * purchased / free-got) but not their own. Aggregates are recomputed on each change.
 */
class TemplateRatings
{
    public function __construct(private TemplateCommerce $commerce) {}

    public function canRate(?User $user, Template $template): bool
    {
        return $user
            && $template->user_id !== $user->id
            && $this->commerce->entitled($user, $template);
    }

    public function userStars(?User $user, Template $template): ?int
    {
        if (! $user) {
            return null;
        }

        return TemplateRating::where('template_id', $template->id)
            ->where('user_id', $user->id)->value('stars');
    }

    /** Upsert a rating (1–5) and recompute the template's aggregates. */
    public function rate(User $user, Template $template, int $stars, ?string $review = null): void
    {
        $stars = max(1, min(5, $stars));

        TemplateRating::updateOrCreate(
            ['template_id' => $template->id, 'user_id' => $user->id],
            ['stars' => $stars, 'review' => $review ? mb_substr($review, 0, 1000) : null],
        );

        $this->recompute($template);
    }

    public function recompute(Template $template): void
    {
        $rows = TemplateRating::where('template_id', $template->id);
        $template->update([
            'rating_count' => $rows->count(),
            'rating_avg' => round((float) $rows->avg('stars'), 2),
        ]);
    }
}
