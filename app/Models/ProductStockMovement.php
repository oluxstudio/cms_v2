<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** One inventory change: sale, restock, manual edit or cancellation refund. */
class ProductStockMovement extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['site_id', 'product_id', 'delta', 'stock_after', 'reason', 'order_id', 'user_id', 'created_at'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
