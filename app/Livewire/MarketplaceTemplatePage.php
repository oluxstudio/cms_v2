<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Models\Site;
use App\Models\Template;
use App\Services\DesignService;
use App\Services\TemplateCommerce;
use App\Support\TemplateAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Template detail — store page for one template, account-level. */
class MarketplaceTemplatePage extends Component
{
    public Site $site;

    public string $slug;

    public function mount(Site $site, string $slug): void
    {
        $this->site = $site;
        $this->slug = $slug;
    }

    public function template(): Template
    {
        $t = Template::with(['creator', 'versions' => fn ($q) => $q->latest()->limit(5)])
            ->whereIn('status', ['published', 'private'])->where('slug', $this->slug)->first();
        // Private: only for the accounts it's assigned to — everyone else gets a plain 404.
        abort_unless($t && TemplateAccess::canSee(Auth::user(), $t, $this->site), 404);

        return $t;
    }

    public function addToLibrary(TemplateCommerce $commerce): void
    {
        $commerce->addFreeToLibrary(Auth::user(), $this->template());
        $this->dispatch('toast', level: 'success', title: 'Added to your library', message: 'Use it on any of your sites from the Design page.');
    }

    /** Apply this template to the site the visitor came from (restore point kept by DesignService). */
    public function useOnThisSite(DesignService $design)
    {
        $t = $this->template();
        if (! $this->canChangeDesign()) {
            abort(403);
        }
        try {
            $design->apply(Auth::user(), $this->site, $t);
        } catch (HttpException $e) {
            $this->dispatch('toast', level: 'error', title: 'Couldn\'t apply it', message: $e->getMessage() ?: 'Add it to your library first.');

            return null;
        }
        session()->flash('toast', ['level' => 'success', 'title' => 'Design applied', 'message' => Str::headline($this->site->name).' now uses '.$t->name.'. Your previous design can be restored from the Design page.']);

        return $this->redirect(url($this->site->name.'/connect'));
    }

    private function canChangeDesign(): bool
    {
        return $this->site->allows(Auth::user(), 'addons.manage') || $this->site->canManageTeam(Auth::user());
    }

    public function buy(TemplateCommerce $commerce): void
    {
        $t = $this->template();
        if ($commerce->inLibrary(Auth::user(), $t)) {
            return;
        }
        $this->redirect($commerce->checkoutUrl(Auth::user(), $t, url()->full(), $this->site->name));
    }

    public function render(TemplateCommerce $commerce)
    {
        $t = $this->template();
        $user = Auth::user();
        // Feature comparison uses the site you came from.
        $refSite = $this->site;

        // What's included — read from the latest version's payload.
        $payload = $t->versions->first()?->payload;
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        $included = [
            'pages' => collect($payload['pages'] ?? [])->pluck('name')->filter()->values(),
            'fonts' => collect($payload['fonts'] ?? [])->count(),
            'has_theme' => ! empty($payload['theme']),
        ];

        return view('livewire.marketplace-template-page', [
            'template' => $t,
            'included' => $included,
            'inLibrary' => $commerce->inLibrary($user, $t) || ($this->site->user && $commerce->inLibrary($this->site->user, $t)),
            'canUse' => $this->canChangeDesign(),
            'usedHere' => $this->site->installedTemplates()->whereNotNull('applied_at')->where('template_id', $t->id)->exists(),
            'justAdded' => (bool) session('mp-added'),
            'refSite' => $refSite,
            'requiredFeatures' => collect((array) $t->required_features)
                ->filter(fn ($k) => FeatureRegistry::exists($k))
                ->map(fn ($k) => [
                    'key' => $k,
                    'name' => FeatureRegistry::get($k)['name'] ?? ucfirst($k),
                    'has' => $refSite?->hasFeature($k) ?? false,
                ])->values(),
            'moreFromCreator' => $t->creator
                ? Template::where('status', 'published')->where('creator_id', $t->creator_id)
                    ->where('id', '!=', $t->id)->limit(4)->get()
                : collect(),
            'licence' => config('templates.licence_scope') === 'account'
                ? 'One licence covers every site in your account.'
                : 'Licensed per site.',
        ]);
    }
}
