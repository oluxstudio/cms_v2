<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Collection as CollectionModel;
use App\Models\CollectionItem;
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

        return view('livewire.collections-page', [
            'collections' => $collections,
            'total' => $total,
            'types' => $types,
            'recent' => $recent,
            'viewing' => $viewing,
            'entries' => $entries,
        ]);
    }

    public function viewEntries(string $id): void
    {
        $this->viewingId = $id;
    }

    public function closeEntries(): void
    {
        $this->reset(['viewingId', 'editingItemId', 'itemForm']);
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
        $this->reset(['name', 'type', 'description', 'editingId', 'allowSubmit', 'autoPublish']);
        $this->type = 'list';
        $this->showModal = true;
    }

    public function openEdit(string $id): void
    {
        $collection = CollectionModel::findOrFail($id);
        $this->editingId = $id;
        $this->name = $collection->name;
        $this->type = $collection->type;
        $this->description = $collection->description ?? '';
        $this->allowSubmit = (bool) $collection->allow_submit;
        $this->autoPublish = (bool) $collection->auto_publish;
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
            CollectionModel::findOrFail($this->editingId)->update([
                'name' => $this->name,
                'type' => $this->type,
                'description' => $this->description,
                'allow_submit' => $this->allowSubmit,
                'auto_publish' => $this->autoPublish,
            ]);
        } else {
            CollectionModel::create([
                'site_id' => $this->site->id,
                'name' => $this->name,
                'type' => $this->type,
                'description' => $this->description,
                'allow_submit' => $this->allowSubmit,
                'auto_publish' => $this->autoPublish,
            ]);
        }

        $this->showModal = false;
        $this->reset(['name', 'type', 'description', 'editingId', 'allowSubmit', 'autoPublish']);
    }

    /** Delete a collection — confirmation happens in the shared modal (data-confirm). */
    public function deleteCollection(string $id): void
    {
        CollectionModel::where('site_id', $this->site->id)->findOrFail($id)->delete();
    }
}
