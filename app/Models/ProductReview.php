<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Customer product review — pending until the owner approves it. */
class ProductReview extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'product_id', 'name', 'email', 'rating', 'body', 'status'];

    protected $casts = ['rating' => 'integer'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
