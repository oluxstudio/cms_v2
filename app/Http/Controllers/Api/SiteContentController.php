<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Collection;
use App\Models\Form;
use App\Models\Media;
use App\Models\Page;
use App\Models\Site;
use App\Services\BlockTreeService;
use App\Support\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteContentController extends Controller
{
    /**
     * GET /api/sites/{siteName}/content
     * Full content tree for a site (all pages → components → nodes).
     * Used by the Vue/Nuxt templates for live preview and content.json generation.
     */
    public function show(string $siteName): JsonResponse
    {
        $site = Site::where('name', $siteName)
            ->firstOrFail();

        return response()->json([
            'site' => $this->siteMeta($site),
            'pages' => $site->livePages()->get()->map(fn ($page) => $this->pagePayload($page, $site))->values(),
            // Full site-wide sets so a client has everything to join by id.
            'collections' => $this->siteCollections($site),
            'forms' => $this->siteForms($site),
        ]);
    }

    /**
     * GET /api/sites/{siteName}/page?url=/about   (defaults to "/")
     * A single page's content tree.
     */
    public function page(Request $request, string $siteName): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        $url = '/'.ltrim($request->query('url', '/'), '/');

        $page = Page::where('site_id', $site->id)
            ->where('url', $url)
            ->firstOrFail();

        return response()->json([
            'site' => $this->siteMeta($site),
            'page' => $this->pagePayload($page, $site),
            'collections' => $this->siteCollections($site),
            'forms' => $this->siteForms($site),
        ]);
    }

    private function siteMeta(Site $site): array
    {
        return [
            'name' => $site->name,
            'domain' => $site->domain,
            'description' => $site->description,
            'theme' => $site->themeValues(),
            // EVERY site attribute (EAV) — templates read their config here.
            'attributes' => $site->attrMap(),
        ];
    }

    private function pagePayload(Page $page, Site $site): array
    {
        $components = $page->components()->with('nodes')->get();

        return [
            'name' => $page->name,
            'url' => $page->url,
            'keywords' => $page->keywords,
            'description' => $page->getAttr('description', ''),
            'attributes' => $page->attrMap(),
            // Classic components attached to this page (ordered), each with
            // ALL of its nodes (flat + nested tree) + linked collections.
            'components' => $components
                ->map(fn ($c) => $c->payload() + ['order' => (int) $c->pivot->order])
                ->values()->all(),
            // Collections on this page (attached + referenced), full with components.
            'collections' => $this->pageCollections($page, $components),
            // Forms this page uses (its BlockKit form blocks), full.
            'forms' => $this->pageForms($page, $site),
            'block_tree' => $this->blockTree($page, $site),
        ];
    }

    /**
     * Collections on this page: those directly ATTACHED to it (page_collection)
     * plus any referenced by its components' collection-nodes (back-compat),
     * deduped by id — each full, with its grouped components + items.
     */
    private function pageCollections(Page $page, $components): array
    {
        $attachedIds = $page->collections()->pluck('collections.id');
        $referencedIds = $components
            ->flatMap(fn ($c) => $c->nodes->where('type', 'collection')->pluck('value'))
            ->filter();

        $ids = $attachedIds->merge($referencedIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return Collection::whereIn('id', $ids)->with('items')->get()
            ->map(fn (Collection $c) => $c->toApiArray())->values()->all();
    }

    /** Full forms referenced by the page's BlockKit form blocks. */
    private function pageForms(Page $page, Site $site): array
    {
        $blockIds = Block::where('page_id', $page->id)->where('type', 'form')->pluck('id');
        if ($blockIds->isEmpty()) {
            return [];
        }
        $names = $blockIds->map(fn ($id) => 'blockkit-'.$id);

        return Form::where('site_id', $site->id)->whereIn('name', $names)->get()
            ->map(fn (Form $f) => $f->toApiArray())->values()->all();
    }

    private function siteCollections(Site $site): array
    {
        return $site->collections()->where('is_public', true)->with('items')->get()
            ->map(fn (Collection $c) => $c->toApiArray())->values()->all();
    }

    private function siteForms(Site $site): array
    {
        return $site->forms()->where('is_active', true)->get()
            ->map(fn (Form $f) => $f->toApiArray())->values()->all();
    }

    /**
     * The page's BlockKit tree (jigsaw model), or null when the page has no
     * blocks — the renderer then falls back to the wireframe sections. Image
     * asset refs (@media/…) are resolved to real URLs at payload time.
     */
    private function blockTree(Page $page, Site $site): ?array
    {
        // A page is a builder page as soon as it has a block ROOT (it was
        // opened in the builder) — from then on the builder is the source of
        // truth, INCLUDING when the canvas is empty: an empty build renders
        // as an empty page, never as leftover legacy wireframe content.
        if (! Block::where('page_id', $page->id)->exists()) {
            return null;
        }
        $tree = app(BlockTreeService::class)->tree($page);

        $composed = app(BlockTreeService::class)
            ->expandComponentRefs($this->composeBlockLayout($page, $tree), $site->id); // live-linked components

        // The PAGE ROOT is the device frame — full width, no default padding,
        // like <body>. Blocks narrow/center only when the user says so.
        $composed['props'] = array_merge(['max_width' => 'full', 'padding' => 'none'], (array) ($composed['props'] ?? []));

        return $this->resolveTreeMedia($composed, $site);
    }

    /**
     * Compose the page inside its layout: render the LAYOUT's own block tree
     * and splice the page's blocks in at the content_slot. What the Layout View
     * shows is exactly what pages render — same tree, same renderer, by
     * construction. Blank (root + slot only) composes to the bare page tree.
     */
    private function composeBlockLayout(Page $page, array $pageTree): array
    {
        $layout = $page->resolvedBlockLayout();
        $layoutTree = app(BlockTreeService::class)->tree($layout);

        $splice = function (array $node) use (&$splice, $pageTree) {
            if (($node['type'] ?? '') === 'content_slot') {
                return [
                    'id' => $node['id'],
                    'type' => 'container',
                    'props' => ['padding' => 'none', 'max_width' => 'full'],
                    'style' => $node['style'] ?? [],
                    'meta' => ['label' => 'Content section'],
                    'children' => $pageTree['children'] ?? [],
                ];
            }
            $node['children'] = array_map($splice, $node['children'] ?? []);

            return $node;
        };

        $composed = $splice($layoutTree);
        $composed['meta']['label'] = $layout->name.' layout';

        return $composed;
    }

    private function resolveTreeMedia(array $node, Site $site): array
    {
        // Rich text defense in depth: rows saved before sanitize-at-save get
        // cleaned at payload time too (the renderer v-htmls this value).
        if (in_array($node['type'] ?? '', ['header', 'content'], true) && isset($node['props']['content'])) {
            $node['props']['content'] = RichText::clean((string) $node['props']['content']);
        }
        // Container/panel background image: @media/… ref → served URL. Kept
        // ROOT-RELATIVE (/storage/…): url() would bake in the request's base
        // path (e.g. /nuxt-preview) and 404; a leading-slash url() in an
        // inline style resolves against the origin on every surface.
        if (! empty($node['props']['bg_image']) && str_starts_with((string) $node['props']['bg_image'], '@media/')) {
            $node['props']['bg_image'] = Media::resolveRef($site->id, (string) $node['props']['bg_image']);
        }
        if (($node['type'] ?? '') === 'image' && ! empty($node['props']['asset_id'])) {
            $node['props']['src'] = Media::resolveRef($site->id, (string) $node['props']['asset_id']);
        }
        // BlockKit media blocks (image OR video from the library): @media/… → served URL.
        if (($node['type'] ?? '') === 'media' && empty($node['props']['src']) && ! empty($node['props']['asset_id'])) {
            $resolved = Media::resolveRef($site->id, (string) $node['props']['asset_id']);
            if ($resolved !== '' && ! preg_match('~^https?://~', $resolved)) {
                $resolved = url($resolved);
            }
            $node['props']['src'] = $resolved;
        }
        $node['children'] = array_map(fn ($c) => $this->resolveTreeMedia($c, $site), $node['children'] ?? []);

        return $node;
    }
}
