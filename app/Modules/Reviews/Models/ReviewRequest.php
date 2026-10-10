<?php

namespace App\Modules\Reviews\Models;

use App\Models\Contact;
use App\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** An emailed "leave us a review" link: one unguessable token, one review. */
class ReviewRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'site_id', 'contact_id', 'name', 'email', 'token',
        'sent_at', 'opened_at', 'completed_at', 'reminder_sent_at',
    ];

    protected $hidden = ['token'];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $r) {
            $r->token ??= self::newToken();
        });
    }

    /** 48 random alphanumerics (~285 bits) — unguessable, URL-safe. */
    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'request_id');
    }

    /** completed | opened | sent | draft */
    public function state(): string
    {
        return match (true) {
            $this->completed_at !== null => 'completed',
            $this->opened_at !== null => 'opened',
            $this->sent_at !== null => 'sent',
            default => 'draft',
        };
    }

    public function url(): string
    {
        return route('reviews.request.show', $this->token);
    }
}
