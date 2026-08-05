<?php

namespace App\Services;

use App\Models\Block;
use App\Models\BlockBatch;
use App\Models\BlockLayout;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The ONE mutation service for the BlockKit tree — jigsaw parity enforced
 * structurally: the manual editors, the REST API and the AI assistant all
 * route through these six operations, so anything one client builds, the
 * others can read, validate against the same catalogue, and rearrange.
 *
 * Owners: a block tree belongs to a Page (page content) or a BlockLayout
 * (the arrangement around its single content_slot). Same machinery for both.
 *
 * $source is 'user' or 'ai'. meta.locked blocks reject AI mutations — the
 * user pins hand-tuned work and the AI cannot touch it (enforced HERE, not
 * by prompt discipline).
 */
class BlockTreeService
{
    public const SOURCE_USER = 'user';

    public const SOURCE_AI = 'ai';

    /** Undo history depth per owner. */
    private const HISTORY_LIMIT = 50;

    /** Open batch (one undo unit). */
    private ?array $batch = null;

    /** True while undo/redo replays ops — suppresses history recording. */
    private bool $replaying = false;

    // ─────────────────────────────────────────────── Owner (page ⊕ layout)

    /** Blocks belong to a page OR a layout — same tree machinery for both. */
    private function ownerColumn(Page|BlockLayout $owner): string
    {
        return $owner instanceof Page ? 'page_id' : 'layout_id';
    }

    private function scoped(Page|BlockLayout $owner)
    {
        return Block::where($this->ownerColumn($owner), $owner->id);
    }

    private function isLayoutOwner(Page|BlockLayout $owner): bool
    {
        // Components share the BlockLayout table but follow PAGE rules:
        // no content_slot, no slot invariant — they get stamped into pages.
        return $owner instanceof BlockLayout && ! $owner->isComponent();
    }

    /** The Blank layout is built in: never editable, never deletable. */
    private function assertOwnerEditable(Page|BlockLayout $owner): void
    {
        if ($owner instanceof BlockLayout && $owner->is_system) {
            throw new \RuntimeException('The Blank layout is built in and cannot be edited.');
        }
    }

    // ─────────────────────────────────────────────── Undo/redo batching

    /**
     * Group several operations into ONE undo unit (e.g. a whole AI turn).
     * Nested calls join the outer batch. The batch commits only if ops recorded.
     */
    public function withBatch(Page|BlockLayout $owner, string $label, string $source, callable $fn): mixed
    {
        if ($this->batch !== null) {
            return $fn(); // join the outer batch
        }
        $this->batch = ['owner_col' => $this->ownerColumn($owner), 'owner_id' => $owner->id, 'source' => $source, 'label' => $label, 'forward' => [], 'inverse' => []];
        try {
            $result = $fn();
            $this->commitBatch();

            return $result;
        } catch (\Throwable $e) {
            $this->batch = null;

            throw $e;
        }
    }

    /** Record one op's forward + inverse into the open batch (or a fresh single-op batch). */
    private function record(Page|BlockLayout $owner, string $label, string $source, array $forward, array $inverse): void
    {
        if ($this->replaying) {
            return;
        }
        if ($this->batch !== null) {
            $this->batch['forward'] = array_merge($this->batch['forward'], $forward);
            $this->batch['inverse'] = array_merge($this->batch['inverse'], $inverse);

            return;
        }
        $this->batch = ['owner_col' => $this->ownerColumn($owner), 'owner_id' => $owner->id, 'source' => $source, 'label' => $label, 'forward' => $forward, 'inverse' => $inverse];
        $this->commitBatch();
    }

    private function commitBatch(): void
    {
        $batch = $this->batch;
        $this->batch = null;
        if (! $batch || ($batch['forward'] === [] && $batch['inverse'] === [])) {
            return;
        }
        // A new mutation invalidates the redo tail and trims old history.
        BlockBatch::where($batch['owner_col'], $batch['owner_id'])->where('undone', true)->delete();
        BlockBatch::create([
            $batch['owner_col'] => $batch['owner_id'],
            'source'  => $batch['source'],
            'label'   => mb_substr($batch['label'], 0, 120),
            'forward' => $batch['forward'],
            'inverse' => $batch['inverse'],
        ]);
        $keep = BlockBatch::where($batch['owner_col'], $batch['owner_id'])->orderByDesc('id')->limit(self::HISTORY_LIMIT)->pluck('id');
        BlockBatch::where($batch['owner_col'], $batch['owner_id'])->whereNotIn('id', $keep)->delete();
    }

    public function canUndo(Page|BlockLayout $owner): ?string
    {
        return BlockBatch::where($this->ownerColumn($owner), $owner->id)->where('undone', false)->orderByDesc('id')->value('label');
    }

    public function canRedo(Page|BlockLayout $owner): ?string
    {
        return BlockBatch::where($this->ownerColumn($owner), $owner->id)->where('undone', true)->orderBy('id')->value('label');
    }

    /** Revert the newest active batch. Returns its label, or null when empty. */
    public function undo(Page|BlockLayout $owner): ?string
    {
        $batch = BlockBatch::where($this->ownerColumn($owner), $owner->id)->where('undone', false)->orderByDesc('id')->first();
        if (! $batch) {
            return null;
        }
        DB::transaction(function () use ($owner, $batch) {
            $this->applyOps($owner, array_reverse($batch->inverse));
            $batch->update(['undone' => true]);
        });

        return $batch->label;
    }

    /** Replay the oldest undone batch. Returns its label, or null when none. */
    public function redo(Page|BlockLayout $owner): ?string
    {
        $batch = BlockBatch::where($this->ownerColumn($owner), $owner->id)->where('undone', true)->orderBy('id')->first();
        if (! $batch) {
            return null;
        }
        DB::transaction(function () use ($owner, $batch) {
            $this->applyOps($owner, $batch->forward);
            $batch->update(['undone' => false]);
        });

        return $batch->label;
    }

    /**
     * Apply normalized history ops. Runs outside validation/locking on purpose:
     * history records state the tree has already legally held.
     */
    private function applyOps(Page|BlockLayout $owner, array $ops): void
    {
        $this->replaying = true;
        try {
            foreach ($ops as $op) {
                match ($op['op']) {
                    'restore_subtree' => $this->restoreSubtree($owner, $op),
                    'restore_block'   => $this->scoped($owner)->whereKey($op['id'])->update([
                        'props' => $op['props'], 'style' => $op['style'], 'meta' => $op['meta'],
                    ]),
                    'move' => $this->applyHistoryMove($owner, $op),
                    'delete_ids' => collect($op['ids'])->each(function ($id) use ($owner) {
                        if ($block = $this->scoped($owner)->find($id)) {
                            $parentId = $block->parent_id;
                            $this->deleteSubtree($owner, $block);
                            $this->resequence($owner, $parentId);
                        }
                    }),
                    default => null,
                };
            }
        } finally {
            $this->replaying = false;
        }
    }

    private function applyHistoryMove(Page|BlockLayout $owner, array $op): void
    {
        $block = $this->scoped($owner)->find($op['id']);
        if (! $block) {
            return;
        }
        $siblings = $this->scoped($owner)->where('parent_id', $op['parent_id'])
            ->where('id', '!=', $block->id)->orderBy('position')->pluck('id')->all();
        $pos = max(0, min((int) $op['position'], count($siblings)));
        array_splice($siblings, $pos, 0, [$block->id]);
        $oldParent = $block->parent_id;
        $block->update(['parent_id' => $op['parent_id']]);
        foreach ($siblings as $i => $id) {
            Block::whereKey($id)->update(['position' => $i]);
        }
        if ($oldParent !== $op['parent_id']) {
            $this->resequence($owner, $oldParent);
        }
    }

    /** Re-create a serialized subtree with its ORIGINAL ids (undo of delete, redo of insert). */
    private function restoreSubtree(Page|BlockLayout $owner, array $op): void
    {
        $this->scoped($owner)->where('parent_id', $op['parent_id'])
            ->where('position', '>=', $op['position'])->increment('position');
        $this->createFromSnapshot($owner, $op['subtree'], $op['parent_id'], $op['position']);
        $this->resequence($owner, $op['parent_id']);
    }

    private function createFromSnapshot(Page|BlockLayout $owner, array $snap, ?string $parentId, int $position): void
    {
        Block::create([
            'id' => $snap['id'], $this->ownerColumn($owner) => $owner->id, 'parent_id' => $parentId, 'position' => $position,
            'type' => $snap['type'], 'props' => $snap['props'], 'style' => $snap['style'], 'meta' => $snap['meta'],
        ]);
        foreach (array_values($snap['children'] ?? []) as $i => $child) {
            $this->createFromSnapshot($owner, $child, $snap['id'], $i);
        }
    }

    /** Serialize a live subtree (ids included) into the history snapshot shape. */
    private function snapshot(Block $block): array
    {
        return [
            'id' => $block->id, 'type' => $block->type,
            'props' => $block->props ?? [], 'style' => $block->style ?? [], 'meta' => $block->meta ?? [],
            'children' => $block->children->map(fn ($c) => $this->snapshot($c))->values()->all(),
        ];
    }

    // ─────────────────────────────────────────────── Components (live-linked)

    /** Which block prop a named component-prop overrides, per block type. */
    public const PROP_TARGET = [
        'header' => 'content', 'content' => 'content',
        'button' => 'label', 'tile' => 'label',
        'input' => 'label', 'textarea' => 'label', 'select' => 'label', 'checkbox' => 'label',
        'media' => 'src',
    ];

    /**
     * Expand every `component_ref` in a tree into the component's LIVE tree:
     * prop overrides applied, the ref's children filling the first slot
     * (slot defaults render when the instance provides none). Editing the
     * component changes every expansion — that's the live link.
     *
     * $forCanvas: expanded nodes are marked `_ro` (component-owned, edited in
     * the component), the slot keeps the ref's id + `_fill` so the canvas can
     * accept per-instance drops; the wrapper stays `component_ref` for the
     * instance chrome. Plain mode (API/export) emits ordinary containers.
     */
    public function expandComponentRefs(array $node, int $siteId, bool $forCanvas = false, array $seen = []): array
    {
        if (($node['type'] ?? '') !== 'component_ref') {
            $node['children'] = array_map(fn ($c) => $this->expandComponentRefs($c, $siteId, $forCanvas, $seen), $node['children'] ?? []);

            return $node;
        }

        $refId = $node['id'];
        $cid = (int) data_get($node, 'props.component_id');
        $component = ($cid && ! in_array($cid, $seen, true) && count($seen) < 5)
            ? BlockLayout::components()->where('site_id', $siteId)->find($cid)
            : null;
        if (! $component) {
            return [
                'id' => $refId, 'type' => $forCanvas ? 'component_ref' : 'container',
                'props' => $forCanvas ? ($node['props'] ?? []) : [],
                'style' => $node['style'] ?? [], 'meta' => ['label' => 'Missing component'], 'children' => [],
            ];
        }

        $overrides = (array) data_get($node, 'props.overrides', []);
        $fill = $node['children'] ?? []; // per-instance slot content (page-owned, editable)
        $slotUsed = false;

        $apply = function (array $n) use (&$apply, $overrides, $fill, &$slotUsed, $forCanvas, $refId) {
            // ◇ Prop placeholder → materialize as a REAL block of its kind,
            // holding the instance's value (or the placeholder's default).
            if ($n['type'] === 'prop') {
                $name = trim((string) data_get($n, 'props.name', ''));
                $value = trim((string) ($overrides[$name] ?? '')) !== '' ? $overrides[$name] : (string) data_get($n, 'props.default', '');
                $made = self::materializeProp((string) data_get($n, 'props.kind', 'heading'), $value, $name);
                $made['id'] = $n['id'];
                $made['style'] = $n['style'] ?? [];
                $made['meta'] = ['label' => $name !== '' ? $name : 'prop'];

                // Canvas: prop content stays CLICKABLE — selecting it opens the
                // instance so the value can be edited right there.
                return $forCanvas ? ($made + ['_prop' => $name, '_ref' => $refId]) : $made;
            }
            $propName = trim((string) data_get($n, 'meta.prop', ''));
            $target = self::PROP_TARGET[$n['type']] ?? null;
            if ($propName !== '' && $target) {
                if (trim((string) ($overrides[$propName] ?? '')) !== '') {
                    $n['props'][$target] = $overrides[$propName];
                }
                if ($forCanvas) {
                    $n['_prop'] = $propName;
                    $n['_ref'] = $refId;
                }
            }
            if ($n['type'] === 'slot' && ! $slotUsed) {
                $slotUsed = true;
                $hasFill = $fill !== [];
                $children = $hasFill ? $fill : array_map($apply, $n['children'] ?? []);
                if (! $hasFill && $forCanvas) {
                    $children = array_map(fn ($c) => $this->markRo($c), $children);
                }

                return [
                    'id' => $forCanvas ? $refId : $n['id'], 'type' => 'container',
                    'props' => ['padding' => 'sm'], 'style' => $n['style'] ?? [],
                    'meta' => ['label' => data_get($n, 'meta.label', 'Slot')],
                    'children' => $children,
                ] + ($forCanvas ? ['_fill' => true] : []);
            }
            if ($n['type'] === 'slot') { // further slots render defaults only
                $n = ['id' => $n['id'], 'type' => 'container', 'props' => ['padding' => 'sm'], 'style' => $n['style'] ?? [], 'meta' => $n['meta'] ?? [], 'children' => $n['children'] ?? []];
            }
            $n['children'] = array_map($apply, $n['children'] ?? []);

            // Prop-bound nodes stay live (clickable); everything else from the
            // component is read-only on a page canvas.
            return $forCanvas && ! ($n['_fill'] ?? false) && ! isset($n['_prop']) ? ($n + ['_ro' => true]) : $n;
        };

        $children = array_map($apply, $this->tree($component)['children'] ?? []);
        // Nested refs inside the component (or the fill) expand too.
        $children = array_map(fn ($c) => $this->expandComponentRefs($c, $siteId, $forCanvas, [...$seen, $cid]), $children);

        return [
            'id' => $refId,
            'type' => $forCanvas ? 'component_ref' : 'container',
            // Plain mode emits a REAL container — ref-only props stay behind.
            'props' => $forCanvas ? ($node['props'] ?? []) : [],
            'style' => $node['style'] ?? [],
            'meta' => ['label' => $component->name],
            'children' => $children,
        ];
    }

    /**
     * A ◇ Prop must not live inside a ▢ Slot: slot defaults are REPLACED by
     * each page's fill, so a prop there would silently vanish — the classic
     * "my prop stopped working". Fail loudly at insert/move time instead.
     */
    private function assertNoPropInsideSlot(Block $parent, array $blocks): void
    {
        $bringsProp = false;
        $walk = function (array $n) use (&$walk, &$bringsProp) {
            if (($n['type'] ?? '') === 'prop') {
                $bringsProp = true;
            }
            foreach ($n['children'] ?? [] as $c) {
                $walk($c);
            }
        };
        foreach ($blocks as $b) {
            $walk(is_array($b) ? $b : []);
        }
        if (! $bringsProp) {
            return;
        }
        for ($node = $parent; $node; $node = $node->parent_id ? Block::find($node->parent_id) : null) {
            if ($node->type === 'slot') {
                throw new \RuntimeException('A Prop can\'t go inside a Slot — the slot\'s content is replaced on every page, so the prop would disappear. Place the Prop outside the Slot.');
            }
        }
    }

    /**
     * Rich text is INLINE HTML — sanitized at the save chokepoint so stored
     * data is always clean, whoever wrote it (user, inline editor, AI, REST).
     */
    public static function sanitizeRichText(string $type, array $props): array
    {
        if (in_array($type, ['header', 'content'], true) && isset($props['content']) && is_string($props['content'])) {
            $props['content'] = \App\Support\RichText::clean($props['content']);
        }

        // CSS-bearing props are injected verbatim into style attributes on all
        // three surfaces — scrub anything that could escape the declaration.
        foreach (['gradient', 'text_gradient', 'overlay_gradient', 'bg_size', 'bg_position'] as $k) {
            if (isset($props[$k]) && is_string($props[$k])) {
                $props[$k] = self::cleanCssValue($props[$k]);
            }
        }

        return $props;
    }

    /**
     * A single CSS VALUE (gradient(), position, size…): no url()/expression,
     * no characters that could break out of the declaration or the attribute.
     */
    public static function cleanCssValue(string $v): string
    {
        $v = preg_replace('/[;{}<>"\'\\\\]+/', '', $v);
        if (preg_match('/url\s*\(|expression|javascript|@import/i', $v)) {
            return '';
        }

        return trim($v);
    }

    /** A prop placeholder's kind + value → the real block it becomes. */
    public static function materializeProp(string $kind, string $value, string $name = ''): array
    {
        return match ($kind) {
            'text'   => ['type' => 'content', 'props' => ['content' => $value], 'children' => []],
            'button' => ['type' => 'button', 'props' => ['label' => $value !== '' ? $value : 'Button', 'action' => ['type' => 'link', 'target' => '#']], 'children' => []],
            'image'  => ['type' => 'media', 'props' => ['kind' => 'image', 'src' => $value, 'alt' => $name !== '' ? $name : 'image'], 'children' => []],
            'video'  => ['type' => 'media', 'props' => ['kind' => 'video', 'src' => $value, 'controls' => true, 'ratio' => '16:9'], 'children' => []],
            default  => ['type' => 'header', 'props' => ['content' => $value, 'level' => 'h2'], 'children' => []],
        };
    }

    /** Recursively mark a subtree read-only for the canvas. */
    private function markRo(array $n): array
    {
        $n['_ro'] = true;
        $n['children'] = array_map(fn ($c) => $this->markRo($c), $n['children'] ?? []);

        return $n;
    }

    // ─────────────────────────────────────────────── Read

    /** The owner's full tree (root container auto-created on first touch). */
    public function tree(Page|BlockLayout $owner): array
    {
        $root = $this->ensureRoot($owner);
        $byParent = $this->scoped($owner)->orderBy('position')->get()->groupBy('parent_id');

        return $this->assemble($root, $byParent);
    }

    /** Every owner gets a root `container` block — the page/layout IS a block. */
    public function ensureRoot(Page|BlockLayout $owner): Block
    {
        $root = $this->scoped($owner)->whereNull('parent_id')->first();
        if ($root) {
            return $root;
        }

        return Block::create([
            'id' => 'blk_'.Str::random(10),
            $this->ownerColumn($owner) => $owner->id,
            'parent_id' => null, 'position' => 0,
            'type' => 'container',
            'props' => ['padding' => 'none', 'max_width' => 'full'],
            'style' => [],
            'meta' => ['label' => $owner instanceof Page ? 'Page' : 'Layout'],
        ]);
    }

    // ─────────────────────────────────────────────── The six operations

    /** Insert one or more block subtrees under a parent at a position. */
    public function insertBlocks(Page|BlockLayout $owner, string $parentId, int $position, array $blocks, string $source = self::SOURCE_USER): array
    {
        $this->assertOwnerEditable($owner);
        $parent = $this->blockOf($owner, $parentId);
        $this->assertLayout($parent);
        $this->assertMutable($parent, $source);

        $insideForm = $this->insideForm($parent);
        foreach ($blocks as $input) {
            $this->validateSubtree($input, $insideForm || (($input['type'] ?? '') === 'form'), $this->isLayoutOwner($owner));
        }
        $this->assertSlotInvariant($owner, $blocks);
        $this->assertNoPropInsideSlot($parent, $blocks);

        return DB::transaction(function () use ($owner, $parent, $position, $blocks, $source) {
            $count = $this->scoped($owner)->where('parent_id', $parent->id)->count();
            $position = max(0, min($position, $count));
            $this->scoped($owner)->where('parent_id', $parent->id)
                ->where('position', '>=', $position)->increment('position');

            $created = [];
            foreach (array_values($blocks) as $i => $input) {
                $created[] = $this->createSubtree($owner, $parent->id, $position + $i, $input);
            }
            $this->resequence($owner, $parent->id);
            $this->assertFormIntegrity($owner, $parent);

            // History: forward re-creates the subtrees, inverse deletes them.
            $forward = [];
            $ids = [];
            foreach ($created as $sub) {
                $block = Block::find($sub['id']);
                $forward[] = ['op' => 'restore_subtree', 'parent_id' => $block->parent_id, 'position' => $block->position, 'subtree' => $this->snapshot($block)];
                $ids[] = $sub['id'];
            }
            $this->record($owner, 'Insert '.count($created).' block(s)', $source, $forward, [['op' => 'delete_ids', 'ids' => $ids]]);

            return $created;
        });
    }

    /** Patch props, style, or meta of an existing block. Partial. */
    public function updateBlock(Page|BlockLayout $owner, string $blockId, array $patch, string $source = self::SOURCE_USER): Block
    {
        $this->assertOwnerEditable($owner);
        $block = $this->blockOf($owner, $blockId);
        $this->assertMutable($block, $source);

        $props = array_key_exists('props', $patch) ? array_merge($block->props ?? [], $patch['props'] ?? []) : ($block->props ?? []);
        // A cleared field arrives as '' — clearing REMOVES the prop (required
        // props keep the empty string so validation still sees them).
        $propSchema = (array) config("blockkit.types.{$block->type}.props", []);
        foreach ($props as $pk => $pv) {
            if ($pv === '' && ! ($propSchema[$pk]['required'] ?? false)) {
                unset($props[$pk]);
            }
        }
        $props = self::sanitizeRichText($block->type, $props);
        $style = array_key_exists('style', $patch) ? array_merge($block->style ?? [], $patch['style'] ?? []) : ($block->style ?? []);
        $meta  = array_key_exists('meta', $patch) ? array_merge($block->meta ?? [], $patch['meta'] ?? []) : ($block->meta ?? []);

        // Only the USER may lock/unlock (the AI cannot unpin work).
        if ($source === self::SOURCE_AI && (bool) data_get($meta, 'locked') !== (bool) data_get($block->meta, 'locked')) {
            throw new \RuntimeException('Only the user can lock or unlock a block.');
        }

        $this->validateProps($block->type, $props, partial: false);
        $this->assertMediaRules($block->type, $props);
        $this->validateStyle($style);

        return DB::transaction(function () use ($owner, $block, $props, $style, $meta, $source) {
            $old = ['props' => $block->props ?? [], 'style' => $block->style ?? [], 'meta' => $block->meta ?? []];
            $block->update(['props' => $props, 'style' => $style, 'meta' => $meta]);
            // A button's action change can affect its form's submit count.
            if ($block->type === 'button') {
                $this->assertFormIntegrity($owner, $block);
            }
            $this->record($owner, 'Edit “'.$block->label().'”', $source,
                [['op' => 'restore_block', 'id' => $block->id, 'props' => $props, 'style' => $style, 'meta' => $meta]],
                [['op' => 'restore_block', 'id' => $block->id, 'props' => $old['props'], 'style' => $old['style'], 'meta' => $old['meta']]],
            );

            return $block->fresh();
        });
    }

    /** Move a block (and its subtree) to a new parent and position. Drag-and-drop calls this. */
    public function moveBlock(Page|BlockLayout $owner, string $blockId, string $newParentId, int $position, string $source = self::SOURCE_USER): Block
    {
        $this->assertOwnerEditable($owner);
        $block = $this->blockOf($owner, $blockId);
        $this->assertMutable($block, $source);
        if ($block->parent_id === null) {
            throw new \RuntimeException('The root cannot be moved.');
        }
        $parent = $this->blockOf($owner, $newParentId);
        $this->assertLayout($parent);
        $this->assertMutable($parent, $source);

        // No cycles: the new parent may not be inside the moving subtree.
        for ($p = $parent; $p !== null; $p = $p->parent_id ? $this->blockOf($owner, $p->parent_id) : null) {
            if ($p->id === $block->id) {
                throw new \RuntimeException('A block cannot be moved inside itself.');
            }
        }
        // Props may not be dragged into a Slot (slot content is replaced per page).
        if ($block->type === 'prop' || $this->scoped($owner)->where('type', 'prop')->get()->contains(fn ($pr) => $this->isInsideSubtree($owner, $pr, $block))) {
            $this->assertNoPropInsideSlot($parent, [['type' => 'prop']]);
        }
        // Containment rules follow the block into its new home.
        if ($this->insideForm($parent) || $parent->type === 'form') {
            $this->assertSubtreeAllowedInForm($block);
        }
        if ($parent->type === 'card') {
            if (! in_array($block->type, config('blockkit.card_children'), true)) {
                throw new \RuntimeException("A card cannot directly contain “{$block->type}”.");
            }
            if ($block->type === 'media' && $this->scoped($owner)->where('parent_id', $parent->id)->where('type', 'media')->where('id', '!=', $block->id)->exists()) {
                throw new \RuntimeException('A card has ONE media slot.');
            }
        }

        return DB::transaction(function () use ($owner, $block, $parent, $position, $source) {
            $oldParent = $block->parent_id;
            $oldPosition = $block->position;

            $siblings = $this->scoped($owner)->where('parent_id', $parent->id)
                ->where('id', '!=', $block->id)->orderBy('position')->pluck('id')->all();
            $position = max(0, min($position, count($siblings)));
            array_splice($siblings, $position, 0, [$block->id]);
            $block->update(['parent_id' => $parent->id]);
            foreach ($siblings as $i => $id) {
                Block::whereKey($id)->update(['position' => $i]);
            }
            if ($oldParent !== $parent->id) {
                $this->resequence($owner, $oldParent);
            }
            $this->assertFormIntegrity($owner, $parent);

            $this->record($owner, 'Move “'.$block->label().'”', $source,
                [['op' => 'move', 'id' => $block->id, 'parent_id' => $parent->id, 'position' => $position]],
                [['op' => 'move', 'id' => $block->id, 'parent_id' => $oldParent, 'position' => $oldPosition]],
            );

            return $block->fresh();
        });
    }

    /** Delete blocks and their subtrees. Layout blocks w/ children need confirmation. */
    public function deleteBlocks(Page|BlockLayout $owner, array $blockIds, bool $confirmed = false, string $source = self::SOURCE_USER): int
    {
        $this->assertOwnerEditable($owner);
        $blocks = array_map(fn ($id) => $this->blockOf($owner, $id), $blockIds);
        $slot = $this->isLayoutOwner($owner) ? $this->scoped($owner)->where('type', 'content_slot')->first() : null;
        foreach ($blocks as $block) {
            $this->assertMutable($block, $source);
            if ($block->parent_id === null) {
                throw new \RuntimeException('The root cannot be deleted.');
            }
            // A layout must keep exactly one content section.
            if ($slot && ($block->id === $slot->id || $this->isInsideSubtree($owner, $slot, $block))) {
                throw new \RuntimeException('A layout must keep its content section — every page renders inside it.');
            }
            if (! $confirmed && $block->isLayout() && $block->children()->exists()) {
                throw new \RuntimeException('confirmation_required: deleting “'.$block->label().'” also deletes everything inside it.');
            }
        }

        return DB::transaction(function () use ($owner, $blocks, $source) {
            $forward = [];
            $inverse = [];
            $count = 0;
            foreach ($blocks as $block) {
                $inverse[] = ['op' => 'restore_subtree', 'parent_id' => $block->parent_id, 'position' => $block->position, 'subtree' => $this->snapshot($block)];
                $forward[] = ['op' => 'delete_ids', 'ids' => [$block->id]];
                $parentId = $block->parent_id;
                $count += $this->deleteSubtree($owner, $block);
                $this->resequence($owner, $parentId);
            }
            $this->record($owner, 'Delete '.count($blocks).' block(s)', $source, $forward, $inverse);

            return $count;
        });
    }

    /** Deep-copy a block subtree as a sibling immediately after the original. */
    public function duplicateBlock(Page|BlockLayout $owner, string $blockId, string $source = self::SOURCE_USER): Block
    {
        $this->assertOwnerEditable($owner);
        $block = $this->blockOf($owner, $blockId);
        if ($block->parent_id === null) {
            throw new \RuntimeException('The root cannot be duplicated.');
        }
        // Duplicating the content section (or a subtree holding it) would make two.
        if ($this->isLayoutOwner($owner)) {
            $slot = $this->scoped($owner)->where('type', 'content_slot')->first();
            if ($slot && ($block->id === $slot->id || $this->isInsideSubtree($owner, $slot, $block))) {
                throw new \RuntimeException('A layout has exactly one content section — it cannot be duplicated.');
            }
        }
        $this->assertMutable($this->blockOf($owner, $block->parent_id), $source);

        return DB::transaction(function () use ($owner, $block, $source) {
            $this->scoped($owner)->where('parent_id', $block->parent_id)
                ->where('position', '>', $block->position)->increment('position');
            $copy = $this->copySubtree($owner, $block, $block->parent_id, $block->position + 1);
            $this->resequence($owner, $block->parent_id);
            $this->assertFormIntegrity($owner, $copy);

            $this->record($owner, 'Duplicate “'.$block->label().'”', $source,
                [['op' => 'restore_subtree', 'parent_id' => $copy->parent_id, 'position' => $copy->position, 'subtree' => $this->snapshot($copy)]],
                [['op' => 'delete_ids', 'ids' => [$copy->id]]],
            );

            return $copy;
        });
    }

    // ─────────────────────────────────────────────── Validation (the contract)

    /** Validate a whole insert subtree: type, kind/children, props, style, meta. */
    private function validateSubtree(array $input, bool $insideForm, bool $forLayout = false): void
    {
        $type = (string) ($input['type'] ?? '');
        $def = config("blockkit.types.{$type}");
        if (! $def) {
            throw new \RuntimeException("Unknown block type “{$type}” — only catalogue blocks are allowed.");
        }
        if ($type === 'content_slot' && ! $forLayout) {
            throw new \RuntimeException('The content section exists only inside layouts — pages ARE the content.');
        }
        if ($insideForm && ! in_array($type, config('blockkit.form_children'), true) && $type !== 'form') {
            throw new \RuntimeException("A form cannot contain “{$type}” blocks.");
        }

        $this->validateProps($type, (array) ($input['props'] ?? []), partial: false);
        $this->assertMediaRules($type, (array) ($input['props'] ?? []));
        $this->validateStyle((array) ($input['style'] ?? []));

        $children = (array) ($input['children'] ?? []);
        if ($children !== [] && $def['kind'] !== 'layout') {
            throw new \RuntimeException("“{$type}” is a content block — it cannot have children.");
        }
        if ($type === 'card') {
            $mediaKids = collect($children)->where('type', 'media')->count();
            if ($mediaKids > 1) {
                throw new \RuntimeException('A card has ONE media slot — put extra media inside the body (a flex/container child).');
            }
            foreach ($children as $child) {
                if (! in_array($child['type'] ?? '', config('blockkit.card_children'), true)) {
                    throw new \RuntimeException("A card cannot directly contain “{$child['type']}” — allowed: ".implode(', ', config('blockkit.card_children')).'.');
                }
            }
        }
        foreach ($children as $child) {
            $this->validateSubtree($child, $insideForm || $type === 'form', $forLayout);
        }

        // A form arriving fully-formed must already respect the one-submit rule.
        if ($type === 'form' && $this->countInputSubmits($input) > 1) {
            throw new \RuntimeException('A form may have exactly one submit button.');
        }
    }

    /** Layouts keep EXACTLY one content_slot; pages get none. */
    private function assertSlotInvariant(Page|BlockLayout $owner, array $blocks): void
    {
        $slotsInPayload = 0;
        $walk = function (array $input) use (&$walk, &$slotsInPayload) {
            if (($input['type'] ?? '') === 'content_slot') {
                $slotsInPayload++;
            }
            foreach ((array) ($input['children'] ?? []) as $child) {
                $walk($child);
            }
        };
        foreach ($blocks as $input) {
            $walk($input);
        }
        if ($slotsInPayload === 0) {
            return;
        }
        if (! $this->isLayoutOwner($owner)) {
            throw new \RuntimeException('The content section exists only inside layouts.');
        }
        $existing = $this->scoped($owner)->where('type', 'content_slot')->count();
        if ($existing + $slotsInPayload > 1) {
            throw new \RuntimeException('A layout has exactly ONE content section.');
        }
    }

    /** Is $needle inside $haystack's subtree (or the same block)? */
    private function isInsideSubtree(Page|BlockLayout $owner, Block $needle, Block $haystack): bool
    {
        for ($p = $needle; $p !== null; $p = $p->parent_id ? $this->scoped($owner)->find($p->parent_id) : null) {
            if ($p->id === $haystack->id) {
                return true;
            }
        }

        return false;
    }

    /** Submit buttons inside an INSERT payload subtree (pre-persistence check). */
    private function countInputSubmits(array $input): int
    {
        $count = (($input['type'] ?? '') === 'button' && data_get($input, 'props.action.type') === 'submit') ? 1 : 0;
        foreach ((array) ($input['children'] ?? []) as $child) {
            $count += $this->countInputSubmits($child);
        }

        return $count;
    }

    /** Props against the catalogue schema: unknown keys rejected, types checked. */
    private function validateProps(string $type, array $props, bool $partial): void
    {
        $schema = (array) config("blockkit.types.{$type}.props", []);

        foreach ($props as $key => $value) {
            $rule = $schema[$key] ?? null;
            if (! $rule) {
                throw new \RuntimeException("Unknown prop “{$key}” for block type “{$type}”.");
            }
            $this->checkValue($type, $key, $rule, $value);
        }
        if (! $partial) {
            foreach ($schema as $key => $rule) {
                if (($rule['required'] ?? false) && (! array_key_exists($key, $props) || $props[$key] === '' || $props[$key] === null)) {
                    throw new \RuntimeException("Prop “{$key}” is required on “{$type}”.");
                }
            }
        }
    }

    private function checkValue(string $type, string $key, array $rule, mixed $value): void
    {
        $fail = fn (string $why) => throw new \RuntimeException("Invalid “{$key}” on “{$type}”: {$why}");
        switch ($rule['type']) {
            case 'string':
                is_string($value) || $fail('expected a string');
                if (($rule['pattern'] ?? null) && ! preg_match($rule['pattern'], $value)) {
                    $fail('must match '.$rule['pattern'].' (snake_case)');
                }
                break;
            case 'bool':
                is_bool($value) || $fail('expected true/false');
                break;
            case 'int':
                is_int($value) || $fail('expected an integer');
                break;
            case 'enum':
                in_array($value, $rule['values'], true) || $fail('must be one of '.implode('|', $rule['values']));
                break;
            case 'columns':
                if (is_int($value)) {
                    break; // plain column count is fine
                }
                is_array($value) || $fail('expected an integer or a responsive map {base, md, lg}');
                foreach ($value as $bp => $n) {
                    in_array($bp, ['base', 'sm', 'md', 'lg', 'xl'], true) || $fail("unknown breakpoint “{$bp}”");
                    (is_int($n) && $n >= 1 && $n <= 6) || $fail('column counts must be integers 1–6');
                }
                break;
            case 'options':
                (is_array($value) && $value !== [] && collect($value)->every(fn ($o) => is_string($o))) || $fail('expected a non-empty array of strings');
                break;
            case 'action':
                is_array($value) || $fail('expected an action object');
                in_array($value['type'] ?? '', ['link', 'submit', 'open_modal', 'open_lightbox', 'custom_event'], true) || $fail('action.type must be link|submit|open_modal|open_lightbox|custom_event');
                foreach (['target', 'url'] as $k) {
                    if (array_key_exists($k, $value) && ! is_string($value[$k])) {
                        $fail("action.{$k} must be a string");
                    }
                }
                break;
            case 'responsive_enum':
                // Mobile-first: a single value, or {base, md, lg} — each from the enum.
                if (is_string($value)) { in_array($value, $rule['values'], true) || $fail('must be one of: '.implode('|', $rule['values'])); break; }
                is_array($value) || $fail('expected a value or a {base, md, lg} object');
                $value !== [] || $fail('breakpoint object cannot be empty');
                foreach ($value as $bp => $v) {
                    in_array($bp, ['base', 'md', 'lg'], true) || $fail("unknown breakpoint “{$bp}”");
                    in_array($v, $rule['values'], true) || $fail("breakpoint “{$bp}” must be one of: ".implode('|', $rule['values']));
                }
                break;
            case 'size':
                // Relative to the parent frame: auto | 0 | <n>% | <n>pt | <n>rem | <n>vh | <n>px
                (is_string($value) && preg_match('/^(auto|0|\d+(\.\d+)?(%|pt|rem|vh|px)|\$[a-z][a-z0-9_-]*)$/i', $value))
                    || $fail('size must be auto, 0, a number with %, pt, rem, vh or px, or a $theme-variable');
                break;
            case 'sides':
                // Legacy shorthand: a bare spacing token (old enum padding data) stays valid.
                if (is_string($value) && preg_match('/^(none|xs|sm|md|lg|xl)$/', $value)) {
                    break;
                }
                is_array($value) || $fail('expected a per-side object {top,right,bottom,left}');
                foreach ($value as $side => $v) {
                    in_array($side, ['top', 'right', 'bottom', 'left'], true) || $fail("unknown side “{$side}”");
                    (is_string($v) && preg_match('/^(auto|0|\d+(\.\d+)?(%|pt|rem|vh|px)|none|xs|sm|md|lg|xl|\$[a-z][a-z0-9_-]*)$/i', $v))
                        || $fail("side “{$side}” must be auto, 0, a number with %, pt, rem, vh or px, a spacing token, or a \$theme-variable");
                }
                break;
            default:
                is_array($value) || $fail('expected an object');
        }
    }

    /**
     * Cross-prop media safety: images always carry alt text; autoplaying video
     * must be muted. Called with the FULL prop set.
     */
    private function assertMediaRules(string $type, array $props): void
    {
        if ($type !== 'media') {
            return;
        }
        $kind = $props['kind'] ?? 'image';
        if ($kind === 'image' && trim((string) ($props['alt'] ?? '')) === '') {
            throw new \RuntimeException('Images require alt text (media.alt).');
        }
        if ($kind === 'video' && ($props['autoplay'] ?? false) && ! ($props['muted'] ?? false)) {
            throw new \RuntimeException('Autoplay video must be muted (set media.muted = true).');
        }
        if (in_array($kind, ['video', 'audio'], true)
            && trim((string) ($props['src'] ?? '')) === ''
            && trim((string) ($props['asset_id'] ?? '')) === '') {
            throw new \RuntimeException("media.src (a URL) or media.asset_id (a @media/… library file) is required for {$kind}.");
        }
    }

    private function validateStyle(array $style): void
    {
        $schema = (array) config('blockkit.style', []);
        foreach ($style as $key => $value) {
            $rule = $schema[$key] ?? null;
            if (! $rule) {
                throw new \RuntimeException("Unknown style key “{$key}”.");
            }
            $this->checkValue('style', $key, $rule, $value);
        }
    }

    /** Forms: every field named, ≤1 submit button in the subtree. */
    private function assertFormIntegrity(Page|BlockLayout $owner, Block $near): void
    {
        // Find the enclosing form (if any) and re-count its submit buttons.
        $form = $near->type === 'form' ? $near : null;
        for ($p = $near; $p !== null && ! $form; $p = $p->parent_id ? $this->blockOf($owner, $p->parent_id) : null) {
            if ($p->type === 'form') {
                $form = $p;
            }
        }
        if (! $form) {
            return;
        }
        if ($this->countSubmits($form) > 1) {
            throw new \RuntimeException('A form may have exactly one submit button.');
        }
    }

    private function countSubmits(Block $block): int
    {
        $count = ($block->type === 'button' && data_get($block->props, 'action.type') === 'submit') ? 1 : 0;
        foreach ($block->children as $child) {
            $count += $this->countSubmits($child);
        }

        return $count;
    }

    private function assertSubtreeAllowedInForm(Block $block): void
    {
        if (! in_array($block->type, config('blockkit.form_children'), true) && $block->type !== 'form') {
            throw new \RuntimeException("A form cannot contain “{$block->type}” blocks.");
        }
        foreach ($block->children as $child) {
            $this->assertSubtreeAllowedInForm($child);
        }
    }

    // ─────────────────────────────────────────────── Internals

    private function blockOf(Page|BlockLayout $owner, string $id): Block
    {
        $block = $this->scoped($owner)->find($id);
        if (! $block) {
            throw new \RuntimeException("Block “{$id}” does not exist on this tree.");
        }

        return $block;
    }

    private function assertLayout(Block $block): void
    {
        $kind = config("blockkit.types.{$block->type}.kind");
        if ($kind !== 'layout') {
            throw new \RuntimeException("“{$block->type}” cannot hold children — pick a layout block (container, flex, grid…).");
        }
    }

    private function assertMutable(Block $block, string $source): void
    {
        if ($source === self::SOURCE_AI && $block->isLocked()) {
            throw new \RuntimeException('Block “'.$block->label().'” is pinned by the user and cannot be modified by the AI.');
        }
    }

    private function insideForm(Block $block): bool
    {
        for ($p = $block; $p !== null; $p = $p->parent) {
            if ($p->type === 'form') {
                return true;
            }
        }

        return false;
    }

    private function createSubtree(Page|BlockLayout $owner, string $parentId, int $position, array $input): array
    {
        $block = Block::create([
            'id' => 'blk_'.Str::random(10),
            $this->ownerColumn($owner) => $owner->id,
            'parent_id' => $parentId,
            'position' => $position,
            'type' => $input['type'],
            'props' => self::sanitizeRichText($input['type'], (array) ($input['props'] ?? [])),
            'style' => (array) ($input['style'] ?? []),
            'meta' => array_merge(['label' => config('blockkit.types.'.$input['type'].'.name', $input['type'])], (array) ($input['meta'] ?? [])),
        ]);
        $childIds = [];
        foreach (array_values((array) ($input['children'] ?? [])) as $i => $child) {
            $childIds[] = $this->createSubtree($owner, $block->id, $i, $child);
        }

        return ['id' => $block->id, 'children' => $childIds];
    }

    private function copySubtree(Page|BlockLayout $owner, Block $block, string $parentId, int $position): Block
    {
        $copy = Block::create([
            'id' => 'blk_'.Str::random(10),
            $this->ownerColumn($owner) => $owner->id,
            'parent_id' => $parentId,
            'position' => $position,
            'type' => $block->type,
            'props' => $block->props ?? [],
            'style' => $block->style ?? [],
            'meta' => array_merge($block->meta ?? [], ['label' => $block->label().' copy', 'locked' => false]),
        ]);
        foreach ($block->children as $i => $child) {
            $this->copySubtree($owner, $child, $copy->id, $i);
        }

        return $copy;
    }

    private function deleteSubtree(Page|BlockLayout $owner, Block $block): int
    {
        $count = 1;
        foreach ($block->children as $child) {
            $count += $this->deleteSubtree($owner, $child);
        }
        $block->delete();

        return $count;
    }

    private function resequence(Page|BlockLayout $owner, ?string $parentId): void
    {
        $ids = $this->scoped($owner)->where('parent_id', $parentId)->orderBy('position')->pluck('id')->all();
        foreach ($ids as $i => $id) {
            Block::whereKey($id)->update(['position' => $i]);
        }
    }

    private function assemble(Block $block, $byParent): array
    {
        return [
            'id' => $block->id,
            'type' => $block->type,
            'props' => $block->props ?? [],
            'style' => $block->style ?? [],
            'meta' => $block->meta ?? [],
            'children' => collect($byParent->get($block->id, collect()))
                ->map(fn ($c) => $this->assemble($c, $byParent))->values()->all(),
        ];
    }
}
