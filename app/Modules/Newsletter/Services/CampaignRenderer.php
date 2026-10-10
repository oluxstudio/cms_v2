<?php

namespace App\Modules\Newsletter\Services;

use App\Models\Media;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Services\SiteConnect\HtmlSanitizer;
use Illuminate\Support\Facades\URL;

/**
 * Campaign body → email-ready HTML. The editor stores library images as
 * "@media/…" refs; mail clients need absolute URLs, so refs are resolved and
 * root-relative src/href made absolute. Per recipient, http(s) links are
 * rewritten through the signed click-tracking route and an open pixel added.
 */
class CampaignRenderer
{
    /** Sanitised body with absolute asset URLs (shared by every recipient). */
    public function baseHtml(NewsletterCampaign $campaign): string
    {
        $html = Media::resolveHtml($campaign->site_id, (string) $campaign->body);
        $html = preg_replace_callback('/((?:src|href)=["\'])(\/(?!\/)[^"\']*)/i', fn ($m) => $m[1].url($m[2]), $html) ?? $html;
        $html = app(HtmlSanitizer::class)->html($html);

        // Email clients ignore stylesheets: give images a safe inline width.
        return preg_replace('/<img(?![^>]*style=)/i', '<img style="max-width:100%;height:auto;border-radius:10px;"', $html) ?? $html;
    }

    /** Recipient copy: tracked links + open pixel. */
    public function personalise(string $html, string $sendToken): string
    {
        $html = preg_replace_callback('/(<a\b[^>]*\bhref=)(["\'])(https?:\/\/[^"\']+)\2/i', function ($m) use ($sendToken) {
            $target = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5);

            return $m[1].$m[2].e(self::clickUrl($sendToken, $target)).$m[2];
        }, $html) ?? $html;

        return $html.'<img src="'.e(route('newsletter.open', $sendToken)).'" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;opacity:0;">';
    }

    public static function clickUrl(string $sendToken, string $target): string
    {
        return URL::signedRoute('newsletter.click', ['send' => $sendToken, 'u' => $target]);
    }
}
