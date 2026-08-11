<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

/**
 * Components admin — STANDALONE content components: named bags of typed
 * nodes the admin builds on their own, then optionally attaches to pages
 * (ordered pivot) or links to collections via collection-typed nodes.
 */
class ComponentsPage extends LivewireComponent
{
    use WithLayoutMode;

    public Site $site;

    public string $search = '';

    /** Active tag filter ('' = all). */
    public string $filterTag = '';

    // Editor state (null = closed, 0 = new)
    public ?string $editingId = null;

    public string $cName = '';

    public string $cDescription = '';

    /** Comma-separated tags, stored as an array on the component. */
    public string $cTags = '';

    /** Node rows: [{label, type, value, description}] */
    public array $nodes = [];

    /** Page ids this component is attached to. */
    public array $pageIds = [];

    /** The collection this component belongs to ('' = none). */
    public string $collectionId = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('components', 'grid');
    }

    public function setTag(string $tag): void
    {
        $this->filterTag = $this->filterTag === $tag ? '' : $tag;
    }

    /** Distinct tags across the site's components, for the filter chips. */
    public function getComponentTagsProperty(): array
    {
        return $this->site->contentComponents()->get()
            ->flatMap(fn ($c) => $c->tags ?? [])->unique()->sort()->values()->all();
    }

    private function guard(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'components.manage'), 403);
    }

    public function getCanManageProperty(): bool
    {
        return $this->site->allows(Auth::user(), 'components.manage');
    }

    public function getComponentsProperty()
    {
        return $this->site->contentComponents()
            ->with(['nodes', 'pages'])
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->filterTag !== '', fn ($q) => $q->whereJsonContains('tags', $this->filterTag))
            ->get();
    }

    public function getSitePagesProperty()
    {
        return $this->site->pages()->orderBy('name')->get(['id', 'name', 'url']);
    }

    public function getSiteCollectionsProperty()
    {
        return $this->site->collections()->orderBy('name')->get(['id', 'name']);
    }

    public function getNodeTypesProperty(): array
    {
        return Node::TYPES;
    }

    // ── Editor ───────────────────────────────────────────────────

    public function open(string $id = ''): void
    {
        $this->guard();
        $this->errorMessage = '';
        $this->editingId = $id;

        if ($id) {
            $c = $this->site->contentComponents()->with(['nodes', 'pages'])->findOrFail($id);
            $this->cName = $c->name;
            $this->cDescription = (string) $c->description;
            $this->cTags = implode(', ', $c->tags ?? []);
            $this->nodes = $c->nodes->map(fn ($n) => [
                'label' => $n->label, 'type' => $n->type,
                'value' => (string) $n->value, 'description' => (string) $n->description,
            ])->values()->all();
            $this->pageIds = $c->pages->pluck('id')->map(fn ($v) => (string) $v)->all();
            $this->collectionId = (string) ($c->collection_id ?? '');
        } else {
            $this->reset(['cName', 'cDescription', 'cTags', 'pageIds', 'collectionId']);
            $this->nodes = [['label' => '', 'type' => 'text', 'value' => '', 'description' => '']];
        }
    }

    public function close(): void
    {
        $this->reset(['editingId', 'cName', 'cDescription', 'cTags', 'nodes', 'pageIds', 'collectionId']);
    }

    /** Parse the comma-separated tags box → clean unique array. */
    private function parsedTags(): array
    {
        return collect(explode(',', $this->cTags))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    public function addNode(): void
    {
        $this->nodes[] = ['label' => '', 'type' => 'text', 'value' => '', 'description' => ''];
    }

    public function removeNode(int $index): void
    {
        unset($this->nodes[$index]);
        $this->nodes = array_values($this->nodes);
    }

    public function moveNode(int $index, int $dir): void
    {
        $to = $index + $dir;
        if (! isset($this->nodes[$index], $this->nodes[$to])) {
            return;
        }
        [$this->nodes[$index], $this->nodes[$to]] = [$this->nodes[$to], $this->nodes[$index]];
    }

    public function save(): void
    {
        $this->guard();
        $this->validate([
            'cName' => ['required', 'string', 'max:120'],
            'cDescription' => ['nullable', 'string', 'max:500'],
        ]);

        $rows = collect($this->nodes)
            ->filter(fn ($n) => trim($n['label'] ?? '') !== '')
            ->values();
        if ($rows->isEmpty()) {
            $this->errorMessage = 'A component needs at least one node — give the first one a label.';

            return;
        }
        foreach ($rows as $n) {
            if (! in_array($n['type'], Node::TYPES, true)) {
                $this->errorMessage = 'Unknown node type.';

                return;
            }
        }

        // Validate the chosen collection belongs to this site (else clear it).
        $collectionId = $this->collectionId !== '' && $this->site->collections()->whereKey($this->collectionId)->exists()
            ? $this->collectionId : null;

        if ($this->editingId) {
            $component = $this->site->contentComponents()->findOrFail($this->editingId);
            $component->update([
                'name' => trim($this->cName),
                'description' => trim($this->cDescription) ?: null,
                'tags' => $this->parsedTags() ?: null,
                'collection_id' => $collectionId,
            ]);
        } else {
            $component = Component::create([
                'site_id' => $this->site->id,
                'name' => trim($this->cName),
                'author' => Auth::user()?->name ?? 'Admin',
                'created_by' => Auth::id(),
                'source' => 'app',
                'description' => trim($this->cDescription) ?: null,
                'tags' => $this->parsedTags() ?: null,
                'collection_id' => $collectionId,
            ]);
        }

        // Give it a slot at the end of the collection when it has none yet.
        if ($collectionId && $component->collection_order === null) {
            $component->update(['collection_order' => (int) Component::where('collection_id', $collectionId)->max('collection_order') + 1]);
        }

        // Replace nodes wholesale — order = row order in the editor.
        $component->nodes()->delete();
        foreach ($rows as $i => $n) {
            $component->nodes()->create([
                'label' => trim($n['label']),
                'type' => $n['type'],
                'value' => (string) ($n['value'] ?? ''),
                'parent' => 0,
                'order' => $i,
                'description' => trim($n['description'] ?? '') ?: null,
            ]);
        }

        // Page attachments — keep existing order, append new ones at the end.
        $valid = $this->site->pages()->whereIn('id', $this->pageIds)->pluck('id');
        $attach = [];
        foreach ($valid as $pageId) {
            $current = $component->pages()->where('pages.id', $pageId)->first();
            $order = $current->pivot->order
                ?? ((int) \DB::table('page_component')->where('page_id', $pageId)->max('order') + 1);
            $attach[$pageId] = ['order' => $order];
        }
        $component->pages()->sync($attach);

        $this->dispatch('toast', level: 'success', title: 'Component saved',
            message: $component->name.' has '.$rows->count().' '.Str::plural('node', $rows->count()).'.');
        $this->close();
    }

    /** Component being VIEWED read-only (null = closed). */
    public ?string $viewingId = null;

    public function view(string $id): void
    {
        $this->viewingId = $this->site->contentComponents()->findOrFail($id)->id;
    }

    public function closeView(): void
    {
        $this->viewingId = null;
    }

    /** Jump from the read-only view straight into the editor. */
    public function editFromView(): void
    {
        $id = $this->viewingId;
        $this->closeView();
        if ($id) {
            $this->open($id);
        }
    }

    public function getViewingProperty(): ?Component
    {
        return $this->viewingId
            ? $this->site->contentComponents()->with(['nodes', 'pages', 'creator'])->find($this->viewingId)
            : null;
    }

    public function deleteComponent(string $id): void
    {
        $this->guard();
        $component = $this->site->contentComponents()->findOrFail($id);
        $component->delete(); // nodes + page pivots cascade
        if ($this->editingId === $id) {
            $this->close();
        }
        $this->dispatch('toast', level: 'success', title: 'Component deleted', message: $component->name.' was removed.');
    }

    public function render()
    {
        return view('livewire.components-page');
    }
}
