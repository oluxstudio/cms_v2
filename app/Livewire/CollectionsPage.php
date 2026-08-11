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
    use WithLayoutMode;

    public Site $site;

    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

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
        $this->reset(['viewingId', 'editingItemId', 'itemForm', 'memberSearch']);
    }

    public function deleteItem(string $itemId): void
    {
        CollectionItem::where('id', $itemId)
            ->whereHas('collection', fn ($q) => $q->where('site_id', $this->site->id))
            ->delete();
    }

    // ── Entry (collection item) editing ──────────────────────────────────

    public ?string $editingItemId = null;   // null when the item form is closed, '' when adding

    public array $itemForm = [];             // field key => value

    /** Open the entry editor — blank for a new entry, prefilled for an existing one. */
    public function openItem(?string $itemId = null): void
    {
        $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->viewingId);
        $keys = collect($collection->fields ?? [])->pluck('key')->all();

        if ($itemId) {
            $item = $collection->items()->findOrFail($itemId);
            $this->itemForm = collect($keys)->mapWithKeys(fn ($k) => [$k => (string) data_get($item->data, $k, '')])->all();
        } else {
            $this->itemForm = collect($keys)->mapWithKeys(fn ($k) => [$k => ''])->all();
        }
        $this->editingItemId = $itemId ?? '';
    }

    public function cancelItem(): void
    {
        $this->reset(['editingItemId', 'itemForm']);
    }

    public function saveItem(): void
    {
        $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->viewingId);
        $keys = collect($collection->fields ?? [])->pluck('key')->all();
        $data = collect($this->itemForm)->only($keys)->map(fn ($v) => is_string($v) ? trim($v) : $v)->all();

        if ($this->editingItemId) {
            $collection->items()->findOrFail($this->editingItemId)->update(['data' => $data]);
        } else {
            $collection->items()->create(['site_id' => $this->site->id, 'data' => $data, 'status' => 'published']);
        }
        $this->reset(['editingItemId', 'itemForm']);
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'type', 'description', 'editingId', 'allowSubmit', 'autoPublish', 'pageIds']);
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
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|min:2',
            'type' => 'required|in:list,grid,table',
            'description' => 'nullable|max:500',
        ]);

        if ($this->editingId) {
            $collection = CollectionModel::where('site_id', $this->site->id)->findOrFail($this->editingId);
            $collection->update([
                'name' => $this->name,
                'type' => $this->type,
                'description' => $this->description,
                'allow_submit' => $this->allowSubmit,
                'auto_publish' => $this->autoPublish,
            ]);
        } else {
            $collection = CollectionModel::create([
                'site_id' => $this->site->id,
                'name' => $this->name,
                'type' => $this->type,
                'description' => $this->description,
                'allow_submit' => $this->allowSubmit,
                'auto_publish' => $this->autoPublish,
            ]);
        }

        $this->syncPages($collection);

        $this->showModal = false;
        $this->reset(['name', 'type', 'description', 'editingId', 'allowSubmit', 'autoPublish', 'pageIds']);
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
