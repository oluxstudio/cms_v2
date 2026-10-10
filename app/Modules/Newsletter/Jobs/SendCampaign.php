<?php

namespace App\Modules\Newsletter\Jobs;

use App\Models\Site;
use App\Models\Subscription;
use App\Modules\Newsletter\Mail\NewsletterCampaignMail;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterSend;
use App\Modules\Newsletter\Newsletter;
use App\Modules\Newsletter\Services\CampaignRenderer;
use App\Modules\Newsletter\Services\CampaignSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one batch of a campaign — one message per recipient, each with its
 * own tracking + unsubscribe link — then re-queues itself until every
 * recipient is done. Batches stop early on a time budget well inside the
 * queue timeout, so a slow SMTP relay never gets the worker killed mid-batch.
 */
class SendCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const BATCH = 100;

    /** Seconds of sending per job run (the job timeout leaves headroom above this). */
    public const BUDGET = 75;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(public string $campaignId) {}

    public function handle(CampaignRenderer $renderer): void
    {
        $campaign = NewsletterCampaign::find($this->campaignId);
        if (! $campaign || $campaign->status !== NewsletterCampaign::SENDING) {
            return;
        }
        $site = Site::find($campaign->site_id);
        if (! $site) {
            return;
        }

        $brand = Newsletter::brand($site);
        $base = $renderer->baseHtml($campaign);
        $started = microtime(true);

        $batch = NewsletterSend::where('campaign_id', $campaign->id)->where('status', 'queued')
            ->orderBy('id')->limit(self::BATCH)->get();
        $subs = Subscription::whereIn('id', $batch->pluck('subscription_id')->filter())->get(['id', 'status', 'token'])->keyBy('id');

        foreach ($batch as $send) {
            if (microtime(true) - $started > self::BUDGET) {
                break;
            }
            // Claim the row: a retried / duplicate job never double-sends.
            if (! NewsletterSend::whereKey($send->id)->where('status', 'queued')->update(['status' => 'sending'])) {
                continue;
            }
            $sub = $subs[$send->subscription_id] ?? null;
            if (! $sub || ! $sub->isActive()) {
                $send->forceFill(['status' => 'skipped', 'error' => 'No longer subscribed'])->save();

                continue;
            }

            try {
                Mail::to($send->email)->send(new NewsletterCampaignMail(
                    $brand, $campaign->subject, $campaign->preheader,
                    $renderer->personalise($base, $send->token),
                    CampaignSender::unsubscribeUrl($sub->token, $send->token),
                ));
                $send->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null])->save();
            } catch (\Throwable $e) {
                report($e);
                $send->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)])->save();
            }
        }

        $this->refreshCounts($campaign);

        if (NewsletterSend::where('campaign_id', $campaign->id)->where('status', 'queued')->exists()) {
            self::dispatch($campaign->id);

            return;
        }

        $campaign->forceFill(['status' => NewsletterCampaign::SENT, 'sent_at' => now()])->save();
    }

    private function refreshCounts(NewsletterCampaign $campaign): void
    {
        $by = NewsletterSend::where('campaign_id', $campaign->id)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $campaign->forceFill([
            'sent_count' => (int) ($by['sent'] ?? 0),
            'failed_count' => (int) ($by['failed'] ?? 0),
        ])->save();
    }
}
