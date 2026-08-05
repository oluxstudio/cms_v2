<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = ['site_id', 'layout_id', 'block_layout_id', 'site_template_id', 'name', 'url', 'keywords', 'is_published'];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /** The chosen layout wrapper for this page (null = site default layout). */
    public function pageAttributes(): HasMany
    {
        return $this->hasMany(PageAttribute::class);
    }

    /** Read a single attribute value by key, falling back to $default. */
    public function getAttr(string $key, mixed $default = null): mixed
    {
        return $this->pageAttributes()->where('key', $key)->value('value') ?? $default;
    }

    /** Create or update an attribute, returning the saved row. */
    public function setAttr(string $key, ?string $value): PageAttribute
    {
        return $this->pageAttributes()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /** Remove an attribute by key. Returns the number of rows deleted. */
    public function forgetAttr(string $key): int
    {
        return $this->pageAttributes()->where('key', $key)->delete();
    }

    /** All attributes as a flat [key => value] array. */
    public function attrMap(): array
    {
        return $this->pageAttributes()->pluck('value', 'key')->all();
    }

    /** The BlockKit layout this page renders inside (null = the Blank system layout). */
    public function blockLayout(): BelongsTo
    {
        return $this->belongsTo(BlockLayout::class);
    }

    /** Resolved BlockKit layout — falls back to the site's undeletable Blank. */
    public function resolvedBlockLayout(): BlockLayout
    {
        return $this->blockLayout ?? BlockLayout::blank($this->site);
    }

    /** Reusable content Components placed on this page (ordered, with settings). */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'page_component')
            ->withPivot(['order', 'settings'])
            ->withTimestamps()
            ->orderBy('page_component.order');
    }
}
