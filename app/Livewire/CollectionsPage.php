<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Livewire\Concerns\WithNestedFields;
use App\Livewire\Concerns\WithVisibilityFields;
use App\Models\Collection as CollectionModel;
use App\Models\CollectionItem;
use App\Models\CollectionItemEvent;
use App\Models\Component as ComponentModel;
use App\Models\Media;
use App\Models\Node;
use App\Models\Site;
use App\Support\CollectionFieldShape;
use App\Support\MediaValue;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class CollectionsPage extends Component
{
    use WithNestedFields;

    /** Roots the nested-field editor may mutate. */
    protected array $nestedRoots = ['itemForm.'];

    use WithLayoutMode;
    use WithVisibilityFields;

    public Site $site;

    public string $search = '';

    /** List filter: all | linked | unlinked | empty | pending */
    #[Url(except: 'all')]
    public string $filter = 'all';

    /** List order: updated | name | entries */
    #[Url(except: 'updated')]
    public string $sort = 'updated';

    public bool $showModal = false;

    public ?string $editingId = null;

    /** Collection whose engagement insights panel is open (null = closed). */
    public ?string $insightsId = null;

    public function toggleInsights(string $id): void
    {
        $this->insightsId = $this->insightsId === $id ? null : $id;
    }

    /** Per-item views/plays for the open collection, last 30 days. */
    public function getMediaInsightsProperty(): ?array
    {
        if (! $this->insightsId) {
            return null;
        }
        $collection = CollectionModel::where('site_id', $this->site->id)->find($this->insightsId);
        if (! $collection) {
            return null;
        }

        $rows = CollectionItemEvent::where('collection_id', $collection->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('collection_item_id, event, COUNT(*) as n')
            ->groupBy('collection_item_id', 'event')->get();

        $titles = $collection->items()->get()->mapWithKeys(fn ($i) => [
            $i->id => (string) ($i->data['title'] ?? $i->data['name'] ?? 'Item '.substr($i->id, -5)),
        ]);

        $per = [];
        foreach ($rows as $r) {
            $key = $r->collection_item_id;
            $per[$key] ??= ['label' => $titles[$key] ?? 'Deleted item', 'view' => 0, 'play' => 0];
            $per[$key][$r->event] = (int) $r->n;
        }
        $views = (int) array_sum(array_column($per, 'view'));
        $plays = (int) array_sum(array_column($per, 'play'));

        return [
            'name' => $collection->name,
            'views' => $views,
            'plays' => $plays,
            'rate' => $views > 0 ? (int) round($plays / $views * 100) : 0,
            // x-analytics.bar-list shape: [label => count], sorted desc.
            'top_viewed' => collect($per)->sortByDesc('view')->take(7)
                ->mapWithKeys(fn ($p, $id) => [$p['label'] ?: substr($id, -5) => $p['view']])->all(),
            'top_played' => collect($per)->sortByDesc('play')->take(7)
                ->mapWithKeys(fn ($p, $id) => [$p['label'] ?: substr($id, -5) => $p['play']])->all(),
        ];
    }

    public ?string $viewingId = null;

    // Form fields
    public string $name = '';

    public string $type = 'list';

    public string $description = '';

    public bool $allowSubmit = false;   // visitors may POST items via the modules API

    public bool $autoPublish = false;   // submissions go live instantly (else pending review)

    /** Page ids this collection is placed on. */
    public array $pageIds = [];

    /** Search for the "add component to collection" selector. */
    public string $memberSearch = '';

    public function mount(Site $site, ?string $screen = null, ?string $collection = null): void
    {
        $this->site = $site;
        $this->initLayout('collections', 'grid');

        // A new collection / a collection's settings: their own pages.
        if ($screen === 'new') {
            $this->openCreate();
        } elseif ($screen === 'settings' && $collection) {
            $this->openEdit($collection);
        }

        // Older deep links (?open={id}[&item={id}]) → the collection's / entry's own page.
        if (($id = (string) request()->query('open')) !== ''
            && CollectionModel::where('site_id', $site->id)->whereKey($id)->exists()) {
            $itemId = (string) request()->query('item');
            $this->redirect($itemId !== '' && CollectionModel::find($id)->items()->whereKey($itemId)->exists()
                ? route('collections.entries.show', [$site->name, $id, $itemId])
                : route('collections.show', [$site->name, $id]), navigate: true);
        }
    }

    /** Leave the settings page: back to the collection (or the list, for a new one). */
    public function cancelSettings(): void
    {
        $back = $this->editingId ? route('collections.show', [$this->site->name, $this->editingId]) : route('collections', $this->site->name);
        $this->showModal = false;
        $this->redirect($back, navigate: true);
    }

    public function render()
    {
        $all = CollectionModel::where('site_id', $this->site->id)
            ->withCount([
                'items',
                'items as published_count' => fn ($q) => $q->where('status', 'published'),
                'items as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'components', 'pages',
            ])
            ->withMax('items', 'updated_at')
            ->get();

        // Blocks that show a collection through a collection-type field (Hero → Hero Words).
        $fieldLinks = Node::where('type', 'collection')
            ->whereHas('component', fn ($q) => $q->where('site_id', $this->site->id))
            ->whereIn('value', $all->pluck('id'))
            ->get(['value', 'component_id'])
            ->groupBy('value')->map(fn ($g) => $g->pluck('component_id')->unique()->count());

        $all->each(function (CollectionModel $c) use ($fieldLinks) {
            $c->blocks_count = (int) $c->components_count + (int) ($fieldLinks[$c->id] ?? 0);
            $c->linked = $c->blocks_count > 0 || $c->pages_count > 0;
            $c->fields_count = count((array) $c->fields);
            $c->last_activity = max(array_filter([$c->items_max_updated_at ? Carbon::parse($c->items_max_updated_at) : null, $c->updated_at]));
        });

        $needle = mb_strtolower(trim($this->search));
        $collections = $all
            ->when($needle !== '', fn ($c) => $c->filter(fn ($col) => str_contains(mb_strtolower($col->name.' '.$col->type.' '.$col->description), $needle)))
            ->filter(fn ($c) => match ($this->filter) {
                'linked' => $c->linked,
                'unlinked' => ! $c->linked,
                'empty' => $c->items_count === 0,
                'pending' => $c->pending_count > 0,
                default => true,
            })
            ->sortBy(fn ($c) => match ($this->sort) {
                'name' => mb_strtolower($c->name),
                'entries' => -$c->items_count,
                default => -$c->last_activity->getTimestamp(),
            })
            ->values();

        $total = $all->count();
        $types = $all->pluck('type')->unique()->count();
        $recent = $all->where('created_at', '>=', now()->startOfWeek())->count();
        $stats = [
            'entries' => (int) $all->sum('published_count'),
            'pending' => (int) $all->sum('pending_count'),
            'empty' => $all->where('items_count', 0)->count(),
            'linked' => $all->where('linked', true)->count(),
            'unlinked' => $all->where('linked', false)->count(),
            // Visitor submissions that go live with no review.
            'autoPublish' => $all->filter(fn ($c) => $c->allow_submit && $c->auto_publish)->count(),
            'byType' => $all->countBy('type')->sortDesc()->all(),
            'largest' => $all->sortByDesc('items_count')->filter(fn ($c) => $c->items_count > 0)->take(5)->values(),
            'recentlyEdited' => $all->sortByDesc(fn ($c) => $c->last_activity->getTimestamp())->take(4)->values(),
            'pendingList' => $all->where('pending_count', '>', 0)->sortByDesc('pending_count')->values(),
            'emptyList' => $all->where('items_count', 0)->values(),
        ];

        $viewing = $this->viewingId
            ? CollectionModel::where('site_id', $this->site->id)->find($this->viewingId)
            : null;
        $entries = $viewing ? $viewing->items()->latest()->limit(200)->get() : collect();

        // Grouped components for the viewed collection + the site components
        // still free to add to it.
        $members = $viewing ? $viewing->components()->withCount('nodes')->get() : collect();
        $available = $viewing
            ? ComponentModel::where('site_id', $this->site->id)->whereNull('collection_id')
                ->when($this->memberSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->memberSearch.'%'))
                ->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.collections-page', [
            'collections' => $collections,
            'stats' => $stats,
            'total' => $total,
            'types' => $types,
            'recent' => $recent,
            'viewing' => $viewing,
            'entries' => $entries,
            'members' => $members,
            'available' => $available,
            'sitePages' => $this->site->pages()->orderBy('name')->get(['id', 'name', 'url']),
        ]);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'linked', 'unlinked', 'empty', 'pending'], true) ? $filter : 'all';
    }

    public function viewEntries(string $id): void
    {
        $this->viewingId = $id;
    }

    public function closeEntries(): void
    {
        $this->reset(['viewingId', 'editingItemId', 'itemForm', 'itemJsonKeys', 'itemLineKeys', 'memberSearch']);
    }

    public function deleteItem(string $itemId): void
    {
        CollectionItem::where('id', $itemId)
            ->whereHas('collection', fn ($q) => $q->where('site_id', $this->site->id))
            ->delete();
        $this->bustRenderCache($this->site);
    }

    // ── Entry (collection item) editing ──────────────────────────────────

    public ?string $editingItemId = null;   // null when the item form is closed, '' when adding

    /** Id of the entry the last saveItem() created (this request only). */
    protected ?string $createdItemId = null;

    public array $itemForm = [];             // field key => value

    /** Fields holding arrays/objects (multi-dimensional data) — edited as JSON. */
    public array $itemJsonKeys = [];

    /** Fields stored as one-path-per-line text (media lists) — edited as a gallery, saved back as lines. */
    public array $itemLineKeys = [];

    /** Open the entry editor — blank for a new entry, prefilled for an existing one. */
    public function openItem(?string $itemId = null): void
    {
        $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->viewingId);
        $keys = collect($collection->fields ?? [])->pluck('key')->all();

        $item = $itemId ? $collection->items()->findOrFail($itemId) : null;
        // Declared list fields (gallery/tags) stored as text → arrays for the editors.
        if ($item) {
            $item->data = CollectionFieldShape::shape((array) ($item->data ?? []), (array) ($collection->fields ?? []));
        }

        // A field is JSON-edited when its schema says so, or when any stored
        // entry holds an array under it (schemas often say "text" for arrays).
        $declared = collect($collection->fields ?? [])
            ->filter(fn ($f) => in_array($f['type'] ?? '', ['json', 'list', 'array', 'images', 'tags'], true))
            ->pluck('key')->all();
        $samples = $item ? collect([$item]) : $collection->items()->latest()->limit(20)->get();
        // Array values: STRUCTURED editing when the shape is uniform (scalar
        // list / row list / group); only irregular/deep values fall back to
        // the raw-JSON textarea.
        $types = collect($collection->fields ?? [])->mapWithKeys(fn ($f) => [($f['key'] ?? '') => $f['type'] ?? 'text']);
        // Declared TEXT fields (text, textareas, dates, media…) always edit as
        // text, even if an older entry stored a list under them.
        $arrayKeys = collect($keys)->filter(fn ($k) => ! CollectionFieldShape::isTextType($types[$k] ?? null)
            && (in_array($k, $declared, true) || $samples->contains(fn ($i) => is_array(data_get($i->data, $k)))))->values();
        $sampleFor = fn ($k) => $item ? data_get($item->data, $k) : $samples->map(fn ($i) => data_get($i->data, $k))->first(fn ($v) => is_array($v));
        // An EMPTY list edits structurally too (the field's schema shapes its first row).
        $this->itemJsonKeys = $arrayKeys->reject(fn ($k) => ($sampleFor($k) ?? []) === [] || self::nestedEditable($sampleFor($k)))->values()->all();

        $this->itemForm = collect($keys)->mapWithKeys(function ($k) use ($item, $arrayKeys, $types) {
            $v = $item ? data_get($item->data, $k, '') : '';
            if (CollectionModel::isBooleanType($types[$k] ?? null)) {
                return [$k => filter_var($v, FILTER_VALIDATE_BOOLEAN)]; // a real true/false for the checkbox
            }
            if (in_array($k, $this->itemJsonKeys, true)) {
                $v = is_array($v) ? $v : ($v === '' || $v === null ? [] : [$v]);

                return [$k => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
            }
            if ($arrayKeys->contains($k)) {
                return [$k => is_array($v) ? $v : []]; // structured editor binds in place
            }

            return [$k => is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES) : (string) $v];
        })->all();
        // Several media paths in one text value → edit as a gallery (saved back as lines).
        $this->itemLineKeys = [];
        foreach ($this->itemForm as $k => $v) {
            if (($paths = MediaValue::lines($v, $collection->site_id)) !== null) {
                $this->itemForm[$k] = $paths;
                $this->itemLineKeys[] = $k;
            }
        }
        $this->editingItemId = $itemId ?? '';
    }

    /** Asset-library options for url-type fields (photo pickers). */
    public function getMediaUrlOptionsProperty(): array
    {
        return Media::where('site_id', $this->site->id)
            ->where('file_type', 'image')->latest()->limit(200)
            ->get()->map(fn ($m) => ['url' => $m->url, 'name' => $m->name])->all();
    }

    /** Asset picked in the media dialog → drop its URL into the item field. */
    #[On('media-picked')]
    public function onMediaPicked(array $context, string $mediaRef, string $url): void
    {
        // Every picker stores the portable "@media/{filename}" reference.
        $value = $mediaRef !== '' ? $mediaRef : $url;
        if ($this->nestedMediaPicked($context, $value)) {
            return;
        }
        if (($context['scope'] ?? '') === 'collection-item' && isset($context['key'])) {
            $this->itemForm[$context['key']] = $value;
        }
    }

    public function cancelItem(): void
    {
        $this->reset(['editingItemId', 'itemForm', 'itemJsonKeys', 'itemLineKeys']);
    }

    public function saveItem(): void
    {
        $this->resetErrorBag();
        $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->viewingId);
        $keys = collect($collection->fields ?? [])->pluck('key')->all();

        // JSON fields decode back to arrays/objects; invalid JSON blocks the save.
        foreach ($this->itemJsonKeys as $k) {
            $raw = trim((string) ($this->itemForm[$k] ?? ''));
            json_decode($raw === '' ? '[]' : $raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError("itemForm.{$k}", 'Invalid JSON: '.json_last_error_msg());

                return;
            }
        }

        // Typed fields: numbers/sliders must be numbers, emails valid — then stored as their type.
        $defs = collect($collection->fields ?? [])->keyBy('key');
        foreach ($defs as $k => $f) {
            $v = $this->itemForm[$k] ?? null;
            if (! is_scalar($v) || trim((string) $v) === '') {
                continue;
            }
            $type = $f['type'] ?? 'text';
            if (in_array($type, ['number', 'slider'], true) && ! is_numeric(trim((string) $v))) {
                $this->addError("itemForm.{$k}", ($f['label'] ?? $k).' must be a number.');
            } elseif ($type === 'email' && ! filter_var(trim((string) $v), FILTER_VALIDATE_EMAIL)) {
                $this->addError("itemForm.{$k}", ($f['label'] ?? $k).' must be a valid email address.');
            }
        }
        // Required fields (a template's @olux-field … required, or Edit fields).
        foreach ($defs as $k => $f) {
            if (! empty($f['required']) && empty($f['hidden']) && empty($f['auto']) && CollectionFieldShape::missingRequired([$k => $this->itemForm[$k] ?? null], [$f]) !== []) {
                $this->addError("itemForm.{$k}", ($f['label'] ?? $k).' is required.');
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $data = collect($this->itemForm)->only($keys)->map(function ($v, $k) use ($defs) {
            if (in_array($k, $this->itemLineKeys, true) && is_array($v)) {
                return implode("\n", array_values(array_filter(array_map(fn ($x) => trim((string) $x), $v), fn ($x) => $x !== '')));
            }
            $type = $defs[$k]['type'] ?? 'text';
            if (CollectionModel::isBooleanType($type)) {
                return filter_var($v, FILTER_VALIDATE_BOOLEAN);
            }
            if (in_array($type, ['number', 'slider'], true) && is_scalar($v) && is_numeric(trim((string) $v))) {
                return 0 + trim((string) $v); // 4 → 4, 4.5 → 4.5
            }
            if (in_array($k, $this->itemJsonKeys, true)) {
                $raw = trim((string) $v);

                return json_decode($raw === '' ? '[]' : $raw, true);
            }
            if (is_array($v)) {
                return $v; // structured nested value — persisted as-is
            }

            return is_string($v) ? trim($v) : $v;
        })->all();

        if ($this->editingItemId) {
            $collection->items()->findOrFail($this->editingItemId)->update(['data' => $data]);
        } else {
            $this->createdItemId = $collection->items()->create(['site_id' => $this->site->id, 'data' => $data, 'status' => 'published'])->id;
        }
        $this->reset(['editingItemId', 'itemForm', 'itemJsonKeys', 'itemLineKeys']);
        $this->bustRenderCache($this->site);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Entry saved — the site shows it on the next load.');
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'type', 'description', 'editingId', 'allowSubmit', 'autoPublish', 'pageIds']);
        $this->resetVisibilityFields();
        $this->type = 'list';
        $this->showModal = true;
    }

    public function openEdit(string $id): void
    {
        $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($id);
        $this->editingId = $id;
        $this->name = $collection->name;
        $this->type = $collection->type;
        $this->description = $collection->description ?? '';
        $this->allowSubmit = (bool) $collection->allow_submit;
        $this->autoPublish = (bool) $collection->auto_publish;
        $this->pageIds = $collection->pages()->pluck('pages.id')->map(fn ($v) => (string) $v)->all();
        $this->hydrateVisibilityFields($collection->visibility);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|min:2',
            'type' => 'required|in:list,grid,table',
            'description' => 'nullable|max:500',
        ]);
        $visibility = $this->assembleVisibility();

        if ($this->editingId) {
            $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->editingId);
            $collection->update([
                'name' => $this->name,
                'visibility' => $visibility,
                'type' => $this->type,
                'description' => $this->description,
                'allow_submit' => $this->allowSubmit,
                'auto_publish' => $this->autoPublish,
            ]);
        } else {
            $collection = CollectionModel::create([
                'site_id' => $this->site->id,
                'name' => $this->name,
                'visibility' => $visibility,
                'type' => $this->type,
                'description' => $this->description,
                'allow_submit' => $this->allowSubmit,
                'auto_publish' => $this->autoPublish,
            ]);
        }

        $this->syncPages($collection);
        $this->bustRenderCache($this->site);

        $this->showModal = false;
        // Saved → the collection's own page.
        $this->redirect(route('collections.show', [$this->site->name, $collection->id]), navigate: true);
        $this->reset(['name', 'type', 'description', 'editingId', 'allowSubmit', 'autoPublish', 'pageIds']);
        $this->resetVisibilityFields();
    }

    /** Attach the collection to the selected pages (order preserved/appended). */
    private function syncPages(CollectionModel $collection): void
    {
        $valid = $this->site->pages()->whereIn('id', $this->pageIds)->pluck('id');
        $attach = [];
        foreach ($valid as $pageId) {
            $current = $collection->pages()->where('pages.id', $pageId)->first();
            $order = $current->pivot->order
                ?? ((int) \DB::table('page_collection')->where('page_id', $pageId)->max('order') + 1);
            $attach[$pageId] = ['order' => $order];
        }
        $collection->pages()->sync($attach);
    }

    // ── Member components (the components this collection groups) ─────────

    /** Add an existing standalone component to the viewed collection. */
    public function addComponent(string $componentId): void
    {
        $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->viewingId);
        $component = ComponentModel::where('site_id', $this->site->id)->findOrFail($componentId);
        $component->update([
            'collection_id' => $collection->id,
            'collection_order' => (int) ComponentModel::where('collection_id', $collection->id)->max('collection_order') + 1,
        ]);
    }

    /** Remove a component from the collection (it becomes standalone again). */
    public function removeComponent(string $componentId): void
    {
        ComponentModel::where('site_id', $this->site->id)->where('collection_id', $this->viewingId)
            ->where('id', $componentId)
            ->update(['collection_id' => null, 'collection_order' => null]);
    }

    /** Reorder a member component within the collection. */
    public function moveComponent(string $componentId, int $dir): void
    {
        $members = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->viewingId)
            ->components()->get();
        $index = $members->search(fn ($c) => $c->id === $componentId);
        $to = $index + $dir;
        if ($index === false || ! isset($members[$to])) {
            return;
        }
        $a = $members[$index];
        $b = $members[$to];
        // Swap their order values (normalise nulls to positions first).
        $members->values()->each(fn ($c, $i) => $c->collection_order ??= $i);
        [$a->collection_order, $b->collection_order] = [$b->collection_order, $a->collection_order];
        $a->save();
        $b->save();
    }

    /** Delete a collection — confirmation happens in the shared modal (data-confirm). */
    public function deleteCollection(string $id): void
    {
        CollectionModel::where('site_id', $this->site->id)->findOrFail($id)->delete();
    }
}
