<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodoItem extends Model
{
    use HasUlids;

    protected $fillable = ['todo_id', 'label', 'done', 'sort'];

    protected $casts = ['done' => 'boolean'];

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }
}
