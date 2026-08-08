<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteActivityLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'site_id',
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'title',
        'description',
        'url',
        'icon',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Display helpers ──────────────────────────────────────────

    /**
     * Tailwind / hex colour pair for the entity icon bubble.
     * Returns [background, foreground].
     */
    public function iconColors(): array
    {
        return match ($this->icon ?? $this->entity_type) {
            'page' => ['#eef2ff', '#6366f1'],
            'form' => ['#fffbeb', '#d97706'],
            'form_response',
            'response' => ['#f0fdf4', '#16a34a'],
            'todo' => ['#eff6ff', '#2563eb'],
            'media' => ['#fef2f2', '#dc2626'],
            'member' => ['#f5f3ff', '#7c3aed'],
            'component' => ['#ecfdf5', '#059669'],
            'estimate' => ['#f5f0e6', '#b45309'],
            'interest' => ['#fdf2f8', '#db2777'],
            'booking' => ['#eff6ff', '#0284c7'],
            'invoice' => ['#ecfdf5', '#047857'],
            default => ['#f9fafb', '#6b7280'],
        };
    }

    /**
     * SVG path(s) for the entity icon — used in <path d="..."/>.
     */
    public function iconPath(): string
    {
        return match ($this->icon ?? $this->entity_type) {
            'page' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z',
            'form' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
            'form_response',
            'response' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'todo' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2m-6 9l2 2 4-4',
            'media' => 'M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z',
            'member' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
            'component' => 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
            'estimate' => 'M9 7h6m-6 4h6m-6 4h3m-7 6h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z',
            'interest' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
            'booking' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'invoice' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
            default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        };
    }

    /**
     * Friendly label for the action badge shown on the card.
     */
    public function actionBadge(): array
    {
        return match ($this->action) {
            'created' => ['Created',     '#eef2ff', '#6366f1'],
            'updated' => ['Updated',     '#fffbeb', '#d97706'],
            'published' => ['Published',   '#f0fdf4', '#16a34a'],
            'unpublished' => ['Unpublished', '#fef2f2', '#dc2626'],
            'deleted' => ['Deleted',     '#fef2f2', '#dc2626'],
            'responded' => ['Response',    '#f0fdf4', '#16a34a'],
            'completed' => ['Completed',   '#f0fdf4', '#16a34a'],
            'joined' => ['Joined',      '#f5f3ff', '#7c3aed'],
            'uploaded' => ['Uploaded',    '#eff6ff', '#2563eb'],
            'confirmed' => ['Confirmed',   '#f0fdf4', '#16a34a'],
            'cancelled' => ['Cancelled',   '#fef2f2', '#dc2626'],
            'sent' => ['Sent',        '#eff6ff', '#2563eb'],
            default => [ucfirst($this->action), '#f9fafb', '#6b7280'],
        };
    }
}
