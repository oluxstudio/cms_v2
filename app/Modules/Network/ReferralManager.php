<?php

namespace App\Modules\Network;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Mail\ReferralConsentMail;
use App\Modules\Network\Mail\ReferralReceivedMail;
use App\Modules\Network\Mail\ReferralUpdateMail;
use App\Modules\Network\Models\NetworkProfile;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralEvent;
use App\Support\SiteProperties;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The Referral Network engine (see Contracts\ReferralService): membership,
 * referring a customer (with consent), the receiver's response, conversion
 * detection from paid bookings / invoices / orders, disputes and expiry.
 * Every state change writes a ReferralEvent (type = new status or action).
 */
class ReferralManager implements ReferralService
{
    // ── Hooks ──────────────────────────────────────────────────────

    /**
     * Conversion hooks: a paid invoice / order / booking on the RECEIVING site
     * converts an open referral for the same customer email. Never lets the
     * network break a payment — everything is caught and reported.
     */
    public static function observe(): void
    {
        $detect = function (?Site $site, ?string $email, string $via): void {
            try {
                if ($site && filled($email)) {
                    app(ReferralService::class)->detectConversion($site, $email, $via);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        };

        $invoice = function (Invoice $m) use ($detect) {
            if ($m->status === 'paid' && ($m->wasRecentlyCreated || ($m->wasChanged('status') && $m->getOriginal('status') !== 'paid'))) {
                $detect($m->site, $m->customer_email, 'invoice:'.$m->id);
            }
        };
        Invoice::created($invoice);
        Invoice::updated($invoice);

        $paidOrder = ['paid', 'shipped', 'delivered', 'fulfilled'];
        $order = function (Order $m) use ($detect, $paidOrder) {
            if (in_array($m->status, $paidOrder, true)
                && ($m->wasRecentlyCreated || ($m->wasChanged('status') && ! in_array($m->getOriginal('status'), $paidOrder, true)))) {
                $detect($m->site, $m->customer_email, 'order:'.$m->id);
            }
        };
        Order::created($order);
        Order::updated($order);

        $booking = function (Booking $m) use ($detect) {
            if ((int) $m->paid_cents > 0 && $m->status !== 'cancelled'
                && ($m->wasRecentlyCreated || ($m->wasChanged('paid_cents') && (int) $m->getOriginal('paid_cents') <= 0))) {
                $detect($m->site, $m->customer_email, 'booking:'.$m->id);
            }
        };
        Booking::created($booking);
        Booking::updated($booking);
    }

    // ── Membership ─────────────────────────────────────────────────

    public function planAllows(Site $site): bool
    {
        $sub = $site->user?->subscription;
        if (! $sub) {
            return false;
        }

        return in_array($sub->plan, (array) config('network.plans'), true)
            && ! $sub->onTrial()
            && ! in_array($sub->status, ['trialing', 'expired', 'cancelled'], true);
    }

    public function profileFor(Site $site): ?NetworkProfile
    {
        return NetworkProfile::where('site_id', $site->id)->first();
    }

    public function saveProfile(Site $site, array $data): NetworkProfile
    {
        $profile = $this->profileFor($site) ?? new NetworkProfile([
            'site_id' => $site->id,
            'currency' => strtolower((string) ($site->currency ?: 'gbp')),
        ]);

        $clean = fn ($v, int $max) => ($v = trim(strip_tags((string) $v))) === '' ? null : mb_substr($v, 0, $max);

        foreach (['business_type' => 60, 'area' => 120, 'pitch' => 500] as $key => $max) {
            if (array_key_exists($key, $data)) {
                $profile->{$key} = $clean($data[$key], $max);
            }
        }
        if (array_key_exists('postcode', $data)) {
            $pc = $clean($data['postcode'], 12);
            $profile->postcode = $pc ? Str::upper(preg_replace('/\s+/', ' ', $pc)) : null;
        }
        if (array_key_exists('services', $data)) {
            $services = is_array($data['services']) ? $data['services'] : explode(',', (string) $data['services']);
            $profile->services = collect($services)->map(fn ($s) => $clean($s, 80))->filter()->unique()->take(30)->values()->all();
        }
        if (array_key_exists('radius_km', $data)) {
            $profile->radius_km = max(1, min(500, (int) $data['radius_km']));
        }
        if (array_key_exists('fee_cents', $data)) {
            $fee = (int) $data['fee_cents'];
            $min = (int) config('network.fee_min_cents');
            $max = (int) config('network.fee_max_cents');
            if ($fee < $min || $fee > $max) {
                throw new NetworkException(sprintf('Your referral fee must be between £%s and £%s.',
                    number_format($min / 100, 2), number_format($max / 100, 2)));
            }
            $profile->fee_cents = $fee;
        }
        if (array_key_exists('accepting', $data)) {
            $profile->accepting = (bool) $data['accepting'];
        }

        $profile->save();

        return $profile;
    }

    public function join(Site $site, User $user): NetworkProfile
    {
        if (! $this->planAllows($site)) {
            throw new NetworkException('The Referral Network is available on paid plans. Upgrade to join — trial accounts can browse only.');
        }
        $profile = $this->saveProfile($site, []);
        $profile->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => config('network.terms_version'),
            'accepting' => true,
        ])->save();

        return $profile;
    }

    public function leave(Site $site, User $user): void
    {
        $this->profileFor($site)?->forceFill(['accepting' => false])->save();
    }

    /**
     * Accepting members other than $site. Filters: q (business type / services
     * / area / pitch), business_type, postcode (Phase 1: postcode-area prefix
     * match, exact outward code ranked first — no geocoding, radius_km ignored).
     */
    public function findPartners(Site $site, array $filters = []): Collection
    {
        $query = NetworkProfile::query()
            ->with('site')
            ->where('accepting', true)
            ->whereNotNull('terms_accepted_at')
            ->where('terms_version', config('network.terms_version'))
            ->where('site_id', '!=', $site->id);

        if (filled($q = trim((string) ($filters['q'] ?? '')))) {
            $like = '%'.mb_strtolower($q).'%';
            $query->where(fn ($w) => $w->whereRaw('LOWER(business_type) LIKE ?', [$like])
                ->orWhereRaw('LOWER(CAST(services AS CHAR)) LIKE ?', [$like])
                ->orWhereRaw('LOWER(area) LIKE ?', [$like])
                ->orWhereRaw('LOWER(pitch) LIKE ?', [$like]));
        }
        if (filled($type = trim((string) ($filters['business_type'] ?? '')))) {
            $query->where('business_type', $type);
        }

        $outward = null;
        if (filled($pc = Str::upper(trim((string) ($filters['postcode'] ?? ''))))) {
            $outward = self::outwardCode($pc);
            $areaLetters = preg_replace('/[^A-Z].*$/', '', $outward) ?: $outward;
            $query->where(fn ($w) => $w->where('postcode', 'like', $areaLetters.'%')
                ->orWhereRaw('UPPER(area) LIKE ?', ['%'.$outward.'%']));
        }

        $mine = $this->profileFor($site);
        $myArea = mb_strtolower(trim((string) $mine?->area));

        return $query->limit(200)->get()
            ->filter(fn (NetworkProfile $p) => $p->site !== null)
            ->sortBy(function (NetworkProfile $p) use ($outward, $myArea) {
                $rank = 2;
                if ($outward && $p->postcode && self::outwardCode(Str::upper($p->postcode)) === $outward) {
                    $rank = 0;
                } elseif (! $outward && $myArea !== '' && mb_strtolower(trim((string) $p->area)) === $myArea) {
                    $rank = 0;
                } elseif ($outward) {
                    $rank = 1;
                }

                return sprintf('%d|%s', $rank, mb_strtolower(self::businessName($p->site)));
            })
            ->values();
    }

    // ── Referring ──────────────────────────────────────────────────

    public function refer(Site $from, Site $to, array $customer, User $user, bool $formTick = false): Referral
    {
        if ($from->id === $to->id) {
            throw new NetworkException("You can't refer a customer to your own business.");
        }
        $fromProfile = $this->profileFor($from);
        $toProfile = $this->profileFor($to);
        if (! $fromProfile?->isMember()) {
            throw new NetworkException('Join the Referral Network (and accept the current terms) before sending referrals.');
        }
        if (! $this->planAllows($from)) {
            throw new NetworkException('Sending referrals needs a paid plan — trial accounts can browse only.');
        }
        if (! $toProfile?->isMember() || ! $toProfile->accepting) {
            throw new NetworkException(self::businessName($to).' is not accepting referrals right now.');
        }

        $name = trim(strip_tags((string) ($customer['name'] ?? '')));
        $email = strtolower(trim((string) ($customer['email'] ?? '')));
        $phone = trim((string) ($customer['phone'] ?? '')) ?: null;
        $note = trim(strip_tags((string) ($customer['note'] ?? ''))) ?: null;
        if ($name === '') {
            throw new NetworkException("Please enter the customer's name.");
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new NetworkException('Please enter a valid email address for the customer.');
        }

        $dupe = Referral::where('to_site_id', $to->id)
            ->where('customer_email', $email)
            ->whereIn('status', array_merge(['pending_consent'], Referral::OPEN))
            ->exists();
        if ($dupe) {
            throw new NetworkException('This customer has already been referred to '.self::businessName($to).' and that referral is still open.');
        }

        $rawToken = Str::random(48);
        $consentText = self::consentText($from, $to, $phone !== null);

        $referral = DB::transaction(function () use ($from, $to, $toProfile, $customer, $user, $formTick, $name, $email, $phone, $note, $rawToken, $consentText) {
            $referral = Referral::create([
                'reference' => self::newReference(),
                'from_site_id' => $from->id,
                'to_site_id' => $to->id,
                'from_contact_id' => $customer['from_contact_id'] ?? null,
                'created_by' => $user->id,
                'customer_name' => mb_substr($name, 0, 160),
                'customer_email' => mb_substr($email, 0, 190),
                'customer_phone' => $phone ? mb_substr($phone, 0, 40) : null,
                'note' => $note ? mb_substr($note, 0, 2000) : null,
                'status' => 'pending_consent',
                'fee_cents' => (int) $toProfile->fee_cents,
                'currency' => $toProfile->currency ?: 'gbp',
                'olux_cut_pct' => (float) config('network.olux_cut_pct'),
                'consent_token_hash' => hash('sha256', $rawToken),
                'consent_method' => $formTick ? 'form_tick' : 'email_link',
                'consent_text' => $consentText,
                'consent_requested_at' => $formTick ? null : now(),
                'consented_at' => $formTick ? now() : null,
                'expires_at' => now()->addDays((int) config('network.consent_days')),
            ]);
            $this->log($referral, 'created', $user, ['method' => $referral->consent_method, 'fee_cents' => $referral->fee_cents]);

            return $referral;
        });

        if ($formTick) {
            $this->log($referral, 'consented', $user, ['method' => 'form_tick']);
            $this->share($referral, $user);
        } else {
            $this->log($referral, 'consent_requested', $user, ['email' => $email]);
            self::send($email, new ReferralConsentMail($referral, $rawToken), $name);
        }

        return $referral->fresh();
    }

    public function findByConsentToken(string $token): ?Referral
    {
        if ($token === '') {
            return null;
        }

        return Referral::where('consent_token_hash', hash('sha256', $token))->with(['fromSite', 'toSite'])->first();
    }

    public function recordConsent(string $token, bool $yes, ?string $ip = null): Referral
    {
        $referral = DB::transaction(function () use ($token, $yes, $ip) {
            $referral = Referral::where('consent_token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $referral) {
                throw new NetworkException('This link is not valid.');
            }
            if ($referral->status !== 'pending_consent') {
                throw new NetworkException('You have already answered — thank you.');
            }
            if ($referral->expires_at && $referral->expires_at->isPast()) {
                throw new NetworkException('This link has expired.');
            }

            // Claim the answer inside the lock (a double-click must not share twice);
            // share() then fills in the contact + window and sends the mails.
            $referral->forceFill([
                'consent_ip' => $ip ? mb_substr($ip, 0, 45) : null,
                'consented_at' => $yes ? now() : null,
                'status' => $yes ? 'shared' : 'consent_refused',
                'shared_at' => $yes ? now() : null,
            ])->save();
            $this->log($referral, $yes ? 'consented' : 'consent_refused', null, ['ip' => $ip]);

            return $referral;
        });

        if ($yes) {
            $this->share($referral, null);
        } else {
            $this->notifyReferrer($referral, 'consent_refused');
        }

        return $referral->fresh();
    }

    /** Consent given: open the conversion window, drop the lead into the receiver's CRM, tell both sides. */
    protected function share(Referral $referral, ?User $user): void
    {
        $from = $referral->fromSite;
        $to = $referral->toSite;

        $contact = Contact::capture($to, $referral->customer_name, $referral->customer_email, $referral->customer_phone,
            'Referred by '.self::businessName($from).' via the Olux Referral Network ('.$referral->reference.')',
            ['referral' => $referral->reference]);
        if ($contact) {
            $contact->forceFill(['data' => array_merge($contact->data ?? [], [
                'source' => 'referral',
                'referral_reference' => $referral->reference,
                'referred_by' => self::businessName($from),
                'referral_note' => $referral->note,
            ])])->save();
        }

        $referral->forceFill([
            'status' => 'shared',
            'shared_at' => now(),
            'expires_at' => now()->addDays((int) config('network.conversion_window_days')),
            'to_contact_id' => $contact?->id,
        ])->save();
        $this->log($referral, 'shared', $user, ['to_contact_id' => $contact?->id]);

        if ($owner = $to->user?->email) {
            self::send($owner, new ReferralReceivedMail($referral));
        }
        if ($referral->consent_method === 'email_link') {
            $this->notifyReferrer($referral, 'shared');
        }
    }

    // ── Receiver / referrer actions ─────────────────────────────────

    public function cancel(Referral $referral, User $user): Referral
    {
        if (! in_array($referral->status, ['pending_consent', 'shared'], true)) {
            throw new NetworkException('This referral can no longer be withdrawn.');
        }
        $referral->forceFill(['status' => 'cancelled'])->save();
        $this->log($referral, 'cancelled', $user);

        return $referral;
    }

    public function accept(Referral $referral, User $user): Referral
    {
        if ($referral->status !== 'shared') {
            throw new NetworkException('Only a newly shared referral can be accepted.');
        }
        $referral->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
        $this->log($referral, 'accepted', $user);
        $this->notifyReferrer($referral, 'accepted');

        return $referral;
    }

    public function decline(Referral $referral, User $user, ?string $reason = null): Referral
    {
        if (! in_array($referral->status, ['shared', 'accepted'], true)) {
            throw new NetworkException('This referral can no longer be declined.');
        }
        $reason = trim(strip_tags((string) $reason)) ?: null;
        $referral->forceFill([
            'status' => 'declined', 'declined_at' => now(),
            'decline_reason' => $reason ? mb_substr($reason, 0, 300) : null,
        ])->save();
        $this->log($referral, 'declined', $user, ['reason' => $reason]);
        $this->notifyReferrer($referral, 'declined');

        return $referral;
    }

    /**
     * Mark converted. Automatic hooks get a silent no-op when the referral isn't
     * OPEN or its window has passed; a 'manual' call throws instead so the UI
     * can explain why.
     */
    public function markConverted(Referral $referral, string $via, ?User $user = null): Referral
    {
        $converted = DB::transaction(function () use ($referral, $via, $user) {
            $fresh = Referral::whereKey($referral->id)->lockForUpdate()->first();
            if (! $fresh || ! in_array($fresh->status, Referral::OPEN, true)
                || ($fresh->expires_at && $fresh->expires_at->isPast())) {
                return false;
            }
            $fresh->forceFill(['status' => 'converted', 'converted_at' => now(), 'converted_via' => mb_substr($via, 0, 40)])->save();
            $this->log($fresh, 'converted', $user, ['via' => $via]);

            return true;
        });

        if (! $converted) {
            if ($via === 'manual') {
                throw new NetworkException('Only an open referral within its '.(int) config('network.conversion_window_days').'-day window can be marked as won.');
            }

            return $referral;
        }

        $referral->refresh();
        $this->notifyReferrer($referral, 'converted');

        return $referral;
    }

    public function detectConversion(Site $toSite, ?string $email, string $via): ?Referral
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        $referral = Referral::where('to_site_id', $toSite->id)
            ->whereRaw('LOWER(customer_email) = ?', [$email])
            ->whereIn('status', Referral::OPEN)
            ->where('expires_at', '>', now())
            ->orderByDesc('shared_at')
            ->first();
        if (! $referral) {
            return null;
        }

        $referral = $this->markConverted($referral, $via);

        return $referral->status === 'converted' ? $referral : null;
    }

    // ── Disputes ───────────────────────────────────────────────────

    public function dispute(Referral $referral, User $user, string $reason): Referral
    {
        if ($referral->status !== 'converted') {
            throw new NetworkException('Only a converted referral can be disputed.');
        }
        $days = (int) config('network.dispute_days');
        if (! $referral->converted_at || $referral->converted_at->copy()->addDays($days)->isPast()) {
            throw new NetworkException("Conversions can only be disputed within {$days} days.");
        }
        $reason = trim(strip_tags($reason));
        if ($reason === '') {
            throw new NetworkException('Please tell us why you are disputing this referral.');
        }
        $referral->forceFill(['status' => 'disputed', 'disputed_at' => now(), 'dispute_reason' => mb_substr($reason, 0, 500)])->save();
        $this->log($referral, 'disputed', $user, ['reason' => $reason]);

        return $referral;
    }

    public function resolveDispute(Referral $referral, User $admin, string $outcome, ?string $note = null): Referral
    {
        if (! $admin->isSuper()) {
            throw new NetworkException('Only Olux staff can resolve disputes.');
        }
        if ($referral->status !== 'disputed') {
            throw new NetworkException('This referral is not in dispute.');
        }
        if (! in_array($outcome, ['upheld', 'rejected'], true)) {
            throw new NetworkException('Choose whether to uphold or reject the dispute.');
        }
        $referral->forceFill([
            'status' => $outcome === 'upheld' ? 'void' : 'converted',
            'dispute_resolved_at' => now(),
            'dispute_outcome' => $outcome,
        ])->save();
        $this->log($referral, 'dispute_'.$outcome, $admin, array_filter(['note' => $note]));

        return $referral;
    }

    // ── Housekeeping ───────────────────────────────────────────────

    public function expireStale(): int
    {
        $count = 0;
        Referral::query()
            ->whereIn('status', array_merge(['pending_consent'], Referral::OPEN))
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->chunkById(200, function ($referrals) use (&$count) {
                foreach ($referrals as $referral) {
                    $from = $referral->status;
                    $updated = Referral::whereKey($referral->id)->where('status', $from)->update(['status' => 'expired', 'updated_at' => now()]);
                    if ($updated) {
                        $this->log($referral, 'expired', null, ['from' => $from]);
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function log(Referral $referral, string $type, ?User $user = null, array $data = []): void
    {
        ReferralEvent::create([
            'referral_id' => $referral->id,
            'type' => mb_substr($type, 0, 32),
            'user_id' => $user?->id,
            'data' => $data ?: null,
            'created_at' => now(),
        ]);
    }

    // ── Helpers (also used by the mails + consent page) ─────────────

    /** The public-facing business name of a site. */
    public static function businessName(?Site $site): string
    {
        if (! $site) {
            return 'A local business';
        }

        return SiteProperties::value($site, 'site_name') ?: Str::headline($site->name);
    }

    /** The exact consent sentence shown on the consent page / form and stored on the referral. */
    public static function consentText(Site $from, Site $to, bool $withPhone = true): string
    {
        $what = $withPhone ? 'my name, email address and phone number' : 'my name and email address';

        return sprintf('I agree that %s may pass %s, with a short note about what I need, to %s so they can contact me about it.',
            self::businessName($from), $what, self::businessName($to));
    }

    /** Mail envelope parts: From NAME = the site's business name, Reply-To = its Site Properties email. */
    public static function sender(Site $site): array
    {
        $clean = fn (?string $v) => trim(mb_substr(preg_replace('/[\r\n\t"<>]+/', ' ', (string) $v), 0, 80));
        $name = $clean(self::businessName($site));
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

    /** "SW1A 1AA" → "SW1A"; "SW1A1AA" → "SW1A"; "SW1A" → "SW1A". */
    public static function outwardCode(string $postcode): string
    {
        $pc = Str::upper(trim($postcode));
        if (str_contains($pc, ' ')) {
            return explode(' ', $pc)[0];
        }

        return strlen($pc) >= 5 ? substr($pc, 0, -3) : $pc;
    }

    private static function newReference(): string
    {
        do {
            $ref = 'REF-'.Str::upper(Str::random(8));
        } while (Referral::where('reference', $ref)->exists());

        return $ref;
    }

    private function notifyReferrer(Referral $referral, string $event): void
    {
        if ($owner = $referral->fromSite?->user?->email) {
            self::send($owner, new ReferralUpdateMail($referral, $event));
        }
    }

    private static function send(string $to, Mailable $mail, ?string $name = null): void
    {
        try {
            Mail::to($to, $name)->send($mail);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
