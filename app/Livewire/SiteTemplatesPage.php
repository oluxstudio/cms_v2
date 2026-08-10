<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithCuratedTemplates;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Templates\TemplateAppRegistry;
use App\Templates\TemplateContract;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SiteTemplatesPage extends Component
{
    use InteractsWithCuratedTemplates;

    public Site $site;

    /** Gallery card metadata (all templates) */
    public array $templates = [];

    /** Full metadata of the currently viewed template */
    public array $selectedTemplate = [];

    /** Key of the currently selected template */
    public string $selectedKey = '';

    /** 'gallery' | 'detail' | 'success' */
    public string $mode = 'gallery';

    /** Folder name of the generated site (after generation) */
    public string $generatedFolder = '';

    // ─────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->loadInstalled();
    }

    /** Load the templates installed to this site (from the Marketplace). */
    private function loadInstalled(): void
    {
        $this->templates = $this->site->installedTemplates()->get()
            ->map(fn ($it) => $this->installedToArray($it))
            ->values()
            ->all();
    }

    /** The installed SiteTemplate whose id matches the gallery card key, or null. */
    private function installedFor(string $key): ?SiteTemplate
    {
        return $this->site->installedTemplates()->whereKey((int) $key)->first();
    }

    /** Card/detail array for an installed template (keyed by its SiteTemplate id). */
    private function installedToArray(SiteTemplate $it): array
    {
        $c = $it->toContract();
        $pages = $c ? $c->pages() : ($it->payload['pages'] ?? []);

        return [
            'key' => (string) $it->id,
            'name' => $it->name,
            'description' => (string) $it->description,
            'category' => (string) $it->category,
            'gradientClass' => $it->gradient_class ?: 'from-slate-400 to-slate-600',
            'thumbnail' => $it->thumbnailUrl(),
            'accentColor' => $it->accent_color ?: '#6366f1',
            'author' => $c?->author() ?? 'Imported',
            'version' => $c?->version() ?? '1.0.0',
            'createdAt' => $c?->createdAt() ?? optional($it->created_at)->toDateString() ?? '2026-01-01',
            'tags' => $c?->tags() ?? [],
            'features' => $c?->features() ?? [],
            'source' => $it->source,
            'builtinKey' => $it->builtin_key,
            'applied' => $it->isApplied(),
            'pageCount' => count($pages),
            'componentCount' => collect($pages)->sum(fn ($p) => count($p['blocks'] ?? $p['components'] ?? [])),
            'pages' => collect($pages)->map(fn ($p) => [
                'name' => $p['name'] ?? 'Page',
                'url' => $p['url'] ?? '/',
                'componentCount' => count($p['blocks'] ?? $p['components'] ?? []),
            ])->toArray(),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Gallery → Detail
    // ─────────────────────────────────────────────────────────────

    /**
     * Show the detail screen for a template.
     * Called when the user clicks a gallery card.
     */
    public function showDetail(string $key): void
    {
        $it = $this->installedFor($key);
        if (! $it) {
            return;
        }

        $this->selectedKey = $key;
        $this->selectedTemplate = $this->installedToArray($it);
        $this->mode = 'detail';
    }

    public function backToGallery(): void
    {
        $this->mode = 'gallery';
        $this->selectedKey = '';
        $this->selectedTemplate = [];
        $this->generatedFolder = '';
    }

    // ─────────────────────────────────────────────────────────────
    // Preview (opens new window via Alpine)
    // ─────────────────────────────────────────────────────────────

    /**
     * Dispatch a browser event that Alpine picks up to open the Vue.js template preview.
     * Can be called from the detail view or from the gallery card directly.
     */
    public function previewTemplate(string $key): void
    {
        $it = $this->installedFor($key);
        if (! $it) {
            return;
        }

        $this->selectedKey = $key;

        // Live, rendered preview using the template's OWN app when one exists (so the
        // preview is EXACTLY what publishes — markup, colours, hover, animations);
        // otherwise the generic renderer. The app fetches demo content via ?template=id.
        $appKey = $this->appKeyFor($it);
        $base = $appKey === TemplateAppRegistry::BLANK ? 'nuxt-preview/' : "nuxt-preview/{$appKey}/";
        $previewUrl = url($base).'?template='.urlencode((string) $it->id);

        $this->dispatch('open-preview', url: $previewUrl);
    }

    /** The template app key that renders this installed template (its real design), or "blank". */
    private function appKeyFor(SiteTemplate $it): string
    {
        foreach ([$it->builtin_key, Str::slug((string) $it->name)] as $k) {
            if ($k && TemplateAppRegistry::exists($k)) {
                return $k;
            }
        }

        return TemplateAppRegistry::BLANK;
    }

    // Curated template browse/preview/use lives in InteractsWithCuratedTemplates.

    // ─────────────────────────────────────────────────────────────
    // Generate
    // ─────────────────────────────────────────────────────────────

    public function generate(): void
    {
        $this->dispatch('toast', level: 'error', title: 'Unavailable', message: 'Legacy template installing was removed — build with blocks and save your own templates in the builder.');
    }

    // ─────────────────────────────────────────────────────────────
    // Apply — scaffold the template's pages / components / nodes into THIS site
    // ─────────────────────────────────────────────────────────────

    public int $appliedPages = 0;

    public int $appliedComponents = 0;

    /** Use (apply) the template — APPENDS its pages, theme, font & assets to the site. */
    public function applyTemplate()
    {
        $this->dispatch('toast', level: 'error', title: 'Unavailable', message: 'Legacy template installing was removed — build with blocks and save your own templates in the builder.');

        return null;
    }

    /** Stop using the template — removes exactly what it added; defaults remain. */
    public function stopUsing(): void
    {
        $this->dispatch('toast', level: 'error', title: 'Unavailable', message: 'Legacy template installing was removed — build with blocks and save your own templates in the builder.');
    }

    // ─────────────────────────────────────────────────────────────
    // GitHub export (connect a PAT, then push the static bundle)
    // ─────────────────────────────────────────────────────────────

    public string $ghOwner = '';

    public string $ghRepo = '';

    public string $ghToken = '';

    public string $ghError = '';

    public bool $ghPushing = false;

    public array $ghResult = [];   // ['repo_url','pages_url','commit'] after a push

    #[Computed]
    public function github()
    {
        return $this->site->githubSettings;
    }

    private function canManage(): bool
    {
        return $this->site->canManageTeam(auth()->user());
    }

    /** Save + validate a Personal Access Token and repo target. */
    public function connectGithub(): void
    {
        $this->dispatch('toast', level: 'error', title: 'Unavailable', message: 'Legacy template installing was removed — build with blocks and save your own templates in the builder.');
    }

    public function disconnectGithub(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $this->site->githubSettings()->delete();
        $this->ghResult = [];
        unset($this->github);
    }

    /** Build the static bundle and push it to the connected repo. */
    public function pushToGithub(): void
    {
        $this->dispatch('toast', level: 'error', title: 'Unavailable', message: 'Legacy template installing was removed — build with blocks and save your own templates in the builder.');
    }

    // ─────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.site-templates-page');
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Screenshot thumbnail URL for a template's gallery card. Priority:
     *   1. an explicit thumbnail() method on the template, else
     *   2. an image dropped at public/template-thumbnails/{key}.{png|jpg|jpeg|webp|avif}.
     * Returns null when there's no screenshot — the card falls back to the gradient.
     */
    private function thumbnailFor(TemplateContract $t): ?string
    {
        if (method_exists($t, 'thumbnail') && ($explicit = $t->thumbnail())) {
            return $explicit;
        }

        foreach (['png', 'jpg', 'jpeg', 'webp', 'avif', 'svg'] as $ext) {
            $rel = "template-thumbnails/{$t->key()}.{$ext}";
            if (is_file(public_path($rel))) {
                return asset($rel);
            }
        }

        return null;
    }

    private function templateToArray(TemplateContract $t): array
    {
        $pages = $t->pages();

        return [
            'key' => $t->key(),
            'name' => $t->name(),
            'description' => $t->description(),
            'category' => $t->category(),
            'gradientClass' => $t->gradientClass(),
            'thumbnail' => $this->thumbnailFor($t),
            'accentColor' => $t->accentColor(),
            'author' => $t->author(),
            'version' => $t->version(),
            'createdAt' => $t->createdAt(),
            'tags' => $t->tags(),
            'features' => $t->features(),
            'pageCount' => count($pages),
            'componentCount' => collect($pages)->sum(fn ($p) => count($p['blocks'] ?? $p['components'] ?? [])),
            'pages' => collect($pages)->map(fn ($p) => [
                'name' => $p['name'],
                'url' => $p['url'],
                'componentCount' => count($p['blocks'] ?? $p['components'] ?? []),
            ])->toArray(),
        ];
    }
}
