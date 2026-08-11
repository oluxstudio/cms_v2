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
        'site_id', 'name', 'title', 'description', 'fields', 'delivery', 'email_template', 'is_active',
    ];

    protected $casts = [
        'fields' => 'array',
        'delivery' => 'array',
        'email_template' => 'array',
        'is_active' => 'boolean',
    ];

    /** Default delivery config for a form with none stored yet (email on, both parties notified). */
    public static function defaultDelivery(): array
    {
        return [
            'channels' => [
                'email' => [
                    'enabled' => true,
                    'notify_visitor' => true,
                    'notify_admin' => true,
                    'admin_address' => null,   // null → falls back to the site owner's email
                ],
            ],
        ];
    }

    /**
     * The form's delivery config merged over the defaults, so absent/partial
     * config still behaves like today. Every registry channel is represented.
     */
    public function deliveryConfig(): array
    {
        $stored = $this->delivery ?? [];
        $channels = $stored['channels'] ?? [];

        $email = array_merge(self::defaultDelivery()['channels']['email'], $channels['email'] ?? []);

        $out = ['channels' => ['email' => $email]];

        // Carry forward config for not-yet-implemented channels (sms/whatsapp) as stored.
        foreach (array_keys(config('form_channels.channels', [])) as $key) {
            if ($key === 'email') {
                continue;
            }
            $out['channels'][$key] = array_merge(['enabled' => false], $channels[$key] ?? []);
        }

        return $out;
    }

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

    /**
     * Canonical API shape — shared by the forms endpoint and the site-content
     * payload. `responses` (a count) is included only for management views.
     */
    public function toApiArray(bool $withResponses = false): array
    {
        $out = [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->displayTitle(),
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'fields' => $this->fields ?? [],
            'submit_url' => route('api.form', ['siteName' => $this->site->name, 'formName' => $this->name]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($withResponses) {
            $out['responses'] = $this->responses()->count();
        }

        return $out;
    }

    // Validation helpers (buildValidationRules / buildValidationMessages /
    // fieldValidationSummary) are provided by the HasFieldSchema trait.
}
