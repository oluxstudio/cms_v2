<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable content component: a named bag of typed NODES (its fields),
 * attachable to Pages via the page_component pivot (ordered, with settings)
 * and linkable to Collections through collection-typed nodes.
 */
class Component extends Model
{
    protected $fillable = ['site_id', 'site_template_id', 'name', 'author', 'description'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** The component's content fields, in display order. */
    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class)->orderBy('order');
    }

    /** Pages this component is attached to (ordered placement + settings). */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_component')
            ->withPivot(['order', 'settings'])
            ->withTimestamps();
    }

    /** Collections referenced by this component's collection-typed nodes. */
    public function collections()
    {
        $ids = $this->nodes->where('type', 'collection')->pluck('value')
            ->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v);

        return Collection::whereIn('id', $ids)->get();
    }
}
