<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Services\TemplateCatalog;
use App\Services\TemplateCommerce;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account-scoped JSON API for the template store + library. Session auth
 * (same pattern as /api/domains/check). Templates are account-level: a
 * library item covers every site the account owns (config templates.licence_scope).
 */
class MarketplaceApiController extends Controller
{
    /** GET /api/templates — paginated store listing with in_library flags. */
    public function index(Request $request, TemplateCatalog $catalog, TemplateCommerce $commerce): JsonResponse
    {
        $filters = [
            'search' => (string) $request->query('q', ''),
            'categories' => array_filter((array) $request->query('category', [])),
            'tags' => array_filter((array) $request->query('tag', [])),
            'creators' => array_filter((array) $request->query('creator', [])),
            'price' => in_array($request->query('price'), ['free', 'paid'], true) ? $request->query('price') : '',
        ];
        $sort = in_array($request->query('sort'), ['popular', 'newest', 'price_asc'], true) ? $request->query('sort') : 'popular';

        $page = $catalog->browse($filters, $sort);
        $owned = TemplateEntitlement::where('user_id', $request->user()->id)->pluck('template_id')->flip();

        return response()->json([
            'data' => collect($page->items())->map(fn (Template $t) => [
                'id' => $t->id, 'slug' => $t->slug, 'name' => $t->name,
                'short_description' => $t->short_description,
                'category' => $t->category, 'tags' => $t->tags,
                'creator' => $t->creator?->only(['id', 'name', 'slug']),
                'price_cents' => (int) $t->price_cents, 'currency' => $t->currency,
                'free' => $t->isFree(), 'thumbnail_url' => $t->thumbnail_url,
                'in_library' => $owned->has($t->id) || $t->user_id === $request->user()->id,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ]);
    }

    /** POST /api/templates/{template}/library — add a FREE template (idempotent). */
    public function addToLibrary(Request $request, Template $template, TemplateCommerce $commerce): JsonResponse
    {
        abort_unless($template->status === 'published' && ! $template->isPrivate(), 404);
        $item = $commerce->addFreeToLibrary($request->user(), $template);

        return response()->json(['in_library' => true, 'source' => $item->source]);
    }

    /** POST /api/templates/{template}/checkout — platform-account Stripe Checkout. */
    public function checkout(Request $request, Template $template, TemplateCommerce $commerce): JsonResponse
    {
        abort_unless($template->status === 'published' && ! $template->isPrivate(), 404);
        if ($commerce->inLibrary($request->user(), $template)) {
            return response()->json(['in_library' => true, 'url' => null]);
        }

        $siteName = (string) ($request->input('site') ?: $request->user()->sites()->latest('id')->value('name'));
        abort_if($siteName === '', 422, 'Create a site first.');

        return response()->json(['url' => $commerce->checkoutUrl(
            $request->user(), $template,
            route('marketplace.template', [$siteName, $template->slug]),
            $siteName,
        )]);
    }

    /** GET /api/library — the account's templates + which sites use each. */
    public function library(Request $request): JsonResponse
    {
        $filter = (string) $request->query('filter', 'all');
        $items = TemplateEntitlement::with(['template.creator'])
            ->where('user_id', $request->user()->id)->latest()->get();
        $items = match ($filter) {
            'purchased' => $items->where('source', 'purchase')->values(),
            'free' => $items->where('source', '!=', 'purchase')->values(),
            default => $items,
        };

        $sites = $request->user()->sites()->get(['id', 'name']);
        $usage = SiteTemplate::whereIn('site_id', $sites->pluck('id'))
            ->whereNotNull('applied_at')->get(['site_id', 'template_id', 'template_version_id'])
            ->groupBy('template_id');

        return response()->json(['data' => $items->map(function (TemplateEntitlement $e) use ($usage, $sites) {
            $t = $e->template;
            $used = collect($usage->get($t?->id) ?? []);

            return [
                'template' => $t?->only(['id', 'slug', 'name', 'category', 'thumbnail_url']),
                'creator' => $t?->creator?->name,
                'source' => $e->source, 'price_paid_cents' => $e->price_paid_cents,
                'purchased_at' => $e->purchased_at?->toIso8601String(),
                'used_on' => $used->map(fn ($u) => $sites->firstWhere('id', $u->site_id)?->name)->filter()->values(),
                'update_available' => $t && $used->contains(fn ($u) => $u->template_version_id !== null
                    && $t->latest_version_id !== null && $u->template_version_id < $t->latest_version_id),
            ];
        })->values()]);
    }
}
