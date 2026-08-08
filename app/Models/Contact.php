<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contact extends Model
{
    use HasFactory;
    use HasUlids;

    /** Pipeline stages, in order. */
    public const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];

    protected $fillable = [
        'site_id', 'name', 'email', 'phone', 'status',
        'source_form_id', 'assigned_user_id', 'data', 'last_activity_at',
    ];

    protected $casts = [
        'data' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sourceForm(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'source_form_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** All form responses tied to this contact (their interaction history). */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    /** Timeline entries for this contact. */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** Record a timeline entry. Actor defaults to the authenticated user. */
    public function logActivity(string $type, ?string $body = null, array $meta = [], ?string $userId = null): Activity
    {
        return $this->activities()->create([
            'user_id' => $userId ?? auth()->id(),
            'type' => $type,
            'body' => $body,
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * THE lead funnel: every visitor interaction (estimate request, booking,
     * contact form, custom form) lands in the CRM through here. Matches by
     * email within the site; creates a NEW-stage contact when unseen; always
     * stamps activity + a timeline entry describing the touchpoint.
     */
    public static function capture(Site $site, string $name, string $email, ?string $phone, string $touchpoint, array $meta = []): ?self
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $contact = static::firstOrCreate(
            ['site_id' => $site->id, 'email' => $email],
            ['name' => trim($name) ?: $email, 'phone' => $phone, 'status' => 'new']
        );

        // Enrich quietly: fill gaps, never overwrite what an owner edited.
        $dirty = [];
        if (! $contact->phone && $phone) {
            $dirty['phone'] = $phone;
        }
        $dirty['last_activity_at'] = now();
        $contact->update($dirty);

        $contact->logActivity('note', $touchpoint, $meta);

        return $contact;
    }

    /**
     * The contact's picture: an explicitly set logo/photo URL (data.avatar),
     * else their Gravatar. The views fall back to colored initials when the
     * image can't load (no Gravatar account, broken URL).
     */
    public function avatarUrl(): string
    {
        $custom = trim((string) ($this->data['avatar'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim((string) $this->email))).'?d=404&s=112';
    }

    /** Deterministic avatar color for the initials fallback. */
    public function avatarColor(): string
    {
        return ['#6366f1', '#8b5cf6', '#ec4899', '#0ea5e9', '#10b981'][abs(crc32((string) $this->name)) % 5];
    }

    public function initials(): string
    {
        return Str::of($this->name ?: '?')
            ->explode(' ')
            ->take(2)
            ->map(fn (string $p) => Str::substr($p, 0, 1))
            ->implode('') ?: '?';
    }
}
