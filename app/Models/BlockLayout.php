<?php

namespace App\Models;

use App\Services\BlockTreeService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A layout IS a block tree it owns: blocks arranged around exactly ONE
 * `content_slot` placeholder, built in the Layout View with the same editor
 * pages use. Pages reference a layout (never copy it); at render time the
 * page's own tree splices into the slot. The per-site "Blank" layout — just
 * the content slot, zero other blocks — always exists, cannot be deleted or
 * edited, and is what a page with no explicit layout resolves to.
 */
class BlockLayout extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'name', 'slug', 'kind', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    /** Only real layouts (wrap pages around a content slot). */
    public function scopeLayouts($q)
    {
        return $q->where('kind', 'layout');
    }

    /** Only user-built components (reusable blocks stamped into pages). */
    public function scopeComponents($q)
    {
        return $q->where('kind', 'component');
    }

    public function isComponent(): bool
    {
        return $this->kind === 'component';
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'layout_id');
    }

    /** The site's built-in Blank layout: content slot only, undeletable. */
    public static function blank(Site $site): self
    {
        $layout = static::firstOrCreate(
            ['site_id' => $site->id, 'slug' => 'blank'],
            ['name' => 'Blank', 'is_system' => true],
        );
        // Seed its tree on first touch: root container → content_slot.
        $svc = app(BlockTreeService::class);
        $root = $svc->ensureRoot($layout);
        if (! $layout->blocks()->where('type', 'content_slot')->exists()) {
            Block::create([
                'id' => 'blk_'.Str::random(10),
                'layout_id' => $layout->id, 'parent_id' => $root->id, 'position' => 0,
                'type' => 'content_slot', 'props' => [], 'style' => [],
                'meta' => ['label' => 'Content section'],
            ]);
        }

        return $layout;
    }

    /** Create a user layout: starts as Blank does (root + slot), then design it. */
    public static function make(Site $site, string $name): self
    {
        $slug = Str::slug($name) ?: 'layout';
        $i = 2;
        while (static::where('site_id', $site->id)->where('slug', $slug)->exists()) {
            $slug = (Str::slug($name) ?: 'layout').'-'.$i++;
        }
        $layout = static::create(['site_id' => $site->id, 'name' => $name, 'slug' => $slug]);
        $svc = app(BlockTreeService::class);
        $root = $svc->ensureRoot($layout);
        Block::create([
            'id' => 'blk_'.Str::random(10),
            'layout_id' => $layout->id, 'parent_id' => $root->id, 'position' => 0,
            'type' => 'content_slot', 'props' => [], 'style' => [],
            'meta' => ['label' => 'Content section'],
        ]);

        return $layout;
    }

    /** The layout's single content_slot block. */
    public function contentSlot(): ?Block
    {
        return $this->blocks()->where('type', 'content_slot')->first();
    }

    /**
     * Create a user COMPONENT: a reusable block built from existing blocks
     * (e.g. a hero section). Same tree machinery, but no content_slot — its
     * tree is stamped into pages from the palette.
     */
    public static function makeComponent(Site $site, string $name): self
    {
        $slug = Str::slug($name) ?: 'component';
        $i = 2;
        while (static::where('site_id', $site->id)->where('slug', $slug)->exists()) {
            $slug = (Str::slug($name) ?: 'component').'-'.$i++;
        }
        $component = static::create(['site_id' => $site->id, 'name' => $name, 'slug' => $slug, 'kind' => 'component']);
        app(BlockTreeService::class)->ensureRoot($component);

        return $component;
    }
}
