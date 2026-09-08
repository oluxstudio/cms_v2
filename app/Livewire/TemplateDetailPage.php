<?php

namespace App\Livewire;

use App\Models\Site;
use App\Models\Template;
use App\Services\TemplateCommerce;
use App\Services\TemplateInstaller;
use App\Support\TemplateCards;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Public product page for one template: description, "View template" (live
 * preview) and "Get template" — guests are sent to log in and come straight
 * back; free templates save to a site; priced ones go to the buy page.
 */
class TemplateDetailPage extends Component
{
    public string $urlKey = '';

    public array $card = [];

    public ?string $templateId = null;

    public string $message = '';

    public function mount(string $templateKey): void
    {
        $this->urlKey = $templateKey;
        $resolved = TemplateCards::resolve($templateKey);
        abort_unless((bool) $resolved, 404);
        [$this->card, $tpl] = $resolved;
        $this->templateId = $tpl?->id;
    }

    /**
     * Use template = one click: a brand-new site is created from the design
     * and the browser lands on its /connect page immediately, while a
     * background job scaffolds the pages, components, forms and modules.
     */
    public function getTemplate()
    {
        $user = Auth::user();

        // Guests log in (or register) and land straight back here.
        if (! $user) {
            session(['url.intended' => route('template.detail', $this->urlKey)]);

            return $this->redirect(route('login', ['mode' => 'register']));
        }

        // Priced and not yet owned → the payment page.
        if ($this->templateId && $this->card['priceCents'] > 0
            && ! app(TemplateCommerce::class)->entitled($user, Template::find($this->templateId))) {
            return $this->redirect(route('template.buy', $this->urlKey));
        }

        if (! $user->currentSubscription()->canCreateSite()) {
            $this->message = 'Your plan has no room for another site — upgrade, or apply this design to an existing site from its My Designs page.';

            return;
        }

        $installer = app(TemplateInstaller::class);
        $site = $installer->createSiteFrom($user, $this->card);

        try {
            $result = $this->templateId
                ? $installer->saveCatalogToSite($user, $site, Template::findOrFail($this->templateId))
                : $installer->saveCuratedToSite($site, $this->card['builtin']);
        } catch (\Throwable $e) {
            $site->delete();
            $this->message = $e->getMessage();

            return;
        }
        if (is_string($result)) {
            $site->delete(); // don't leave an empty site behind a Stripe detour

            return $this->redirect($result);
        }

        $installer->applyAsync($site, $result);

        return $this->redirect(url($site->name.'/connect'));
    }

    public function render()
    {
        return view('livewire.template-detail-page');
    }
}
