<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A visitor's saved estimate request (Estimator module lead). */
class Estimate extends Model
{
    protected $fillable = [
        'site_id', 'estimator_id', 'reference', 'trade', 'customer_name', 'customer_email',
        'customer_phone', 'notes', 'inputs', 'results', 'cost_low_cents', 'cost_high_cents',
        'currency', 'hours', 'completion', 'status', 'ip_address',
    ];

    protected $casts = ['inputs' => 'array', 'results' => 'array', 'hours' => 'float'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function estimator(): BelongsTo
    {
        return $this->belongsTo(Estimator::class);
    }

    public static function newReference(): string
    {
        do {
            $ref = 'EST'.strtoupper(Str::random(7));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }

    public function costLabel(): string
    {
        return Money::format($this->cost_low_cents, $this->currency)
            .' – '.Money::format($this->cost_high_cents, $this->currency);
    }
}
