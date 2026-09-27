<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Services\DesignService;
use App\Services\TemplateCommerce;
use App\Support\CuratedTemplates;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Site › Design: the site's current template + a "Change template" picker
 * reading from the account's LIBRARY. Applying saves a restore point first;
 * the toast offers Undo (revert).
 */
class SiteDesignPage extends Component
{
    public Site $site;

    public bool $picking = false;

    public ?string $selectedId = null;

    public bool $canUndo = false;

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'addons.manage'), 403);
    }

    public function openPicker(): void
    {
        $this->picking = true;
        $this->selectedId = null;
    }

    public function closePicker(): void
    {
        $this->picking = false;
    }

    public function select(string $id): void
    {
        $this->selectedId = $id;
    }

    public function apply(DesignService $design): void
    {
        abort_if($this->selectedId === null, 422);
        $template = Template::where('status', 'published')->findOrFail($this->selectedId);
        $result = $design->apply(Auth::user(), $this->site, $template);
        $this->site->refresh();
        $this->picking = false;
        $this->canUndo = true;

        $msg = $result['applied'].' is now this site\'s design.';
        if ($result['features_enabled'] !== []) {
            $msg .= ' Turned on: '.implode(', ', $result['features_enabled']).'.';
        }
        $this->dispatch('toast', level: 'success', title: 'Template applied', message: $msg);
    }

    public function undo(DesignService $design): void
    {
        $design->revert(Auth::user(), $this->site);
        $this->site->refresh();
        $this->canUndo = false;
        $this->dispatch('toast', level: 'success', title: 'Restored', message: 'The site is back on its previous design.');
    }

    public function render(TemplateCommerce $commerce)
    {
        $user = Auth::user();
        $applied = $this->site->installedTemplates()->whereNotNull('applied_at')->with('template')->first();
        // Legacy sites bound by renderer key without a catalog row still show something.
        $current = $applied?->template
            ?? ($applied ? null : null);
        $curatedFallback = ! $applied && $this->site->template && $this->site->template !== 'blank'
            ? CuratedTemplates::find($this->site->template)
            : null;

        $libraryTemplates = TemplateEntitlement::with('template.creator')
            ->where('user_id', $user->id)->latest()->get()
            ->pluck('template')->filter()->values();

        $selected = $this->selectedId ? $libraryTemplates->firstWhere('id', $this->selectedId) : null;
        $wouldEnable = $selected
            ? collect((array) $selected->required_features)
                ->filter(fn ($k) => FeatureRegistry::exists($k) && ! $this->site->hasFeature($k))
                ->map(fn ($k) => FeatureRegistry::get($k)['name'] ?? ucfirst($k))->values()
            : collect();

        return view('livewire.site-design-page', [
            'applied' => $applied,
            'current' => $current,
            'curatedFallback' => $curatedFallback,
            'libraryTemplates' => $libraryTemplates,
            'selected' => $selected,
            'wouldEnable' => $wouldEnable,
        ]);
    }
}
