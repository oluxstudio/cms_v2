<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Todo extends Model
{
    use HasUlids;

    protected $fillable = [
        'site_id', 'user_id', 'assigned_user_id', 'title', 'description',
        'status', 'priority', 'due_at', 'completed_at', 'sort',
    ];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TodoItem::class)->orderBy('sort');
    }

    /** Progress of the checkable sub-list, 0–100. */
    public function progress(): int
    {
        $total = $this->items->count();
        if ($total === 0) {
            return $this->status === 'done' ? 100 : 0;
        }

        return (int) round($this->items->where('done', true)->count() / $total * 100);
    }

    /** RBAC: todos are shared across a site's team members. */
    public function scopeVisibleTo(Builder $q, Site $site, User $user): Builder
    {
        return $q->where('site_id', $site->id);
    }

    /** Whether a user may edit/delete this todo (creator, assignee, or admin). */
    public function editableBy(User $user): bool
    {
        return $this->user_id === $user->id
            || $this->assigned_user_id === $user->id
            || $this->site->canManageTeam($user);
    }
}
