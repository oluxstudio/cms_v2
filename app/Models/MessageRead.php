<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Per-user read receipt for a (broadcast) message. */
class MessageRead extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['message_id', 'user_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
