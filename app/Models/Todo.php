<?php

namespace App\Models;

use Carbon\CarbonInterface;
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
        'status', 'priority', 'starts_at', 'due_at', 'completed_at', 'sort',
    ];

    protected $casts = ['starts_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime'];

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

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    /** Task statuses in board order. */
    public const STATUSES = ['open' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done'];

    /**
     * Timeline data for the task view: the overall span (task dates, widened
     * by any scheduled items) and one row per scheduled item, all as % offsets
     * so the view can draw bars. Null when nothing is dated.
     *
     * @return array{start:CarbonInterface,end:CarbonInterface,days:int,task:?array,today:?float,rows:list<array>}|null
     */
    public function timeline(): ?array
    {
        $items = $this->items->filter(fn ($i) => $i->isScheduled());
        $starts = $items->pluck('starts_at')->push($this->starts_at)->filter();
        $ends = $items->pluck('ends_at')->push($this->due_at)->filter();
        if ($starts->isEmpty() || $ends->isEmpty()) {
            return null;
        }
        $start = $starts->min()->copy()->startOfDay();
        $end = $ends->max()->copy()->endOfDay();
        if ($end->lte($start)) {
            $end = $start->copy()->endOfDay();
        }
        $total = max(1, $start->diffInSeconds($end));
        $pct = fn ($at) => round(max(0, min(100, $start->diffInSeconds($at, false) / $total * 100)), 2);
        $bar = fn ($from, $to) => ['left' => $pct($from), 'width' => max(1.5, $pct($to) - $pct($from))];

        return [
            'start' => $start,
            'end' => $end,
            'days' => (int) $start->diffInDays($end) + 1,
            'task' => ($this->starts_at && $this->due_at) ? $bar($this->starts_at, $this->due_at) : null,
            'today' => now()->between($start, $end) ? $pct(now()) : null,
            'rows' => $items->values()->map(fn ($i) => [
                'id' => $i->id, 'label' => $i->label, 'done' => $i->done,
                'assignee' => $i->assignee?->name, 'from' => $i->starts_at, 'to' => $i->ends_at,
            ] + $bar($i->starts_at, $i->ends_at))->all(),
        ];
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_at !== null && $this->due_at->isPast();
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
