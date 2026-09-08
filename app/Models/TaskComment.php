<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A team member's note on a task — the conversation that tracks its progress. */
class TaskComment extends Model
{
    use HasUlids;

    protected $fillable = ['todo_id', 'user_id', 'body'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Todo::class, 'todo_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
