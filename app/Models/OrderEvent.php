<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Append-only order history: one row per lifecycle step (who, when, what). */
class OrderEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['site_id', 'order_id', 'status', 'note', 'user_id', 'created_at'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
