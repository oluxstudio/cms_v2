<?php

namespace App\Modules\Newsletter\Jobs;

use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Services\CampaignSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Every minute (routes/console.php): start each scheduled campaign that is
 * due. One that can't start (over the plan's allowance, empty audience)
 * goes back to draft with the reason shown on its card.
 */
class DispatchScheduledCampaigns implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(CampaignSender $sender): void
    {
        NewsletterCampaign::where('status', NewsletterCampaign::SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->each(function (NewsletterCampaign $campaign) use ($sender) {
                if ($error = $sender->start($campaign)) {
                    NewsletterCampaign::whereKey($campaign->id)->where('status', NewsletterCampaign::SCHEDULED)
                        ->update(['status' => NewsletterCampaign::DRAFT, 'error' => mb_substr('Scheduled send didn\'t go out: '.$error, 0, 250)]);
                }
            });
    }
}
