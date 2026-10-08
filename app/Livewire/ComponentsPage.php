<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Livewire\Concerns\WithVisibilityFields;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use App\Support\SiteProperties;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component as LivewireComponent;

/**
 * Components admin — STANDALONE content components: named bags of typed
 * nodes the admin builds on their own, then optionally attaches to pages
 * (ordered pivot) or links to collections via collection-typed nodes.
 */
class ComponentsPage extends LivewireComponent
{
    use WithLayoutMode;
    use WithVisibilityFields;

    public Site $site;

    #[Url(except: '')]
    public string $search = '';

    /** Active tag filter ('' = all). */
    #[Url(as: 'tag', except: '')]
    public string $filterTag = '';

    /** Collection filter: '' = all, 'none' = standalone, else a collection id. */
    #[Url(as: 'collection', except: '')]
    public string $filterCollection = '';

    /** List filter: all | used | unused | source | inactive | attention */
    #[Url(except: 'all')]
    public string $filter = 'all';

    /** List order: updated | name | used | fields */
    #[Url(except: 'updated')]
    public string $sort = 'updated';

    public const FILTERS = ['all', 'used', 'unused', 'source', 'inactive', 'attention'];

    public const SORTS = ['updated', 'name', 'used', 'fields'];

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

    public function mount(Site $site, ?string $screen = null, ?string $component = null): void
    {
        $this->site = $site;
        $this->initLayout('components', 'grid');
        // A component and a new component each have their own page.
        match ($screen) {
            'new' => $this->open(''),
            'view' => $this->view((string) $component),
            'edit' => $this->open((string) $component),
            default => null,
        };
    }

    /** The components list, or one component's own page (view / edit). */
    public function pageUrl(?string $id = null, bool $edit = false): string
    {
        if ($id === null || $id === '' || $id === '0') {
            return route('site.components', $this->site->name);
        }

        return route($edit ? 'site.components.edit' : 'site.components.show', [$this->site->name, $id]);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'updated';
        }
    }

    /** Clear search, filter, tag and collection — back to every component. */
    public function resetListing(): void
    {
        $this->reset(['search', 'filterTag', 'filterCollection', 'filter']);
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

    /** The listing — every annotated component narrowed by search, tag, collection and filter, then sorted. */
    public function getComponentsProperty(): SupportCollection
    {
        return $this->applyListing($this->annotatedComponents());
    }

    /**
     * Every component of the site with what the listing, tiles and rails
     * need, in a fixed number of queries: field counts, empty fields, last
     * edit, its pages (live vs. parked by a template switch) and data sources.
     */
    private function annotatedComponents(): SupportCollection
    {
        $all = $this->site->contentComponents()
            ->with(['collection:id,name', 'creator:id,name'])
            ->withCount([
                'nodes',
                'nodes as empty_nodes_count' => fn ($q) => $q->where(fn ($w) => $w->whereNull('value')->orWhere('value', '')),
            ])
            ->withMax('nodes', 'updated_at')
            ->get();

        if ($all->isEmpty()) {
            return $all;
        }
        $ids = $all->pluck('id');

        // Placements: a pivot row is LIVE while its page is the current template's
        // and not parked under /_archived-…; otherwise the placement is inactive.
        $placements = DB::table('page_component')
            ->join('pages', 'pages.id', '=', 'page_component.page_id')
            ->whereIn('page_component.component_id', $ids)
            ->orderBy('pages.name')
            ->get(['page_component.component_id', 'pages.id as page_id', 'pages.name', 'pages.url',
                'page_component.active', 'pages.template_active'])
            ->map(function ($r) {
                $r->live = (bool) $r->active && (bool) $r->template_active && ! str_starts_with((string) $r->url, '/_archived-');

                return $r;
            })
            ->groupBy('component_id');

        // Data sources through collection-typed fields (besides the component's own collection).
        $nodeLinks = Node::whereIn('component_id', $ids)->where('type', 'collection')
            ->whereNotNull('value')->where('value', '!=', '')
            ->get(['component_id', 'value'])->groupBy('component_id');
        $linkedNames = $nodeLinks->isEmpty() ? collect()
            : $this->site->collections()->whereIn('id', $nodeLinks->flatten()->pluck('value')->unique())->pluck('name', 'id');

        return $all->each(function (Component $c) use ($placements, $nodeLinks, $linkedNames) {
            $c->nodes_count = (int) $c->nodes_count;
            // Site Properties are filled on their own page (blank = optional
            // setting), so they never count as "empty fields to fill in".
            $c->empty_nodes_count = SiteProperties::isComponent($c) ? 0 : (int) $c->empty_nodes_count;
            $rows = $placements[$c->id] ?? collect();
            $c->live_pages = $rows->where('live', true)->unique('page_id')->values();
            $c->placements_count = $rows->count();
            $c->live_pages_count = $c->live_pages->count();
            $c->is_used = $c->live_pages_count > 0;
            $c->is_inactive = $rows->isNotEmpty() && ! $c->is_used;

            $sources = collect();
            if ($c->collection) {
                $sources->push($c->collection->name);
            }
            foreach ($nodeLinks[$c->id] ?? [] as $n) {
                if (isset($linkedNames[$n->value])) {
                    $sources->push($linkedNames[$n->value]);
                }
            }
            $c->data_sources = $sources->unique()->values();
            $c->has_source = $c->data_sources->isNotEmpty();

            $c->needs_attention = $c->empty_nodes_count > 0 || $c->nodes_count === 0 || $c->is_inactive;
            $c->last_edited = max(array_filter([
                $c->updated_at,
                $c->nodes_max_updated_at ? Carbon::parse($c->nodes_max_updated_at) : null,
            ]));
        });
    }

    /**
     * The collection select SCOPES the whole page — listing, tiles and rails —
     * to one collection's components ('none' = standalone ones).
     */
    private function scoped(SupportCollection $all): SupportCollection
    {
        return match ($this->filterCollection) {
            '' => $all,
            'none' => $all->whereNull('collection_id')->values(),
            default => $all->where('collection_id', $this->filterCollection)->values(),
        };
    }

    private function applyListing(SupportCollection $all): SupportCollection
    {
        $needle = mb_strtolower(trim($this->search));

        return $this->scoped($all)
            ->when($needle !== '', fn ($c) => $c->filter(fn ($x) => str_contains(
                mb_strtolower($x->name.' '.$x->description.' '.implode(' ', $x->tags ?? [])), $needle)))
            ->when($this->filterTag !== '', fn ($c) => $c->filter(fn ($x) => in_array($this->filterTag, $x->tags ?? [], true)))
            ->filter(fn ($x) => match ($this->filter) {
                'used' => $x->is_used,
                'unused' => $x->placements_count === 0,
                'source' => $x->has_source,
                'inactive' => $x->is_inactive,
                'attention' => $x->needs_attention,
                default => true,
            })
            ->sortBy(fn ($x) => match ($this->sort) {
                'name' => mb_strtolower($x->name),
                'used' => -$x->live_pages_count,
                'fields' => -$x->nodes_count,
                default => -$x->last_edited->getTimestamp(),
            })
            ->values();
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
            $this->hydrateVisibilityFields($c->visibility);
        } else {
            $this->reset(['cName', 'cDescription', 'cTags', 'pageIds', 'collectionId']);
            $this->resetVisibilityFields();
            $this->nodes = [['label' => '', 'type' => 'text', 'value' => '', 'description' => '']];
        }
    }

    /** Leave the editor: back to the component's page (or the list, for a new one). */
    public function close(): void
    {
        $id = $this->editingId;
        $this->reset(['editingId', 'cName', 'cDescription', 'cTags', 'nodes', 'pageIds', 'collectionId']);
        $this->resetVisibilityFields();
        $this->redirect($this->pageUrl($id), navigate: true);
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

        $visibility = $this->assembleVisibility();

        // Validate the chosen collection belongs to this site (else clear it).
        $collectionId = $this->collectionId !== '' && $this->site->collections()->whereKey($this->collectionId)->exists()
            ? $this->collectionId : null;

        if ($this->editingId) {
            $component = $this->site->contentComponents()->findOrFail($this->editingId);
            $component->update([
                'name' => trim($this->cName),
                'visibility' => $visibility,
                'description' => trim($this->cDescription) ?: null,
                'tags' => $this->parsedTags() ?: null,
                'collection_id' => $collectionId,
            ]);
        } else {
            $component = Component::create([
                'site_id' => $this->site->id,
                'name' => trim($this->cName),
                'visibility' => $visibility,
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

        $this->bustRenderCache($this->site);

        $this->dispatch('toast', level: 'success', title: 'Component saved',
            message: $component->name.' has '.$rows->count().' '.Str::plural('node', $rows->count()).'.');
        $this->close();
        // Saved → the component's own page (a new one included).
        $this->redirect($this->pageUrl((string) $component->id), navigate: true);
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
        $this->redirect($this->pageUrl(), navigate: true);
    }

    /** From the component's page to its edit page. */
    public function editFromView(): void
    {
        $id = $this->viewingId;
        $this->viewingId = null;
        if ($id) {
            $this->open($id);
            $this->redirect($this->pageUrl($id, true), navigate: true);
        }
    }

    public function getViewingProperty(): ?Component
    {
        return $this->viewingId
            ? $this->site->contentComponents()->with(['nodes', 'pages', 'creator'])->find($this->viewingId)
            : null;
    }

    /** Copy a component and its fields (not its page placements) as a new standalone one. */
    public function duplicateComponent(string $id): void
    {
        $this->guard();
        $source = $this->site->contentComponents()->with('nodes')->findOrFail($id);

        $copy = $source->replicate(['collection_order']);
        $copy->name = Str::limit($source->name.' (copy)', 120, '');
        $copy->author = Auth::user()?->name ?? 'Admin';
        $copy->created_by = Auth::id();
        $copy->source = 'app';
        $copy->save();

        // Re-create nodes, remapping nested `parent` ids onto the new rows.
        $map = [];
        $pending = $source->nodes->values();
        $isRoot = fn ($p) => $p === null || $p === '' || $p === '0' || $p === 0;
        while ($pending->isNotEmpty()) {
            $ready = $pending->filter(fn ($n) => $isRoot($n->parent) || isset($map[(string) $n->parent])
                || ! $source->nodes->contains('id', $n->parent));
            if ($ready->isEmpty()) {
                $ready = $pending; // unresolvable cycle — keep the rows as roots
            }
            foreach ($ready as $n) {
                $parent = $isRoot($n->parent) ? 0 : ($map[(string) $n->parent] ?? 0);
                $map[(string) $n->id] = $copy->nodes()->create([
                    'label' => $n->label, 'type' => $n->type, 'value' => $n->value,
                    'parent' => $parent, 'order' => $n->order, 'description' => $n->description,
                ])->id;
            }
            $pending = $pending->reject(fn ($n) => isset($map[(string) $n->id]))->values();
        }

        if ($copy->collection_id) {
            $copy->update(['collection_order' => (int) Component::where('collection_id', $copy->collection_id)->max('collection_order') + 1]);
        }

        $this->dispatch('toast', level: 'success', title: 'Component duplicated', message: $copy->name.' was created.');
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
        $everything = $this->annotatedComponents();
        $components = $this->applyListing($everything);
        $all = $this->scoped($everything);

        $total = $all->count();
        $stats = [
            'total' => $total,
            'fields' => (int) $all->sum('nodes_count'),
            'empty' => (int) $all->sum('empty_nodes_count'),
            'emptyComponents' => $all->where('empty_nodes_count', '>', 0)->count(),
            'used' => $all->where('is_used', true)->count(),
            'unused' => $all->where('placements_count', 0)->count(),
            'inactive' => $all->where('is_inactive', true)->count(),
            'source' => $all->where('has_source', true)->count(),
            'attention' => $all->where('needs_attention', true)->count(),
            'placements' => (int) $all->sum('live_pages_count'),
            'noFields' => $all->where('nodes_count', 0)->values(),
            'recent' => $all->where('created_at', '>=', now()->startOfWeek())->count(),
            'emptyList' => $all->where('empty_nodes_count', '>', 0)->sortByDesc('empty_nodes_count')->take(4)->values(),
            'mostUsed' => $all->where('live_pages_count', '>', 0)->sortByDesc('live_pages_count')->take(5)->values(),
            'recentlyEdited' => $all->sortByDesc(fn ($c) => $c->last_edited->getTimestamp())->take(4)->values(),
            'byTag' => $all->flatMap(fn ($c) => $c->tags ?? [])->countBy()->sortDesc()->take(6)->all(),
        ];

        return view('livewire.components-page', [
            'components' => $components,
            'stats' => $stats,
            'siteTotal' => $everything->count(),
            'tags' => $everything->flatMap(fn ($c) => $c->tags ?? [])->unique()->sort()->values()->all(),
        ]);
    }
}
