<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A typed content field on a Component. `parent` (an id, 0 = root) allows
 * simple nesting; `type` is one of TYPES — `collection` stores a Collection id
 * in `value`, linking the component to that collection's items.
 */
class Node extends Model
{
    public const TYPES = ['text', 'url', 'image', 'number', 'boolean', 'color', 'collection'];

    protected $fillable = ['component_id', 'parent', 'label', 'value', 'type', 'order', 'description'];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /** The linked Collection when this is a collection-typed node. */
    public function linkedCollection(): ?Collection
    {
        return $this->type === 'collection' && is_numeric($this->value)
            ? Collection::find((int) $this->value)
            : null;
    }
}
