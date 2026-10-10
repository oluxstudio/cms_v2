<?php

namespace App\Modules\Newsletter\Models;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One campaign → one recipient: delivery + open / click / unsubscribe tracking. */
class NewsletterSend extends Model
{
    use HasUlids;

    /** Statuses that use up the account's monthly send allowance. */
    public const COUNTED = ['queued', 'sending', 'sent'];

    protected $fillable = [
        'campaign_id', 'site_id', 'subscription_id', 'email', 'token', 'status', 'error', 'sent_at',
        'opened_at', 'open_count', 'clicked_at', 'click_count', 'unsubscribed_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'open_count' => 'integer',
        'click_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (NewsletterSend $s) => $s->token ??= Str::random(40));
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'campaign_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** First open bumps the campaign's unique-open counter. */
    public function recordOpen(): void
    {
        $first = $this->opened_at === null;
        $this->forceFill(['opened_at' => $this->opened_at ?? now(), 'open_count' => $this->open_count + 1])->save();
        if ($first) {
            NewsletterCampaign::whereKey($this->campaign_id)->increment('opens_count');
        }
    }

    /** A click implies an open (images are often blocked). */
    public function recordClick(): void
    {
        if ($this->opened_at === null) {
            $this->recordOpen();
        }
        $first = $this->clicked_at === null;
        $this->forceFill(['clicked_at' => $this->clicked_at ?? now(), 'click_count' => $this->click_count + 1])->save();
        if ($first) {
            NewsletterCampaign::whereKey($this->campaign_id)->increment('clicks_count');
        }
    }
}
