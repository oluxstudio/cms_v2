<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'form_name',
        'fields',
        'ip_address',
        'read_at',
    ];

    protected $casts = [
        'fields'     => 'array',
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForForm($query, string $formName)
    {
        return $query->where('form_name', $formName);
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    public function markAsRead(): void
    {
        if ($this->isUnread()) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Get a specific field value by key.
     */
    public function field(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }
}
