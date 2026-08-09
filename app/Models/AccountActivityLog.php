<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row on the account audit trail (Settings → Activity & Logs).
 * See the AccountActivity recorder for how rows are created.
 */
class AccountActivityLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id', 'actor_id', 'action', 'title', 'description', 'category', 'icon', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Hex accent for the timeline dot, by category (inline-styled — no JIT dependency). */
    public function accent(): string
    {
        return [
            'Login' => '#6366f1',    // indigo
            'Security' => '#f59e0b',  // amber
            'Team' => '#10b981',      // emerald
            'Sites' => '#3b82f6',     // blue
            'API' => '#8b5cf6',       // violet
            'Profile' => '#0ea5e9',   // sky
        ][$this->category] ?? '#9ca3af'; // gray
    }
}
