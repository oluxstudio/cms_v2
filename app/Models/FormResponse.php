<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FormResponse extends Model
{
    use HasFactory;

    protected $fillable = ['form_id', 'contact_id', 'fields', 'ip_address', 'read_at', 'converted_at'];

    protected $casts = [
        'fields'       => 'array',
        'read_at'      => 'datetime',
        'converted_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Heuristically pull name / email / phone out of the submitted fields,
     * matching field keys case-insensitively. Returns the lead-shaped data
     * plus the leftover fields under 'extra'.
     *
     * @return array{name:?string,email:?string,phone:?string,extra:array}
     */
    public function extractContactData(): array
    {
        $fields = $this->fields ?? [];
        $email = $phone = $first = $last = $name = null;
        $extra = [];

        foreach ($fields as $key => $value) {
            if (! is_scalar($value) || $value === '') {
                continue;
            }
            $k = strtolower((string) $key);

            if (! $email && str_contains($k, 'email')) {
                $email = trim((string) $value);
            } elseif (! $phone && (str_contains($k, 'phone') || str_contains($k, 'mobile') || str_contains($k, 'tel'))) {
                $phone = trim((string) $value);
            } elseif (! $first && (str_contains($k, 'first') && str_contains($k, 'name'))) {
                $first = trim((string) $value);
            } elseif (! $last && (str_contains($k, 'last') && str_contains($k, 'name'))) {
                $last = trim((string) $value);
            } elseif (! $name && $k === 'name' || (! $name && str_contains($k, 'name') && ! str_contains($k, 'first') && ! str_contains($k, 'last') && ! str_contains($k, 'user'))) {
                $name = trim((string) $value);
            } else {
                $extra[$key] = $value;
            }
        }

        $fullName = $name
            ?? trim(($first ?? '') . ' ' . ($last ?? ''))
            ?: ($email ? Str::before($email, '@') : 'Unknown');

        return [
            'name'  => $fullName ?: 'Unknown',
            'email' => $email,
            'phone' => $phone,
            'extra' => $extra,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Retrieve a single field value by its key.
     * Dot-notation supported via data_get().
     */
    public function field(string $key, mixed $default = null): mixed
    {
        return data_get($this->fields, $key, $default);
    }
}
