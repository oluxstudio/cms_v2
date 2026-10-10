<?php

namespace App\Modules\Memberships\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\Memberships\Member;
use App\Modules\Memberships\MembershipService;
use App\Modules\Memberships\MembershipUrls;
use App\Modules\Memberships\MemberToken;
use Illuminate\Http\Request;

/**
 * Member-facing web pages: the magic-link landing, the paid-checkout return
 * page, and "manage my membership" (tier, renewal, cancel, Stripe billing
 * portal). The browser session remembers the member per site.
 */
class MembershipPublicController extends Controller
{
    public function __construct(private MembershipService $memberships) {}

    private function site(string $siteName): Site
    {
        return Site::where('name', $siteName)->firstOrFail();
    }

    private static function sessionKey(Site $site): string
    {
        return 'memberships.member.'.$site->id;
    }

    private function sessionMember(Site $site, Request $request): ?Member
    {
        $id = $request->session()->get(self::sessionKey($site));
        $member = $id ? Member::with('tier')->where('site_id', $site->id)->find($id) : null;

        return $member && $member->status !== 'pending' ? $member : null;
    }

    /** GET /preview/{site}/membership/auth/{token} — exchange the link for a member token. */
    public function auth(string $siteName, string $token, Request $request)
    {
        $site = $this->site($siteName);
        $result = $this->memberships->exchangeMagicLink($site, $token);
        if (! $result) {
            return response()->view('memberships-public-link', ['site' => $site, 'siteTitle' => MembershipUrls::siteTitle($site)], 410);
        }
        [$member, $session, $magic] = $result;
        $request->session()->put(self::sessionKey($site), $member->id);

        $back = $magic->next === 'manage' ? null : ($magic->return_url ?: $site->publicUrl());

        return $back
            ? redirect()->away(MembershipUrls::withToken($back, $session))
            : redirect()->route('memberships.public.manage', $site->name);
    }

    /** GET /preview/{site}/membership/welcome?session_id= — back from a paid checkout. */
    public function success(string $siteName, Request $request)
    {
        $site = $this->site($siteName);
        $sessionId = (string) $request->query('session_id');
        $member = $sessionId !== '' ? Member::with('tier')->where('site_id', $site->id)->where('stripe_checkout_id', $sessionId)->first() : null;

        // Webhooks don't reach local/dev hosts — confirm with Stripe directly.
        if ($member && $member->status === 'pending' && ($gw = $this->memberships->subscriptionGateway($site))) {
            try {
                $d = $gw->subscriptionCheckoutDetails($site, $sessionId);
                if ($d['paid']) {
                    $this->memberships->activate($member, null, ['subscription' => $d['subscription'], 'customer' => $d['customer']]);
                    $member->refresh();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('memberships-public-success', [
            'site' => $site, 'siteTitle' => MembershipUrls::siteTitle($site), 'member' => $member, 'siteUrl' => $site->publicUrl(),
        ]);
    }

    /** GET /preview/{site}/membership — the member's own page (or the sign-in form). */
    public function manage(string $siteName, Request $request)
    {
        $site = $this->site($siteName);

        // Template sites link here with the member token they hold.
        if ($raw = $request->query('member_token')) {
            if ($m = $this->memberships->memberForToken($site, (string) $raw)) {
                $request->session()->put(self::sessionKey($site), $m->id);
            }

            return redirect()->route('memberships.public.manage', $site->name);
        }

        $member = $this->sessionMember($site, $request);

        return view('memberships-public-manage', [
            'site' => $site,
            'siteTitle' => MembershipUrls::siteTitle($site),
            'member' => $member,
            'events' => $member ? $member->events()->limit(8)->get() : collect(),
            'canPortal' => $member && $member->stripe_customer_id && $this->memberships->subscriptionGateway($site),
            'siteUrl' => $site->publicUrl(),
        ]);
    }

    public function login(string $siteName, Request $request)
    {
        $site = $this->site($siteName);
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $member = Member::where('site_id', $site->id)->where('email', mb_strtolower(trim($data['email'])))->first();
        if ($member && $member->status !== 'pending') {
            $this->memberships->sendMagicLink($member, null, 'manage');
        }

        return back()->with('membership_status', 'If that email belongs to a member, a sign-in link is on its way.');
    }

    public function cancel(string $siteName, Request $request)
    {
        $site = $this->site($siteName);
        $member = $this->sessionMember($site, $request);
        abort_unless($member, 403);

        try {
            $this->memberships->cancel($member, 'member');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('membership_error', 'We could not cancel right now — please try again or contact us.');
        }

        return back()->with('membership_status', $member->fresh()->status === 'cancelled'
            ? 'Your membership has been cancelled.'
            : 'Cancelled — your membership stays active until the end of the period you have paid for.');
    }

    public function portal(string $siteName, Request $request)
    {
        $site = $this->site($siteName);
        $member = $this->sessionMember($site, $request);
        $gw = $this->memberships->subscriptionGateway($site);
        abort_unless($member && $member->stripe_customer_id && $gw, 403);

        try {
            return redirect()->away($gw->billingPortalUrl($site, $member->stripe_customer_id, route('memberships.public.manage', $site->name)));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('membership_error', 'Billing settings are not available right now — please contact us to update your card.');
        }
    }

    public function signout(string $siteName, Request $request)
    {
        $site = $this->site($siteName);
        if ($id = $request->session()->pull(self::sessionKey($site))) {
            MemberToken::where('member_id', $id)->where('kind', 'session')->delete();
        }

        return redirect()->route('memberships.public.manage', $site->name);
    }
}
