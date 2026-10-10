<?php

namespace App\Modules\Newsletter\Models;

use App\Models\Site;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterCampaign extends Model
{
    use HasUlids;

    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    protected $fillable = [
        'site_id', 'subject', 'preheader', 'body', 'audience_tag', 'status', 'scheduled_at', 'started_at', 'sent_at',
        'recipients_count', 'sent_count', 'failed_count', 'opens_count', 'clicks_count', 'unsubscribes_count',
        'error', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipients_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'opens_count' => 'integer',
        'clicks_count' => 'integer',
        'unsubscribes_count' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(NewsletterSend::class, 'campaign_id');
    }

    /** The subscribers this campaign goes to (subscribed only; optionally one tag). */
    public function audience(): Builder
    {
        return Subscription::where('site_id', $this->site_id)->active()
            ->when($this->audience_tag, fn ($q) => $q->tagged($this->audience_tag));
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::SCHEDULED], true);
    }

    public function openRate(): int
    {
        return $this->sent_count > 0 ? (int) round($this->opens_count / $this->sent_count * 100) : 0;
    }

    public function clickRate(): int
    {
        return $this->sent_count > 0 ? (int) round($this->clicks_count / $this->sent_count * 100) : 0;
    }
}
