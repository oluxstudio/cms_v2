<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Collection as CollectionModel;
use App\Models\CollectionItem;
use App\Models\Component as ComponentModel;
use App\Models\Site;
use Livewire\Component;

class CollectionsPage extends Component
{
    use \App\Livewire\Concerns\WithVisibilityFields;

    use WithLayoutMode;

    public Site $site;

    public string $search = '';

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

        $rows = \App\Models\CollectionItemEvent::where('collection_id', $collection->id)
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

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('collections', 'list');

        // Deep link (?open={id}) — e.g. a component's "Manage data source →".
        if (($id = (string) request()->query('open')) !== ''
            && CollectionModel::where('site_id', $site->id)->whereKey($id)->exists()) {
            $this->viewingId = $id;
        }
    }

    public function render()
    {
        $collections = CollectionModel::where('site_id', $this->site->id)
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('type', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            }))
            ->latest()
            ->get();

        $total = CollectionModel::where('site_id', $this->site->id)->count();
        $types = CollectionModel::where('site_id', $this->site->id)->distinct('type')->count('type');
        $recent = CollectionModel::where('site_id', $this->site->id)
            ->where('created_at', '>=', now()->startOfWeek())->count();

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

    public function viewEntries(string $id): void
    {
        $this->viewingId = $id;
    }

    public function closeEntries(): void
    {
        $this->reset(['viewingId', 'editingItemId', 'itemForm', 'itemJsonKeys', 'memberSearch']);
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

    public array $itemForm = [];             // field key => value

    /** Fields holding arrays/objects (multi-dimensional data) — edited as JSON. */
    public array $itemJsonKeys = [];

    /** Open the entry editor — blank for a new entry, prefilled for an existing one. */
    public function openItem(?string $itemId = null): void
    {
        $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->viewingId);
        $keys = collect($collection->fields ?? [])->pluck('key')->all();

        $item = $itemId ? $collection->items()->findOrFail($itemId) : null;

        // A field is JSON-edited when its schema says so, or when any stored
        // entry holds an array under it (schemas often say "text" for arrays).
        $declared = collect($collection->fields ?? [])
            ->filter(fn ($f) => in_array($f['type'] ?? '', ['json', 'list', 'array'], true))
            ->pluck('key')->all();
        $samples = $item ? collect([$item]) : $collection->items()->latest()->limit(20)->get();
        $this->itemJsonKeys = collect($keys)->filter(fn ($k) => in_array($k, $declared, true)
            || $samples->contains(fn ($i) => is_array(data_get($i->data, $k))))->values()->all();

        $this->itemForm = collect($keys)->mapWithKeys(function ($k) use ($item) {
            $v = $item ? data_get($item->data, $k, '') : '';
            if (in_array($k, $this->itemJsonKeys, true)) {
                $v = is_array($v) ? $v : ($v === '' || $v === null ? [] : [$v]);

                return [$k => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
            }

            return [$k => is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES) : (string) $v];
        })->all();
        $this->editingItemId = $itemId ?? '';
    }

    /** Asset-library options for url-type fields (photo pickers). */
    public function getMediaUrlOptionsProperty(): array
    {
        return \App\Models\Media::where('site_id', $this->site->id)
            ->where('file_type', 'image')->latest()->limit(200)
            ->get()->map(fn ($m) => ['url' => $m->url, 'name' => $m->name])->all();
    }

    /** Asset picked in the media dialog → drop its URL into the item field. */
    #[\Livewire\Attributes\On('media-picked')]
    public function onMediaPicked(array $context, string $mediaRef, string $url): void
    {
        if (($context['scope'] ?? '') === 'collection-item' && isset($context['key'])) {
            $this->itemForm[$context['key']] = $url;
        }
    }

    public function cancelItem(): void
    {
        $this->reset(['editingItemId', 'itemForm', 'itemJsonKeys']);
    }

    public function saveItem(): void
    {
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

        $data = collect($this->itemForm)->only($keys)->map(function ($v, $k) {
            if (in_array($k, $this->itemJsonKeys, true)) {
                $raw = trim((string) $v);

                return json_decode($raw === '' ? '[]' : $raw, true);
            }

            return is_string($v) ? trim($v) : $v;
        })->all();

        if ($this->editingItemId) {
            $collection->items()->findOrFail($this->editingItemId)->update(['data' => $data]);
        } else {
            $collection->items()->create(['site_id' => $this->site->id, 'data' => $data, 'status' => 'published']);
        }
        $this->reset(['editingItemId', 'itemForm', 'itemJsonKeys']);
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
