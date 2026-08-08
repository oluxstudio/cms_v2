<?php

namespace App\Livewire;

use App\Models\Block;
use App\Models\BlockLayout;
use App\Models\BlockPreset;
use App\Models\BuilderTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Models\Site;
use App\Services\BlockHtmlExporter;
use App\Services\BlockTreeService;
use App\Services\SiteAppExporter;
use App\Support\ThemeTokens;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The BlockKit editor — the human half of jigsaw parity. Every action here
 * routes through the SAME BlockTreeService the AI assistant uses, so anything
 * built by hand can be read and extended by the AI and vice versa. The layers
 * panel, drag-and-drop and inspector are all views over the one block tree.
 */
class BlockEditor extends Component
{
    public string $siteId;

    public ?string $pageId = null;

    /** Active editor tab — URL-addressable so the nav can deep-link ?tab=components. */
    #[Url(as: 'tab', except: 'build')]
    public string $tab = 'build';

    public ?string $selectedId = null;

    // ── Pages tab ──
    public string $newPageName = '';

    public string $pageName = '';

    public string $pageUrl = '';

    public bool $pagePublished = true;

    // ── Theme tab ──
    public array $theme = [];

    // ── Layouts tab ──
    public string $newLayoutName = '';

    /** Layout being edited in the canvas (Layout View); null = editing the page. */
    public ?string $editingLayoutId = null;

    /** Inspector working copies (bound to the schema-driven form). */
    public array $propsForm = [];

    public array $styleForm = [];

    public string $labelForm = '';

    /** Name this block is exposed under as a component prop (components only). */
    public string $propNameForm = '';

    /** Per-block custom JS (meta.script) — runs on the live page & export, never on the canvas. */
    public string $scriptForm = '';

    /** Page-level custom JS (page attribute custom_js). */
    public string $pageScript = '';

    protected function service(): BlockTreeService
    {
        return app(BlockTreeService::class);
    }

    protected function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    protected function page(): ?Page
    {
        return $this->pageId
            ? Page::where('site_id', $this->siteId)->find($this->pageId)
            : null;
    }

    public function mount(string $siteId, ?string $pageId = null): void
    {
        $this->siteId = $siteId;
        $this->theme = $this->site()->themeValues();
        $first = $pageId ?: $this->site()->livePages()->orderBy('id')->value('id');
        if ($first) {
            $this->selectPage((int) $first);
        }
    }

    // ─────────────────────────────────────────────── Pages tab

    public function addPage(): void
    {
        $name = trim($this->newPageName);
        if ($name === '') {
            return;
        }
        $url = '/'.Str::slug($name);
        $i = 2;
        while ($this->site()->pages()->where('url', $url)->exists()) {
            $url = '/'.Str::slug($name).'-'.$i++;
        }
        $page = $this->site()->pages()->create(['name' => $name, 'url' => $url, 'keywords' => '', 'is_published' => false]);
        $this->newPageName = '';
        $this->selectPage($page->id);
        $this->tab = 'build';
        $this->dispatch('toast', level: 'success', title: 'Page added', message: "“{$name}” created — build it with blocks.");
    }

    public function savePage(): void
    {
        $page = $this->page();
        if (! $page) {
            return;
        }
        $name = trim($this->pageName) ?: $page->name;
        $page->update([
            'name' => $name,
            'url' => '/'.ltrim(Str::slug(trim($this->pageUrl, '/')) ?: Str::slug($name), '/'),
            'is_published' => $this->pagePublished,
        ]);
        // Page-level custom JS — runs on the live page and in exports.
        $page->setAttr('custom_js', trim($this->pageScript) !== '' ? $this->pageScript : null);
        $this->pageUrl = $page->fresh()->url;
        $this->dispatch('bk-mutated');
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Page details updated.');
    }

    public function deletePage(string $id): void
    {
        $page = Page::where('site_id', $this->siteId)->find($id);
        if (! $page) {
            return;
        }
        $page->delete();
        if ($this->pageId === $id) {
            $next = $this->site()->livePages()->orderBy('id')->value('id');
            $next ? $this->selectPage((int) $next) : ($this->pageId = null);
        }
        $this->dispatch('toast', level: 'success', title: 'Deleted', message: 'Page removed.');
    }

    // ─────────────────────────────────────────────── Layouts (Layout View)

    /**
     * The tree currently being edited: a LAYOUT when the Layout View opened
     * one, else the selected page. Every canvas action targets this owner.
     */
    protected function owner(): Page|BlockLayout|null
    {
        if ($this->editingLayoutId) {
            return BlockLayout::where('site_id', $this->siteId)->find($this->editingLayoutId);
        }

        return $this->page();
    }

    public function createBlockLayout(): void
    {
        $name = trim($this->newLayoutName);
        if ($name === '') {
            return;
        }
        $layout = BlockLayout::make($this->site(), $name);
        $this->newLayoutName = '';
        $this->editLayout($layout->id); // straight into designing it
    }

    /** Open a layout's tree in the canvas — same editor, layout as the owner. */
    public function editLayout(string $layoutId): void
    {
        $layout = BlockLayout::where('site_id', $this->siteId)->find($layoutId);
        if (! $layout) {
            return;
        }
        if ($layout->is_system) {
            $this->dispatch('toast', level: 'error', title: 'Blank is built in', message: 'The Blank layout is just the content section — it cannot be edited.');

            return;
        }
        $this->editingLayoutId = $layout->id;
        $this->selectedId = null;
        $this->resetForms();
        $this->tab = 'build';
        $this->dispatch('toast', level: 'success', title: "Editing “{$layout->name}”", message: $layout->isComponent()
            ? 'Design your component with any blocks — it joins the palette and can be dropped into every page.'
            : 'Blocks you place here render on every page using this layout. The dashed Content section is where each page\'s own blocks go.');
    }

    /** Leave the Layout View — back to editing the selected page. */
    public function stopEditingLayout(): void
    {
        $this->editingLayoutId = null;
        $this->selectedId = null;
        $this->resetForms();
        $this->dispatch('bk-mutated');
    }

    public function deleteBlockLayout(string $id): void
    {
        $layout = BlockLayout::where('site_id', $this->siteId)->find($id);
        if (! $layout || $layout->is_system) {
            $this->dispatch('toast', level: 'error', title: 'Protected', message: 'The Blank layout is built in and cannot be deleted.');

            return;
        }
        // Safety: pages using it fall back to Blank (reported, not silent).
        $dependents = $layout->pages()->pluck('name');
        $layout->pages()->update(['block_layout_id' => null]);
        $layout->delete();
        $this->dispatch('bk-mutated');
        $this->dispatch('toast', level: 'success', title: 'Layout deleted', message: $dependents->isEmpty()
            ? 'Layout removed.'
            : $dependents->count().' page(s) reassigned to Blank: '.$dependents->take(4)->implode(', ').($dependents->count() > 4 ? '…' : ''));
    }

    // ─────────────────────────────────────── Components (user-built blocks)

    public string $newComponentName = '';

    /** Create a component (e.g. a hero section) and open it in the canvas. */
    public function createComponent(): void
    {
        $name = trim($this->newComponentName);
        if ($name === '') {
            return;
        }
        $component = BlockLayout::makeComponent($this->site(), $name);
        $this->newComponentName = '';
        $this->editLayout($component->id); // same canvas, component as the owner
    }

    public function deleteComponent(string $id): void
    {
        $component = BlockLayout::components()->where('site_id', $this->siteId)->find($id);
        if (! $component) {
            return;
        }
        if ($this->editingLayoutId === $component->id) {
            $this->stopEditingLayout();
        }
        $component->delete(); // already-stamped copies on pages are untouched
        $this->dispatch('toast', level: 'success', title: 'Component deleted', message: 'Copies already placed on pages are kept.');
    }

    /** Stamp a component's blocks into the current canvas at an exact spot. */
    /**
     * Pending component stamp awaiting prop values:
     * { id, parentId, index, name, props: [{name, default}], values: {name => value} }
     */
    public ?array $pendingStamp = null;

    /** Which block prop a named component-prop overrides, per block type. */
    private const PROP_TARGET = BlockTreeService::PROP_TARGET;

    /**
     * Entry point for drop/click: if the component exposes props, open the
     * fill-in dialog (defaults prefilled); otherwise stamp immediately.
     */
    public function requestComponentInsert(string $componentId, string $parentId, int $index): void
    {
        $component = BlockLayout::components()->where('site_id', $this->siteId)->find($componentId);
        if (! $component) {
            return;
        }
        $props = $this->componentProps($component);
        if ($props === []) {
            $this->insertComponentAt($componentId, $parentId, $index);

            return;
        }
        $this->pendingStamp = [
            'id' => $componentId, 'parentId' => $parentId, 'index' => $index,
            'name' => $component->name, 'props' => $props,
            'values' => collect($props)->mapWithKeys(fn ($p) => [$p['name'] => $p['default']])->all(),
        ];
    }

    /** Confirm the dialog: stamp with the entered prop values. */
    public function confirmStamp(): void
    {
        if (! $this->pendingStamp) {
            return;
        }
        $s = $this->pendingStamp;
        $this->pendingStamp = null;
        $this->insertComponentAt($s['id'], $s['parentId'], $s['index'], (array) ($s['values'] ?? []));
    }

    public function cancelStamp(): void
    {
        $this->pendingStamp = null;
    }

    /** The props a component exposes: blocks marked with meta.prop + their default content. */
    private function componentProps(BlockLayout $component): array
    {
        $out = [];
        $walk = function (array $node) use (&$walk, &$out) {
            // ◇ Prop placeholder blocks — positioned right where the value lands.
            if ($node['type'] === 'prop') {
                $name = trim((string) data_get($node, 'props.name', ''));
                if ($name !== '') {
                    $out[$name] ??= ['name' => $name, 'default' => (string) data_get($node, 'props.default', '')];
                }
            }
            // Blocks marked "Expose as prop" in the inspector.
            $name = trim((string) data_get($node, 'meta.prop', ''));
            $target = self::PROP_TARGET[$node['type']] ?? null;
            if ($name !== '' && $target) {
                $out[$name] ??= ['name' => $name, 'default' => (string) ($node['props'][$target] ?? '')];
            }
            foreach ($node['children'] ?? [] as $c) {
                $walk($c);
            }
        };
        $walk($this->service()->tree($component));

        return array_values($out);
    }

    public function insertComponentAt(string $componentId, string $parentId, int $index, array $overrides = []): void
    {
        $owner = $this->owner();
        $component = BlockLayout::components()->where('site_id', $this->siteId)->find($componentId);
        if (! $owner || ! $component) {
            return;
        }
        if ($owner instanceof BlockLayout && $owner->id === $component->id) {
            $this->dispatch('toast', level: 'error', title: 'Not here', message: 'A component cannot contain itself.');

            return;
        }
        $parent = Block::find($parentId);
        if (! $parent || ! $parent->isLayout()) {
            return;
        }
        try {
            // LIVE LINK: one reference block — the component renders through it,
            // so editing the component updates every placed instance.
            $created = $this->service()->insertBlocks($owner, $parentId, max(0, $index), [[
                'type' => 'component_ref',
                'props' => ['component_id' => $component->id, 'overrides' => array_filter($overrides, fn ($v) => trim((string) $v) !== '')],
                'meta' => ['label' => $component->name],
            ]]);
            $this->select($created[0]['id']);
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not added', message: $e->getMessage());
        }
    }

    /**
     * Detach a placed instance: replace the live reference with an independent
     * static copy of the component's current tree (overrides + fill kept).
     */
    public function detachComponent(string $refId): void
    {
        $owner = $this->owner();
        $ref = Block::find($refId);
        if (! $owner || ! $ref || $ref->type !== 'component_ref') {
            return;
        }
        $expanded = $this->service()->expandComponentRefs(
            $this->refAsArray($ref), $this->siteId, false
        );
        try {
            $this->service()->insertBlocks($owner, $ref->parent_id, $ref->position, [$this->stripIds($expanded)]);
            $this->service()->deleteBlocks($owner, [$refId], true);
            $this->deselect();
            $this->dispatch('bk-mutated');
            $this->dispatch('toast', level: 'success', title: 'Detached', message: 'This copy is now independent — component edits no longer affect it.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not detached', message: $e->getMessage());
        }
    }

    /** A ref block + its children as a plain tree array. */
    private function refAsArray(Block $ref): array
    {
        $byParent = Block::where($ref->page_id ? 'page_id' : 'layout_id', $ref->page_id ?: $ref->layout_id)
            ->orderBy('position')->get()->groupBy('parent_id');
        $build = function (Block $b) use (&$build, $byParent) {
            return [
                'id' => $b->id, 'type' => $b->type, 'props' => $b->props ?? [],
                'style' => $b->style ?? [], 'meta' => $b->meta ?? [],
                'children' => ($byParent[$b->id] ?? collect())->map($build)->values()->all(),
            ];
        };

        return $build($ref);
    }

    /** Palette click: stamp the component into the selected container, else the page root. */
    public function insertComponent(string $componentId): void
    {
        $owner = $this->owner();
        if (! $owner) {
            return;
        }
        $parent = $this->selectedId ? Block::find($this->selectedId) : null;
        if (! $parent || ! $parent->isLayout()) {
            $parent = $this->service()->ensureRoot($owner);
        }
        $this->requestComponentInsert($componentId, $parent->id, $parent->children()->count());
    }

    /**
     * Deep-copy a tree node as insert input (fresh ids assigned by the service).
     * Applies per-instance prop overrides (meta.prop → the block's content
     * prop; the component's own value is the default) and turns `slot`
     * placeholders into plain empty containers to fill on the page.
     */
    private function stripIds(array $node, array $overrides = []): array
    {
        $props = $node['props'] ?? [];
        $meta = array_diff_key($node['meta'] ?? [], ['locked' => 1]);

        $propName = trim((string) data_get($node, 'meta.prop', ''));
        $target = self::PROP_TARGET[$node['type']] ?? null;
        if ($propName !== '' && $target && trim((string) ($overrides[$propName] ?? '')) !== '') {
            $props[$target] = $overrides[$propName];
        }
        unset($meta['prop']); // stamped copies are plain blocks, not prop markers

        // Slot placeholder → container the user fills on this page. Blocks
        // placed inside the slot in the component act as its default content.
        if ($node['type'] === 'slot') {
            return [
                'type' => 'container', 'props' => ['padding' => 'sm'], 'style' => $node['style'] ?? [],
                'meta' => ['label' => data_get($node, 'meta.label', 'Slot')],
                'children' => array_map(fn ($c) => $this->stripIds($c, $overrides), $node['children'] ?? []),
            ];
        }

        return [
            'type' => $node['type'],
            'props' => $props,
            'style' => $node['style'] ?? [],
            'meta' => $meta,
            'children' => array_map(fn ($c) => $this->stripIds($c, $overrides), $node['children'] ?? []),
        ];
    }

    /** Layout picker: assign this page's layout (null/'' = the Blank default). */
    public function setPageBlockLayout(?string $layoutId): void
    {
        $page = $this->page();
        if (! $page) {
            return;
        }
        $layout = $layoutId ? BlockLayout::where('site_id', $this->siteId)->find($layoutId) : null;
        // Content-section blocks live on the page — switching layouts never touches them.
        $page->update(['block_layout_id' => $layout?->id]);
        $this->dispatch('bk-mutated');
        $this->dispatch('toast', level: 'success', title: 'Layout set', message: 'Page now uses “'.($layout->name ?? 'Blank').'”.');
    }

    // ─────────────────────────────────────────────── Theme tab

    public function addThemeVariable(): void
    {
        $vars = (array) ($this->theme['variables'] ?? []);
        $vars[] = ['name' => '', 'value' => ''];
        $this->theme['variables'] = $vars;
    }

    public function removeThemeVariable(int $index): void
    {
        $vars = (array) ($this->theme['variables'] ?? []);
        unset($vars[$index]);
        $this->theme['variables'] = array_values($vars);
    }

    public function saveTheme(): void
    {
        $site = $this->site();
        $clean = array_merge(Site::THEME_DEFAULTS, array_intersect_key($this->theme, Site::THEME_DEFAULTS));
        // User-defined variables ($primary, $hero-pad, …) ride along with the theme.
        $clean['variables'] = ThemeTokens::clean((array) ($this->theme['variables'] ?? []));
        $site->update(['theme' => $clean]);
        $this->theme = $clean;
        $this->dispatch('bk-mutated'); // preview picks the new tokens up on reload
        $this->dispatch('toast', level: 'success', title: 'Theme saved', message: 'Site look updated.');
    }

    public function selectPage(string $id): void
    {
        $page = Page::where('site_id', $this->siteId)->find($id);
        if (! $page) {
            return;
        }
        $this->pageId = $page->id;
        $this->editingLayoutId = null;
        $this->selectedId = null;
        $this->resetForms();
        $this->pageName = $page->name;
        $this->pageUrl = $page->url;
        $this->pageScript = (string) $page->getAttr('custom_js', '');
        $this->pagePublished = (bool) $page->is_published;
        $this->service()->ensureRoot($page);
        $this->dispatch('bk-page-changed', pageId: $page->id);
    }

    /** The AI (or another client) mutated the tree — re-render layers + preview. */
    #[On('bk-mutated')]
    public function refreshTree(): void
    {
        // Selected block may have been deleted by the mutation.
        if ($this->selectedId && ! Block::find($this->selectedId)) {
            $this->selectedId = null;
            $this->resetForms();
        } elseif ($this->selectedId) {
            $this->select($this->selectedId); // reload inspector values
        }
    }

    // ─────────────────────────────────────────────── Selection + inspector

    public function select(string $blockId): void
    {
        $owner = $this->owner();
        $col = $owner instanceof BlockLayout ? 'layout_id' : 'page_id';
        $block = Block::where($col, $owner?->id)->find($blockId);
        if (! $block) {
            return;
        }
        $this->selectedId = $blockId;
        $this->propsForm = $block->props ?? [];
        // Responsive enums (e.g. flex direction): a stored string becomes the
        // mobile-first base so the three breakpoint selects bind cleanly.
        foreach ((array) config("blockkit.types.{$block->type}.props", []) as $k => $rule) {
            if (($rule['type'] ?? '') === 'responsive_enum' && is_string($this->propsForm[$k] ?? null)) {
                $this->propsForm[$k] = ['base' => $this->propsForm[$k]];
            }
        }
        $this->styleForm = $block->style ?? [];
        $this->labelForm = $block->label();
        $this->propNameForm = (string) data_get($block->meta, 'prop', '');
        $this->scriptForm = (string) data_get($block->meta, 'script', '');
        $this->dispatch('bk-selected', blockId: $blockId);
    }

    public function saveInspector(): void
    {
        if (! $this->selectedId || ! $this->owner()) {
            return;
        }
        try {
            // Renaming a component prop? Carry every instance's value over to
            // the new name — otherwise placed pages silently fall back to the
            // default and the rename "loses" their content.
            if ($this->editingLayout()?->isComponent() ?? false) {
                $block = Block::find($this->selectedId);
                $old = $block?->type === 'prop'
                    ? trim((string) data_get($block->props, 'name', ''))
                    : trim((string) data_get($block?->meta, 'prop', ''));
                $new = $block?->type === 'prop'
                    ? trim((string) ($this->propsForm['name'] ?? ''))
                    : trim($this->propNameForm);
                if ($old !== '' && $new !== '' && $old !== $new) {
                    $this->renamePropOnInstances($this->editingLayout()->id, $old, $new);
                }
            }

            $this->service()->updateBlock($this->owner(), $this->selectedId, [
                'props' => $this->coerceProps(),
                'style' => collect($this->styleForm)
                    ->map(fn ($v) => is_array($v) ? array_filter($v, fn ($x) => $x !== '' && $x !== null) : $v)
                    ->filter(fn ($v) => $v !== '' && $v !== null && $v !== [])
                    // HTML inputs (ranges, number fields) submit strings — coerce
                    // int-typed style keys so validation sees real integers.
                    ->map(fn ($v, $k) => (config("blockkit.style.{$k}.type") === 'int' && is_numeric($v)) ? (int) $v : $v)
                    ->all(),
                'meta' => array_merge(
                    ['label' => trim($this->labelForm) ?: 'Block', 'script' => trim($this->scriptForm)],
                    // Prop exposure only means something while designing a component.
                    ($this->editingLayout()?->isComponent() ?? false)
                        ? ['prop' => trim($this->propNameForm)]
                        : [],
                ),
            ]);
            $this->dispatch('bk-mutated');
            $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Block updated.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not saved', message: $e->getMessage());
        }
    }

    /** Move stored per-instance override values from an old prop name to its new one. */
    private function renamePropOnInstances(string $componentId, string $old, string $new): void
    {
        Block::where('type', 'component_ref')->get()
            ->filter(fn ($ref) => (int) data_get($ref->props, 'component_id') === $componentId)
            ->each(function ($ref) use ($old, $new) {
                $props = $ref->props ?? [];
                if (array_key_exists($old, $props['overrides'] ?? [])) {
                    $props['overrides'][$new] = $props['overrides'][$old];
                    unset($props['overrides'][$old]);
                    $ref->update(['props' => $props]);
                }
            });
    }

    /** Cast checkbox/int form strings back to the catalogue's declared types. */
    private function coerceProps(): array
    {
        $type = Block::find($this->selectedId)?->type;
        $schema = (array) config("blockkit.types.{$type}.props", []);
        $out = [];
        foreach ($this->propsForm as $key => $value) {
            $rule = $schema[$key] ?? null;
            if (! $rule) {
                continue;
            }
            if ($value === '' || $value === null) {
                // updateBlock MERGES props — pass '' through so "cleared" actually
                // clears (the service strips empty non-required keys after merge).
                $out[$key] = '';

                continue;
            }
            $out[$key] = match ($rule['type']) {
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'int' => (int) $value,
                'columns' => is_array($value) ? array_map('intval', array_filter($value, fn ($v) => $v !== '' && $v !== null)) : (int) $value,
                'options' => is_array($value) ? $value : array_values(array_filter(array_map('trim', explode("\n", (string) $value)))),
                // Responsive enum: drop empty breakpoints; all empty → key omitted upstream.
                'responsive_enum' => is_array($value) ? array_filter($value, fn ($v) => $v !== '' && $v !== null) : $value,
                default => $value,
            };
        }

        // A breakpoint object with every level cleared means “unset”.
        return array_filter($out, fn ($v) => $v !== []);
    }

    public function toggleLock(): void
    {
        if (! $this->selectedId || ! $this->owner()) {
            return;
        }
        $block = Block::find($this->selectedId);
        try {
            $this->service()->updateBlock($this->owner(), $this->selectedId, [
                'meta' => ['locked' => ! $block->isLocked()],
            ]);
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Pin failed', message: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────── Structure ops

    /** Insert a new block (catalogue defaults) into the selected layout block, else the root. */
    public function insertBlock(string $type): void
    {
        $page = $this->owner();
        if (! $page) {
            return;
        }
        $parent = $this->selectedId ? Block::find($this->selectedId) : null;
        if (! $parent || ! $parent->isLayout()) {
            $parent = $this->service()->ensureRoot($page);
        }
        try {
            $created = $this->service()->insertBlocks($page, $parent->id, $parent->children()->count(), [
                $this->starterFor($type),
            ]);
            $this->select($created[0]['id']);
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not added', message: $e->getMessage());
        }
    }

    /** Drop from the palette: insert a new block at an exact spot on the canvas. */
    public function insertBlockAt(string $type, string $parentId, int $index): void
    {
        $owner = $this->owner();
        if (! $owner) {
            return;
        }
        $parent = Block::find($parentId);
        if (! $parent || ! $parent->isLayout()) {
            return;
        }
        try {
            $created = $this->service()->insertBlocks($owner, $parentId, max(0, $index), [
                $this->starterFor($type),
            ]);
            $this->select($created[0]['id']);
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not added', message: $e->getMessage());
        }
    }

    /** Inspector media picker: use a library file — and clear any manual URL so it wins. */
    /** Ancestor chain (root→selected) for the canvas breadcrumb bar. */
    protected function selectedPath(?array $tree): array
    {
        if (! $tree || ! $this->selectedId) {
            return [];
        }
        $path = [];
        $find = function (array $node, array $trail) use (&$find, &$path): bool {
            $trail[] = [
                'id' => $node['id'],
                'type' => $node['type'],
                'label' => $node['meta']['label'] ?? ucfirst($node['type']),
            ];
            if ($node['id'] === $this->selectedId) {
                $path = $trail;

                return true;
            }
            foreach ($node['children'] ?? [] as $child) {
                if ($find($child, $trail)) {
                    return true;
                }
            }

            return false;
        };
        $find($tree, []);

        return $path;
    }

    /** Generic asset pick (x-field.asset modal): set any prop → save immediately. */
    public function pickProp(string $key, string $ref): void
    {
        $this->propsForm[$key] = $ref;
        if ($key === 'asset_id' && array_key_exists('src', $this->propsForm)) {
            $this->propsForm['src'] = ''; // library ref replaces a manual URL
        }
        $this->saveInspector();
    }

    /** One-click centering: margin-left/right auto (MDN pattern) + save. */
    public function centerBlock(string $bag = 'props'): void
    {
        $form = $bag === 'style' ? 'styleForm' : 'propsForm';
        $margin = (array) ($this->{$form}['margin'] ?? []);
        $this->{$form}['margin'] = array_merge(['top' => $margin['top'] ?? '0', 'bottom' => $margin['bottom'] ?? '0'], $margin, ['left' => 'auto', 'right' => 'auto']);
        $this->saveInspector();
    }

    /** Inline rich-text editing on the canvas: dblclick → edit → blur saves here. */
    public function updateContent(string $blockId, string $html): void
    {
        $owner = $this->owner();
        if (! $owner) {
            return;
        }
        try {
            // Sanitized inside the service — the canvas sends raw editor HTML.
            $this->service()->updateBlock($owner, $blockId, ['props' => ['content' => $html]]);
            if ($this->selectedId === $blockId) {
                $this->select($blockId); // refresh inspector copy
            }
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not saved', message: $e->getMessage());
        }
    }

    public function deselect(): void
    {
        $this->selectedId = null;
        $this->propsForm = $this->styleForm = [];
        $this->labelForm = '';
    }

    // ─────────────────────────────────────────────── Reusable blocks

    /** Save the selected block (and everything inside it) as a named reusable. */
    public function saveReusable(string $name): void
    {
        $name = trim($name);
        if (! $name || ! $this->selectedId) {
            return;
        }
        $block = Block::find($this->selectedId);
        if (! $block || $block->type === 'content_slot') {
            return;
        }
        BlockPreset::create([
            'user_id' => auth()->id(),
            'name' => $name,
            'root_type' => $block->type,
            'tree' => $this->subtreeArray($block),
        ]);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: "“{$name}” added to Reusable blocks.");
    }

    public function deleteReusable(string $id): void
    {
        BlockPreset::where('user_id', auth()->id())->whereKey($id)->delete();
    }

    /** Drop / click a reusable: insert a fresh copy of its subtree. */
    public function insertReusableAt(string $id, ?string $parentId = null, ?int $index = null): void
    {
        $owner = $this->owner();
        $preset = BlockPreset::where('user_id', auth()->id())->find($id);
        if (! $owner || ! $preset) {
            return;
        }
        $parent = $parentId ? Block::find($parentId) : null;
        if (! $parent || ! $parent->isLayout()) {
            $parent = $this->service()->ensureRoot($owner);
        }
        try {
            $created = $this->service()->insertBlocks($owner, $parent->id, $index ?? $parent->children()->count(), [$preset->tree]);
            $this->select($created[0]['id']);
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not added', message: $e->getMessage());
        }
    }

    /** An id-less nested array of a block — ready to re-insert anywhere. */
    private function subtreeArray(Block $block): array
    {
        return [
            'type' => $block->type,
            'props' => $block->props ?? [],
            'style' => $block->style ?? [],
            'meta' => array_diff_key($block->meta ?? [], ['locked' => 1]),
            'children' => $block->children()->orderBy('position')->get()->map(fn ($c) => $this->subtreeArray($c))->values()->all(),
        ];
    }

    // ─────────────────────────────────────────────── Builder templates
    //    A template = the whole build (content + layout + theme), saved per
    //    user. users.template_limit caps how many (1 free slot by default).

    private function templateQuery()
    {
        return BuilderTemplate::where('user_id', auth()->id());
    }

    /** Snapshot the current build into a named template. */
    public function saveTemplate(string $name): void
    {
        $name = trim($name);
        $page = $this->page();
        if (! $name || ! $page) {
            return;
        }
        $limit = max(1, (int) (auth()->user()->template_limit ?? 1));
        if ($this->templateQuery()->count() >= $limit) {
            $this->dispatch('toast', level: 'error', title: 'Template limit reached',
                message: "Your plan allows {$limit} ".str('template')->plural($limit).'. Update an existing one, or upgrade for more slots.');

            return;
        }

        $layout = $page->resolvedBlockLayout();
        $pageRoot = $this->service()->ensureRoot($page);
        $layoutRoot = $this->service()->ensureRoot($layout);

        BuilderTemplate::create([
            'user_id' => auth()->id(),
            'name' => $name,
            'is_default' => $this->templateQuery()->count() === 0, // first one = the default/free slot
            'payload' => [
                'content' => $pageRoot->children()->orderBy('position')->get()->map(fn ($c) => $this->subtreeArray($c))->values()->all(),
                'layout' => $layout->is_system ? [] : $layoutRoot->children()->orderBy('position')->get()->map(fn ($c) => $this->subtreeArray($c))->values()->all(),
                'layout_name' => $layout->is_system ? null : $layout->name,
                'theme' => $this->site()->themeValues(),
            ],
        ]);
        $this->dispatch('toast', level: 'success', title: 'Template saved', message: "“{$name}” captured — content, layout and theme.");
    }

    /** Overwrite an existing template with the current build. */
    public function updateTemplate(string $id): void
    {
        $tpl = $this->templateQuery()->find($id);
        $page = $this->page();
        if (! $tpl || ! $page) {
            return;
        }
        $layout = $page->resolvedBlockLayout();
        $pageRoot = $this->service()->ensureRoot($page);
        $layoutRoot = $this->service()->ensureRoot($layout);
        $tpl->update(['payload' => [
            'content' => $pageRoot->children()->orderBy('position')->get()->map(fn ($c) => $this->subtreeArray($c))->values()->all(),
            'layout' => $layout->is_system ? [] : $layoutRoot->children()->orderBy('position')->get()->map(fn ($c) => $this->subtreeArray($c))->values()->all(),
            'layout_name' => $layout->is_system ? null : $layout->name,
            'theme' => $this->site()->themeValues(),
        ]]);
        $this->dispatch('toast', level: 'success', title: 'Template updated', message: "“{$tpl->name}” now holds the current build.");
    }

    /** Load a template into the current page — then modify it freely. */
    public function applyTemplate(string $id): void
    {
        $tpl = $this->templateQuery()->find($id);
        $page = $this->page();
        if (! $tpl || ! $page) {
            return;
        }
        $p = $tpl->payload;
        try {
            // 1 · Content: replace the page's blocks with the template's.
            $root = $this->service()->ensureRoot($page);
            $ids = $root->children()->pluck('id')->all();
            if ($ids) {
                // The user already confirmed the replace in the Apply dialog.
                $this->service()->deleteBlocks($page, $ids, confirmed: true);
            }
            if (! empty($p['content'])) {
                $this->service()->insertBlocks($page, $root->id, 0, $p['content']);
            }

            // 2 · Layout: recreate the template's layout (fresh copy) or fall back to Blank.
            //    The stored children include the content_slot as a position marker —
            //    blocks before it insert above the fresh layout's slot, the rest below.
            $layoutNodes = array_values($p['layout'] ?? []);
            $nonSlot = array_values(array_filter($layoutNodes, fn ($n) => ($n['type'] ?? '') !== 'content_slot'));
            if ($nonSlot !== []) {
                $layout = BlockLayout::make($this->site(), ($p['layout_name'] ?? $tpl->name).' (template)');
                $lroot = $this->service()->ensureRoot($layout);
                $slotIdx = collect($layoutNodes)->search(fn ($n) => ($n['type'] ?? '') === 'content_slot');
                $pre = $slotIdx === false ? $nonSlot : array_slice($layoutNodes, 0, $slotIdx);
                $post = $slotIdx === false ? [] : array_values(array_filter(array_slice($layoutNodes, $slotIdx + 1), fn ($n) => ($n['type'] ?? '') !== 'content_slot'));
                if ($pre !== []) {
                    $this->service()->insertBlocks($layout, $lroot->id, 0, $pre);
                }
                if ($post !== []) {
                    $this->service()->insertBlocks($layout, $lroot->id, count($pre) + 1, $post);
                }
                $page->update(['block_layout_id' => $layout->id]);
            } else {
                $page->update(['block_layout_id' => null]); // Blank
            }

            // 3 · Theme.
            if (! empty($p['theme'])) {
                $this->theme = $p['theme'];
                $this->saveTheme();
            }

            $this->deselect();
            $this->dispatch('bk-mutated');
            $this->dispatch('toast', level: 'success', title: 'Template applied', message: "“{$tpl->name}” loaded — keep building on top of it.");
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not applied', message: $e->getMessage());
        }
    }

    public function setDefaultTemplate(string $id): void
    {
        $this->templateQuery()->update(['is_default' => false]);
        $this->templateQuery()->whereKey($id)->update(['is_default' => true]);
    }

    public function deleteTemplate(string $id): void
    {
        $tpl = $this->templateQuery()->find($id);
        if (! $tpl) {
            return;
        }
        $wasDefault = $tpl->is_default;
        $tpl->delete();
        if ($wasDefault) {
            $this->templateQuery()->first()?->update(['is_default' => true]);
        }
    }

    // ─────────────────────────────────────────────── Export HTML

    public ?string $exportHtml = null;

    public function exportPage(): void
    {
        $page = $this->page();
        if (! $page && ! $this->owner()) {
            return;
        }
        $tree = $this->editingLayoutId
            ? $this->service()->tree($this->owner())
            : $this->composeCanvas($this->page());
        $tree = $this->service()->expandComponentRefs($tree, $this->siteId); // live components → real markup
        $tree = $this->resolveBgImages($tree); // @media/… background refs → URLs
        $this->exportHtml = BlockHtmlExporter::render(
            $tree,
            $this->page()?->name ?? 'Exported page',
            (array) ($this->site()->themeValues()['variables'] ?? []),
            (string) ($this->page()?->getAttr('custom_js', '') ?? ''),
        );
    }

    public function closeExport(): void
    {
        $this->exportHtml = null;
    }

    /** PUBLISH (default): generate the standalone Nuxt 4 app and push it to olux-studio/templates. */
    public function publishSite(): void
    {
        try {
            $dest = app(SiteAppExporter::class)->publish($this->site());
            $this->dispatch('toast', level: 'success', title: 'Site published',
                message: "Nuxt 4 app pushed to templates/{$this->site()->name} — run npm install && npm run dev there.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', level: 'error', title: 'Publish failed', message: $e->getMessage());
        }
    }

    /** EXPORT: generate the standalone Nuxt 4 app and download it as a zip. */
    public function exportSiteZip(): mixed
    {
        try {
            $zip = app(SiteAppExporter::class)->zip($this->site());

            return response()->download($zip, $this->site()->name.'-site.zip')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', level: 'error', title: 'Export failed', message: $e->getMessage());

            return null;
        }
    }

    /** Walk a tree resolving @media/… bg_image refs to served URLs (export path). */
    private function resolveBgImages(array $node): array
    {
        if (! empty($node['props']['bg_image']) && str_starts_with((string) $node['props']['bg_image'], '@media/')) {
            $node['props']['bg_image'] = Media::resolveRef($this->siteId, (string) $node['props']['bg_image']);
        }
        $node['children'] = array_map(fn ($c) => $this->resolveBgImages($c), $node['children'] ?? []);

        return $node;
    }

    /** A sensible starter subtree per type — required props filled with editable defaults. */
    protected function starterFor(string $type): array
    {
        $label = config("blockkit.types.{$type}.name", $type);
        $starters = [
            // ── Palette aliases: friendly pieces that insert a preset block ──
            'video_player' => ['type' => 'media', 'props' => ['kind' => 'video', 'src' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'controls' => true, 'ratio' => '16:9'], 'meta' => ['label' => 'Video player']],
            'image_viewer' => ['type' => 'media', 'props' => ['kind' => 'image', 'image_brief' => 'Describe the image you want here', 'alt' => 'Image'], 'meta' => ['label' => 'Image viewer']],
            'logo' => ['type' => 'media', 'props' => ['kind' => 'image', 'image_brief' => 'Your logo', 'alt' => 'Logo', 'fit' => 'contain'], 'meta' => ['label' => 'Logo']],

            'prop' => ['props' => ['name' => 'prop_'.substr(uniqid(), -4), 'kind' => 'heading', 'default' => 'Default value'], 'meta' => ['label' => 'Prop']],
            'navbar' => ['props' => ['items' => ['Home | /', 'About | /about', 'Contact | /contact']]],
            'panel' => ['props' => []],
            'header' => ['props' => ['content' => 'New heading', 'level' => 'h2']],
            'content' => ['props' => ['content' => 'New paragraph — click to edit.']],
            'button' => ['props' => ['label' => 'Click me', 'action' => ['type' => 'link', 'target' => '#']]],
            'media' => ['props' => ['kind' => 'image', 'image_brief' => 'Describe the image you want here', 'alt' => 'Placeholder image']],
            'tile' => ['props' => ['label' => 'New tile', 'media' => ['ratio' => '4:3'], 'action' => ['type' => 'link', 'target' => '#']]],
            'list' => ['props' => ['items' => ['First item', 'Second item', 'Third item']]],
            'lightbox' => ['props' => ['media_ids' => ['(paste media block ids here)']]],
            'modal' => ['props' => ['title' => 'New modal'], 'children' => [
                ['type' => 'content', 'props' => ['content' => 'Modal content — wire a button action (open_modal) to this modal’s id.'], 'meta' => ['label' => 'Modal text']],
            ]],
            'card' => ['props' => ['variant' => 'elevated'], 'children' => [
                ['type' => 'header', 'props' => ['content' => 'Card title', 'level' => 'h3'], 'meta' => ['label' => 'Card title']],
                ['type' => 'content', 'props' => ['content' => 'Card body copy.'], 'meta' => ['label' => 'Card body']],
            ]],
            'input' => ['props' => ['name' => 'field_'.substr(uniqid(), -4), 'label' => 'New field', 'field_type' => 'text']],
            'textarea' => ['props' => ['name' => 'message_'.substr(uniqid(), -4), 'label' => 'Message']],
            'select' => ['props' => ['name' => 'choice_'.substr(uniqid(), -4), 'label' => 'Choose one', 'options' => ['Option A', 'Option B']]],
            'checkbox' => ['props' => ['name' => 'agree_'.substr(uniqid(), -4), 'label' => 'I agree']],
            'form' => ['props' => ['submit_action' => 'collect', 'success_message' => 'Thanks — we got your message.'], 'children' => [
                ['type' => 'input', 'props' => ['name' => 'email', 'label' => 'Email', 'field_type' => 'email', 'required' => true], 'meta' => ['label' => 'Email field']],
                ['type' => 'button', 'props' => ['label' => 'Send', 'action' => ['type' => 'submit']], 'meta' => ['label' => 'Submit button']],
            ]],
        ];

        return array_merge(
            ['type' => $type, 'props' => [], 'meta' => ['label' => $label]],
            $starters[$type] ?? [],
        );
    }

    /** Drag-and-drop handler — SortableJS onEnd calls this. */
    public function moveBlock(string $blockId, string $newParentId, int $position): void
    {
        $page = $this->owner();
        if (! $page) {
            return;
        }
        try {
            $this->service()->moveBlock($page, $blockId, $newParentId, $position);
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not moved', message: $e->getMessage());
        }
    }

    public function deleteBlock(string $blockId, bool $confirmed = false): void
    {
        $page = $this->owner();
        if (! $page) {
            return;
        }
        try {
            $this->service()->deleteBlocks($page, [$blockId], $confirmed);
            if ($this->selectedId === $blockId) {
                $this->selectedId = null;
                $this->resetForms();
            }
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'confirmation_required')) {
                // Surface the confirm dialog client-side; retry arrives confirmed.
                $this->dispatch('bk-confirm-delete', blockId: $blockId);

                return;
            }
            $this->dispatch('toast', level: 'error', title: 'Not deleted', message: $e->getMessage());
        }
    }

    public function duplicateBlock(string $blockId): void
    {
        $page = $this->owner();
        if (! $page) {
            return;
        }
        try {
            $copy = $this->service()->duplicateBlock($page, $blockId);
            $this->select($copy->id);
            $this->dispatch('bk-mutated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Not duplicated', message: $e->getMessage());
        }
    }

    protected function resetForms(): void
    {
        $this->propsForm = [];
        $this->styleForm = [];
        $this->labelForm = '';
        $this->propNameForm = '';
    }

    protected function editingLayout(): ?BlockLayout
    {
        return $this->editingLayoutId
            ? BlockLayout::where('site_id', $this->siteId)->find($this->editingLayoutId)
            : null;
    }

    // ─────────────────────────────────────────────── Undo / redo

    public function undo(): void
    {
        if (! $this->owner()) {
            return;
        }
        $label = $this->service()->undo($this->owner());
        if ($label !== null) {
            $this->refreshTree();
            $this->dispatch('bk-mutated');
            $this->dispatch('toast', level: 'success', title: 'Undone', message: $label);
        }
    }

    public function redo(): void
    {
        if (! $this->owner()) {
            return;
        }
        $label = $this->service()->redo($this->owner());
        if ($label !== null) {
            $this->refreshTree();
            $this->dispatch('bk-mutated');
            $this->dispatch('toast', level: 'success', title: 'Redone', message: $label);
        }
    }

    // ─────────────────────────────────────────────── Render

    public function render()
    {
        BlockLayout::blank($this->site()); // the undeletable default always exists

        $page = $this->page();
        $owner = $this->owner();
        $editingLayout = $this->editingLayoutId ? $owner : null;
        $selected = $this->selectedId ? Block::find($this->selectedId) : null;

        // The canvas tree: the layout's own tree in the Layout View; on a page,
        // the layout wraps the page — layout nodes marked read-only (_ro), the
        // content_slot carrying the page's editable children. WYSIWYG both ways.
        $tree = null;
        if ($editingLayout) {
            $tree = $this->service()->tree($editingLayout);
        } elseif ($page) {
            $tree = $this->composeCanvas($page);
        }
        if ($tree) {
            // Live-linked components render THROUGH their refs on the canvas.
            $tree = $this->service()->expandComponentRefs($tree, $this->siteId, forCanvas: true);
        }

        return view('livewire.block-editor', [
            'canUndo' => $owner ? $this->service()->canUndo($owner) : null,
            'canRedo' => $owner ? $this->service()->canRedo($owner) : null,
            'site' => $this->site(),
            'page' => $page,
            'editingLayout' => $editingLayout,
            'pages' => $this->site()->livePages()->orderBy('id')->get(),
            'blockLayouts' => BlockLayout::layouts()->where('site_id', $this->siteId)->withCount('pages')->orderByDesc('is_system')->orderBy('name')->get(),
            'components' => BlockLayout::components()->where('site_id', $this->siteId)->withCount('blocks')->orderBy('name')->get(),
            'pageLayout' => $page?->resolvedBlockLayout(),
            'tree' => $tree,
            'selected' => $selected,
            // Ancestor chain root→selected — the breadcrumb bar renders it so
            // ANY wrapper is one tap away (the mobile answer to hover/cycling).
            'selectedPath' => $this->selectedPath($tree),
            'schema' => $selected ? (array) config('blockkit.types.'.$selected->type.'.props', []) : [],
            'styleSchema' => (array) config('blockkit.style', []),
            'catalogue' => config('blockkit.types'),
            'palette' => $this->palette(),
            'previewUrl' => $page && ! $editingLayout ? $this->site()->previewUrl($page->url) : null,
            'reusables' => BlockPreset::where('user_id', auth()->id())->orderBy('name')->get(),
            'mediaImages' => $selected && in_array($selected->type, ['media', 'tile', 'container', 'panel', 'grid', 'flex', 'card', 'masonry', 'modal', 'lightbox'], true)
                ? Media::where('site_id', $this->siteId)->where('file_type', 'image')->latest()->limit(30)->get()
                : collect(),
            'builderTemplates' => BuilderTemplate::where('user_id', auth()->id())->orderByDesc('is_default')->orderBy('name')->get(),
            // Live instance inspector: the linked component + its exposed props.
            'refComponent' => $refComponent = ($selected?->type === 'component_ref')
                ? BlockLayout::components()->where('site_id', $this->siteId)->find((int) data_get($selected->props, 'component_id'))
                : null,
            'refProps' => $refComponent ? $this->componentProps($refComponent) : [],
            'templateLimit' => max(1, (int) (auth()->user()->template_limit ?? 1)),
            'mediaVideos' => $selected && $selected->type === 'media'
                ? Media::where('site_id', $this->siteId)->where('file_type', 'video')->latest()->limit(30)->get()
                : collect(),
            // Rich-text link picker: link to a site page or a media file.
            'linkMedia' => Media::where('site_id', $this->siteId)->latest()->limit(40)->get(),
        ]);
    }

    /**
     * The ＋ Add block palette, grouped per the builder taxonomy. Entries whose
     * key isn't a catalogue type are aliases — starterFor() maps them to a
     * preset block (e.g. Video player → media kind:video).
     */
    private function palette(): array
    {
        $t = fn (string $key) => config("blockkit.types.{$key}");
        $alias = fn (string $name, string $icon, string $desc) => ['name' => $name, 'icon' => $icon, 'description' => $desc];

        return [
            'Containers' => [
                'container' => $t('container'),
                'panel' => $t('panel'),
                'card' => $t('card'),
                'modal' => $t('modal'),
                // Slot + Prop: shown only while designing a component (the blade filters them).
                'slot' => $t('slot'),
                'prop' => $t('prop'),
            ],
            'Grid' => [
                'grid' => $t('grid'),
                'flex' => $t('flex'),
                'masonry' => $t('masonry'),
            ],
            'Content' => [
                'header' => $t('header'),
                'content' => $t('content'),
                'navbar' => $t('navbar'),
                'tile' => $t('tile'),
                'list' => $t('list'),
                'button' => $t('button'),
                'divider' => $t('divider'),
            ],
            'Media' => [
                'image_viewer' => $alias('Image viewer', '🖼', 'An image from the media library or a brief.'),
                'video_player' => $alias('Video player', '▶', 'YouTube, Vimeo or a hosted video file.'),
                'logo' => $alias('Logo', '◆', 'Your brand mark — an image that keeps its proportions.'),
                'lightbox' => $t('lightbox'),
            ],
            'Form' => [
                'form' => $t('form'),
                'input' => $t('input'),
                'textarea' => $t('textarea'),
                'select' => $t('select'),
                'checkbox' => $t('checkbox'),
            ],
        ];
    }

    /**
     * Canvas tree for a page: the layout's blocks (read-only, `_ro`) around the
     * content section, whose children are the page's own editable tree.
     */
    private function composeCanvas(Page $page): array
    {
        $layout = $page->resolvedBlockLayout();
        $layoutTree = $this->service()->tree($layout);
        $pageTree = $this->service()->tree($page);

        // Blank (or any layout that adds nothing): the canvas IS the page —
        // no wrapper chrome, no slot box. Totally empty page = totally empty canvas.
        $layoutKids = $layoutTree['children'] ?? [];
        if (count($layoutKids) === 1 && ($layoutKids[0]['type'] ?? '') === 'content_slot') {
            return $pageTree; // bare page tree; the root renders as an invisible drop area
        }

        $mark = function (array $node) use (&$mark, $pageTree, $layout) {
            if ($node['type'] === 'content_slot') {
                $node['_slot'] = true;
                // Drops into the slot land on the PAGE's root — the slot itself
                // belongs to the layout and never receives page blocks directly.
                $node['id'] = $pageTree['id'];
                $node['children'] = $pageTree['children'];

                return $node;
            }
            $node['_ro'] = true;
            $node['_layout'] = $layout->name;
            $node['children'] = array_map($mark, $node['children']);

            return $node;
        };

        return $mark($layoutTree);
    }
}
