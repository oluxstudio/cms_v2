<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Livewire\Forms\PageForm;
use App\Models\Page;
use App\Models\PageAttribute;
use App\Models\Site;
use App\Services\TemplateInstaller;
use App\Services\TemplateScaffolder;
use App\Support\TemplateLayouts;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class PageComponent extends Component
{
    use WithLayoutMode;

    public Site $site;

    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public PageForm $form;

    // ── Selected-page detail drawer: metadata, preview & layout ──
    public ?string $detailPageId = null;

    public string $metaDescription = '';

    public string $metaKeywords = '';

    public string $ogImage = '';

    public bool $isPublished = true;

    /** @var array<int,array{key:string,value:string}> custom page attributes */
    public array $attrRows = [];

    /** Attribute keys owned by the builder/pipeline — never edited here. */
    private const MANAGED_ATTRS = ['description', 'og_image', 'custom_js', 'page_styles'];

    public function show(string $pageId): void
    {
        $page = Page::where('site_id', $this->site->id)->findOrFail($pageId);
        $this->detailPageId = $page->id;
        $this->metaDescription = (string) ($page->getAttr('description') ?? '');
        $this->metaKeywords = (string) $page->keywords;
        $this->ogImage = (string) ($page->getAttr('og_image') ?? '');
        $this->isPublished = (bool) $page->is_published;
        $this->attrRows = collect($page->attrMap())
            ->except(self::MANAGED_ATTRS)
            ->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])
            ->values()->all();
        $this->resetErrorBag();
    }

    public function closeDetail(): void
    {
        $this->reset(['detailPageId', 'metaDescription', 'metaKeywords', 'ogImage', 'attrRows']);
    }

    public function getDetailPageProperty(): ?Page
    {
        return $this->detailPageId
            ? Page::where('site_id', $this->site->id)->find($this->detailPageId)
            : null;
    }

    public function addAttrRow(): void
    {
        if (count($this->attrRows) < 60) {
            $this->attrRows[] = ['key' => '', 'value' => ''];
        }
    }

    public function removeAttrRow(int $i): void
    {
        unset($this->attrRows[$i]);
        $this->attrRows = array_values($this->attrRows);
    }

    /** Persist metadata + custom attributes (PageApiController's contract). */
    public function saveMeta(): void
    {
        abort_unless($this->site->allows(auth()->user(), 'pages.manage'), 403);
        $page = Page::where('site_id', $this->site->id)->findOrFail($this->detailPageId);

        $this->validate([
            'metaDescription' => ['nullable', 'string', 'max:5000'],
            'metaKeywords' => ['nullable', 'string', 'max:500'],
            'ogImage' => ['nullable', 'string', 'max:2000'],
            'attrRows.*.key' => ['nullable', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_\-]*$/'],
            'attrRows.*.value' => ['nullable', 'string', 'max:5000'],
        ], [
            'attrRows.*.key.regex' => 'Attribute keys may only contain letters, numbers, dashes and underscores.',
        ]);

        $page->update(['keywords' => trim($this->metaKeywords), 'is_published' => $this->isPublished]);

        // Managed meta: blank clears the attribute entirely.
        trim($this->metaDescription) !== ''
            ? $page->setAttr('description', trim($this->metaDescription))
            : $page->forgetAttr('description');
        trim($this->ogImage) !== ''
            ? $page->setAttr('og_image', trim($this->ogImage))
            : $page->forgetAttr('og_image');

        // Reconcile custom attributes: write the rows, forget removed keys.
        $kept = [];
        foreach ($this->attrRows as $row) {
            $key = trim($row['key']);
            if ($key === '' || in_array($key, self::MANAGED_ATTRS, true)) {
                continue;
            }
            $page->setAttr($key, (string) $row['value']);
            $kept[] = $key;
        }
        foreach (array_keys($page->attrMap()) as $existing) {
            if (! in_array($existing, self::MANAGED_ATTRS, true) && ! in_array($existing, $kept, true)) {
                $page->forgetAttr($existing);
            }
        }

        $this->show($page->id); // refresh the drawer's state
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Page metadata updated.');
    }

    // ── Component picker (attach content components to a page) ──
    public ?string $pickerPageId = null;

    public string $pickerSearch = '';

    public string $pickerTag = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('pages', 'grid');
    }

    public function openPicker(string $pageId): void
    {
        abort_unless($this->site->allows(auth()->user(), 'pages.manage'), 403);
        $this->pickerPageId = Page::where('site_id', $this->site->id)->findOrFail($pageId)->id;
        $this->reset(['pickerSearch', 'pickerTag']);
    }

    public function closePicker(): void
    {
        $this->reset(['pickerPageId', 'pickerSearch', 'pickerTag']);
    }

    public function getPickerPageProperty(): ?Page
    {
        return $this->pickerPageId
            ? Page::where('site_id', $this->site->id)->find($this->pickerPageId)
            : null;
    }

    /** Site components filtered by the picker's search + tag. */
    public function getPickerComponentsProperty()
    {
        return $this->site->contentComponents()->with('nodes')
            ->when($this->pickerSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->pickerSearch.'%'))
            ->get()
            ->when($this->pickerTag !== '', fn ($c) => $c->filter(
                fn ($comp) => in_array($this->pickerTag, $comp->tags ?? [], true)
            )->values());
    }

    /** Every tag used across the site's components — the filter chips. */
    public function getComponentTagsProperty(): array
    {
        return $this->site->contentComponents()->pluck('tags')
            ->flatMap(fn ($t) => $t ?? [])->unique()->sort()->values()->all();
    }

    /** Attach/detach a component on the picker's page (order appends). */
    public function toggleComponent(string $componentId): void
    {
        abort_unless($this->site->allows(auth()->user(), 'pages.manage'), 403);
        $page = $this->pickerPage;
        $component = $this->site->contentComponents()->findOrFail($componentId);
        if (! $page) {
            return;
        }

        if ($page->components()->where('components.id', $component->id)->exists()) {
            $page->components()->detach($component->id);
        } else {
            $order = (int) \DB::table('page_component')->where('page_id', $page->id)->max('order') + 1;
            $page->components()->attach($component->id, ['order' => $order]);
        }
    }

    /** List filter: all | live | hidden | inactive | attention. */
    #[Url(except: 'all')]
    public string $filter = 'all';

    /** List order: menu | name | updated | sections. */
    #[Url(except: 'menu')]
    public string $sort = 'menu';

    private const FILTERS = ['all', 'live', 'hidden', 'inactive', 'attention'];

    private const SORTS = ['menu', 'name', 'updated', 'sections'];

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'menu';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter', 'sort']);
    }

    public function render()
    {
        // One query for every page + its section count/names, layout and the
        // newest section edit; SEO descriptions in one more grouped read.
        $all = Page::where('site_id', $this->site->id)
            ->withCount(['components as sections_count' => fn ($q) => $q->where('page_component.active', true)])
            ->withMax('components', 'updated_at')
            ->with([
                'activeComponents' => fn ($q) => $q->select('components.id', 'components.name'),
                'blockLayout:id,name',
            ])
            ->get();

        $described = PageAttribute::whereIn('page_id', $all->pluck('id'))
            ->where('key', 'description')
            ->whereNotNull('value')->where('value', '!=', '')
            ->pluck('page_id')->map(fn ($id) => (string) $id)->flip();

        $all->each(function (Page $p) use ($described) {
            $p->is_home = $p->url === '/';
            // Builder internals: layout-region shadow pages and archived copies.
            $p->is_system = str_starts_with((string) $p->url, '/_layout-') || str_starts_with((string) $p->url, '/_archived-');
            $p->is_inactive = $p->template_active === false;
            $p->is_live = ! $p->is_inactive && ! $p->is_system && (bool) $p->is_published;
            $p->is_hidden = ! $p->is_inactive && ! $p->is_system && ! $p->is_published;
            $p->in_nav = ! $p->is_inactive && ! $p->is_system;
            $p->has_seo = isset($described[(string) $p->id]);
            $p->is_empty = (int) $p->sections_count === 0;
            $p->issues = ($p->is_inactive || $p->is_system) ? [] : array_values(array_filter([
                $p->is_empty ? 'No sections' : null,
                $p->has_seo ? null : 'No SEO description',
            ]));
            $p->needs_attention = $p->issues !== [];
            $sectionEdit = $p->components_max_updated_at ? Carbon::parse($p->components_max_updated_at) : null;
            $p->last_edited = $sectionEdit && $sectionEdit->gt($p->updated_at) ? $sectionEdit : $p->updated_at;
        });

        $needle = mb_strtolower(trim($this->search));
        $pages = $all
            ->when($needle !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p->name.' '.$p->url.' '.$p->keywords), $needle)))
            ->filter(fn ($p) => match ($this->filter) {
                'live' => $p->is_live,
                'hidden' => $p->is_hidden,
                'inactive' => $p->is_inactive,
                'attention' => $p->needs_attention,
                default => true,
            });
        $pages = (match ($this->sort) {
            'name' => $pages->sortBy(fn ($p) => mb_strtolower($p->name)),
            'updated' => $pages->sortByDesc(fn ($p) => $p->last_edited?->getTimestamp() ?? 0),
            'sections' => $pages->sortByDesc('sections_count'),
            // Menu order = the site nav's order: home first, then as created.
            default => $pages->sort(fn ($a, $b) => [(int) ! $a->is_home, (int) $a->is_system, $a->created_at?->getTimestamp() ?? 0, (string) $a->id]
                <=> [(int) ! $b->is_home, (int) $b->is_system, $b->created_at?->getTimestamp() ?? 0, (string) $b->id]),
        })->values();

        $total = $all->count();
        $real = $all->reject(fn ($p) => $p->is_system || $p->is_inactive);
        $stats = [
            'live' => $all->where('is_live', true)->count(),
            'hidden' => $all->where('is_hidden', true)->count(),
            'inactive' => $all->where('is_inactive', true)->count(),
            'system' => $all->filter(fn ($p) => $p->is_system && ! $p->is_inactive)->count(),
            'inNav' => $all->where('in_nav', true)->count(),
            'noSeo' => $real->where('has_seo', false)->count(),
            'empty' => $real->where('is_empty', true)->count(),
            'attention' => $all->where('needs_attention', true)->count(),
            'sections' => (int) $real->sum('sections_count'),
            'avgSections' => $real->count() ? round($real->avg('sections_count'), 1) : 0,
            'thisWeek' => $all->where('created_at', '>=', now()->startOfWeek())->count(),
            'layouts' => $all->map(fn ($p) => $p->blockLayout?->name ?? 'Blank')->unique()->count(),
            'attentionList' => $all->where('needs_attention', true)->values(),
            'inactiveList' => $all->where('is_inactive', true)->values(),
            'biggest' => $real->where('sections_count', '>', 0)->sortByDesc('sections_count')->take(5)->values(),
            'recent' => $all->reject(fn ($p) => $p->is_system)->sortByDesc(fn ($p) => $p->last_edited?->getTimestamp() ?? 0)->take(4)->values(),
        ];

        return view('livewire.page-component', [
            'pages' => $pages,
            'total' => $total,
            'stats' => $stats,
        ]);
    }

    /** Starting layout for a NEW page: 'blank' or a template page slug. */
    public string $layout = 'blank';

    /** Layouts offered by the applied template (empty when none applied). */
    public function getLayoutsProperty(): array
    {
        return TemplateLayouts::for($this->site);
    }

    public function openCreate(): void
    {
        abort_unless($this->site->allowsPageEdit(auth()->user(), null), 403);
        $this->form->reset();
        $this->editingId = 0;
        $this->layout = 'blank';
        $this->showModal = true;
    }

    public function openEdit(string $id): void
    {
        abort_unless($this->site->allowsPageEdit(auth()->user(), $id), 403);
        $page = Page::findOrFail($id);
        $this->editingId = $id;
        $this->form->name = $page->name;
        $this->form->url = $page->url;
        $this->form->keywords = $page->keywords;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->form->validate();

        if ($this->editingId) {
            abort_unless($this->site->allowsPageEdit(auth()->user(), (string) $this->editingId), 403);
            Page::findOrFail($this->editingId)->update([
                'name' => $this->form->name,
                'url' => $this->form->url,
                'keywords' => $this->form->keywords,
            ]);
        } else {
            abort_unless($this->site->allowsPageEdit(auth()->user(), null), 403);
            // applyPages silently skips existing URLs — surface it instead.
            if (Page::where('site_id', $this->site->id)->where('url', $this->form->url)->exists()) {
                $this->addError('form.url', 'A page with this URL already exists.');

                return;
            }

            $layoutDef = $this->layout !== 'blank' ? ($this->layouts[$this->layout]['def'] ?? null) : null;
            if ($layoutDef) {
                // Scaffold from the template layout: the chosen page's block
                // composition with default content. Existing same-name
                // components are REUSED (one component per name per site).
                $def = array_merge($layoutDef, [
                    'name' => $this->form->name,
                    'url' => $this->form->url,
                    'keywords' => (string) ($this->form->keywords ?? ''),
                ]);
                app(TemplateScaffolder::class)->applyPages($this->site, [$def]);
                $this->dispatch('toast', level: 'success', title: 'Page created',
                    message: 'Built from the '.$this->layouts[$this->layout]['name'].' layout — edit it in the Content tab.');
            } else {
                Page::create([
                    'site_id' => $this->site->id,
                    'name' => $this->form->name,
                    'url' => $this->form->url,
                    'keywords' => (string) ($this->form->keywords ?? ''),
                ]);
            }
        }

        $this->showModal = false;
        $this->form->reset();
        $this->layout = 'blank';
    }

    /** Delete a page — confirmation happens in the shared modal (data-confirm). */
    /** Bring a page parked by a template switch back — it is the owner's from now on (active under any template). */
    public function activatePage(string $id): void
    {
        abort_unless($this->site->allowsPageEdit(auth()->user(), $id), 403);
        $page = Page::where('site_id', $this->site->id)->findOrFail($id);
        $page->forceFill(['template_keys' => array_values(array_unique([...(array) $page->template_keys, TemplateInstaller::OWNER_KEEP])), 'template_active' => true])->save();
        $this->dispatch('toast', level: 'success', title: 'Page activated', message: $page->name.' is back on the site and stays whichever template you use.');
    }

    public function deletePage(string $id): void
    {
        abort_unless($this->site->allowsPageEdit(auth()->user(), $id), 403);
        Page::where('site_id', $this->site->id)->findOrFail($id)->delete();
    }
}
