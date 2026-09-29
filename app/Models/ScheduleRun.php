<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['command', 'status', 'trigger', 'started_at', 'finished_at', 'output'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function seconds(): ?int
    {
        return $this->finished_at ? (int) $this->started_at->diffInSeconds($this->finished_at) : null;
    }
}
