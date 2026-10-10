<?php

namespace App\Modules\Memberships\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\Memberships\Member;
use App\Modules\Memberships\MembershipAccess;
use App\Modules\Memberships\MembershipService;
use App\Modules\Memberships\MembershipTier;
use App\Modules\Memberships\MembershipUrls;
use App\Modules\Memberships\MemberToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Memberships public API for template sites (stateless, no CSRF).
 *
 *   GET  /api/sites/{site}/memberships/tiers    → tiers + whether paid joins work
 *   POST /api/sites/{site}/memberships/join     → free: active + emailed link · paid: checkout_url
 *   POST /api/sites/{site}/memberships/login    → emails a sign-in link
 *   GET  /api/sites/{site}/memberships/me       → the signed-in member (Bearer member token)
 *   POST /api/sites/{site}/memberships/logout   → revokes the member token
 *   GET  /api/sites/{site}/memberships/access   → ?type=&id= one check · no params: all gated content
 */
class MembershipApiController extends Controller
{
    public function __construct(private MembershipService $memberships) {}

    private function site(string $siteName): Site
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        abort_unless($site->hasFeature('memberships'), 404);

        return $site;
    }

    private function member(Site $site, Request $request): ?Member
    {
        return $this->memberships->memberForToken($site, $request->bearerToken() ?: $request->header('X-Member-Token'));
    }

    public function tiers(string $siteName): JsonResponse
    {
        $site = $this->site($siteName);
        $paidAvailable = $this->memberships->canBillRecurring($site);
        $tiers = MembershipTier::where('site_id', $site->id)->where('active', true)->orderBy('sort')->orderBy('price_cents')->get();

        return response()->json([
            'currency' => $this->memberships->currency($site),
            'paid_available' => $paidAvailable,
            'tiers' => $tiers->map(fn ($t) => $t->toPublic() + ['available' => $t->isFree() || $paidAvailable])->values(),
        ]);
    }

    public function join(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
            'tier' => ['required', 'string', 'max:140'],
            'return_url' => ['nullable', 'string', 'max:500'],
        ]);

        $tier = MembershipTier::where('site_id', $site->id)->where('active', true)
            ->where(fn ($q) => $q->where('id', $data['tier'])->orWhere('slug', $data['tier']))->first();
        if (! $tier) {
            return response()->json(['message' => 'That membership tier is not available.', 'errors' => ['tier' => ['Unknown tier.']]], 422);
        }

        $result = $this->memberships->join($site, $tier, $data['name'], $data['email'], $data['return_url'] ?? null);

        return response()->json($result['body'], $result['status']);
    }

    public function login(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'return_url' => ['nullable', 'string', 'max:500'],
        ]);

        $member = Member::where('site_id', $site->id)->where('email', mb_strtolower(trim($data['email'])))->first();
        if ($member && $member->hasAccess()) {
            $this->memberships->sendMagicLink($member, MembershipUrls::safeReturn($site, $data['return_url'] ?? null));
        }

        // Same answer either way — the endpoint never reveals who is a member.
        return response()->json(['message' => 'If that email belongs to a member, a sign-in link is on its way.'], 202);
    }

    public function me(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $member = $this->member($site, $request);
        if (! $member) {
            return response()->json(['message' => 'Not signed in.'], 401);
        }

        return response()->json(['member' => $member->toPublic(), 'manage_url' => route('memberships.public.manage', $site->name)]);
    }

    public function logout(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $raw = $request->bearerToken() ?: $request->header('X-Member-Token');
        if (is_string($raw) && $raw !== '') {
            MemberToken::where('site_id', $site->id)->where('kind', 'session')->where('token_hash', MemberToken::hash($raw))->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function access(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $data = $request->validate([
            'type' => ['nullable', Rule::in(MembershipAccess::TYPES)],
            'id' => ['nullable', 'string', 'max:40', 'required_with:type'],
        ]);
        $member = $this->member($site, $request);

        if (! empty($data['type'])) {
            return response()->json(['type' => $data['type'], 'id' => $data['id']]
                + $this->memberships->check($site, $data['type'], $data['id'], $member)
                + ['member' => (bool) $member?->hasAccess()]);
        }

        return response()->json([
            'member' => (bool) $member?->hasAccess(),
            'gated' => $this->memberships->gatedContent($site, $member),
        ]);
    }
}
