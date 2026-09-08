<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task item (subtask): who does it, what exactly, and — optionally — when.
 * Items with both a start and an end show as bars on the task timeline.
 */
class TodoItem extends Model
{
    use HasUlids;

    protected $fillable = ['todo_id', 'label', 'description', 'assigned_user_id', 'starts_at', 'ends_at', 'done', 'sort'];

    protected $casts = ['done' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function isScheduled(): bool
    {
        return $this->starts_at !== null && $this->ends_at !== null;
    }
}
