<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
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
    use HasUlids;

    protected $fillable = ['site_id', 'site_template_id', 'name', 'author', 'created_by', 'source', 'description', 'tags'];

    protected $casts = ['tags' => 'array'];

    /** The user who created this component (null for legacy/system rows). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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
        $ids = $this->nodes->where('type', 'collection')->pluck('value')->filter();

        return Collection::whereIn('id', $ids)->get();
    }

    /**
     * The API shape of a component — one definition used by the components
     * CRUD API and the site content payload: the component with ALL its
     * nodes (ordered) and the collections its collection-nodes point at.
     */
    public function payload(bool $withPages = false): array
    {
        $out = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'tags' => array_values($this->tags ?? []),
            'source' => $this->source ?? 'app',
            'created_by' => $this->creator?->name ?? $this->author,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'nodes' => $this->nodes->map(fn ($n) => [
                'id' => $n->id,
                'label' => $n->label,
                'type' => $n->type,
                'value' => $n->value,
                'parent' => $n->parent,
                'order' => (int) $n->order,
                'description' => $n->description,
            ])->values()->all(),
            // Nested form of the same nodes (assembled via `parent`) so clients
            // can render the tree directly without stitching the flat list.
            'node_tree' => self::buildNodeTree($this->nodes),
            'collections' => $this->collections()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
        ];

        if ($withPages) {
            $out['pages'] = $this->pages->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'url' => $p->url, 'order' => (int) $p->pivot->order,
            ])->values()->all();
        }

        return $out;
    }

    /**
     * Assemble a flat node collection into a nested tree using each node's
     * `parent` (root nodes have parent '0'/0/''/null). Children are ordered.
     */
    public static function buildNodeTree($nodes): array
    {
        $isRoot = fn ($p) => $p === null || $p === '' || $p === '0' || $p === 0;

        $byParent = [];
        foreach ($nodes as $n) {
            $key = $isRoot($n->parent) ? '__root__' : (string) $n->parent;
            $byParent[$key][] = $n;
        }

        $build = function ($parentKey) use (&$build, &$byParent) {
            $rows = $byParent[$parentKey] ?? [];
            usort($rows, fn ($a, $b) => (int) $a->order <=> (int) $b->order);

            return array_map(fn ($n) => [
                'id' => $n->id,
                'label' => $n->label,
                'type' => $n->type,
                'value' => $n->value,
                'order' => (int) $n->order,
                'description' => $n->description,
                'children' => $build((string) $n->id),
            ], $rows);
        };

        return $build('__root__');
    }
}
