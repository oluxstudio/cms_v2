<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One node in a page's BlockKit tree. Layout blocks (container, h_list,
 * v_list, grid, masonry, form) have children; content blocks are leaves.
 * The tree is mutated ONLY through BlockTreeService — the same six operations
 * whether the client is the drag-and-drop editor or the AI assistant.
 */
class Block extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'page_id', 'layout_id', 'parent_id', 'position', 'type', 'props', 'style', 'meta'];

    protected $casts = ['props' => 'array', 'style' => 'array', 'meta' => 'array'];

    public static function newId(): string
    {
        return 'blk_'.Str::lower(Str::random(12));
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function isLayout(): bool
    {
        return (config('blockkit.types.'.$this->type.'.kind') ?? 'content') === 'layout';
    }

    public function isLocked(): bool
    {
        return (bool) data_get($this->meta, 'locked', false);
    }

    public function label(): string
    {
        return (string) (data_get($this->meta, 'label')
            ?: config('blockkit.types.'.$this->type.'.name', $this->type));
    }
}
