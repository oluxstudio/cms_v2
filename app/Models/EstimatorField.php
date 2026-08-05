<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-defined estimator field. Visitor-entered (number / select /
 * toggle / text) or "fixed" — set data the admin controls that formulas
 * can reference without the visitor seeing an input.
 */
class EstimatorField extends Model
{
    public const TYPES = ['number', 'select', 'toggle', 'text', 'fixed'];

    protected $fillable = ['site_id', 'estimator_id', 'key', 'label', 'type', 'options', 'value', 'unit', 'required', 'sort'];

    protected $casts = ['options' => 'array', 'required' => 'boolean', 'value' => 'float'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function estimator(): BelongsTo
    {
        return $this->belongsTo(Estimator::class);
    }

    /** Resolve the numeric value this field contributes to formulas. */
    public function numericValue(mixed $input): float
    {
        return match ($this->type) {
            'fixed' => (float) ($this->value ?? 0),
            'toggle' => filter_var($input, FILTER_VALIDATE_BOOLEAN) ? 1.0 : 0.0,
            'select' => (float) (collect($this->options ?? [])->firstWhere('label', $input)['value']
                ?? (is_numeric($input) ? $input : 0)),
            default => is_numeric($input) ? (float) $input : 0.0,
        };
    }
}
