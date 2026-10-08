<?php

namespace App\Livewire;

use App\Models\Collection as CollectionModel;
use App\Models\CollectionItem;
use App\Models\Component as ComponentModel;
use App\Models\Media;
use App\Models\Node;
use App\Models\Site;
use App\Support\CollectionAutoFields;
use App\Support\CollectionQuery;
use App\Support\MediaValue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A collection's own page (/{site}/collections/{id}) — the "Source ↗" target
 * from a block's grid on the Edit page. Lists every entry in manual order with
 * add / reorder / publish / delete, plus which blocks show it. Clicking an
 * entry opens a side panel: VIEW mode (every field, created/updated times)
 * with an Edit switch. Deleting is soft (deleted_at) — restorable below.
 *
 * Entry editing (form, media picker, JSON/nested fields) is inherited from
 * CollectionsPage, so both screens share one tested implementation.
 */
class CollectionDetailPage extends CollectionsPage
{
    /** Side panel: the entry being looked at (null = closed) and its mode. */
    public ?string $panelId = null;

    public string $panelMode = 'view';   // view | edit

    public bool $showTrash = false;

    /** Field types an entry field can have (key => label shown in the picker). */
    public const FIELD_TYPES = [
        'text' => 'Input (one line)', 'textarea2' => 'Textarea (2 rows)', 'textarea' => 'Textarea (4 rows)',
        'textarea6' => 'Textarea (6 rows)', 'textarea10' => 'Textarea (10 rows)', 'textarea15' => 'Textarea (15 rows)',
        'number' => 'Number (decimals ok)',
        'email' => 'Email', 'tel' => 'Phone', 'url' => 'Link', 'date' => 'Date', 'datetime' => 'Date & time',
        'select' => 'Dropdown', 'radio' => 'Radio buttons', 'checkbox' => 'Checkbox (yes/no)', 'toggle' => 'Toggle (on/off switch)',
        'slider' => 'Slider', 'image' => 'Image', 'images' => 'Images (gallery)', 'media' => 'Audio / video / file',
        'list' => 'List', 'tags' => 'Tags', 'slug' => 'Slug (web address, made from other fields)',
    ];

    /** Structured types the picker can't create but must keep (a template's rows / group / JSON fields). */
    public const KEPT_TYPES = ['rows' => 'Rows (sub-fields)', 'group' => 'Group (sub-fields)', 'json' => 'JSON', 'array' => 'List (JSON)'];

    private static function knownType(?string $type): bool
    {
        return array_key_exists((string) $type, self::FIELD_TYPES) || array_key_exists((string) $type, self::KEPT_TYPES);
    }

    /** "Edit fields" panel: one row per field. */
    public bool $editingFields = false;

    public array $fieldRows = [];

    public function mount(Site $site, ?string $collection = null, ?string $entry = null, ?string $screen = null): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'collections.view'), 403);
        $col = CollectionModel::where('site_id', $site->id)->findOrFail((string) $collection);
        $this->viewingId = $col->id;

        // Each entry / the field editor is its own page (routes collections.entries.*, collections.fields).
        match ($screen) {
            'entry' => $this->viewItem((string) $entry),
            'edit' => $this->canManage() ? $this->editItem((string) $entry) : $this->viewItem((string) $entry),
            'new' => $this->canManage() ? $this->addEntry() : null,
            'fields' => $this->canManage() ? $this->openFields() : null,
            default => null,
        };
        // Older links: ?item={id} / ?new=1 → the entry's own page.
        if ($screen === null) {
            $itemId = (string) request()->query('item');
            if ($itemId !== '' && $col->items()->whereKey($itemId)->exists()) {
                $this->redirect($this->entryUrl($itemId), navigate: true);
            } elseif (request()->boolean('new') && $this->canManage()) {
                $this->redirect($this->pageUrl('new'), navigate: true);
            }
        }
    }

    /** URL of this collection's page, or one of its own sub-pages. */
    public function pageUrl(?string $screen = null): string
    {
        $args = [$this->site->name, $this->viewingId];

        return match ($screen) {
            'new' => route('collections.entries.new', $args),
            'fields' => route('collections.fields', $args),
            default => route('collections.show', $args),
        };
    }

    public function entryUrl(string $itemId, bool $edit = false): string
    {
        return route($edit ? 'collections.entries.edit' : 'collections.entries.show', [$this->site->name, $this->viewingId, $itemId]);
    }

    private function canManage(): bool
    {
        return $this->site->allows(Auth::user(), 'collections.manage');
    }

    private function guardManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function collection(): CollectionModel
    {
        return CollectionModel::where('site_id', $this->site->id)->findOrFail($this->viewingId);
    }

    // ── Side panel ────────────────────────────────────────────────

    public function viewItem(string $itemId): void
    {
        $this->collection()->items()->findOrFail($itemId);
        parent::cancelItem();
        $this->panelId = $itemId;
        $this->panelMode = 'view';
    }

    public function editItem(?string $itemId = null): void
    {
        $this->guardManage();
        $itemId ??= $this->panelId;
        parent::openItem($itemId);
        $this->panelId = $itemId;
        $this->panelMode = 'edit';
    }

    public function addEntry(): void
    {
        $this->guardManage();
        parent::openItem(null);
        $this->panelId = '';
        $this->panelMode = 'edit';
    }

    /** Edit → back to the entry's page (a new, unsaved entry → the collection). */
    public function cancelItem(): void
    {
        parent::cancelItem();
        if ($this->panelId) {
            $this->panelMode = 'view';
            $this->redirect($this->entryUrl($this->panelId), navigate: true);
        } else {
            $this->closePanel();
        }
    }

    /** Leave the entry's page → back to the collection. */
    public function closePanel(): void
    {
        parent::cancelItem();
        $this->panelId = null;
        $this->panelMode = 'view';
        $this->redirect($this->pageUrl(), navigate: true);
    }

    // ── Fields (schema): label, type and per-type settings ─────────

    public function openFields(): void
    {
        $this->guardManage();
        parent::cancelItem();            // no entry open on the field editor's page
        $this->panelId = null;
        $this->panelMode = 'view';
        $this->fieldRows = collect($this->collection()->fields ?? [])->map(fn ($f) => [
            'key' => (string) ($f['key'] ?? $f['name'] ?? ''),
            'label' => (string) ($f['label'] ?? Str::headline((string) ($f['key'] ?? ''))),
            'type' => self::knownType($f['type'] ?? 'text') ? ($f['type'] ?? 'text') : 'text',
            'options' => implode(', ', (array) ($f['options'] ?? [])),
            'min' => (string) ($f['min'] ?? ''),
            'max' => (string) ($f['max'] ?? ''),
            'step' => (string) ($f['step'] ?? ''),
            'required' => (bool) ($f['required'] ?? false),
            'hidden' => (bool) ($f['hidden'] ?? false),
            'auto' => (string) ($f['auto'] ?? ''),
            'slugFrom' => CollectionAutoFields::slugSources($f['auto'] ?? null),
        ])->values()->all();
        $this->resetErrorBag();
        $this->editingFields = true;
    }

    public function addFieldRow(): void
    {
        $this->fieldRows[] = ['key' => '', 'label' => '', 'type' => 'text', 'options' => '', 'min' => '', 'max' => '', 'step' => '', 'required' => false, 'hidden' => false, 'auto' => '', 'slugFrom' => []];
    }

    public function removeFieldRow(int $i): void
    {
        unset($this->fieldRows[$i]);
        $this->fieldRows = array_values($this->fieldRows);
    }

    public function moveFieldRow(int $i, int $dir): void
    {
        $j = $i + ($dir < 0 ? -1 : 1);
        if (isset($this->fieldRows[$i], $this->fieldRows[$j])) {
            [$this->fieldRows[$i], $this->fieldRows[$j]] = [$this->fieldRows[$j], $this->fieldRows[$i]];
        }
    }

    /** Leave the field editor's page → back to the collection. */
    public function closeFields(): void
    {
        $this->reset('editingFields', 'fieldRows');
        $this->resetErrorBag();
        $this->redirect($this->pageUrl(), navigate: true);
    }

    /**
     * Save the schema. Entry data is kept: a removed field's values stay
     * stored (just not shown), a retyped field's values are converted; existing keys keep their name so data
     * lines up; anything else on a field (nested list schema, help…) is kept.
     */
    public function saveFields(): void
    {
        $this->guardManage();
        $this->resetErrorBag();
        $col = $this->collection();
        $old = collect($col->fields ?? [])->keyBy(fn ($f) => (string) ($f['key'] ?? $f['name'] ?? ''));
        $used = [];
        $out = [];
        foreach ($this->fieldRows as $i => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                $this->addError("fieldRows.$i.label", 'Give this field a name.');

                continue;
            }
            $type = self::knownType($row['type'] ?? '') ? $row['type'] : 'text';
            $key = (string) ($row['key'] ?? '') ?: Str::snake(Str::ascii($label));
            $key = $key !== '' ? $key : 'field';
            for ($n = 2, $base = $key; in_array($key, $used, true) || (($row['key'] ?? '') === '' && $old->has($key)); $n++) {
                $key = $base.'_'.$n;
            }
            $used[] = $key;
            $options = collect(explode(',', (string) ($row['options'] ?? '')))->map(fn ($o) => trim($o))->filter(fn ($o) => $o !== '')->unique()->values()->all();
            if (in_array($type, ['select', 'radio'], true) && count($options) < 2) {
                $this->addError("fieldRows.$i.options", 'Add at least two options, separated by commas.');
            }
            $num = fn ($v) => is_numeric(trim((string) $v)) ? 0 + trim((string) $v) : null;
            [$min, $max, $step] = [$num($row['min'] ?? ''), $num($row['max'] ?? ''), $num($row['step'] ?? '')];
            if ($type === 'slider') {
                [$min, $max, $step] = [$min ?? 0, $max ?? 100, $step ?? 1];
            }
            if ($min !== null && $max !== null && $min >= $max) {
                $this->addError("fieldRows.$i.max", 'Max must be bigger than min.');
            }
            if ($step !== null && $step <= 0) {
                $this->addError("fieldRows.$i.step", 'Step must be above 0.');
            }

            $auto = (string) ($row['auto'] ?? '');
            $auto = CollectionAutoFields::valid($auto) && CollectionAutoFields::slugSources($auto) === [] ? $auto : null;
            if ($type === 'slug') {
                // Built from the fields ticked under "Made from" (fields of this collection).
                $known = collect($this->fieldRows)->pluck('key')->filter()->all();
                $auto = CollectionAutoFields::slugSource(array_values(array_intersect((array) ($row['slugFrom'] ?? []), $known)));
                if ($auto === null) {
                    $this->addError("fieldRows.$i.slugFrom", 'Tick at least one field to make the slug from.');
                }
            }

            $def = array_diff_key((array) ($old[$key] ?? []), array_flip(['options', 'min', 'max', 'step', 'hidden', 'auto']));
            $out[] = array_filter([
                'key' => $key,
                'label' => $label,
                'type' => $type,
                // A system-filled field is never typed in, so it can't be required.
                'required' => ! $auto && (bool) ($row['required'] ?? false) ? true : null,
                'hidden' => (bool) ($row['hidden'] ?? false) ?: null,
                'auto' => $auto,
                'options' => in_array($type, ['select', 'radio'], true) ? $options : null,
                'min' => in_array($type, ['number', 'slider'], true) ? $min : null,
                'max' => in_array($type, ['number', 'slider'], true) ? $max : null,
                'step' => in_array($type, ['number', 'slider'], true) ? $step : null,
            ], fn ($v) => $v !== null) + array_diff_key($def, array_flip(['key', 'label', 'type', 'required']));
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        // Fields that just got a system source are filled on the existing entries.
        $newlyAuto = collect($out)->filter(fn ($f) => ! empty($f['auto']) && (($old[$f['key']]['auto'] ?? null) !== $f['auto']))->isNotEmpty();
        $retyped = collect($out)->filter(fn ($f) => $old->has($f['key']) && ($old[$f['key']]['type'] ?? 'text') !== $f['type'])->values()->all();
        $col->update(['fields' => $out]);
        // A changed type converts the stored values ("yes" → on for a toggle, "12" → 12 for a number).
        if ($retyped !== []) {
            \App\Support\CollectionFieldShape::coerceEntries($col, $retyped);
        }
        if ($newlyAuto) {
            CollectionAutoFields::backfill($col->fresh());
        }
        $this->bustRenderCache($this->site);
        $this->closeFields();
        $this->dispatch('toast', level: 'success', title: 'Fields saved', message: 'Entries now edit with these field types.');
    }

    // ── Changes (all need collections.manage) ─────────────────────

    public function openItem(?string $itemId = null): void
    {
        $this->guardManage();
        parent::openItem($itemId);
    }

    public function saveItem(): void
    {
        $this->guardManage();
        $isNew = $this->editingItemId === '';
        $editedId = $this->editingItemId;
        parent::saveItem();
        if ($this->getErrorBag()->isNotEmpty()) {
            return; // invalid JSON etc. — stay in edit mode
        }
        if ($isNew) {
            // New entries go to the end of the manual order.
            $col = $this->collection();
            $created = $this->createdItemId ? $col->items()->find($this->createdItemId) : null;
            if ($created && $created->position === null) {
                $created->update(['position' => (int) $col->items()->max('position') + 1]);
            }
            $editedId = $created?->id;
        }
        // Back to the page of what was just saved.
        if ($editedId) {
            $this->viewItem((string) $editedId);
            $this->redirect($this->entryUrl((string) $editedId), navigate: true);
        } else {
            $this->closePanel();
        }
    }

    /** Soft delete: the entry disappears everywhere but can be restored. */
    public function deleteItem(string $itemId): void
    {
        $this->guardManage();
        $item = $this->collection()->items()->findOrFail($itemId);
        $item->delete();
        $this->bustRenderCache($this->site);
        if ($this->panelId === $itemId) {
            $this->closePanel();
        }
        $this->dispatch('toast', level: 'success', title: 'Entry deleted', message: 'It no longer appears anywhere — restore it from “Deleted entries” if you need it back.');
    }

    public function restoreItem(string $itemId): void
    {
        $this->guardManage();
        $item = CollectionItem::onlyTrashed()->where('collection_id', $this->viewingId)->findOrFail($itemId);
        $item->restore();
        $this->bustRenderCache($this->site);
        $this->dispatch('toast', level: 'success', title: 'Entry restored', message: 'It\'s back in the collection, in its old place.');
    }

    /** Move an entry one place up (-1) or down (+1) in the manual order. */
    public function moveItem(string $itemId, int $dir): void
    {
        $this->guardManage();
        $ids = $this->collection()->items()->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $i = array_search($itemId, $ids, true);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        DB::transaction(function () use ($ids) {
            foreach ($ids as $pos => $id) {
                CollectionItem::whereKey($id)->update(['position' => $pos]);
            }
        });
        $this->bustRenderCache($this->site);
    }

    /** Published ⇄ draft (drafts never reach the site). */
    public function toggleStatus(string $itemId): void
    {
        $this->guardManage();
        $item = $this->collection()->items()->findOrFail($itemId);
        $item->update(['status' => $item->status === 'published' ? 'draft' : 'published']);
        $this->bustRenderCache($this->site);
    }

    /** Blocks that show this collection (data source or a list field), with their selection. */
    private function usedBy(CollectionModel $col): array
    {
        $viaNode = Node::where('type', 'collection')->where('value', $col->id)->pluck('component_id');

        return ComponentModel::where('site_id', $this->site->id)
            ->where(fn ($q) => $q->where('collection_id', $col->id)->orWhereIn('id', $viaNode))
            ->orderBy('name')->get(['id', 'name', 'collection_id', 'collection_queries'])
            ->map(function (ComponentModel $c) use ($col) {
                $q = CollectionQuery::normalize($c->collectionQuery($col->id), CollectionQuery::fieldKeys($col));
                $published = $col->items()->where('status', 'published')->get();

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'role' => (string) $c->collection_id === (string) $col->id ? 'data source' : 'list field',
                    'summary' => CollectionQuery::summary($q, $published->count(), CollectionQuery::apply($col, $q, $published)->count()),
                    'url' => url($this->site->name.'/connect?component='.$c->id),
                ];
            })->all();
    }

    public function render()
    {
        $col = $this->collection();
        $entries = $col->items()->get();
        $keys = CollectionQuery::fieldKeys($col);
        $label = function (CollectionItem $i) use ($keys) {
            $d = (array) ($i->data ?? []);
            foreach (['name', 'title', 'label', ...$keys] as $k) {
                if (is_string($d[$k] ?? null) && trim($d[$k]) !== '') {
                    return strip_tags($d[$k]);
                }
            }

            return 'Untitled entry';
        };
        // List thumbnail: the first field holding an image (any field name).
        $image = function (CollectionItem $i) use ($col) {
            foreach ((array) ($i->data ?? []) as $v) {
                if (($m = MediaValue::detect($v, $col->site_id)) && $m['kind'] === 'image') {
                    return $m['url'];
                }
            }

            return '';
        };

        $trashed = CollectionItem::onlyTrashed()->where('collection_id', $col->id)->latest('deleted_at')->get();
        $panelItem = $this->panelId ? $col->items()->find($this->panelId) : null;

        return view('livewire.collection-detail-page', [
            // Grouped components (the collection's members) + those still free to add.
            'members' => $col->components()->withCount('nodes')->get(),
            'available' => \App\Models\Component::where('site_id', $this->site->id)->whereNull('collection_id')
                ->when($this->memberSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->memberSearch.'%'))
                ->orderBy('name')->get(['id', 'name']),
            'viewing' => $col,
            'entries' => $entries,
            'trashed' => $trashed,
            'panelItem' => $panelItem,
            'label' => $label,
            'image' => $image,
            'fields' => $keys,
            'usedBy' => $this->usedBy($col),
            'canManage' => $this->canManage(),
            'stats' => [
                'total' => $entries->count(),
                'published' => $entries->where('status', 'published')->count(),
                'drafts' => $entries->where('status', '!=', 'published')->count(),
                'fields' => count($keys),
            ],
        ]);
    }
}
