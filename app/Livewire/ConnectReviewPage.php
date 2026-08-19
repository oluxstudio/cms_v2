<?php

namespace App\Livewire;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\ContentVersion;
use App\Models\Form;
use App\Models\Node;
use App\Models\Post;
use App\Models\Site;
use App\Services\ContentVersioner;
use App\Services\SiteConnect\AssetImporter;
use App\Services\SiteConnect\PageJsonPublisher;
use Illuminate\Support\Facades\Auth;
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
    public Site $site;

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

    /** The iframe src: the selected page of the client site, in edit mode. */
    public function getEmbedUrlProperty(): ?string
    {
        if ($this->clientUrl === '') {
            return null;
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
    public function onEditSelect(?string $id = null, ?string $key = null, string $kind = 'component'): void
    {
        if ($id) {
            $this->select($kind, $id);

            return;
        }
        if (! $key) {
            return;
        }
        if ($model = $this->resolveByKey($kind, $key)) {
            $this->select($kind, $model->id);
        }
    }

    /** Resolve a hand-authored client marker (data-olx-key) to a real model. */
    private function resolveByKey(string $kind, string $key): mixed
    {
        $lkey = strtolower($key);

        return match ($kind) {
            'collection' => Collection::where('site_id', $this->site->id)->whereRaw('LOWER(slug) = ?', [$lkey])->first(),
            'form' => Form::where('site_id', $this->site->id)->whereRaw('LOWER(name) = ?', [$lkey])->first(),
            'post' => Post::where('site_id', $this->site->id)->whereRaw('LOWER(slug) = ?', [$lkey])->first(),
            // Component key = camelCase(name); match case-insensitively so
            // "Book CTA" (→bookCTA) resolves a data-olx-key="bookCta".
            default => $this->site->contentComponents()->get()->first(fn (Component $c) => strtolower(Str::camel($c->name)) === $lkey),
        };
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

            return;
        }
        // Collection item field: item data isn't @media-resolved by the
        // generator, so store the absolute asset URL instead of the ref.
        $item = $context['itemIndex'] ?? null;
        $key = $context['itemKey'] ?? null;
        if ($item !== null && $key !== null && isset($this->edit['items'][$item])) {
            $this->edit['items'][$item]['data'][$key] = str_starts_with($url, '/') ? url($url) : $url;
        }
    }

    /** Select a content model by kind + id — loads it for view/edit. */
    public function select(string $kind, string $id): void
    {
        $this->selectedKind = $kind;
        $this->selectedId = $id;
        $this->mode = 'edit';
        $this->loadEdit();
    }

    public function edit(): void
    {
        $this->mode = 'edit';
    }

    public function viewOnly(): void
    {
        $this->mode = 'view';
    }

    public function deselect(): void
    {
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
        }
    }

    private function loadCollection(string $id): void
    {
        $col = Collection::with('items')->where('site_id', $this->site->id)->find($id);
        if ($col) {
            $this->edit = ['type' => 'collection', 'id' => $col->id, 'name' => $col->name,
                'schema' => collect($col->fields ?? [])->pluck('name')->filter()->values()->all(),
                'items' => $col->items->map(fn (CollectionItem $i) => ['id' => $i->id, 'data' => $i->data ?? []])->all()];
        }
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
            $node = $component ? $this->nodeByFieldKey($component, $field) : null;
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
        $this->refreshPreview('Saved');
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
            return;
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);
        CollectionItem::where('id', $itemId)->where('site_id', $this->site->id)
            ->where('collection_id', $col->id)->delete();
        if ($this->selectedId === $col->id) {
            $this->loadEdit();
        }
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
            return;
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);
        $schema = collect($col->fields ?? [])->pluck('name')->filter()->values()->all();
        CollectionItem::create([
            'collection_id' => $col->id,
            'site_id' => $this->site->id,
            'status' => 'published',
            'data' => array_merge(array_fill_keys($schema, ''), $this->schemaDefaults($col)),
        ]);
        $this->select('collection', $col->id);
        $this->refreshPreview('Item added');
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
        foreach (array_slice($fields, 0, 20) as $spec) {
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
            $type = in_array($spec['type'] ?? 'text', ['text', 'url', 'image', 'number', 'boolean', 'color'], true)
                ? $spec['type'] : 'text';
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
        $schema = $this->edit['schema'] ?? [];
        $col = Collection::where('site_id', $this->site->id)->find($this->edit['id'] ?? null);
        $defaults = $col ? $this->schemaDefaults($col) : [];
        $this->edit['items'][] = ['id' => null, 'data' => array_merge(array_fill_keys($schema, ''), $defaults)];
    }

    public function removeItem(int $i): void
    {
        $this->guard();
        $item = $this->edit['items'][$i] ?? null;
        if ($item && ! empty($item['id'])) {
            CollectionItem::where('id', $item['id'])->where('site_id', $this->site->id)->delete();
        }
        unset($this->edit['items'][$i]);
        $this->edit['items'] = array_values($this->edit['items']);
    }

    public function saveCollection(): void
    {
        $this->guard();
        $col = Collection::where('site_id', $this->site->id)->find($this->edit['id']);
        if (! $col) {
            return;
        }
        app(ContentVersioner::class)->capture($col, Auth::user()?->name);
        foreach ($this->edit['items'] as $item) {
            if (! empty($item['id'])) {
                CollectionItem::where('id', $item['id'])->where('site_id', $this->site->id)->update(['data' => $item['data']]);
            } else {
                CollectionItem::create(['collection_id' => $col->id, 'site_id' => $this->site->id, 'status' => 'published', 'data' => $item['data']]);
            }
        }
        $this->loadEdit();
        $this->refreshPreview('Collection saved');
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
     * Republish page.json after every save and toast the result. No iframe
     * reload needed: connect.js polls page.json in edit mode and re-applies
     * the fresh content in place within ~1.5s.
     */
    private function refreshPreview(string $what = 'Saved'): void
    {
        foreach ($this->site->livePages()->get() as $page) {
            app(PageJsonPublisher::class)->publish($page);
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
        $parts = parse_url($this->clientUrl);
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
            'clientOrigin' => $this->clientOrigin,
            'pages' => $this->site->livePages()->orderBy('name')->get(['name', 'url']),
            'versions' => $versions,
        ]);
    }
}
