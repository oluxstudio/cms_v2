<?php

namespace App\Livewire;

use App\Jobs\InstallTemplateJob;
use App\Livewire\Concerns\WithNestedFields;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\ContentVersion;
use App\Models\Form;
use App\Models\Media;
use App\Models\Node;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Services\CollectionSourceExtractor;
use App\Services\ContentVersioner;
use App\Services\SiteConnect\AssetImporter;
use App\Services\SiteConnect\PageJsonPublisher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component as LivewireComponent;

/**
 * Site preview & edit: the CMS embeds the LIVE client site in an iframe
 * (with ?olx-edit=1), so the preview looks exactly like the client site.
 * connect.js in edit mode outlines each component and postMessages clicks up;
 * a reliable Livewire.dispatch bridge turns that into select(), opening the
 * editor here. Saving writes to the real models and refreshes the iframe. The
 * client site renders its content from the CMS page.json (same data-olx markers).
 */
class ConnectReviewPage extends LivewireComponent
{
    use WithNestedFields;

    /** Roots the nested-field editor may mutate. */
    protected array $nestedRoots = ['edit.items.'];

    public Site $site;

    /** Embedded inside another page (e.g. the page-detail Content tab): hides page-level chrome like the client-URL bar. */
    public bool $embedded = false;

    /** The client site URL to embed (stored as the `client_url` site attribute). */
    public string $clientUrl = '';

    /** Input buffer for setting/changing the client URL. */
    public string $urlInput = '';

    /** Path of the page shown in the preview iframe ('/' = home). */
    public string $previewPath = '/';

    /** Selected content: kind (component|collection|form|post) + id. */
    public ?string $selectedKind = null;

    public ?string $selectedId = null;

    /** 'view' (read-only) or 'edit' (the inline editor). */
    public string $mode = 'view';

    /** Editable buffer for the selected model. */
    public array $edit = [];

    public string $flash = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'components.view'), 403);
        $this->clientUrl = (string) $site->getAttr('client_url', '');
        $this->urlInput = $this->clientUrl;
    }

    /** Save the client site URL to embed in the preview iframe. */
    public function saveClientUrl(): void
    {
        $this->guard();
        $url = trim($this->urlInput);
        $this->site->setAttr('client_url', $url);
        $this->clientUrl = $url;
        $this->flash = $url ? 'Preview URL saved.' : 'Preview URL cleared.';
    }

    /**
     * Background template-install state: '' | installing | done | failed.
     * Set by TemplateInstaller::bind() and finished by InstallTemplateJob;
     * the blade polls while it's 'installing'.
     */
    public function getInstallStatusProperty(): string
    {
        return (string) $this->site->getAttr('template_install', '');
    }

    /** Re-run the background install for the currently applied design. */
    public function retryInstall(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'builder.manage'), 403);
        $row = $this->site->installedTemplates()->whereNotNull('applied_at')->first();
        if (! $row) {
            return;
        }
        $this->site->setAttr('template_install', 'installing');
        InstallTemplateJob::dispatch($this->site->id, $row->id);
    }

    /**
     * Live preview: the clean visitor-facing view of the current page —
     * the applied template rendering this site's content, NO edit chrome.
     */
    public function getLivePreviewUrlProperty(): ?string
    {
        if ($this->clientUrl !== '') {
            return rtrim($this->clientUrl, '/').'/'.ltrim($this->previewPath, '/');
        }

        return $this->site->templatePreviewUrl(ltrim($this->previewPath, '/') ?: null);
    }

    /** Renderer mode: no external client URL — embed the site's own template renderer. */
    public function getRendererModeProperty(): bool
    {
        return $this->clientUrl === '';
    }

    /**
     * The iframe src: the external client site (edit mode) when connected,
     * otherwise the site's OWN template renderer showing live CMS content.
     */
    public function getEmbedUrlProperty(): ?string
    {
        if ($this->clientUrl === '') {
            $page = ltrim($this->previewPath, '/') ?: null;

            $url = $this->site->templatePreviewUrl($page);

            // Edit mode for the shell's olux-edit plugin (click-to-edit).
            return $url ? $url.(str_contains($url, '?') ? '&' : '?').'olx-edit=1' : null;
        }
        $base = rtrim($this->clientUrl, '/').'/'.ltrim($this->previewPath, '/');
        $sep = str_contains($base, '?') ? '&' : '?';

        return rtrim($base, '/').$sep.'olx-edit=1';
    }

    /** Switching pages closes the editor — the selection belonged to the old page. */
    public function updatedPreviewPath(): void
    {
        $this->deselect();
    }

    /** connect.js click bridge → select the clicked component by id or key. */
    #[On('olx-edit-select')]
    public function onEditSelect(?string $id = null, ?string $key = null, string $kind = 'component', ?int $itemIndex = null, ?string $itemText = null): void
    {
        // The store's product grid edits like a collection grid: the panel
        // key "products" opens the site's Products as entry cards.
        if ($id === null && $kind === 'collection' && strtolower((string) $key) === 'products') {
            $this->select('products', 'products');
            if (($index = $itemText !== null ? $this->itemIndexByText($itemText) : $itemIndex) !== null) {
                $this->dispatch('olx-editor-focus', target: 'item', index: $index);
            }

            return;
        }
        if ($id) {
            $this->select($kind, $id);
        } elseif ($key && ($model = $this->resolveByKey($kind, $key)
                // Authors also mark plain component sections with
                // data-olx-panel (e.g. "donate-cta") — when no collection
                // carries the slug, open the component of that name
                // instead of loading nothing.
                ?? ($kind === 'collection' ? $this->componentByKey(strtolower($key)) : null))) {
            // The fallback may resolve across kinds — trust the model, not
            // the marker.
            $kind = match (true) {
                $model instanceof Component => 'component',
                $model instanceof Collection => 'collection',
                $model instanceof Form => 'form',
                default => $kind,
            };
            // A node-less component is an empty panel; when the pipeline
            // linked it to its data-source collection, open THAT — the
            // section's real, editable content (legal sections, archives…).
            if ($model instanceof Component && $model->collection_id
                && ! $model->nodes()->exists()
                && Collection::where('site_id', $this->site->id)->whereKey($model->collection_id)->exists()) {
                [$kind, $model] = ['collection', Collection::find($model->collection_id)];
            }
            $this->select($kind, $model->id);
        } else {
            return;
        }
        // Clicked a specific entry in the preview → open its card in the panel.
        if (($this->edit['type'] ?? null) === 'collection') {
            // Templates sort/filter their grids, so the clicked row's DOM
            // index rarely matches our item order — match by the row's
            // visible text first, fall back to the index.
            $matched = $itemText !== null ? $this->itemIndexByText($itemText) : null;
            $index = $matched ?? $itemIndex;
            if ($index !== null) {
                $this->dispatch('olx-editor-focus', target: 'item', index: $index);
            }
        }
    }

    /**
     * Find the collection entry the clicked preview row shows: the item
     * whose longest string value appears in the row's visible text wins.
     */
    private function itemIndexByText(string $itemText): ?int
    {
        $norm = fn (string $s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
        $hay = $norm($itemText);
        if ($hay === '') {
            return null;
        }
        $best = null;
        $bestLen = 0;
        foreach (($this->edit['items'] ?? []) as $i => $item) {
            foreach (($item['data'] ?? []) as $v) {
                if (! is_string($v)) {
                    continue;
                }
                $val = $norm($v);
                $len = mb_strlen($val);
                // Short values ("yes", "2") match everything — require substance.
                if ($len >= 6 && $len > $bestLen && str_contains($hay, $val)) {
                    $best = $i;
                    $bestLen = $len;
                }
            }
        }

        return $best;
    }

    /** Resolve a hand-authored client marker (data-olx-key) to a real model. */
    private function resolveByKey(string $kind, string $key): mixed
    {
        $lkey = strtolower($key);

        return match ($kind) {
            // Markers may carry the slug ("about-points") OR its camel form
            // ("heroTags") — accept both by kebab-ing the key too.
            'collection' => Collection::where('site_id', $this->site->id)
                ->whereRaw('LOWER(slug) IN (?, ?)', [$lkey, strtolower(Str::kebab((string) $key))])->first(),
            'form' => Form::where('site_id', $this->site->id)->whereRaw('LOWER(name) = ?', [$lkey])->first(),
            'post' => Post::where('site_id', $this->site->id)->whereRaw('LOWER(slug) = ?', [$lkey])->first(),
            // Component key = camelCase(name); match case-insensitively so
            // "Book CTA" (→bookCTA) resolves a data-olx-key="bookCta". Renderer
            // shells send the slug form instead ("Why Us" → why-us) — accept
            // both, PREFERRING components attached to the page being previewed
            // (legacy sites can carry same-named components on several pages).
            default => $this->componentByKey($lkey),
        };
    }

    /**
     * "＋ Add item" under a repeatable list in the preview: rows live as
     * component nodes labelled "{Prefix} {n} {Field}" — clone the first
     * row's field set as row n+1 (values copied so the layout stays whole),
     * then open the component so the owner can edit the new row.
     */
    public function inlineNodeItemAdd(?string $key, string $prefix): void
    {
        $this->guard();
        $component = $this->componentByKey(strtolower((string) $key));
        if (! $component || $prefix === '') {
            return;
        }
        $component->load('nodes');

        $rows = [];
        foreach ($component->nodes as $n) {
            if (preg_match('/^'.preg_quote($prefix, '/').' (\d+) (.+)$/', (string) $n->label, $m)) {
                $rows[(int) $m[1]][$m[2]] ??= $n;
            }
        }
        if ($rows === []) {
            return;
        }
        ksort($rows);
        $next = max(array_keys($rows)) + 1;
        $template = $rows[array_key_first($rows)];

        app(ContentVersioner::class)->capture($component, Auth::user()?->name);
        $order = (int) $component->nodes->max('order') + 1;
        foreach ($template as $field => $node) {
            $component->nodes()->create([
                'label' => "{$prefix} {$next} {$field}",
                'type' => $node->type,
                'value' => (string) $node->value,
                'parent' => '0',
                'order' => $order++,
            ]);
        }

        $this->select('component', $component->id);
        $this->dispatch('olx-editor-focus', target: 'fields');
        $this->refreshPreview('Item added');
    }

    /** Slug/camel component lookup, current-preview-page first. */
    private function componentByKey(string $lkey): ?Component
    {
        $match = fn (Component $c) => strtolower(Str::camel($c->name)) === $lkey || Str::slug($c->name) === $lkey;

        $page = $this->site->livePages()->where('url', '/'.ltrim($this->previewPath, '/'))->first();
        if ($page && ($hit = $page->components()->with('nodes')->get()->first($match))) {
            return $hit;
        }

        return $this->site->contentComponents()->get()->first($match);
    }

    /** Media picker selection → fill the image node being edited (@media ref). */
    #[On('media-picked')]
    public function onMediaPicked(array $context = [], string $mediaRef = '', string $url = ''): void
    {
        // New-schema-field default value (collection editor's "+ Add field").
        if (($context['scope'] ?? '') === 'connect-new-field') {
            $this->newField['default'] = str_starts_with($url, '/') ? url($url) : $url;

            return;
        }
        if (($context['scope'] ?? '') !== 'connect') {
            return;
        }
        $i = $context['nodeIndex'] ?? null;
        if ($i !== null && isset($this->edit['nodes'][$i])) {
            $this->edit['nodes'][$i]['value'] = $mediaRef;
            // Picking an asset for an EXISTING node applies immediately —
            // no separate "Save component" needed for the swap to show.
            $nodeId = $this->edit['nodes'][$i]['id'] ?? null;
            if ($nodeId) {
                $node = Node::where('id', $nodeId)
                    ->whereHas('component', fn ($q) => $q->where('site_id', $this->site->id))
                    ->first();
                if ($node) {
                    if ($component = Component::where('site_id', $this->site->id)->find($node->component_id)) {
                        app(ContentVersioner::class)->capture($component, Auth::user()?->name);
                    }
                    $node->update(['value' => $mediaRef]);
                    $this->refreshPreview('Image updated');
                }
            }

            return;
        }
        // Collection item field: item data isn't @media-resolved by the
        // generator, so store the absolute asset URL instead of the ref.
        $item = $context['itemIndex'] ?? null;
        $key = $context['itemKey'] ?? null;
        if ($item !== null && $key !== null && isset($this->edit['items'][$item])) {
            $value = str_starts_with($url, '/') ? url($url) : $url;
            $this->edit['items'][$item]['data'][$key] = $value;
            // Existing items apply immediately (new/unsaved rows wait for Save).
            $itemId = $this->edit['items'][$item]['id'] ?? null;
            $row = $itemId ? CollectionItem::where('id', $itemId)->where('site_id', $this->site->id)->first() : null;
            if ($row) {
                if ($col = Collection::where('site_id', $this->site->id)->find($row->collection_id)) {
                    app(ContentVersioner::class)->capture($col, Auth::user()?->name);
                }
                $row->update(['data' => array_merge($row->data ?? [], [$key => $value])]);
                $this->refreshPreview('Image updated');
            }
        }
    }

    /**
     * Detail routes ([slug].vue) grouped with real URLs from their data
     * source — profiles, study/sermon/programme pages render by id exactly
     * like the original site, so the editor lists them for preview too.
     *
     * @return array<string, array<int, array{label:string,url:string}>>
     */
    public function getDynamicPagesProperty(): array
    {
        $appDir = base_path('templates/'.$this->site->template);
        if (! $this->site->template || ! is_dir("$appDir/app/pages")) {
            return [];
        }
        $extractor = app(CollectionSourceExtractor::class);
        $groups = [];
        foreach (File::allFiles("$appDir/app/pages") as $f) {
            if ($f->getExtension() !== 'vue' || ! str_starts_with($f->getFilename(), '[')) {
                continue;
            }
            $rel = str_replace('\\', '/', Str::after($f->getPathname(), '/app/pages/'));
            $base = trim(dirname($rel), './');
            if ($base === '') {
                continue;
            }
            // Several data sources may match (shared composables) — take the
            // first whose rows actually produce routable keys. Rows without a
            // slug/id field route by their item id (e.g. media detail pages).
            $candidates = collect($extractor->sourceSlugsForFile($appDir, $f->getPathname()))
                ->map(fn ($slug) => $this->site->collections()->get()
                    ->first(fn ($c) => Str::slug($c->slug ?: $c->name) === Str::slug($slug)))
                ->filter();
            $build = function ($col, bool $allowItemId) use ($base) {
                return $col->items()->limit(6)->get()->map(function ($i) use ($base, $allowItemId) {
                    $d = (array) ($i->data ?? []);
                    $key = $d['slug'] ?? $d['id'] ?? ($allowItemId ? $i->id : null);
                    if (! $key) {
                        return null;
                    }

                    return [
                        'label' => (string) ($d['name'] ?? $d['title'] ?? $key),
                        'url' => '/'.$base.'/'.$key,
                    ];
                })->filter()->values()->all();
            };
            // Prefer a source whose rows carry their own slug/id; fall back
            // to item-id routing (media-style detail pages) only after.
            $rows = [];
            foreach ([false, true] as $allowItemId) {
                foreach ($candidates as $col) {
                    $rows = $build($col, $allowItemId);
                    if ($rows !== []) {
                        break 2;
                    }
                }
            }
            if ($rows !== []) {
                $groups[Str::headline(basename($base))] = $rows;
            }
        }
        ksort($groups);

        return $groups;
    }

    /** Select a content model by kind + id — loads it for view/edit. */
    public function select(string $kind, string $id): void
    {
        $this->selectedKind = $kind;
        $this->selectedId = $id;
        $this->mode = 'edit';
        $this->loadEdit();
        // Bring the editor into view (drawer/panel may be scrolled or below);
        // on the mobile carousel this slides across to the Edit pane.
        $this->dispatch('olx-editor-focus', target: 'top');
        $this->dispatch('carousel-go', i: 2);
    }

    public function edit(): void
    {
        $this->mode = 'edit';
    }

    public function viewOnly(): void
    {
        $this->mode = 'view';
    }

    /** Header save icon — routes to the right save for whatever is open. */
    public function save(): void
    {
        match ($this->edit['type'] ?? null) {
            'component' => $this->saveComponent(),
            'collection' => $this->saveCollection(),
            'form' => $this->saveForm(),
            'post' => $this->savePost(),
            default => null,
        };
        // Saved → the mobile carousel returns to the preview to show the result.
        if (($this->edit['type'] ?? null) !== null) {
            $this->dispatch('carousel-go', i: 1);
        }
    }

    /** Closing the editor also returns the mobile carousel to the preview. */
    public function backToPreview(): void
    {
        $this->dispatch('carousel-go', i: 1);
    }

    public function deselect(): void
    {
        $this->dispatch('carousel-go', i: 1);
        $this->selectedKind = $this->selectedId = null;
        $this->edit = [];
        $this->mode = 'view';
    }

    private function loadEdit(): void
    {
        $this->edit = [];
        if (! $this->selectedId) {
            return;
        }
        match ($this->selectedKind) {
            'component' => $this->loadComponent($this->selectedId),
            'collection' => $this->loadCollection($this->selectedId),
            'products' => $this->loadProducts(),
            'form' => $this->loadForm($this->selectedId),
            'post' => $this->loadPost($this->selectedId),
            default => null,
        };
    }

    private function loadComponent(string $id): void
    {
        $c = Component::with('nodes')->where('site_id', $this->site->id)->find($id);
        if ($c) {
            $this->edit = ['type' => 'component', 'id' => $c->id, 'name' => $c->name, 'removedNodes' => [],
                'nodes' => $c->nodes->map(fn (Node $n) => ['id' => $n->id, 'label' => $n->label, 'type' => $n->type, 'value' => (string) $n->value])->all()];

            // Data-source grid: a component fed by a collection (Pastors →
            // Leadership) shows that collection's entries in the panel.
            if ($c->collection_id && ($col = Collection::withCount('items')->where('site_id', $this->site->id)->find($c->collection_id))) {
                $this->edit['collection'] = [
                    'id' => $col->id, 'name' => $col->name, 'count' => $col->items_count,
                    'items' => $col->items()->limit(12)->get()->map(function ($i) {
                        $d = (array) ($i->data ?? []);
                        $img = (string) ($d['img'] ?? $d['image'] ?? $d['photo'] ?? '');

                        return [
                            'id' => $i->id,
                            'label' => (string) ($d['name'] ?? $d['title'] ?? array_values(array_filter($d, 'is_string'))[0] ?? '…'),
                            'img' => $img !== '' ? Media::resolveRef($this->site->id, '@media/'.basename($img)) : '',
                        ];
                    })->all(),
                ];
            }
        }
    }

    private function loadCollection(string $id): void
    {
        $col = Collection::with('items')->where('site_id', $this->site->id)->find($id);
        if ($col) {
            $this->edit = ['type' => 'collection', 'id' => $col->id, 'name' => $col->name,
                'schema' => collect($col->fields ?? [])->map(fn ($f) => $f['name'] ?? $f['key'] ?? null)->filter()->unique()->values()->all(),
                'items' => $col->items->map(fn (CollectionItem $i) => ['id' => $i->id, 'data' => $i->data ?? []])->all()];
        }
    }

    /**
     * The site's store Products presented exactly like a collection grid —
     * same entry cards, add/duplicate/remove and save flow in the panel.
     * `products => true` routes persistence to saveProducts().
     */
    private function loadProducts(): void
    {
        $this->edit = [
            'type' => 'collection', 'products' => true, 'id' => 'products', 'name' => 'Products',
            'schema' => ['name', 'category', 'price', 'description', 'image'],
            'items' => $this->site->products()->orderBy('sort')->orderBy('created_at')->get()
                ->map(fn ($p) => ['id' => $p->id, 'data' => [
                    'name' => (string) $p->name,
                    'category' => (string) $p->category,
                    'price' => number_format($p->price_cents / 100, 2, '.', ''),
                    'description' => (string) $p->description,
                    'image' => (string) $p->image,
                ]])->all(),
        ];
    }

    private function loadForm(string $id): void
    {
        $f = Form::where('site_id', $this->site->id)->find($id);
        if ($f) {
            $this->edit = ['type' => 'form', 'id' => $f->id, 'title' => $f->title,
                'endpoint' => (string) ($f->delivery['external_action'] ?? ''),
                'fields' => $f->fields ?? []];
        }
    }

    private function loadPost(string $id): void
    {
        $p = Post::where('site_id', $this->site->id)->find($id);
        if ($p) {
            $this->edit = ['type' => 'post', 'id' => $p->id, 'title' => $p->title,
                'excerpt' => (string) $p->excerpt, 'body' => (string) $p->body];
        }
    }

    // --- inline saves (all through the real models) -------------------------

    private function guard(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'components.manage'), 403);
    }

    // --- in-preview editing (connect.js postMessage bridge) -----------------

    /**
     * A field was edited in place inside the preview iframe. `field` is the
     * page.json key (camelCase of the node label, dotted for nesting, e.g.
     * "cta.label"); for collections `itemId` targets the item row.
     */
    public function inlineFieldEdit(?string $id, ?string $key, string $kind, string $field, string $value, ?string $itemId = null): void
    {
        $this->guard();
        // An itemId always means a collection item — even when the enclosing
        // marker is a component (embedded/linked list).
        if ($itemId) {
            $item = CollectionItem::where('id', $itemId)->where('site_id', $this->site->id)->first();
            if (! $item) {
                return;
            }
            if ($col = Collection::where('site_id', $this->site->id)->find($item->collection_id)) {
                app(ContentVersioner::class)->capture($col, Auth::user()?->name);
            }
            $data = $item->data ?? [];
            $data[$field] = $value;
            $item->update(['data' => $data]);
        } else {
            $component = $id
                ? Component::with('nodes')->where('site_id', $this->site->id)->find($id)
                : $this->resolveByKey('component', (string) $key)?->load('nodes');
            if (! $component) {
                return;
            }
            $node = $this->nodeByFieldKey($component, $field);
            // Marker exists in the template but the component lacks the node
            // (dynamic field() bindings aren't extracted) — create it so the
            // edit persists and the field appears in the sidebar from now on.
            if (! $node && $field !== '' && ! str_contains($field, '.')) {
                app(ContentVersioner::class)->capture($component, Auth::user()?->name);
                $node = $component->nodes()->create([
                    'label' => Str::headline($field),
                    'type' => 'text',
                    'value' => $value,
                    'parent' => '0',
                    'order' => (int) $component->nodes->max('order') + 1,
                ]);
                if ($this->selectedId) {
                    $this->loadEdit();
                }
                $this->refreshPreview('Saved', reloadFrame: false);

                return;
            }
            if (! $node) {
                return;
            }
            app(ContentVersioner::class)->capture($component, Auth::user()?->name);
            if ($node->type === 'image' && trim($value) !== '') {
                $value = app(AssetImporter::class)->importNodeValue($this->site, $value);
            }
            $node->update(['value' => $value]);
        }
        if ($this->selectedId) {
            $this->loadEdit();
        }
        $this->refreshPreview('Saved', reloadFrame: false);
    }

    /** ✕ on a collection item inside the preview (standalone or embedded in a component). */
    public function inlineItemRemove(?string $id, ?string $key, string $itemId): void
    {
        $this->guard();
        $col = $this->resolveCollection($id, $key);
        if (! $col) {
            // Embedded list: the marker is the COMPONENT, so resolve the
            // collection from the item itself (site-scoped).
            $item = CollectionItem::where('id', $itemId)->where('site_id', $this->site->id)->first();
            $col = $item ? Collection::where('site_id', $this->site->id)->find($item->collection_id) : null;
        }
        if (! $col) {
            $this->dispatch('toast', level: 'error', title: 'Could not remove item',
                message: 'The item or its collection could not be found — it may already be deleted.');

            return;
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);
        CollectionItem::where('id', $itemId)->where('site_id', $this->site->id)
            ->where('collection_id', $col->id)->delete();
        // Show the collection on the right and flash its items section.
        $this->select('collection', $col->id);
        $this->dispatch('olx-editor-focus', target: 'items');
        $this->refreshPreview('Item removed');
    }

    /** "+ Add item" on a collection inside the preview (standalone or embedded). */
    public function inlineItemAdd(?string $id, ?string $key, ?string $componentKey = null, ?string $field = null): void
    {
        $this->guard();
        $col = $this->resolveCollection($id, $key);
        if (! $col && $field !== null) {
            // Embedded list: componentKey/field identify the collection-typed
            // node whose value is the linked collection id.
            $component = $componentKey ? $this->resolveByKey('component', $componentKey) : null;
            $component ??= $id ? Component::with('nodes')->where('site_id', $this->site->id)->find($id) : null;
            $node = $component instanceof Component ? $this->nodeByFieldKey($component->load('nodes'), $field) : null;
            $col = $node?->type === 'collection'
                ? Collection::where('site_id', $this->site->id)->find($node->value)
                : null;
        }
        if (! $col) {
            $this->dispatch('toast', level: 'error', title: 'Could not add item',
                message: 'No collection matches this marker — check that its data-olx-key equals the collection slug.');

            return;
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);
        $schema = collect($col->fields ?? [])->map(fn ($f) => $f['name'] ?? $f['key'] ?? null)->filter()->unique()->values()->all();
        CollectionItem::create([
            'collection_id' => $col->id,
            'site_id' => $this->site->id,
            'status' => 'published',
            'data' => array_merge(array_fill_keys($schema, ''), $this->schemaDefaults($col)),
        ]);
        $this->select('collection', $col->id);
        // Jump the editor to the freshly added item so it's ready to fill in.
        $this->dispatch('olx-editor-focus', target: 'last-item');
        $this->refreshPreview('Item added');
    }

    /**
     * Inline edit of a collection row's field in a RENDERER preview — rows
     * carry no item ids there, so the shell addresses the row by position
     * among published items.
     */
    /**
     * Link/button editor from the preview: saves the label AND the href in one
     * go. The href lands in the sibling field whose name looks like a link
     * (href/url/link) — created on the component when it doesn't exist yet.
     */
    public function inlineLinkEdit(?string $key, string $kind, string $labelField, string $label, string $href, ?int $index = null, string $oldLabel = '', string $oldHref = ''): void
    {
        $this->guard();

        if ($index !== null && $kind === 'collection') {
            $col = $this->resolveCollection(null, $key);
            $item = $col?->items()->where('status', 'published')->orderBy('position')->orderBy('id')
                ->get()->values()->get($index);
            if (! $item) {
                return;
            }
            app(ContentVersioner::class)->capture($col, Auth::user()?->name);
            $data = $item->data ?? [];
            if ($label !== '') {
                $data[$labelField] = $label;
            }
            // Find the row's link-ish field; default to "href".
            $hrefField = collect(array_keys($data))
                ->merge(collect($col->fields ?? [])->pluck('key'))
                ->first(fn ($k) => preg_match('/href|url|link/i', (string) $k)) ?: 'href';
            if ($href !== '') {
                $data[$hrefField] = $href;
            }
            $item->update(['data' => $data]);
            $this->refreshPreview('Saved', reloadFrame: false);

            return;
        }

        $component = $this->resolveByKey('component', (string) $key)?->load('nodes');
        if (! $component) {
            return;
        }
        app(ContentVersioner::class)->capture($component, Auth::user()?->name);

        // 1. Marked label → node by field key. 2. UNMARKED links (nav menus:
        //    "Left Nav 2 Label"/"… Href" node rows) → match nodes by their
        //    CURRENT values, pairing label+href via the shared "{prefix} {n}" stem.
        $labelNode = $labelField !== '' ? $this->nodeByFieldKey($component, $labelField) : null;
        if (! $labelNode && $oldLabel !== '') {
            $labelNode = $component->nodes->first(fn ($n) => trim((string) $n->value) === $oldLabel
                && ! preg_match('/href|url|link/i', (string) $n->label));
        }
        if ($label !== '' && $labelNode) {
            $labelNode->update(['value' => $label]);
        }

        if ($href !== '') {
            $hrefNode = null;
            if ($labelNode && preg_match('/^(.*?)\s*(label|title|text|name)$/i', (string) $labelNode->label, $m)) {
                $stem = trim($m[1]); // e.g. "Right Nav 2"
                if ($stem !== '') {
                    $hrefNode = $component->nodes->first(fn ($n) => preg_match('/href|url|link/i', (string) $n->label)
                        && str_starts_with((string) $n->label, $stem));
                }
            }
            $hrefNode ??= $oldHref !== ''
                ? $component->nodes->first(fn ($n) => trim((string) $n->value) === $oldHref
                    && preg_match('/href|url|link/i', (string) $n->label))
                : null;
            $hrefNode ??= $component->nodes->first(fn ($n) => preg_match('/^(link|href|url)$/i', (string) $n->label));

            if ($hrefNode) {
                $hrefNode->update(['value' => $href]);
            } elseif ($labelNode) {
                $component->nodes()->create([
                    'label' => 'Link', 'type' => 'text', 'value' => $href,
                    'parent' => '0', 'order' => (int) $component->nodes->max('order') + 1,
                ]);
            }
        }
        if ($this->selectedId === $component->id) {
            $this->loadEdit();
        }
        $this->refreshPreview('Saved', reloadFrame: false);
    }

    public function inlineFieldEditByIndex(?string $key, string $field, string $value, int $index): void
    {
        $this->guard();
        $col = $this->resolveCollection(null, $key);
        if (! $col || $field === '') {
            return;
        }
        $item = $col->items()->where('status', 'published')->orderBy('position')->orderBy('id')
            ->get()->values()->get($index);
        if (! $item) {
            return;
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);
        $data = $item->data ?? [];
        $data[$field] = $value;
        $item->update(['data' => $data]);
        if ($this->selectedId === $col->id) {
            $this->loadEdit();
        }
        $this->refreshPreview('Saved', reloadFrame: false);
    }

    /**
     * ✕ on a collection row in a RENDERER preview: rows there carry no item
     * ids, so the shell sends the row's position among published items.
     */
    public function inlineItemRemoveByIndex(?string $key, int $index): void
    {
        $this->guard();
        $col = $this->resolveCollection(null, $key);
        if (! $col) {
            return;
        }
        $item = $col->items()->where('status', 'published')->orderBy('position')->orderBy('id')
            ->get()->values()->get($index);
        if (! $item) {
            return;
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);
        $item->delete();
        $this->select('collection', $col->id);
        $this->refreshPreview('Item removed');
    }

    /**
     * Marker-first registration (edit-mode preview only — never the public
     * token): unmatched client markers create the corresponding CMS model,
     * seeded from the markup's fallback content; matched components gain any
     * fields their record lacks as new nodes. Existing keys are never mutated
     * beyond adding missing nodes.
     */
    public function registerMarkers(array $markers): void
    {
        $this->guard();
        $page = $this->site->pages()->where('url', $this->previewPath)->first();
        $created = [];
        $updated = [];

        foreach (array_slice($markers, 0, 20) as $marker) {
            if (! is_array($marker)) {
                continue;
            }
            $kind = (string) ($marker['kind'] ?? 'component');
            $key = trim((string) ($marker['key'] ?? ''));
            if ($key === '' || strlen($key) > 60 || ! preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
                continue;
            }
            $existing = $this->resolveByKey($kind, $key);

            if ($kind === 'component') {
                if ($existing instanceof Component) {
                    if ($this->addMissingNodes($existing, $marker['fields'] ?? []) > 0) {
                        $updated[] = $existing->name;
                    }
                } else {
                    $component = Component::create(['site_id' => $this->site->id,
                        'name' => Str::headline($key), 'author' => 'site-connect', 'source' => 'imported']);
                    $this->addMissingNodes($component, $marker['fields'] ?? []);
                    $page?->components()->syncWithoutDetaching([$component->id => ['order' => $page->components()->count()]]);
                    $created[] = $component->name;
                }
            } elseif ($kind === 'collection' && ! $existing) {
                $schema = collect($marker['schema'] ?? [])
                    ->filter(fn ($n) => is_string($n) && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $n))
                    ->take(20)
                    ->map(fn ($n) => ['key' => $n, 'name' => $n, 'label' => Str::headline($n), 'type' => 'text'])
                    ->values()->all();
                if ($schema === []) {
                    continue;
                }
                // Slug must equal strtolower(key): resolveByKey matches LOWER(slug).
                $collection = Collection::create(['site_id' => $this->site->id,
                    'name' => Str::headline($key), 'slug' => strtolower($key),
                    'type' => 'grid', 'is_public' => true, 'fields' => $schema]);
                if (is_array($marker['item'] ?? null) && $marker['item'] !== []) {
                    CollectionItem::create(['collection_id' => $collection->id, 'site_id' => $this->site->id,
                        'status' => 'published',
                        'data' => collect($marker['item'])->only(array_column($schema, 'key'))
                            ->map(fn ($v) => (string) $v)->all()]);
                }
                $page?->collections()->syncWithoutDetaching([$collection->id => ['order' => $page->collections()->count()]]);
                $created[] = $collection->name;
            } elseif ($kind === 'form' && ! $existing) {
                $fields = collect($marker['fields'] ?? [])->filter(fn ($f) => is_array($f))->take(20)
                    ->map(function (array $f) {
                        $fieldKey = Str::slug((string) ($f['key'] ?? 'field'), '_');
                        $type = in_array($f['type'] ?? 'text', ['text', 'email', 'tel', 'number', 'url', 'date', 'textarea', 'select', 'radio', 'checkbox'], true)
                            ? $f['type'] : 'text';

                        return ['key' => $fieldKey, 'name' => $fieldKey,
                            'label' => Str::limit((string) ($f['label'] ?? Str::headline($fieldKey)), 80, ''),
                            'type' => $type, 'required' => (bool) ($f['required'] ?? false)];
                    })->values()->all();
                if ($fields === []) {
                    continue;
                }
                Form::create(['site_id' => $this->site->id, 'name' => strtolower($key),
                    'title' => Str::headline($key), 'fields' => $fields, 'is_active' => true]);
                $created[] = Str::headline($key);
            } elseif ($kind === 'post' && ! $existing) {
                $values = collect($marker['fields'] ?? [])->filter(fn ($f) => is_array($f))->keyBy('field');
                Post::create(['site_id' => $this->site->id, 'user_id' => $this->site->user_id,
                    'title' => (string) ($values['title']['value'] ?? Str::headline($key)),
                    'slug' => Post::uniqueSlug($this->site->id, strtolower($key)),
                    'excerpt' => $values['excerpt']['value'] ?? null,
                    'body' => (string) ($values['body']['value'] ?? ''),
                    'status' => 'published', 'published_at' => now()]);
                $created[] = Str::headline($key);
            }
        }

        if ($created !== [] || $updated !== []) {
            $this->refreshPreview($created !== []
                ? 'Created from client markup: '.implode(', ', array_unique($created))
                : 'New fields added: '.implode(', ', array_unique($updated)));
        }
    }

    /** Create nodes for field specs the component doesn't have yet (dotted = one nesting level). */
    private function addMissingNodes(Component $component, array $fields): int
    {
        $added = 0;
        $seen = [];
        foreach (array_slice($fields, 0, 20) as $spec) {
            $specKey = strtolower((string) (is_array($spec) ? ($spec['field'] ?? '') : ''));
            if ($specKey !== '' && isset($seen[$specKey])) {
                continue; // duplicate field name within one payload
            }
            $seen[$specKey] = true;
            if (! is_array($spec)) {
                continue;
            }
            $path = trim((string) ($spec['field'] ?? ''));
            if ($path === '' || strlen($path) > 80 || ! preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)?$/', $path)) {
                continue;
            }
            $component->load('nodes');
            if ($this->nodeByFieldKey($component, $path)) {
                continue;
            }
            $segments = explode('.', $path);
            $parent = '0';
            if (count($segments) === 2) {
                $parentNode = $this->nodeByFieldKey($component, $segments[0])
                    ?? $component->nodes()->create(['label' => Str::headline($segments[0]), 'type' => 'text',
                        'value' => '', 'parent' => '0', 'order' => (int) $component->nodes()->max('order') + 1]);
                $parent = $parentNode->id;
            }
            $type = (string) ($spec['type'] ?? 'text');
            if (! in_array($type, ['text', 'url', 'image', 'number', 'boolean', 'color'], true)) {
                $type = 'text';
            }
            $component->nodes()->create(['label' => Str::headline(end($segments)), 'type' => $type,
                'value' => Str::limit((string) ($spec['value'] ?? ''), 5000, ''),
                'parent' => $parent, 'order' => (int) $component->nodes()->max('order') + 1]);
            $added++;
        }

        return $added;
    }

    private function resolveCollection(?string $id, ?string $key): ?Collection
    {
        return $id
            ? Collection::where('site_id', $this->site->id)->find($id)
            : $this->resolveByKey('collection', (string) $key);
    }

    /**
     * Find the node behind a page.json field key ("heading", "cta.label") —
     * the inverse of PageJsonGenerator's camel(slug(label)) keying, walking
     * root nodes then one child level for dotted paths.
     */
    private function nodeByFieldKey(Component $component, string $field): ?Node
    {
        $camel = fn (Node $n) => Str::camel(Str::slug($n->label));
        $isRoot = fn (Node $n) => $n->parent === null || $n->parent === '' || (string) $n->parent === '0';
        $nodes = $component->nodes;

        $current = null;
        foreach (explode('.', $field) as $seg) {
            $pool = $current
                ? $nodes->filter(fn (Node $n) => $n->parent === $current->id)
                : $nodes->filter($isRoot);
            $current = $pool->first(fn (Node $n) => $camel($n) === $seg);
            if (! $current) {
                return null;
            }
        }

        return $current;
    }

    /** Jump from a collection-typed component node to its linked collection's items. */
    public function openLinkedCollection(string $collectionId): void
    {
        if (Collection::where('site_id', $this->site->id)->find($collectionId)) {
            $this->select('collection', $collectionId);
        }
    }

    /** Buffer for a new collection schema field: label + type + default value. */
    public array $newField = ['label' => '', 'type' => 'text', 'default' => ''];

    /** Item field types the collection editor offers. */
    public const ITEM_FIELD_TYPES = ['text', 'textarea', 'image', 'url', 'number', 'date'];

    /**
     * Extend the selected collection's item schema with a new field — works
     * for standalone collections AND ones linked inside a component. EVERY
     * existing item gains the field, filled with the default value; new items
     * start with it too.
     */
    public function addCollectionField(): void
    {
        $this->guard();
        $key = Str::slug(trim((string) ($this->newField['label'] ?? '')), '_');
        if ($key === '' || $this->selectedKind !== 'collection') {
            return;
        }
        $col = Collection::where('site_id', $this->site->id)->find($this->selectedId);
        if (! $col) {
            return;
        }
        $fields = $col->fields ?? [];
        if (collect($fields)->contains(fn ($f) => ($f['key'] ?? $f['name'] ?? '') === $key)) {
            $this->newField = ['label' => '', 'type' => 'text', 'default' => ''];

            return; // already in the schema
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);

        $type = in_array($this->newField['type'] ?? 'text', self::ITEM_FIELD_TYPES, true) ? $this->newField['type'] : 'text';
        $default = (string) ($this->newField['default'] ?? '');
        if ($type === 'image' && $default !== '') {
            $default = app(AssetImporter::class)->importNodeValue($this->site, $default);
        }
        $fields[] = ['key' => $key, 'name' => $key,
            'label' => trim((string) $this->newField['label']), 'type' => $type, 'default' => $default];
        $col->update(['fields' => $fields]);

        // Backfill: every existing item gains the field with the default value.
        foreach ($col->items()->get() as $item) {
            $data = $item->data ?? [];
            if (! array_key_exists($key, $data)) {
                $data[$key] = $default;
                $item->update(['data' => $data]);
            }
        }

        $this->newField = ['label' => '', 'type' => 'text', 'default' => ''];
        $this->loadEdit();
        $this->refreshPreview('Field "'.$key.'" added to all '.$col->name.' items');
    }

    /** Default values per schema key (used when creating new items). */
    private function schemaDefaults(Collection $col): array
    {
        return collect($col->fields ?? [])
            ->mapWithKeys(fn ($f) => [($f['name'] ?? $f['key'] ?? '') => (string) ($f['default'] ?? '')])
            ->except([''])->all();
    }

    /** Buffer a new field; it becomes a real Node on save. */
    public function addNode(): void
    {
        $this->edit['nodes'][] = ['id' => null, 'label' => 'New field', 'type' => 'text', 'value' => ''];
    }

    /** Drop a field from the buffer; existing nodes are deleted on save. */
    public function removeNode(int $i): void
    {
        $node = $this->edit['nodes'][$i] ?? null;
        if ($node && ! empty($node['id'])) {
            $this->edit['removedNodes'][] = $node['id'];
        }
        unset($this->edit['nodes'][$i]);
        $this->edit['nodes'] = array_values($this->edit['nodes']);
    }

    public function saveComponent(): void
    {
        $this->guard();
        $component = Component::where('site_id', $this->site->id)->find($this->edit['id']);
        if (! $component) {
            return;
        }
        app(ContentVersioner::class)->capture($component, Auth::user()?->name);
        foreach ($this->edit['removedNodes'] ?? [] as $removedId) {
            // Delete the node and any children nested under it.
            Node::where('component_id', $component->id)
                ->where(fn ($q) => $q->where('id', $removedId)->orWhere('parent', $removedId))
                ->delete();
        }
        $order = (int) $component->nodes()->max('order');
        $importer = app(AssetImporter::class);
        foreach ($this->edit['nodes'] ?? [] as $node) {
            $type = in_array($node['type'] ?? 'text', Node::TYPES, true) ? $node['type'] : 'text';
            $value = (string) $node['value'];
            if ($type === 'image' && $value !== '') {
                // Pasted asset URLs land in the media library (@media ref).
                $value = $importer->importNodeValue($this->site, $value);
            }
            if ($type === 'collection' && ($value === '' || Collection::where('site_id', $this->site->id)->find($value) === null)) {
                // A list INSIDE the component: back it with a real (linked)
                // collection so items get the full collection editor + history.
                $value = Collection::create([
                    'site_id' => $this->site->id,
                    'name' => $component->name.' '.Str::headline(trim($node['label']) ?: 'Items'),
                    'slug' => Str::slug($component->name.'-'.($node['label'] ?? 'items')).'-'.Str::lower(Str::random(4)),
                    'type' => 'grid', 'is_public' => true, 'fields' => [],
                ])->id;
            }
            if (empty($node['id'])) {
                $component->nodes()->create([
                    'label' => trim($node['label']) ?: 'Field',
                    'type' => $type,
                    'value' => $value,
                    'parent' => '0',
                    'order' => ++$order,
                ]);
            } else {
                Node::where('id', $node['id'])
                    ->where('component_id', $component->id)
                    ->update(['value' => $value, 'label' => $node['label']]);
            }
        }
        $this->loadEdit();
        $this->refreshPreview('Component saved');
    }

    public function addItem(): void
    {
        $this->guard();
        $schema = $this->edit['schema'] ?? [];
        $col = Collection::where('site_id', $this->site->id)->find($this->edit['id'] ?? null);
        $defaults = $col ? $this->schemaDefaults($col) : [];
        $data = array_merge(array_fill_keys($schema, ''), $defaults);
        // Persist at once (like delete) so the preview shows the new row
        // immediately — typing then updates it field by field.
        $this->edit['items'][] = ['id' => $this->persistNewItem($data, count($this->edit['items'] ?? [])), 'data' => $data];
        $this->dispatch('olx-editor-focus', target: 'last-item');
        $this->refreshPreview('Item added');
    }

    /** Copy an item — the fastest way to add another row of the same shape. */
    public function duplicateItem(int $i): void
    {
        $this->guard();
        $item = $this->edit['items'][$i] ?? null;
        if ($item === null) {
            return;
        }
        array_splice($this->edit['items'], $i + 1, 0, [['id' => $this->persistNewItem((array) $item['data'], $i + 1), 'data' => $item['data']]]);
        $this->dispatch('olx-editor-focus', target: 'item', index: $i + 1);
        $this->refreshPreview('Item duplicated');
    }

    /** Create the backing row for a fresh panel entry; returns its id. */
    private function persistNewItem(array $data, int $pos): ?string
    {
        if ($this->edit['products'] ?? false) {
            $name = trim((string) ($data['name'] ?? '')) ?: 'Untitled product';
            $base = Str::slug($name) ?: 'product';
            $slug = $base;
            for ($i = 2; $this->site->products()->where('slug', $slug)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }

            return $this->site->products()->create([
                'name' => $name, 'slug' => $slug,
                'category' => (string) ($data['category'] ?? ''),
                'price_cents' => (int) round(((float) ($data['price'] ?? 0)) * 100),
                'description' => (string) ($data['description'] ?? ''),
                'image' => (string) ($data['image'] ?? ''),
                'currency' => $this->site->products()->value('currency') ?: 'gbp',
                'is_active' => true, 'sort' => $pos,
            ])->id;
        }
        $col = Collection::where('site_id', $this->site->id)->find($this->edit['id'] ?? null);
        if (! $col) {
            return null;
        }

        return CollectionItem::create([
            'collection_id' => $col->id, 'site_id' => $this->site->id,
            'status' => 'published', 'data' => $data, 'position' => $pos,
        ])->id;
    }

    /**
     * Live item edits: a blurred entry-card field persists at once and the
     * preview follows — the same immediacy as add/delete. (Save still works
     * as the explicit "persist everything" action.)
     */
    public function updated(string $prop): void
    {
        if (($this->edit['type'] ?? null) !== 'collection' || ! preg_match('/^edit\.items\.(\d+)\./', $prop, $m)) {
            return;
        }
        $this->guard();
        $item = $this->edit['items'][(int) $m[1]] ?? null;
        if (! $item || empty($item['id'])) {
            return;
        }
        $d = (array) ($item['data'] ?? []);
        if ($this->edit['products'] ?? false) {
            $this->site->products()->whereKey($item['id'])->update([
                'name' => trim((string) ($d['name'] ?? '')) ?: 'Untitled product',
                'category' => (string) ($d['category'] ?? ''),
                'price_cents' => (int) round(((float) ($d['price'] ?? 0)) * 100),
                'description' => (string) ($d['description'] ?? ''),
                'image' => (string) ($d['image'] ?? ''),
            ]);
            $this->dispatch('olx-reload-frame');

            return;
        }
        CollectionItem::where('id', $item['id'])->where('site_id', $this->site->id)->update(['data' => $d]);
        $this->refreshPreview('Item updated');
    }

    /** From a component's data-source card: jump to the collection AND start a new entry. */
    public function addToLinkedCollection(string $collectionId): void
    {
        $this->select('collection', $collectionId);
        $this->addItem();
    }

    /** Reorder an item one step up (-1) or down (+1); persists immediately. */
    public function moveItem(int $i, int $dir): void
    {
        $this->guard();
        $j = $i + ($dir < 0 ? -1 : 1);
        $items = $this->edit['items'] ?? [];
        if (! isset($items[$i]) || ! isset($items[$j])) {
            return;
        }
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        $this->edit['items'] = array_values($items);
        // Persist order for rows that exist in the DB (unsaved rows keep
        // their place and get a position when the collection is saved).
        foreach ($this->edit['items'] as $pos => $item) {
            if (! empty($item['id'])) {
                CollectionItem::where('id', $item['id'])->where('site_id', $this->site->id)->update(['position' => $pos]);
            }
        }
        $this->refreshPreview('Order updated');
    }

    public function removeItem(int $i): void
    {
        $this->guard();
        $item = $this->edit['items'][$i] ?? null;
        if ($item && ! empty($item['id'])) {
            if ($this->edit['products'] ?? false) {
                $this->site->products()->whereKey($item['id'])->delete();
            } else {
                CollectionItem::where('id', $item['id'])->where('site_id', $this->site->id)->delete();
            }
        }
        unset($this->edit['items'][$i]);
        $this->edit['items'] = array_values($this->edit['items']);
        // The row is gone from the database — show that in the preview
        // immediately, not only after the next Save.
        if ($item && ! empty($item['id'])) {
            if ($this->edit['products'] ?? false) {
                // The store grid fetches products at mount — hard reload.
                $this->dispatch('olx-reload-frame');
                $this->dispatch('toast', level: 'success', title: 'Product removed', message: 'The store preview is reloading.');
            } else {
                $this->refreshPreview('Item removed');
            }
        }
    }

    public function saveCollection(): void
    {
        $this->guard();
        if ($this->edit['products'] ?? false) {
            $this->saveProducts();

            return;
        }
        $col = Collection::where('site_id', $this->site->id)->find($this->edit['id']);
        if (! $col) {
            return;
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);
        foreach ($this->edit['items'] as $pos => $item) {
            if (! empty($item['id'])) {
                CollectionItem::where('id', $item['id'])->where('site_id', $this->site->id)->update(['data' => $item['data'], 'position' => $pos]);
            } else {
                CollectionItem::create(['collection_id' => $col->id, 'site_id' => $this->site->id, 'status' => 'published', 'data' => $item['data'], 'position' => $pos]);
            }
        }
        $this->loadEdit();
        $this->refreshPreview('Collection saved');
    }

    /** Persist the products grid: update by id, create the id-less rows. */
    private function saveProducts(): void
    {
        foreach ($this->edit['items'] as $pos => $item) {
            $d = (array) ($item['data'] ?? []);
            $attrs = [
                'name' => trim((string) ($d['name'] ?? '')) ?: 'Untitled product',
                'category' => (string) ($d['category'] ?? ''),
                'price_cents' => (int) round(((float) ($d['price'] ?? 0)) * 100),
                'description' => (string) ($d['description'] ?? ''),
                'image' => (string) ($d['image'] ?? ''),
                'sort' => $pos,
            ];
            if (! empty($item['id'])) {
                $this->site->products()->whereKey($item['id'])->update($attrs);
            } else {
                $base = Str::slug($attrs['name']) ?: 'product';
                $slug = $base;
                for ($i = 2; $this->site->products()->where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $this->site->products()->create($attrs + ['slug' => $slug, 'currency' => $this->site->products()->value('currency') ?: 'gbp', 'is_active' => true]);
            }
        }
        $this->loadEdit();
        // The store grid fetches products at mount — a hard reload shows the
        // change (the in-place content refresh doesn't cover the products API).
        $this->dispatch('olx-reload-frame');
        $this->dispatch('toast', level: 'success', title: 'Products saved', message: 'The store preview is reloading with your changes.');
        $this->dispatch('carousel-go', i: 1);
    }

    public function addFormField(): void
    {
        // Blank key → derived from the label on save.
        $this->edit['fields'][] = ['key' => '', 'name' => '', 'type' => 'text', 'label' => 'New field', 'required' => false];
    }

    public function removeFormField(int $i): void
    {
        unset($this->edit['fields'][$i]);
        $this->edit['fields'] = array_values($this->edit['fields'] ?? []);
    }

    public function saveForm(): void
    {
        $this->guard();
        $f = Form::where('site_id', $this->site->id)->find($this->edit['id']);
        if (! $f) {
            return;
        }
        app(ContentVersioner::class)->capture($f, Auth::user()?->name);
        // Normalise fields: the CMS-canonical `key` (used by the submission
        // validator + response store) is derived from the label when blank.
        $fields = array_values(array_map(function ($field) {
            $key = trim($field['key'] ?? '') ?: Str::slug($field['label'] ?? 'field', '_');
            $field['key'] = $key;
            $field['name'] = $key;
            $field['required'] = (bool) ($field['required'] ?? false);

            return $field;
        }, $this->edit['fields'] ?? []));

        $delivery = $f->delivery ?? [];
        // Empty endpoint → use the CMS's own submission route (external_action unset),
        // so submissions are captured as FormResponses in the CRM.
        $delivery['external_action'] = trim($this->edit['endpoint']) ?: null;
        $f->update(['title' => $this->edit['title'], 'fields' => $fields, 'delivery' => $delivery]);
        $this->edit['fields'] = $fields;
        $this->refreshPreview('Form saved');
    }

    public function savePost(): void
    {
        $this->guard();
        $post = Post::where('site_id', $this->site->id)->find($this->edit['id']);
        if (! $post) {
            return;
        }
        app(ContentVersioner::class)->capture($post, Auth::user()?->name);
        $post->update(['title' => $this->edit['title'], 'excerpt' => $this->edit['excerpt'], 'body' => $this->edit['body']]);
        $this->refreshPreview('Post saved');
    }

    /**
     * Republish page.json after every save and toast the result. Only the page
     * being PREVIEWED publishes synchronously (that's what the poll reads);
     * the rest republish after the response so the editor stays snappy.
     * No iframe reload needed: connect.js re-applies content in place.
     */
    private function refreshPreview(string $what = 'Saved', bool $reloadFrame = true): void
    {
        // Renderer mode: the shell reads /api/sites/{name}/content once at
        // boot — reload the iframe so the fresh edit shows. Live-app-derived
        // templates ALSO read page.json for their own field() bindings, so
        // republish the current page too (cheap, synchronous).
        // Inline in-page edits skip the reload: the typed text is already
        // on screen, and yanking the frame away mid-editing is jarring.
        if ($this->rendererMode) {
            $current = $this->site->livePages()->get()->first(fn ($p) => $p->url === $this->previewPath);
            if ($current) {
                app(PageJsonPublisher::class)->publish($current);
            }
            // COLLECTION rows are snapshotted by useSiteContent() at component
            // setup — the in-place content refetch can't re-render them, so
            // item adds/removes/edits need a real frame reload. Block FIELD
            // edits stay in-place (reactive through useOluxContent).
            if ($reloadFrame && ($this->edit['type'] ?? null) === 'collection') {
                $this->dispatch('olx-reload-frame');
                $this->dispatch('toast', level: 'success', title: $what, message: 'The preview is reloading with your changes.');

                return;
            }
            // In-place: the shell re-fetches content and Vue re-renders —
            // no iframe reload, the preview never flashes.
            $this->dispatch('olx-refresh-frame');
            $this->dispatch('toast', level: 'success', title: $what, message: 'Saved — the preview updates in place.');

            return;
        }

        $pages = $this->site->livePages()->get();
        $current = $pages->first(fn ($p) => $p->url === $this->previewPath) ?? $pages->first();
        if ($current) {
            app(PageJsonPublisher::class)->publish($current);
        }
        $rest = $pages->reject(fn ($p) => $current && $p->id === $current->id)->pluck('id')->all();
        if ($rest !== []) {
            dispatch(function () use ($rest) {
                foreach (Page::whereIn('id', $rest)->get() as $page) {
                    app(PageJsonPublisher::class)->publish($page);
                }
            })->afterResponse();
        }
        $this->dispatch('toast', level: 'success', title: $what,
            message: 'The preview updates in place in a moment.');
    }

    /** Publish page.json for every live page (so client sites pick up edits). */
    public function publish(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'publish.manage'), 403);
        $pages = $this->site->livePages()->get();
        if ($pages->isEmpty()) {
            $this->flash = 'No pages to publish yet.';

            return;
        }
        foreach ($pages as $page) {
            app(PageJsonPublisher::class)->publish($page);
        }
        $this->flash = 'Published page.json for '.$pages->count().' page(s).';
    }

    /** Origin of the embedded client site — the ONLY origin the message bridge trusts. */
    public function getClientOriginProperty(): string
    {
        // Renderer mode embeds the CMS's own nuxt-preview shell (same origin),
        // so its olux-edit plugin messages are trusted from our own origin.
        if ($this->clientUrl === '') {
            $parts = parse_url(url('/'));
        } else {
            $parts = parse_url($this->clientUrl);
        }
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** Restore an earlier snapshot of the selected content (history panel). */
    public function revertTo(string $versionId): void
    {
        $this->guard();
        $version = ContentVersion::where('site_id', $this->site->id)->find($versionId);
        abort_unless($version !== null, 404);
        app(ContentVersioner::class)->restore($version);
        if ($this->selectedId === $version->subject_id) {
            $this->loadEdit();
        }
        $this->refreshPreview('Reverted to earlier version');
    }

    public function render()
    {
        $versions = $this->selectedId
            ? ContentVersion::where('site_id', $this->site->id)
                ->where('subject_type', $this->selectedKind)->where('subject_id', $this->selectedId)
                ->orderByDesc('created_at')->orderByDesc('id')->take(3)->get()
            : collect();

        return view('livewire.connect-review-page', [
            'embedUrl' => $this->embedUrl,
            'livePreviewUrl' => $this->livePreviewUrl,
            'clientOrigin' => $this->clientOrigin,
            'pages' => $this->site->livePages()->orderBy('name')->get(['name', 'url']),
            'versions' => $versions,
        ]);
    }
}
