<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use Livewire\Component;
use App\Models\Site;
use App\Models\Collection as CollectionModel;

class CollectionsPage extends Component
{
    use WithLayoutMode;

    public Site $site;
    public string $search = '';
    public bool $showModal = false;
    public int $editingId = 0;
    public int $viewingId = 0;

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

        $total  = CollectionModel::where('site_id', $this->site->id)->count();
        $types  = CollectionModel::where('site_id', $this->site->id)->distinct('type')->count('type');
        $recent = CollectionModel::where('site_id', $this->site->id)
                                 ->where('created_at', '>=', now()->startOfWeek())->count();

        $viewing = $this->viewingId
            ? CollectionModel::where('site_id', $this->site->id)->find($this->viewingId)
            : null;
        $entries = $viewing ? $viewing->items()->latest()->limit(200)->get() : collect();

        return view('livewire.collections-page', [
            'collections' => $collections,
            'total'       => $total,
            'types'       => $types,
            'recent'      => $recent,
            'viewing'     => $viewing,
            'entries'     => $entries,
        ]);
    }

    public function viewEntries(int $id): void
    {
        $this->viewingId = $id;
    }

    public function closeEntries(): void
    {
        $this->viewingId = 0;
    }

    public function deleteItem(int $itemId): void
    {
        \App\Models\CollectionItem::where('id', $itemId)
            ->whereHas('collection', fn ($q) => $q->where('site_id', $this->site->id))
            ->delete();
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'type', 'description', 'editingId', 'allowSubmit', 'autoPublish']);
        $this->type = 'list';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $collection        = CollectionModel::findOrFail($id);
        $this->editingId   = $id;
        $this->name        = $collection->name;
        $this->type        = $collection->type;
        $this->description = $collection->description ?? '';
        $this->allowSubmit = (bool) $collection->allow_submit;
        $this->autoPublish = (bool) $collection->auto_publish;
        $this->showModal   = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'        => 'required|min:2',
            'type'        => 'required|in:list,grid,table',
            'description' => 'nullable|max:500',
        ]);

        if ($this->editingId) {
            CollectionModel::findOrFail($this->editingId)->update([
                'name'         => $this->name,
                'type'         => $this->type,
                'description'  => $this->description,
                'allow_submit' => $this->allowSubmit,
                'auto_publish' => $this->autoPublish,
            ]);
        } else {
            CollectionModel::create([
                'site_id'      => $this->site->id,
                'name'         => $this->name,
                'type'         => $this->type,
                'description'  => $this->description,
                'allow_submit' => $this->allowSubmit,
                'auto_publish' => $this->autoPublish,
            ]);
        }

        $this->showModal = false;
        $this->reset(['name', 'type', 'description', 'editingId', 'allowSubmit', 'autoPublish']);
    }

    /** Delete a collection — confirmation happens in the shared modal (data-confirm). */
    public function deleteCollection(int $id): void
    {
        CollectionModel::where('site_id', $this->site->id)->findOrFail($id)->delete();
    }
}
