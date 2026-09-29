<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasUlids;

    protected $fillable = [
        'title', 'body', 'level', 'link_url', 'link_label', 'audience', 'plans', 'account_ids',
        'starts_at', 'ends_at', 'dismissible', 'add_to_alerts', 'alerts_sent_at', 'created_by',
    ];

    protected $casts = [
        'plans' => 'array',
        'account_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'alerts_sent_at' => 'datetime',
        'dismissible' => 'boolean',
        'add_to_alerts' => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function state(): string
    {
        return match (true) {
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at && $this->ends_at->isPast() => 'ended',
            default => 'active',
        };
    }

    /** Is this announcement aimed at this account? */
    public function targets(User $user): bool
    {
        return match ($this->audience) {
            'plans' => in_array($user->currentSubscription()->plan, (array) $this->plans, true),
            'accounts' => in_array($user->id, (array) $this->account_ids, true),
            default => true,
        };
    }
}
