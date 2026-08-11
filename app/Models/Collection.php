<?php

namespace App\Models;

use App\Support\HasFieldSchema;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasFactory;
    use HasFieldSchema;
    use HasUlids;

    protected $fillable = [
        'site_id', 'name', 'slug', 'type', 'description', 'fields', 'is_public', 'allow_submit', 'auto_publish',
    ];

    protected $casts = [
        'fields' => 'array',
        'is_public' => 'boolean',
        'allow_submit' => 'boolean',
        'auto_publish' => 'boolean',
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

    /**
     * The canonical API shape — one definition shared by the collections
     * endpoint and the site-content payload.
     *
     * @param  bool  $withItems  include the items array
     * @param  bool  $everything  include all items regardless of status (else published only)
     */
    public function toApiArray(bool $withItems = true, bool $everything = false): array
    {
        $out = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'description' => $this->description,
            'fields' => $this->fields ?? [],
            'is_public' => (bool) $this->is_public,
            'allow_submit' => (bool) $this->allow_submit,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($withItems) {
            $items = $everything ? $this->items : $this->items->where('status', 'published')->values();
            $out['items'] = $items->map(fn (CollectionItem $i) => [
                'id' => $i->id,
                'data' => $i->data ?? [],
                'status' => $i->status,
                'created_at' => $i->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $out;
    }
}
