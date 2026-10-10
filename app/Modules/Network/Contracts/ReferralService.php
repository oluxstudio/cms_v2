<?php

namespace App\Modules\Network\Contracts;

use App\Models\Site;
use App\Models\User;
use App\Modules\Network\Models\NetworkProfile;
use App\Modules\Network\Models\Referral;
use Illuminate\Support\Collection;

/**
 * The Referral Network engine: membership, sending/receiving leads, customer
 * consent, conversion and disputes. Money lives in ReferralBillingService.
 * Every state change writes a ReferralEvent. Methods throw
 * \App\Modules\Network\NetworkException with a user-facing message when an
 * action isn't allowed (wrong status, not a member, plan too low…).
 */
interface ReferralService
{
    /** Paid (non-trial) plan in config('network.plans') — trial accounts may browse only. */
    public function planAllows(Site $site): bool;

    public function profileFor(Site $site): ?NetworkProfile;

    /** Create/update the profile (business_type, services[], area, postcode, radius_km, pitch, fee_cents, accepting). */
    public function saveProfile(Site $site, array $data): NetworkProfile;

    /** Accept the current network terms (config('network.terms_version')). Requires planAllows(). */
    public function join(Site $site, User $user): NetworkProfile;

    /** Stop receiving referrals (accepting=false); open referrals still run their course. */
    public function leave(Site $site, User $user): void;

    /** Accepting members other than $site, optionally filtered by ['q','business_type','postcode','radius_km']. */
    public function findPartners(Site $site, array $filters = []): Collection;

    /**
     * Refer a customer from $from to $to. $customer = [name, email, phone?, note?, from_contact_id?].
     * $formTick = the customer already ticked consent on a form → shared at once;
     * otherwise a consent email with a signed link is sent and status is pending_consent.
     * Snapshots the receiver's fee_cents and config('network.olux_cut_pct').
     */
    public function refer(Site $from, Site $to, array $customer, User $user, bool $formTick = false): Referral;

    /** Customer answered the consent link. Yes → shared (contact created in receiver CRM, receiver notified). */
    public function recordConsent(string $token, bool $yes, ?string $ip = null): Referral;

    /** Look up a pending referral by its raw consent token (for the consent page). */
    public function findByConsentToken(string $token): ?Referral;

    public function cancel(Referral $referral, User $user): Referral;

    public function accept(Referral $referral, User $user): Referral;

    public function decline(Referral $referral, User $user, ?string $reason = null): Referral;

    /** Mark converted; $via = 'booking:{id}'|'invoice:{id}'|'order:{id}'|'manual'. No-op if not OPEN or window passed. */
    public function markConverted(Referral $referral, string $via, ?User $user = null): Referral;

    /** Called from paid hooks: find an OPEN referral to $toSite for $email within the window and convert it. */
    public function detectConversion(Site $toSite, ?string $email, string $via): ?Referral;

    /** Receiver disputes a conversion within config('network.dispute_days'). */
    public function dispute(Referral $referral, User $user, string $reason): Referral;

    /** Super admin: 'upheld' → void (no charge); 'rejected' → back to converted. */
    public function resolveDispute(Referral $referral, User $admin, string $outcome, ?string $note = null): Referral;

    /** Expire stale pending_consent and OPEN referrals. Returns count. */
    public function expireStale(): int;

    /** Append an audit event. */
    public function log(Referral $referral, string $type, ?User $user = null, array $data = []): void;
}
