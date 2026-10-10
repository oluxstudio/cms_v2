<?php

namespace App\Modules\Network\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Append-only history of a referral (audit trail for consent and disputes). */
class ReferralEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['referral_id', 'type', 'user_id', 'data', 'created_at'];

    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];
}
