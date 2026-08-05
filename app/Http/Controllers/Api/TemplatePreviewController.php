<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Support\BlockPayloadPresenter;
use App\Templates\ArrayTemplate;
use App\Templates\TemplateAppRegistry;
use App\Templates\TemplateContract;
use App\Templates\TemplateRegistry;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/templates/preview/{ref}
 *
 * Serves a template's design (pages + theme + css) in the SAME shape as
 * SiteContentController::show, so the nuxt-preview SPA can render a faithful
 * PREVIEW of a template before it is installed to any site (?template={ref}).
 *
 * {ref} resolves, in order, to: an installed SiteTemplate id → a published
 * catalog Template (by slug) → a built-in template key.
 */
class TemplatePreviewController extends Controller
{
    public function show(string $ref): JsonResponse
    {
        $resolved = $this->resolve($ref);
        if (! $resolved) {
            return response()->json(['error' => 'Template not found.'], 404);
        }

        [$name, $contract, $css] = $resolved;

        $theme = method_exists($contract, 'theme') ? ($contract->theme() ?: []) : [];
        if ($css !== '') {
            $theme['template_css'] = $css;
        }
        if (($url = $this->fontUrl($theme['font'] ?? null)) !== null) {
            $theme['font_url'] = $url;
        }

        $pages = collect($contract->pages())->map(fn ($p) => [
            'name' => $p['name'] ?? 'Page',
            'url' => '/'.ltrim($p['url'] ?? '/', '/'),
            'keywords' => $p['keywords'] ?? '',
            'wireframe' => collect($p['blocks'] ?? [])
                ->map(fn ($b) => BlockPayloadPresenter::fromArray(is_array($b) ? $b : [], $name))
                ->values()->all(),
            'components' => [],
        ])->values();

        return response()->json([
            'site' => ['name' => $name, 'theme' => $theme, 'preview' => true],
            'pages' => $pages,
        ]);
    }

    /**
     * @return array{0:string,1:TemplateContract,2:string}|null [name, contract, css]
     */
    private function resolve(string $ref): ?array
    {
        // 1. Installed template (uploaded/built-in) by id.
        if (ctype_digit($ref) && ($st = SiteTemplate::find((int) $ref)) && ($c = $st->toContract())) {
            $css = $st->template_version_id && $st->templateVersion
                ? (string) ($st->templateVersion->payload['css'] ?? '')
                : (string) ($st->payload['css'] ?? '');

            return [$st->name ?: $c->name(), $c, $css];
        }

        // 2. Published catalog template by slug.
        if ($tpl = Template::where('slug', $ref)->first()) {
            if ($c = $tpl->toContract()) {
                return [$tpl->name, $c, (string) ($tpl->latestVersion?->payload['css'] ?? '')];
            }
        }

        // 3. Curated template APP by key (file registry). Image nodes point at the
        //    template's own bundled assets (assets/images/*) and stay editable.
        if ($t = TemplateAppRegistry::find($ref)) {
            $m = $t['manifest'];
            if (! empty($m['pages'])) {
                $contract = new ArrayTemplate(
                    ['name' => $t['name']],
                    $m['pages'],
                    is_array($m['theme'] ?? null) ? $m['theme'] : [],
                );

                return [$t['name'], $contract, (string) ($m['css'] ?? '')];
            }
        }

        // 4. Built-in class template by key.
        if ($c = TemplateRegistry::find($ref)) {
            $css = method_exists($c, 'css') ? (string) $c->css() : '';

            return [$c->name(), $c, $css];
        }

        return null;
    }

    /** Google-Fonts stylesheet URL for a non-base font family, or null for Inter. */
    private function fontUrl(?string $family): ?string
    {
        if (! $family || strtolower($family) === 'inter') {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?family='
            .str_replace(' ', '+', $family).':wght@400;500;600;700;800&display=swap';
    }
}
