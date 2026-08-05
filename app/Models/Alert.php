<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = ['site_id', 'user_id', 'level', 'type', 'audience', 'title', 'body', 'link', 'meta', 'read_at'];

    protected $casts = ['meta' => 'array', 'read_at' => 'datetime'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * RBAC: alerts a given user may see on a site.
     * - admins-only alerts require owner/admin role
     * - user-targeted alerts are visible to that user (admins also see them)
     */
    public function scopeVisibleTo(Builder $q, Site $site, User $user): Builder
    {
        $isAdmin = $site->canManageTeam($user);

        return $q->where('site_id', $site->id)
            ->when(! $isAdmin, fn ($q) => $q
                ->where('audience', 'all')
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id)));
    }
}
