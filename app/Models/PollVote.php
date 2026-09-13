<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single visitor vote — deduped by voter_hash per poll. */
class PollVote extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['site_id', 'poll_id', 'poll_option_id', 'voter_hash', 'ip_address', 'created_at'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }
}
