<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Append-only interest signal from the storefront: view | add_to_cart. */
class ProductEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const EVENTS = ['view', 'add_to_cart'];

    protected $fillable = ['site_id', 'product_id', 'event', 'session_hash', 'created_at'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
