<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Models\Site;
use App\Models\Template;
use App\Services\TemplateCommerce;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

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
        return Template::with(['creator', 'versions' => fn ($q) => $q->latest()->limit(5)])
            ->where('status', 'published')->where('slug', $this->slug)->firstOrFail();
    }

    public function addToLibrary(TemplateCommerce $commerce): void
    {
        $commerce->addFreeToLibrary(Auth::user(), $this->template());
        $this->dispatch('toast', level: 'success', title: 'Added to your library', message: 'Use it on any of your sites from the Design page.');
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
            'inLibrary' => $commerce->inLibrary($user, $t),
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
