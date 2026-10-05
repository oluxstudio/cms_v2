<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateCreator;
use App\Models\User;
use App\Templates\TemplateAppRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turn an existing site into a new catalog template (a draft by Olux Studio):
 * its live pages → sections → fields and its theme become version 1.0.0, and
 * the site's current template app is kept as the renderer, so a site that
 * applies it looks the same.
 */
class SiteToTemplate
{
    public function create(Site $site, User $by, string $name, string $visibility = 'public'): Template
    {
        $renderer = $site->renderTemplateKey();
        $pages = $site->livePages()->orderBy('created_at')->get()->map(fn ($page) => [
            'name' => $page->name,
            'url' => $page->url,
            'keywords' => (string) $page->keywords,
            'attributes' => $page->attrMap(),
            // Same page shape TemplateScaffolder::applyPages reads (url/name/attributes/blocks[nodes]).
            'blocks' => $page->activeComponents()->with('nodes')->orderBy('page_component.order')->get()->map(fn ($c) => [
                'name' => $c->name,
                'description' => (string) $c->description,
                'nodes' => $c->nodes->sortBy('order')->map(fn ($n) => [
                    'label' => $n->label,
                    'type' => $n->type,
                    // "@media/…" refs point into THIS site's library — make them real URLs.
                    'value' => str_starts_with((string) $n->value, '@media/') ? url(Media::resolveRef($site->id, (string) $n->value)) : (string) $n->value,
                    'order' => (int) $n->order,
                    'description' => (string) $n->description,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();

        return DB::transaction(function () use ($site, $by, $name, $visibility, $renderer, $pages) {
            $creator = TemplateCreator::firstOrCreate(['slug' => 'olux-studio'], ['name' => 'Olux Studio']);
            $base = Str::slug($name) ?: 'template';
            $slug = $base;
            for ($i = 2; Template::where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }

            $t = Template::create([
                'uuid' => (string) Str::uuid(),
                'creator_id' => $creator->id,
                'user_id' => null,
                'name' => $name,
                'slug' => $slug,
                'description' => 'Made from the '.Str::headline($site->name).' site.',
                'status' => 'draft',
                'visibility' => in_array($visibility, ['public', 'private'], true) ? $visibility : 'public',
                'price_cents' => 0,
                'currency' => 'gbp',
                'source' => 'builtin',
                // The renderer app that draws it (graceway, hairco…); blank = the platform renderer.
                'builtin_key' => $renderer !== TemplateAppRegistry::BLANK ? $renderer : null,
            ]);
            $v = $t->versions()->create([
                'version' => '1.0.0',
                'status' => 'published',
                'manifest' => ['name' => $name, 'renderer' => $renderer, 'author' => 'Olux Studio', 'source_site' => $site->name],
                'payload' => ['theme' => is_array($site->theme) ? $site->theme : [], 'fonts' => [], 'css' => '', 'pages' => $pages],
            ]);
            $t->update(['latest_version_id' => $v->id]);

            AccountActivity::record($by->id, 'template.from_site', 'Template "'.$name.'" made from site '.$site->name,
                ['actor_id' => $by->id, 'category' => 'templates', 'icon' => 'template', 'meta' => ['template_id' => $t->id, 'site_id' => $site->id]]);

            return $t;
        });
    }
}
