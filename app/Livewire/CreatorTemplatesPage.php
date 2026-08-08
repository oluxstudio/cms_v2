<?php

namespace App\Livewire;

use App\Models\Template;
use App\Services\StripeConnect;
use App\Services\TemplateAnalytics;
use App\Services\TemplatePublisher;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Creator dashboard ("My Templates"): publish a .zip as a draft catalog template,
 * submit it for review, and — for moderators — approve/reject the review queue.
 */
class CreatorTemplatesPage extends Component
{
    use WithFileUploads;

    public $zip = null;

    public string $tName = '';

    public string $tCategory = 'Business';

    public string $tPrice = '0';          // dollars

    public string $err = '';

    public string $ok = '';

    // moderation
    public ?string $rejectingId = null;

    public string $rejectReason = '';

    private function publisher(): TemplatePublisher
    {
        return app(TemplatePublisher::class);
    }

    public function getIsModeratorProperty(): bool
    {
        return $this->publisher()->isModerator(Auth::user());
    }

    /** Whether marketplace payments are configured at all (platform Stripe keys set). */
    public function getPaymentsConfiguredProperty(): bool
    {
        return app(StripeConnect::class)->configured();
    }

    /** Whether this creator can sell paid templates (Connect onboarded + charges enabled). */
    public function getCanSellProperty(): bool
    {
        return app(StripeConnect::class)->canSell(Auth::user());
    }

    public function getMyTemplatesProperty()
    {
        return Template::where('user_id', Auth::id())->latest('id')->get();
    }

    /** Creator earnings totals (sales, gross, fees, net, installs). */
    public function getEarningsProperty(): array
    {
        return app(TemplateAnalytics::class)->creatorSummary(Auth::user());
    }

    /** Per-template analytics rows (installs, sales, revenue, rating). */
    public function getAnalyticsProperty()
    {
        return app(TemplateAnalytics::class)->perTemplate(Auth::user());
    }

    public function getReviewQueueProperty()
    {
        if (! $this->isModerator) {
            return collect();
        }

        return Template::with('user')->where('status', 'in_review')->latest('submitted_at')->get();
    }

    /** Publish an uploaded .zip as a new draft template owned by the current user. */
    public function publish(): void
    {
        $this->err = '';
        $this->ok = '';
        $this->validate(['zip' => ['required', 'file', 'max:30720']], ['zip.required' => 'Choose a .zip file.']);

        if (strtolower($this->zip->getClientOriginalExtension()) !== 'zip') {
            $this->err = 'The template must be a .zip file.';

            return;
        }

        $priceCents = (int) round(((float) $this->tPrice) * 100);
        if ($priceCents > 0 && ! $this->canSell) {
            $this->err = 'Connect payouts before publishing a paid template (or set the price to 0).';

            return;
        }

        try {
            $tpl = $this->publisher()->publishFromZip(Auth::user(), $this->zip->getRealPath(), [
                'name' => trim($this->tName),
                'category' => trim($this->tCategory),
                'price_cents' => (int) round(((float) $this->tPrice) * 100),
            ]);
        } catch (\Throwable $e) {
            $this->err = $e->getMessage();

            return;
        }

        $this->reset('zip', 'tName', 'tPrice');
        $this->ok = '“'.$tpl->name.'” created as a draft. Submit it for review when ready.';
    }

    public function submit(string $id): void
    {
        $tpl = Template::where('user_id', Auth::id())->find($id);
        if ($tpl) {
            $this->publisher()->submit($tpl);
            $this->ok = '“'.$tpl->name.'” submitted for review.';
        }
    }

    public function deleteTemplate(string $id): void
    {
        Template::where('user_id', Auth::id())->whereKey($id)->delete();
        $this->ok = 'Template deleted.';
    }

    public function approve(string $id): void
    {
        if (! $this->isModerator) {
            return;
        }
        $tpl = Template::find($id);
        if ($tpl) {
            $this->publisher()->approve($tpl);
            $this->ok = '“'.$tpl->name.'” approved and published.';
        }
    }

    public function reject(string $id): void
    {
        if (! $this->isModerator) {
            return;
        }
        $tpl = Template::find($id);
        if ($tpl) {
            $this->publisher()->reject($tpl, $this->rejectReason ?: 'Did not meet guidelines.');
            $this->rejectingId = null;
            $this->rejectReason = '';
            $this->ok = '“'.$tpl->name.'” rejected.';
        }
    }

    public function render()
    {
        return view('livewire.creator-templates-page');
    }
}
