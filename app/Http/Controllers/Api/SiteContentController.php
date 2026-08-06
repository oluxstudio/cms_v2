<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Site;
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
            'site'  => $this->siteMeta($site),
            'pages' => $site->livePages()->get()->map(fn ($page) => $this->pagePayload($page, $site))->values(),
        ]);
    }

    /**
     * GET /api/sites/{siteName}/page?url=/about   (defaults to "/")
     * A single page's content tree.
     */
    public function page(Request $request, string $siteName): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        $url = '/' . ltrim($request->query('url', '/'), '/');

        $page = Page::where('site_id', $site->id)
            ->where('url', $url)
            ->firstOrFail();

        return response()->json([
            'site' => $this->siteMeta($site),
            'page' => $this->pagePayload($page, $site),
        ]);
    }

    private function siteMeta(Site $site): array
    {
        return [
            'name'        => $site->name,
            'domain'      => $site->domain,
            'description' => $site->description,
            'theme'       => $site->themeValues(),
            // EVERY site attribute (EAV) — templates read their config here.
            'attributes'  => $site->attrMap(),
        ];
    }

    private function pagePayload(Page $page, Site $site): array
    {
        return [
            'name'        => $page->name,
            'url'         => $page->url,
            'keywords'    => $page->keywords,
            'description' => $page->getAttr('description', ''),
            'attributes'  => $page->attrMap(),
            // Classic components attached to this page (ordered), each with
            // ALL of its nodes + linked collections.
            'components'  => $page->components()->with('nodes')->get()
                ->map(fn ($c) => $c->payload() + ['order' => (int) $c->pivot->order])
                ->values()->all(),
            'block_tree'  => $this->blockTree($page, $site),
        ];
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
        if (! \App\Models\Block::where('page_id', $page->id)->exists()) {
            return null;
        }
        $tree = app(\App\Services\BlockTreeService::class)->tree($page);

        $composed = app(\App\Services\BlockTreeService::class)
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
        $layoutTree = app(\App\Services\BlockTreeService::class)->tree($layout);

        $splice = function (array $node) use (&$splice, $pageTree) {
            if (($node['type'] ?? '') === 'content_slot') {
                return [
                    'id'       => $node['id'],
                    'type'     => 'container',
                    'props'    => ['padding' => 'none', 'max_width' => 'full'],
                    'style'    => $node['style'] ?? [],
                    'meta'     => ['label' => 'Content section'],
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
            $node['props']['content'] = \App\Support\RichText::clean((string) $node['props']['content']);
        }
        // Container/panel background image: @media/… ref → served URL. Kept
        // ROOT-RELATIVE (/storage/…): url() would bake in the request's base
        // path (e.g. /nuxt-preview) and 404; a leading-slash url() in an
        // inline style resolves against the origin on every surface.
        if (! empty($node['props']['bg_image']) && str_starts_with((string) $node['props']['bg_image'], '@media/')) {
            $node['props']['bg_image'] = \App\Models\Media::resolveRef($site->id, (string) $node['props']['bg_image']);
        }
        if (($node['type'] ?? '') === 'image' && ! empty($node['props']['asset_id'])) {
            $node['props']['src'] = \App\Models\Media::resolveRef($site->id, (string) $node['props']['asset_id']);
        }
        // BlockKit media blocks (image OR video from the library): @media/… → served URL.
        if (($node['type'] ?? '') === 'media' && empty($node['props']['src']) && ! empty($node['props']['asset_id'])) {
            $resolved = \App\Models\Media::resolveRef($site->id, (string) $node['props']['asset_id']);
            if ($resolved !== '' && ! preg_match('~^https?://~', $resolved)) {
                $resolved = url($resolved);
            }
            $node['props']['src'] = $resolved;
        }
        $node['children'] = array_map(fn ($c) => $this->resolveTreeMedia($c, $site), $node['children'] ?? []);

        return $node;
    }
}
