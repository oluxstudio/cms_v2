<?php

namespace App\Models;

use App\Support\HasFieldSchema;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasFactory;
    use HasFieldSchema;

    protected $fillable = [
        'site_id', 'name', 'slug', 'type', 'description', 'fields', 'is_public', 'allow_submit',
    ];

    protected $casts = [
        'fields'       => 'array',
        'is_public'    => 'boolean',
        'allow_submit' => 'boolean',
    ];

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn ($value, array $attrs) => Str::slug($value ?: ($attrs['name'] ?? '')),
        );
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class);
    }

    public function displayTitle(): string
    {
        return $this->name ?: ucwords(str_replace(['-', '_'], ' ', (string) $this->slug));
    }
}
