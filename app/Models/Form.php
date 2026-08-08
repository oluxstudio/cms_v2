<?php

namespace App\Models;

use App\Support\HasFieldSchema;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Form extends Model
{
    use HasFactory;
    use HasFieldSchema;
    use HasUlids;

    protected $fillable = [
        'site_id', 'name', 'title', 'description', 'fields', 'is_active',
    ];

    protected $casts = [
        'fields' => 'array',
        'is_active' => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function latestResponse(): HasOne
    {
        return $this->hasOne(FormResponse::class)->latestOfMany();
    }

    // ─────────────────────────────────────────────────────────────
    // Display helpers
    // ─────────────────────────────────────────────────────────────

    public function displayTitle(): string
    {
        return $this->title
            ?? ucwords(str_replace(['-', '_'], ' ', $this->name));
    }

    public function unreadCount(): int
    {
        return $this->responses()->whereNull('read_at')->count();
    }

    // Validation helpers (buildValidationRules / buildValidationMessages /
    // fieldValidationSummary) are provided by the HasFieldSchema trait.
}
