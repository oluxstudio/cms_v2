<?php

namespace App\Models;

use App\Models\Concerns\HasVisibilityRules;
use App\Support\CollectionQuery;
use App\Support\HasFieldSchema;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasFactory, HasVisibilityRules;
    use HasFieldSchema;
    use HasUlids;

    protected $fillable = [
        'site_id', 'name', 'slug', 'type', 'description', 'fields', 'is_public', 'allow_submit', 'auto_publish',
        'visibility',
    ];

    protected $casts = [
        'fields' => 'array',
        'is_public' => 'boolean',
        'allow_submit' => 'boolean',
        'auto_publish' => 'boolean',
        'visibility' => 'array',
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
        // NULL positions (legacy rows) sort last, then by age — stable order.
        return $this->hasMany(CollectionItem::class)
            ->orderByRaw('position is null')->orderBy('position')->orderBy('created_at');
    }

    /** The components grouped by this collection (e.g. testimonial 1, 2, 3…), ordered. */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class)->orderByRaw('collection_order is null, collection_order')->orderBy('id');
    }

    /** Pages this collection is attached to (ordered placement + settings). */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_collection')
            ->withPivot(['order', 'settings'])
            ->withTimestamps();
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
            'visibility' => $this->visibilityPayload(),
            'allow_submit' => (bool) $this->allow_submit,
            // The components grouped by this collection (each with nodes + node_tree).
            'components' => $this->components()->with('nodes')->get()
                ->map(fn (Component $c) => $c->payload())->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($withItems) {
            $items = $everything ? $this->items : $this->items->where('status', 'published')->values();
            $out['items'] = $items->map(fn (CollectionItem $i) => [
                'id' => $i->id,
                // @media/… refs anywhere in the entry (galleries and nested
                // rows too) resolve to served URLs.
                'data' => Media::resolveDeep($this->site_id, $i->data ?? []),
                'status' => $i->status,
                'created_at' => $i->created_at?->toIso8601String(),
            ])->values()->all();
            if ($views = $this->blockViews($everything ? $this->items->where('status', 'published')->values() : $items)) {
                $out['views'] = $views;
            }
        }

        return $out;
    }

    /**
     * Per-block selections: blocks that read this collection with a saved
     * query (Edit page → "Items in this block") get the ids they show, keyed
     * by the block's slug ("Events Grid" → "events-grid", which templates
     * derive from EventsGridBlock.vue) and by its id ("#01h…", for blocks with
     * one copy per page). The full items list is unchanged.
     *
     * @return array<string, array{block: string, ids: list<string>, query: array}>
     */
    /** @var array<string, \Illuminate\Support\Collection> per-request: site id → blocks with queries */
    private static array $queryBlocks = [];

    public static function forgetBlockQueries(?string $siteId = null): void
    {
        if ($siteId) {
            unset(self::$queryBlocks[$siteId]);
        } else {
            self::$queryBlocks = [];
        }
    }

    public function blockViews(?\Illuminate\Support\Collection $published = null): array
    {
        // One lookup per site per request (a payload serialises every collection).
        self::$queryBlocks[$this->site_id] ??= Component::where('site_id', $this->site_id)->whereNotNull('collection_queries')
            ->get(['id', 'name', 'collection_queries']);
        $blocks = self::$queryBlocks[$this->site_id]
            ->filter(fn (Component $c) => ! empty(($c->collection_queries ?? [])[$this->id] ?? null));
        if ($blocks->isEmpty()) {
            return [];
        }
        $published ??= $this->items->where('status', 'published')->values();
        $views = [];
        foreach ($blocks as $c) {
            $q = CollectionQuery::normalize($c->collectionQuery($this->id), CollectionQuery::fieldKeys($this));
            if ($q === []) {
                continue;
            }
            $view = [
                'block' => $c->name,
                'ids' => CollectionQuery::apply($this, $q, $published)->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                'query' => $q,
            ];
            $views[Str::slug($c->name)] = $view;
            // also by the block's id: copies of one block on several pages (a per-page
            // block like a ministry page's) each keep their own selection
            $views['#'.$c->id] = $view;
        }

        return $views;
    }
}
