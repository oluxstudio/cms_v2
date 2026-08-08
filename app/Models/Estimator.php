<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A named estimator (e.g. "Cleaner") — a site can run many. Each owns its
 * fields, its calculations and its own customer email template.
 */
class Estimator extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'name', 'slug', 'email_subject', 'email_body', 'sort'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(EstimatorField::class)->orderBy('sort')->orderBy('id');
    }

    public function calcs(): HasMany
    {
        return $this->hasMany(EstimatorCalc::class)->orderBy('sort')->orderBy('id');
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    /** Unique slug for a new estimator within a site. */
    public static function slugFor(string $siteId, string $name): string
    {
        $base = Str::slug($name) ?: 'estimator';
        $slug = $base;
        $n = 1;
        while (static::where('site_id', $siteId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    /** The drafted email subject, with a sensible default. */
    public function emailSubject(): string
    {
        return $this->email_subject ?: 'Your {service} estimate {reference} from {site}';
    }

    /** The drafted email body, with a sensible default. */
    public function emailBody(): string
    {
        return $this->email_body
            ?: "Hi {name},\n\nThanks for requesting a {service} estimate from {site}. Here is what we calculated for you — reference {reference}.\n\nWe'll be in touch shortly to talk it through.";
    }
}
