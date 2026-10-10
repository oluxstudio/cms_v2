<?php

namespace App\Modules\Reviews;

use App\Models\Media;
use App\Models\Site;
use App\Modules\Reviews\Mail\NewReviewNotification;
use App\Modules\Reviews\Mail\ReviewRequestMail;
use App\Modules\Reviews\Models\Review;
use App\Modules\Reviews\Models\ReviewRequest;
use App\Support\SiteProperties;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Reviews & Testimonials — the module's one service: moderation rules
 * (auto-publish threshold), the public aggregate + schema.org JSON-LD,
 * request/reminder emails and owner notifications.
 */
class ReviewService
{
    public const FEATURE = 'reviews';

    public const REMINDER_AFTER_DAYS = 5;

    // ── Settings ───────────────────────────────────────────────────

    /** Minimum stars that publish without review, or null ("never"). */
    public static function autoPublishMin(Site $site): ?int
    {
        $v = (string) ($site->feature(self::FEATURE)['auto_publish_min_stars'] ?? 'never');

        return in_array($v, ['3', '4', '5'], true) ? (int) $v : null;
    }

    public static function requestMessage(Site $site): string
    {
        $msg = trim((string) ($site->feature(self::FEATURE)['request_message'] ?? ''));

        return $msg !== '' ? $msg : 'Thanks for choosing us — would you leave a quick review?';
    }

    public static function siteName(Site $site): string
    {
        return SiteProperties::value($site, 'site_name') ?: Str::headline($site->name);
    }

    // ── Writing ────────────────────────────────────────────────────

    /** Plain text only: tags stripped, whitespace tidied, length capped. */
    public static function clean(?string $value, int $max): ?string
    {
        $v = trim(preg_replace("/[ \t]+/u", ' ', strip_tags((string) $value)) ?? '');
        $v = preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $v)) ?? $v;

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    public static function ipHash(?string $ip): ?string
    {
        return $ip ? hash('sha256', $ip.'|'.config('app.key')) : null;
    }

    /**
     * A visitor's review (on-site form or a request link). Published at once
     * when the rating meets the site's threshold and the text doesn't look
     * spammy; otherwise pending — and the owner is told.
     */
    public static function submit(Site $site, array $data, string $source, ?ReviewRequest $request = null, ?string $ip = null): Review
    {
        $rating = max(1, min(5, (int) $data['rating']));
        $body = (string) self::clean($data['body'] ?? '', 3000);
        $min = self::autoPublishMin($site);
        $linky = preg_match_all('~(https?://|www\.)~i', $body.' '.($data['title'] ?? '')) >= 2;
        $publish = $min !== null && $rating >= $min && ! $linky;

        $review = DB::transaction(function () use ($site, $data, $source, $request, $ip, $rating, $body, $publish) {
            $review = Review::create([
                'site_id' => $site->id,
                'request_id' => $request?->id,
                'name' => self::clean($data['name'] ?? '', 120) ?? 'Anonymous',
                'email' => filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : $request?->email,
                'rating' => $rating,
                'title' => self::clean($data['title'] ?? null, 160),
                'body' => $body,
                'photo' => filled($data['photo'] ?? null) ? trim($data['photo']) : null,
                'status' => $publish ? 'published' : 'pending',
                'published_at' => $publish ? now() : null,
                'source' => $source,
                'ip_address' => $ip,
                'ip_hash' => self::ipHash($ip),
            ]);
            $request?->forceFill(['completed_at' => now()])->save();

            return $review;
        });

        if ($review->status === 'pending') {
            self::notifyOwner($site, $review);
        }

        return $review;
    }

    public static function notifyOwner(Site $site, Review $review): void
    {
        $to = $site->user?->email;
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new NewReviewNotification($site, $review));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Requests ───────────────────────────────────────────────────

    /** Create + email a request. Returns null for an invalid address. */
    public static function sendRequest(Site $site, string $email, ?string $name = null, ?string $contactId = null): ?ReviewRequest
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $request = ReviewRequest::create([
            'site_id' => $site->id,
            'contact_id' => $contactId,
            'name' => self::clean($name, 120),
            'email' => $email,
        ]);
        self::mailRequest($site, $request, false);

        return $request;
    }

    /** (Re)send a request email; the link stays the same. */
    public static function mailRequest(Site $site, ReviewRequest $request, bool $reminder): void
    {
        try {
            Mail::to($request->email, $request->name)->send(new ReviewRequestMail($site, $request, $reminder));
            $request->forceFill($reminder ? ['reminder_sent_at' => now()] : ['sent_at' => now()])->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** One reminder, 5+ days after the request, for links not yet used. */
    public static function sendReminders(): int
    {
        $sent = 0;
        ReviewRequest::query()
            ->whereNull('completed_at')->whereNull('reminder_sent_at')
            ->whereNotNull('sent_at')->where('sent_at', '<=', now()->subDays(self::REMINDER_AFTER_DAYS))
            ->where('sent_at', '>=', now()->subDays(60)) // never nag about ancient requests
            ->with('site')
            ->chunkById(200, function ($requests) use (&$sent) {
                foreach ($requests as $r) {
                    if (! $r->site || ! $r->site->hasFeature(self::FEATURE)) {
                        continue;
                    }
                    self::mailRequest($r->site, $r, true);
                    $sent++;
                }
            });

        return $sent;
    }

    /** Envelope parts: From NAME = the site's name, Reply-To = its Site Properties email. */
    public static function sender(Site $site): array
    {
        $clean = fn (?string $v) => trim(mb_substr(preg_replace('/[\r\n\t"<>]+/', ' ', (string) $v), 0, 80));
        $name = $clean(self::siteName($site));
        $reply = trim(SiteProperties::value($site, 'email') ?: SiteProperties::value($site, 'reply_to'));
        $out = [];
        if ($name !== '' && filled(config('mail.from.address'))) {
            $out['from'] = new Address((string) config('mail.from.address'), $name);
        }
        if (filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $out['replyTo'] = [new Address($reply, $name !== '' ? $name : null)];
        }

        return $out;
    }

    // ── Reading ────────────────────────────────────────────────────

    /** {average, count, distribution{5..1}} over PUBLISHED reviews — one query. */
    public static function aggregate(Site $site): array
    {
        $rows = Review::where('site_id', $site->id)->published()
            ->selectRaw('rating, COUNT(*) as n')->groupBy('rating')->pluck('n', 'rating');

        $dist = [];
        foreach ([5, 4, 3, 2, 1] as $s) {
            $dist[$s] = (int) ($rows[$s] ?? 0);
        }
        $count = array_sum($dist);
        $sum = 0;
        foreach ($dist as $s => $n) {
            $sum += $s * $n;
        }

        return [
            'average' => $count ? round($sum / $count, 1) : null,
            'count' => $count,
            'distribution' => $dist,
        ];
    }

    /**
     * A resolver for "@media/…" photo refs, built from ONE media query (only
     * when some review has a ref) — so a page of reviews never runs N queries.
     */
    public static function photoResolver(Site $site, Collection $reviews): \Closure
    {
        $map = null;
        if ($reviews->contains(fn ($r) => str_starts_with((string) $r->photo, '@media/'))) {
            $map = [];
            foreach (Media::where('site_id', $site->id)->get(['name', 'url']) as $m) {
                $map[mb_strtolower(basename($m->url))] ??= $m->url;
                $map[mb_strtolower($m->name)] ??= $m->url;
            }
        }

        return function (?string $value) use ($map): ?string {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            $url = str_starts_with($value, '@media/') ? ($map[mb_strtolower(trim(substr($value, 7)))] ?? '') : $value;
            if ($url === '') {
                return null;
            }

            return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
        };
    }

    /** The public shape of one review — no email, no IP. */
    public static function present(Review $r, \Closure $photo): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'rating' => $r->rating,
            'title' => $r->title,
            'body' => $r->body,
            'photo' => $photo($r->photo),
            'featured' => $r->featured,
            'source' => $r->source,
            'date' => ($r->published_at ?? $r->created_at)?->toDateString(),
            'reply' => $r->reply_body ? ['body' => $r->reply_body, 'date' => $r->replied_at?->toDateString()] : null,
        ];
    }

    /**
     * schema.org JSON-LD: the business (from Site Properties) carrying its
     * AggregateRating and the given PUBLISHED reviews. Unpublished rows are
     * filtered here too, so a caller can't leak them by mistake.
     */
    public static function jsonLd(Site $site, array $aggregate, iterable $reviews): array
    {
        $biz = SiteProperties::schemaOrg($site, null, $site->publicUrl() ?: null);
        $out = array_intersect_key($biz, array_flip(['@context', '@type', 'name', 'url', 'image', 'logo', 'telephone', 'address', 'priceRange']));
        $out['@context'] = 'https://schema.org';
        $out['@type'] = $biz['@type'] ?? 'LocalBusiness';
        $out['name'] = $biz['name'] ?? self::siteName($site);

        if ($aggregate['count'] > 0) {
            $out['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $aggregate['average'],
                'reviewCount' => $aggregate['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        $items = [];
        foreach ($reviews as $r) {
            if ($r->status !== 'published') {
                continue;
            }
            $items[] = array_filter([
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $r->name],
                'datePublished' => ($r->published_at ?? $r->created_at)?->toDateString(),
                'name' => $r->title,
                'reviewBody' => $r->body,
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $r->rating, 'bestRating' => 5, 'worstRating' => 1],
            ], fn ($v) => $v !== null && $v !== '');
        }
        if ($items) {
            $out['review'] = $items;
        }

        return $out;
    }

    /** JSON safe to drop inside <script type="application/ld+json"> — "<", ">", "&", quotes hex-escaped. */
    public static function encodeJsonLd(array $ld, bool $pretty = false): string
    {
        return json_encode($ld, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0)) ?: '{}';
    }

    public static function jsonLdScript(array $ld, bool $pretty = false): string
    {
        return '<script type="application/ld+json">'.self::encodeJsonLd($ld, $pretty).'</script>';
    }
}
