<?php

namespace App\Modules\Newsletter\Services;

use App\Models\Site;
use App\Modules\Newsletter\Jobs\SendCampaign;
use App\Modules\Newsletter\Mail\NewsletterCampaignMail;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Newsletter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Starts a campaign: checks audience + plan allowance, snapshots recipients, queues the batch job. */
class CampaignSender
{
    /** @return string|null null when sending started, else why it didn't. */
    public function start(NewsletterCampaign $campaign): ?string
    {
        if (! $campaign->isEditable()) {
            return 'This campaign has already been sent.';
        }
        if (trim($campaign->subject) === '' || trim(strip_tags((string) $campaign->body, '<img>')) === '') {
            return 'Add a subject and some content before sending.';
        }

        $count = $campaign->audience()->count();
        if ($count === 0) {
            return $campaign->audience_tag
                ? 'Nobody subscribed carries the tag “'.$campaign->audience_tag.'”.'
                : 'There are no subscribed addresses to send to yet.';
        }

        $site = Site::find($campaign->site_id);
        if ($limitError = SendQuota::check($site, $count)) {
            return $limitError;
        }

        // Claim it atomically so a double click / the scheduler can't start it twice.
        $claimed = NewsletterCampaign::whereKey($campaign->id)
            ->whereIn('status', [NewsletterCampaign::DRAFT, NewsletterCampaign::SCHEDULED])
            ->update(['status' => NewsletterCampaign::SENDING, 'started_at' => now(), 'error' => null]);
        if (! $claimed) {
            return 'This campaign is already sending.';
        }

        // Snapshot the audience now: later signups don't join a running send.
        $now = now();
        $total = 0;
        $campaign->audience()->select(['id', 'email'])->chunkById(1000, function ($subs) use ($campaign, $now, &$total) {
            DB::table('newsletter_sends')->insert($subs->map(fn ($s) => [
                'id' => (string) Str::ulid(),
                'campaign_id' => $campaign->id,
                'site_id' => $campaign->site_id,
                'subscription_id' => $s->id,
                'email' => $s->email,
                'token' => Str::random(40),
                'status' => 'queued',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
            $total += $subs->count();
        });

        NewsletterCampaign::whereKey($campaign->id)->update(['recipients_count' => $total]);
        $campaign->refresh();

        SendCampaign::dispatch($campaign->id);

        return null;
    }

    /** A one-off copy to the author: no tracking, no live unsubscribe link, not counted. */
    public function sendTest(NewsletterCampaign $campaign, string $email): void
    {
        $site = Site::findOrFail($campaign->site_id);
        $html = app(CampaignRenderer::class)->baseHtml($campaign);

        Mail::to($email)->send(new NewsletterCampaignMail(
            Newsletter::brand($site), $campaign->subject ?: '(no subject)', $campaign->preheader, $html, null, true,
        ));
    }

    public static function unsubscribeUrl(string $subscriberToken, ?string $sendToken = null): string
    {
        return route('newsletter.unsubscribe', array_filter(['token' => $subscriberToken, 'c' => $sendToken]));
    }
}
