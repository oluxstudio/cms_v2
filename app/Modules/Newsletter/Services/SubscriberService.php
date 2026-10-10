<?php

namespace App\Modules\Newsletter\Services;

use App\Models\Site;
use App\Models\Subscription;
use App\Modules\Newsletter\Mail\NewsletterConfirmation;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterSend;
use App\Modules\Newsletter\Newsletter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

/** Subscriber lifecycle: signup (with optional double opt-in), confirm, unsubscribe, CSV in/out. */
class SubscriberService
{
    public const IMPORT_MAX_ROWS = 20000;

    /**
     * Sign an address up. Outcomes:
     *   created   — new + subscribed            pending  — new, confirmation emailed
     *   resubscribed — was unsubscribed, now subscribed (or pending again)
     *   resent    — still pending; confirmation emailed again
     *   already   — already subscribed (nothing changed)
     *
     * @return array{0: Subscription, 1: string}
     */
    public function subscribe(Site $site, string $email, ?string $name = null, ?string $source = null, ?string $ip = null, array $tags = [], bool $skipConfirm = false): array
    {
        $email = mb_strtolower(trim($email));
        $double = ! $skipConfirm && Newsletter::doubleOptIn($site);
        $existing = Subscription::where('site_id', $site->id)->where('email', $email)->first();

        if ($existing && $existing->isActive()) {
            if ($tags) {
                $existing->update(['tags' => Subscription::cleanTags(array_merge($existing->tags ?? [], $tags))]);
            }

            return [$existing, 'already'];
        }

        if ($existing) {
            $wasPending = $existing->isPending();
            $existing->fill([
                'status' => $double ? Subscription::PENDING : Subscription::SUBSCRIBED,
                'name' => $name ?: $existing->name,
                'ip_address' => $ip ?? $existing->ip_address,
                'tags' => Subscription::cleanTags(array_merge($existing->tags ?? [], $tags)) ?: null,
                'unsubscribed_at' => null,
                'confirmed_at' => $double ? null : now(),
            ])->save();
            if ($double) {
                $this->sendConfirmation($existing);
            }

            return [$existing, $wasPending ? ($double ? 'resent' : 'resubscribed') : 'resubscribed'];
        }

        $sub = Subscription::create([
            'site_id' => $site->id,
            'email' => $email,
            'name' => $name ?: null,
            'source' => $source,
            'ip_address' => $ip,
            'tags' => Subscription::cleanTags($tags) ?: null,
            'status' => $double ? Subscription::PENDING : Subscription::SUBSCRIBED,
            'confirmed_at' => $double ? null : now(),
        ]);
        if ($double) {
            $this->sendConfirmation($sub);
        }

        return [$sub, $double ? 'pending' : 'created'];
    }

    public function sendConfirmation(Subscription $sub): void
    {
        Mail::to($sub->email)->queue(new NewsletterConfirmation($sub));
        $sub->forceFill(['confirmation_sent_at' => now()])->save();
    }

    public function confirm(Subscription $sub): void
    {
        if ($sub->isPending()) {
            $sub->forceFill(['status' => Subscription::SUBSCRIBED, 'confirmed_at' => now()])->save();
        }
    }

    /** Unsubscribe; when it came from a campaign email, credit that campaign. */
    public function unsubscribe(Subscription $sub, ?NewsletterSend $send = null): void
    {
        if ($sub->status !== Subscription::UNSUBSCRIBED) {
            $sub->forceFill(['status' => Subscription::UNSUBSCRIBED, 'unsubscribed_at' => now()])->save();
        }
        if ($send && $send->unsubscribed_at === null && $send->subscription_id === $sub->id) {
            $send->forceFill(['unsubscribed_at' => now()])->save();
            NewsletterCampaign::whereKey($send->campaign_id)->increment('unsubscribes_count');
        }
    }

    /** The admin list query: search (email / name), status and tag filters. */
    public function query(Site $site, string $search = '', string $status = 'all', string $tag = ''): Builder
    {
        $search = trim($search);

        return Subscription::where('site_id', $site->id)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when(in_array($status, Subscription::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($tag !== '', fn ($q) => $q->tagged($tag));
    }

    /** Every distinct tag on the site's list, A–Z. */
    public function tags(Site $site): array
    {
        return Subscription::where('site_id', $site->id)->whereNotNull('tags')->pluck('tags')
            ->flatten()->filter()->unique(fn ($t) => mb_strtolower($t))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))->values()->all();
    }

    /**
     * Import "email,name,tags" rows (header optional; tags separated by ; or |).
     * Imported addresses are subscribed directly (the owner vouches for consent),
     * existing ones gain the tags — unsubscribed people are never re-subscribed.
     *
     * @return array{added: int, updated: int, skipped: int}
     */
    public function import(Site $site, string $csv, array $extraTags = []): array
    {
        $added = $updated = $skipped = 0;
        $rows = array_slice(preg_split('/\r\n|\r|\n/', trim($csv)), 0, self::IMPORT_MAX_ROWS + 1);
        $map = ['email' => 0, 'name' => 1, 'tags' => 2];

        foreach ($rows as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = array_map('trim', str_getcsv($line));
            if ($i === 0 && ! filter_var($cols[0] ?? '', FILTER_VALIDATE_EMAIL)) {
                // Header row: honour the column order it declares.
                $head = array_map(fn ($c) => mb_strtolower(trim($c)), $cols);
                foreach (['email', 'name', 'tags'] as $k) {
                    $pos = array_search($k, $head, true);
                    $map[$k] = $pos === false ? null : $pos;
                }
                $map['email'] ??= 0;

                continue;
            }

            $email = mb_strtolower((string) ($cols[$map['email']] ?? ''));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                $skipped++;

                continue;
            }
            $name = $map['name'] !== null ? mb_substr((string) ($cols[$map['name']] ?? ''), 0, 255) : '';
            $tags = Subscription::cleanTags(array_merge(
                $map['tags'] !== null ? Subscription::cleanTags((string) ($cols[$map['tags']] ?? '')) : [],
                $extraTags,
            ));

            $existing = Subscription::where('site_id', $site->id)->where('email', $email)->first();
            if ($existing) {
                $existing->update([
                    'name' => $existing->name ?: ($name ?: null),
                    'tags' => Subscription::cleanTags(array_merge($existing->tags ?? [], $tags)) ?: null,
                ]);
                $updated++;
            } else {
                Subscription::create([
                    'site_id' => $site->id, 'email' => $email, 'name' => $name ?: null, 'source' => 'import',
                    'tags' => $tags ?: null, 'status' => Subscription::SUBSCRIBED, 'confirmed_at' => now(),
                ]);
                $added++;
            }
        }

        return compact('added', 'updated', 'skipped');
    }

    /** CSV of the (filtered) list: email, name, status, tags, source, subscribed_at, confirmed_at, unsubscribed_at. */
    public function csv(Builder $query): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['email', 'name', 'status', 'tags', 'source', 'subscribed_at', 'confirmed_at', 'unsubscribed_at']);
        $query->orderBy('created_at')->chunk(1000, function ($rows) use ($out) {
            foreach ($rows as $s) {
                fputcsv($out, array_map([self::class, 'cell'], [
                    $s->email, $s->name, $s->status, implode(';', $s->tags ?? []), $s->source,
                    $s->created_at?->toIso8601String(), $s->confirmed_at?->toIso8601String(), $s->unsubscribed_at?->toIso8601String(),
                ]));
            }
        });
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Neutralise spreadsheet formula injection in exported cells. */
    private static function cell(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v;
    }
}
