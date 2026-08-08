<?php

namespace App\Models;

use App\Services\Estimator\Formula;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An admin-authored calculation over the site's estimator fields. */
class EstimatorCalc extends Model
{
    use HasUlids;

    public const FORMATS = ['money', 'number', 'hours'];

    protected $fillable = ['site_id', 'estimator_id', 'name', 'formula', 'format', 'sort'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function estimator(): BelongsTo
    {
        return $this->belongsTo(Estimator::class);
    }

    /** Run this calc against resolved field values → [raw, formatted]. */
    public function run(array $vars, string $currency = 'gbp'): array
    {
        $raw = Formula::evaluate($this->formula, $vars) ?? 0.0;

        $formatted = match ($this->format) {
            'money' => Money::format((int) round($raw * 100), $currency),
            'hours' => rtrim(rtrim(number_format($raw, 1), '0'), '.').' h',
            default => rtrim(rtrim(number_format($raw, 2), '0'), '.'),
        };

        return ['name' => $this->name, 'format' => $this->format, 'raw' => round($raw, 4), 'formatted' => $formatted];
    }
}
