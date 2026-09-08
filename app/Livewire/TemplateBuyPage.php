<?php

namespace App\Livewire;

use App\Models\Site;
use App\Models\Template;
use App\Services\TemplateCommerce;
use App\Services\TemplateInstaller;
use App\Support\TemplateCards;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The payment page for a priced template: what you're buying, the price,
 * which site it lands on, then off to Stripe Checkout.
 */
class TemplateBuyPage extends Component
{
    public string $urlKey = '';

    public array $card = [];

    public string $templateId = '';

    public string $siteId = '';

    public string $errorMessage = '';

    public function mount(string $templateKey): void
    {
        $this->urlKey = $templateKey;
        $resolved = TemplateCards::resolve($templateKey);
        abort_unless((bool) $resolved && $resolved[1] !== null, 404);
        [$this->card, $tpl] = $resolved;
        $this->templateId = $tpl->id;

        // Free or already owned → nothing to pay for.
        if ($this->card['priceCents'] <= 0 || app(TemplateCommerce::class)->entitled(Auth::user(), $tpl)) {
            $this->redirect(route('template.detail', $templateKey));

            return;
        }
        $this->siteId = (string) (Site::where('user_id', Auth::id())->orderBy('name')->value('id') ?? '');
    }

    #[Computed]
    public function mySites(): array
    {
        return Site::where('user_id', Auth::id())->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all();
    }

    public function buyNow(TemplateInstaller $installer)
    {
        $this->errorMessage = '';
        $site = Site::where('user_id', Auth::id())->find($this->siteId);
        if (! $site) {
            $this->errorMessage = 'Pick which of your sites gets this template.';

            return;
        }

        try {
            $result = $installer->saveCatalogToSite(Auth::user(), $site, Template::findOrFail($this->templateId));
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        // Unentitled + paid → a Stripe URL; anything else means it's already theirs.
        return is_string($result)
            ? $this->redirect($result)
            : $this->redirect(url($site->name.'/designs'));
    }

    public function render()
    {
        return view('livewire.template-buy-page');
    }
}
