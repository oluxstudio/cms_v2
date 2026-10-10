<?php

namespace App\Modules\Memberships;

use App\Models\Collection;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Modules\Memberships\Mail\MembershipCancelledMail;
use App\Modules\Memberships\Mail\MembershipMagicLinkMail;
use App\Modules\Memberships\Mail\MembershipWelcomeMail;
use App\Payments\PaymentManager;
use App\Services\TaskLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The membership lifecycle: join (free → active at once, paid → recurring
 * Stripe Checkout on the site's connected account), activation, sign-in by
 * magic link, member tokens for template sites, cancellation, and the
 * members-only content checks.
 */
class MembershipService
{
    public const MAGIC_TTL_MINUTES = 30;

    public const SESSION_TTL_DAYS = 90;

    public function __construct(private PaymentManager $payments) {}

    // ── payments ────────────────────────────────────────────────────────

    /** The site's gateway when it can bill recurring (Stripe Connect, connected + chargeable). */
    public function subscriptionGateway(Site $site): ?object
    {
        $gw = $this->payments->for($site);

        return $gw->available($site) && method_exists($gw, 'supportsSubscriptions') && $gw->supportsSubscriptions()
            ? $gw : null;
    }

    public function canBillRecurring(Site $site): bool
    {
        return $this->subscriptionGateway($site) !== null;
    }

    public function currency(Site $site): string
    {
        return strtolower((string) ($site->feature('memberships')['currency'] ?? 'gbp')) ?: 'gbp';
    }

    // ── joining ─────────────────────────────────────────────────────────

    /**
     * @return array{status: int, body: array}
     */
    public function join(Site $site, MembershipTier $tier, string $name, string $email, ?string $returnUrl = null): array
    {
        $email = mb_strtolower(trim($email));
        $returnUrl = MembershipUrls::safeReturn($site, $returnUrl);

        if (! $tier->isFree() && ! ($gw = $this->subscriptionGateway($site))) {
            return ['status' => 422, 'body' => [
                'message' => 'Paid memberships are not available right now — this site has not connected its payments yet.',
                'code' => 'payments_not_connected',
            ]];
        }

        $member = Member::where('site_id', $site->id)->where('email', $email)->first();

        // Already in: never re-bill — send a sign-in link instead.
        if ($member && $member->hasAccess()) {
            $this->sendMagicLink($member, $returnUrl);

            return ['status' => 200, 'body' => [
                'status' => 'existing',
                'message' => 'You are already a member — we have emailed you a sign-in link.',
            ]];
        }

        $member ??= new Member(['site_id' => $site->id, 'email' => $email]);
        $member->fill([
            'name' => trim($name),
            'tier_id' => $tier->id,
            'status' => 'pending',
            'price_cents' => (int) $tier->price_cents,
            'interval' => $tier->interval,
            'currency' => $tier->currency,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
        ])->save();
        $member->log('joined', $tier->name);

        if ($tier->isFree()) {
            $this->activate($member, null, [], $returnUrl);

            return ['status' => 201, 'body' => [
                'status' => 'active',
                'message' => 'Welcome! Check your email for your sign-in link.',
                'member' => $member->fresh('tier')->toPublic(),
            ]];
        }

        try {
            $session = $gw->createSubscriptionCheckout(
                $site,
                $tier->name.' membership — '.MembershipUrls::siteTitle($site),
                (int) $tier->price_cents, $tier->currency, $tier->interval,
                route('memberships.public.success', $site->name).'?session_id={CHECKOUT_SESSION_ID}',
                $returnUrl ?: route('memberships.public.manage', $site->name),
                ['membership_id' => $member->id, 'site_id' => $site->id],
                $email,
            );
        } catch (\Throwable $e) {
            Log::error('Membership checkout failed', ['site' => $site->id, 'msg' => $e->getMessage()]);

            return ['status' => 502, 'body' => ['message' => 'Could not start the payment. Please try again later.']];
        }

        $member->update(['stripe_checkout_id' => $session->id]);
        $member->log('checkout_started', $tier->priceLabel());

        return ['status' => 201, 'body' => ['status' => 'checkout', 'checkout_url' => $session->url]];
    }

    /**
     * pending/cancelled → active, exactly once (welcome email + magic link on
     * the transition only, so webhook retries never double-send).
     */
    public function activate(Member $member, ?\DateTimeInterface $renewsAt, array $stripe = [], ?string $returnUrl = null): bool
    {
        $changed = DB::transaction(function () use ($member, $renewsAt, $stripe) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->first();
            if (! $locked) {
                return false;
            }
            $ids = array_filter([
                'stripe_customer_id' => $stripe['customer'] ?? null,
                'stripe_subscription_id' => $stripe['subscription'] ?? null,
            ]);
            if ($locked->status === 'active') {
                $locked->update($ids + ($renewsAt ? ['renews_at' => $renewsAt] : []));

                return false;
            }
            $locked->update($ids + [
                'status' => 'active',
                'joined_at' => $locked->joined_at ?? now(),
                'renews_at' => $renewsAt ?? ($locked->isPaid() ? MembershipUrls::nextRenewal($locked->interval) : null),
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
            ]);

            return true;
        });

        $member->refresh();
        if ($changed) {
            $member->log('activated');
            $this->sendWelcome($member, $returnUrl);
            try {
                app(TaskLogger::class)->alert($member->site,
                    'New member — '.$member->name.' ('.($member->tier?->name ?? 'member').')',
                    'membership', 'success', $member->priceLabel(), null, 'all', url($member->site->name.'/memberships'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $changed;
    }

    /** Member-initiated or admin cancel: paid → at period end on Stripe; free → now. */
    public function cancel(Member $member, string $by = 'member'): void
    {
        $site = $member->site;
        if ($member->isPaid() && $member->stripe_subscription_id && $member->status !== 'cancelled') {
            $gw = $this->payments->for($site);
            if (! method_exists($gw, 'cancelSubscription')) {
                throw new \RuntimeException('Payments are not connected, so the subscription cannot be cancelled here.');
            }
            $gw->cancelSubscription($site, $member->stripe_subscription_id, true);
            $member->update(['cancel_at_period_end' => true]);
            $member->log('cancel_requested', 'Ends '.($member->renews_at?->format('j M Y') ?? 'at period end').' · by '.$by);

            return;
        }

        $this->markCancelled($member, 'by '.$by);
    }

    public function markCancelled(Member $member, ?string $detail = null): void
    {
        if ($member->status === 'cancelled') {
            return;
        }
        $member->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_at_period_end' => false]);
        $member->log('cancelled', $detail);
        MemberToken::where('member_id', $member->id)->where('kind', 'session')->delete();
        $this->mail($member, new MembershipCancelledMail($member->fresh('tier', 'site')));
    }

    // ── sign-in ─────────────────────────────────────────────────────────

    public function sendMagicLink(Member $member, ?string $returnUrl = null, ?string $next = null): void
    {
        [$raw] = MemberToken::issue($member, 'magic', now()->addMinutes(self::MAGIC_TTL_MINUTES), [
            'return_url' => $returnUrl, 'next' => $next,
        ]);
        $member->log('magic_link');
        $this->mail($member, new MembershipMagicLinkMail($member->loadMissing('site'), MembershipUrls::magicLink($member->site, $raw)));
    }

    /**
     * Exchange a magic-link token for a member session token. Links stay
     * usable until they expire (mail scanners pre-open links), and each use
     * mints its own session token.
     *
     * @return array{0: Member, 1: string, 2: MemberToken}|null
     */
    public function exchangeMagicLink(Site $site, string $raw): ?array
    {
        $magic = MemberToken::findLive($site->id, 'magic', $raw);
        $member = $magic?->member;
        if (! $member || $member->site_id !== $site->id || $member->status === 'cancelled') {
            return null;
        }
        $magic->update(['used_at' => $magic->used_at ?? now()]);
        [$session] = MemberToken::issue($member, 'session', now()->addDays(self::SESSION_TTL_DAYS));

        // A free member who never confirmed counts as joined once they sign in.
        $member->update(['last_login_at' => now()]);
        $member->log('signed_in');

        return [$member, $session, $magic];
    }

    public function memberForToken(Site $site, ?string $raw): ?Member
    {
        $token = MemberToken::findLive($site->id, 'session', $raw);
        if (! $token) {
            return null;
        }
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subHour())) {
            $token->update(['last_used_at' => now(), 'expires_at' => now()->addDays(self::SESSION_TTL_DAYS)]);
        }
        $member = $token->member()->with('tier')->first();

        return $member && $member->site_id === $site->id ? $member : null;
    }

    // ── members-only content ────────────────────────────────────────────

    public function rule(Site $site, string $type, string $id): ?MembershipAccess
    {
        return MembershipAccess::where('site_id', $site->id)->where('content_type', $type)->where('content_id', $id)->first();
    }

    /** @return array{gated: bool, allowed: bool, tiers: array} */
    public function check(Site $site, string $type, string $id, ?Member $member): array
    {
        $rule = $this->rule($site, $type, $id);
        if (! $rule) {
            return ['gated' => false, 'allowed' => true, 'tiers' => []];
        }

        return [
            'gated' => true,
            'allowed' => $member !== null && $member->hasAccess() && $rule->allowsTier($member->tier_id),
            'tiers' => $this->tierRefs($site, $rule->tierIds()),
        ];
    }

    /** Every gated item, with what a template needs to lock it (slug / url). */
    public function gatedContent(Site $site, ?Member $member = null): array
    {
        $rules = MembershipAccess::where('site_id', $site->id)->get();
        if ($rules->isEmpty()) {
            return [];
        }
        $ids = fn ($t) => $rules->where('content_type', $t)->pluck('content_id')->all();
        $posts = Post::where('site_id', $site->id)->whereIn('id', $ids('post'))->get(['id', 'title', 'slug'])->keyBy('id');
        $pages = Page::where('site_id', $site->id)->whereIn('id', $ids('page'))->get(['id', 'name', 'url'])->keyBy('id');
        $cols = Collection::where('site_id', $site->id)->whereIn('id', $ids('collection'))->get(['id', 'name', 'slug'])->keyBy('id');
        $tiers = MembershipTier::where('site_id', $site->id)->get(['id', 'name', 'slug'])->keyBy('id');

        return $rules->map(function (MembershipAccess $r) use ($posts, $pages, $cols, $tiers, $member) {
            $item = match ($r->content_type) {
                'post' => ($p = $posts[$r->content_id] ?? null) ? ['title' => $p->title, 'slug' => $p->slug] : null,
                'page' => ($p = $pages[$r->content_id] ?? null) ? ['title' => $p->name, 'url' => $p->url] : null,
                'collection' => ($c = $cols[$r->content_id] ?? null) ? ['title' => $c->name, 'slug' => $c->slug] : null,
                default => null,
            };
            if (! $item) {
                return null; // content was deleted
            }

            return ['type' => $r->content_type, 'id' => $r->content_id] + $item + [
                'tiers' => collect($r->tierIds())->map(fn ($id) => $tiers[$id] ?? null)->filter()
                    ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->values()->all(),
                'allowed' => $member !== null && $member->hasAccess() && $r->allowsTier($member->tier_id),
            ];
        })->filter()->values()->all();
    }

    private function tierRefs(Site $site, array $ids): array
    {
        return $ids === [] ? [] : MembershipTier::where('site_id', $site->id)->whereIn('id', $ids)
            ->orderBy('sort')->get(['id', 'name', 'slug'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->all();
    }

    // ── mail ────────────────────────────────────────────────────────────

    public function sendWelcome(Member $member, ?string $returnUrl = null): void
    {
        [$raw] = MemberToken::issue($member, 'magic', now()->addDays(2), ['return_url' => $returnUrl]);
        $this->mail($member, new MembershipWelcomeMail($member->fresh(['tier', 'site']), MembershipUrls::magicLink($member->site, $raw)));
    }

    public function mail(Member $member, $mailable): void
    {
        try {
            Mail::to($member->email, $member->name)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
