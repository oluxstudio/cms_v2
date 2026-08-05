<?php

namespace App\Livewire;

use App\Models\Site;
use App\Templates\TemplateAppRegistry;
use App\Templates\TemplateContract;
use App\Templates\TemplateRegistry;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TemplatesPage extends Component
{
    // Gallery list (metadata only — loaded once in mount)
    public array $templates = [];

    // Full data for the selected template, including pages/components
    public array $selectedTemplate = [];

    public string $selectedKey = '';

    public bool $showModal = false;

    // Site creation form
    public string $siteName = '';

    public string $domain = '';

    public string $owner = '';

    public string $description = '';

    public function mount(): void
    {
        $this->templates = collect(TemplateRegistry::all())
            ->map(fn (TemplateContract $t) => [
                'key' => $t->key(),
                'name' => $t->name(),
                'description' => $t->description(),
                'category' => $t->category(),
                'gradientClass' => $t->gradientClass(),
                'accentColor' => $t->accentColor(),
                'pageCount' => count($t->pages()),
                'previewUrl' => $this->previewUrlFor($t->key()),
            ])
            ->values()
            ->toArray();
    }

    public function selectTemplate(string $key): void
    {
        $template = TemplateRegistry::find($key);
        if (! $template) {
            return;
        }

        $this->selectedKey = $key;

        // Build the full preview data — pages → components → node count
        $pages = collect($template->pages())->map(function (array $pageDef) {
            return [
                'name' => $pageDef['name'],
                'url' => $pageDef['url'],
                'components' => collect($pageDef['components'])->map(fn ($c) => [
                    'name' => $c['name'],
                    'description' => $c['description'] ?? '',
                    'nodeCount' => count($c['nodes']),
                ])->toArray(),
            ];
        })->toArray();

        $this->selectedTemplate = [
            'key' => $template->key(),
            'name' => $template->name(),
            'description' => $template->description(),
            'category' => $template->category(),
            'gradientClass' => $template->gradientClass(),
            'accentColor' => $template->accentColor(),
            'pages' => $pages,
            'previewUrl' => $this->previewUrlFor($template->key()),
        ];

        // Pre-fill owner from the authenticated user
        $user = auth()->user();
        $this->owner = $user?->name ?? $user?->email ?? '';

        $this->showModal = true;
    }

    /**
     * The template's live demo URL — its OWN published app rendering the authored
     * demo content (exactly what a site built from it will look like), or null
     * when no app preview has been built for the key.
     */
    private function previewUrlFor(string $key): ?string
    {
        return TemplateAppRegistry::exists($key) && file_exists(public_path("nuxt-preview/{$key}/index.html"))
            ? url("nuxt-preview/{$key}/")
            : null;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedKey = '';
        $this->selectedTemplate = [];
        $this->reset(['siteName', 'domain', 'owner', 'description']);
    }

    /** Create the site directly — pages are built with blocks in the builder. */
    public function generate(): mixed
    {
        $this->validate([
            'siteName' => 'required|min:2|max:60|unique:sites,name',
            'domain' => 'required|max:100',
            'owner' => 'required|max:100',
            'description' => 'nullable|max:500',
        ]);

        // Plan enforcement: same site-count limit as the Sites screen.
        $sub = Auth::user()->currentSubscription();
        $limits = $sub->tier()['limits'] ?? [];
        // array_key_exists, NOT ?? — a null limit means UNLIMITED (Enterprise).
        $limit = array_key_exists('sites', $limits) ? $limits['sites'] : 1;
        if ($limit !== null && Site::where('user_id', Auth::id())->count() >= $limit) {
            $this->addError('siteName', "Your {$sub->tier()['name']} plan allows {$limit} site(s) — upgrade to add more.");

            return null;
        }

        $site = Site::create([
            'name' => $this->siteName,
            'domain' => $this->domain,
            'owner' => $this->owner,
            'description' => $this->description,
            'template' => 'blank',
            'user_id' => Auth::id(),
        ]);
        $site->members()->syncWithoutDetaching([Auth::id() => ['role' => 'owner']]);
        $site->pages()->create(['name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);

        // Builder is hidden — new sites land on their dashboard.
        return redirect('/'.$site->name.'/dashboard');
    }

    public function render()
    {
        return view('livewire.templates-page');
    }
}
