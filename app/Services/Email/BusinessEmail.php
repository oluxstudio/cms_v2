<?php

namespace App\Services\Email;

use App\Jobs\Email\CheckEmailDnsJob;
use App\Jobs\Email\CreateMailboxJob;
use App\Jobs\Email\DeleteMailboxJob;
use App\Jobs\Email\EnableEmailDomainJob;
use App\Jobs\Email\MailboxAliasJob;
use App\Jobs\Email\UpdateMailboxJob;
use App\Models\DomainOrder;
use App\Models\EmailDomain;
use App\Models\Mailbox;
use App\Models\MailboxAlias;
use App\Models\Site;
use App\Models\User;
use App\Services\AccountActivity;
use App\Services\TaskLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Business email for a tenant (the site's owning account): switching email on
 * for a verified domain, and creating / managing its mailboxes and aliases.
 * Every rule is checked HERE, server-side, before anything is queued; the
 * provider calls themselves run in queued jobs.
 */
class BusinessEmail
{
    public const LOCAL_PART = '/^[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?$/';

    /** Second-level suffixes a UK small business might sit under (subdomain detection). */
    private const MULTI_LEVEL_SUFFIXES = ['co.uk', 'org.uk', 'me.uk', 'ltd.uk', 'plc.uk', 'net.uk', 'sch.uk', 'ac.uk', 'gov.uk', 'nhs.uk', 'police.uk', 'com.au', 'co.nz', 'co.za', 'com.ng', 'co.ke', 'com.gh'];

    public function __construct(private EmailDns $dns) {}

    // ── Eligibility ────────────────────────────────────────────────

    /** Why email can't be switched on for this site's domain yet, or null when it can. */
    public function ineligibility(Site $site): ?string
    {
        $sub = $site->user?->currentSubscription();
        if (! $site->domain || $site->domain_verified_at === null) {
            return 'Connect and verify your own domain on the Go live page first.';
        }
        if (! self::isApex($site->domain)) {
            return 'Business email works on a main domain (like yourbusiness.co.uk), not a subdomain.';
        }
        if (! $sub || ! $sub->allowsCustomDomain() || $sub->mailboxLimit() < 1) {
            return 'Your plan doesn\'t include business email. Upgrade to add mailboxes.';
        }

        return null;
    }

    /** Is this a registrable domain rather than a subdomain of one? */
    public static function isApex(string $domain): bool
    {
        $domain = strtolower(trim($domain, '. '));
        $labels = explode('.', $domain);
        if (count($labels) < 2) {
            return false;
        }
        $suffixes = array_merge(self::MULTI_LEVEL_SUFFIXES, array_map(fn ($t) => ltrim((string) $t, '.'), array_keys((array) config('domains.tlds', []))));
        $longest = 1;
        foreach ($suffixes as $s) {
            if ($s !== '' && str_ends_with($domain, '.'.$s)) {
                $longest = max($longest, substr_count($s, '.') + 1);
            }
        }

        return count($labels) === $longest + 1;
    }

    /** Registered through us at Openprovider (we manage its DNS) or bring-your-own. */
    public function sourceFor(Site $site): string
    {
        $ours = config('domains.driver') === 'openprovider' && DomainOrder::where('domain', $site->domain)
            ->where('type', 'register')->where('status', 'registered')->exists();

        return $ours ? 'openprovider' : 'byo';
    }

    // ── Enable ─────────────────────────────────────────────────────

    /**
     * Switch email on. When the domain already receives mail somewhere else,
     * the tenant must confirm ($confirmMxChange) — existing mail would stop
     * arriving at the old provider.
     */
    public function enable(Site $site, User $actor, bool $confirmMxChange = false): EmailDomain
    {
        if ($reason = $this->ineligibility($site)) {
            throw ValidationException::withMessages(['domain' => $reason]);
        }
        $existing = EmailDomain::where('domain', strtolower($site->domain))->first();
        if ($existing && $existing->account_id !== $site->user_id) {
            throw ValidationException::withMessages(['domain' => 'This domain is already set up for email on another account.']);
        }
        if ($existing) {
            return $existing;
        }
        if ($this->dns->hasForeignMx($site->domain) && ! $confirmMxChange) {
            throw ValidationException::withMessages(['confirm' => 'This domain already receives email elsewhere. Confirm the switch to continue.']);
        }

        $d = EmailDomain::create([
            'account_id' => $site->user_id,
            'site_id' => $site->id,
            'domain' => strtolower($site->domain),
            'source' => $this->sourceFor($site),
            'status' => 'pending_dns',
            'mx_change_confirmed_at' => $confirmMxChange ? now() : null,
        ]);
        AccountActivity::record($site->user_id, 'email.enabled', 'Business email switched on for '.$d->domain,
            ['actor_id' => $actor->id, 'category' => 'email', 'icon' => 'envelope', 'meta' => ['domain' => $d->domain, 'mx_confirmed' => $confirmMxChange]]);
        EnableEmailDomainJob::dispatch($d->id);

        return $d;
    }

    public function recheckDns(EmailDomain $d): void
    {
        $d->update(['status' => $d->status === 'failed' ? 'verifying' : $d->status, 'dns_attempts' => 0]);
        CheckEmailDnsJob::dispatch($d->id);
    }

    // ── Mailboxes ──────────────────────────────────────────────────

    /** Validates a local part; returns it normalised or throws. */
    public function validLocalPart(string $local, string $field = 'local_part'): string
    {
        $local = strtolower(trim($local));
        if ($local === '' || strlen($local) > 64 || ! preg_match(self::LOCAL_PART, $local) || str_contains($local, '..')) {
            throw ValidationException::withMessages([$field => 'Use lowercase letters, numbers and . - _ (up to 64 characters), starting and ending with a letter or number.']);
        }
        if (in_array($local, (array) config('email.reserved'), true)) {
            throw ValidationException::withMessages([$field => "\"{$local}@\" is reserved and can't be used."]);
        }

        return $local;
    }

    public static function generatePassword(): string
    {
        // Letters, digits and a few safe symbols; always all four classes.
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!#%+-=?@_'];
        $chars = array_map(fn ($s) => $s[random_int(0, strlen($s) - 1)], $sets);
        $all = implode('', $sets);
        while (count($chars) < 20) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);

        return implode('', $chars);
    }

    /**
     * Create a mailbox (pending) and queue it at the provider. Returns
     * [$mailbox, $password] — the password is shown to the tenant ONCE and
     * never stored.
     *
     * @return array{0: Mailbox, 1: string}
     */
    public function createMailbox(EmailDomain $d, User $actor, string $local, ?string $displayName = null, ?string $password = null): array
    {
        $this->guardUsable($d);
        $this->throttle('mailbox-create', $actor, (int) config('email.rate_limits.create', 10));
        $local = $this->validLocalPart($local);
        $password = $password !== null && $password !== '' ? $this->validPassword($password) : self::generatePassword();

        // One account at a time so two quick clicks can't both slip under the limit.
        $mailbox = Cache::lock('email-limit:'.$d->account_id, 10)->block(5, function () use ($d, $actor, $local, $displayName) {
            $sub = User::find($d->account_id)?->currentSubscription();
            $limit = $sub?->mailboxLimit() ?? 0;
            if (! $sub || $sub->mailboxesUsed() >= $limit) {
                throw ValidationException::withMessages(['limit' => "You're using all {$limit} mailboxes on your plan. Upgrade or add more mailboxes to create another."]);
            }
            if ($d->addressTaken($local)) {
                throw ValidationException::withMessages(['local_part' => "{$local}@{$d->domain} is already in use (as a mailbox or alias)."]);
            }

            return Mailbox::create([
                'account_id' => $d->account_id,
                'email_domain_id' => $d->id,
                'local_part' => $local,
                'display_name' => trim((string) $displayName) ?: null,
                'quota_gb' => (int) config('email.quota_gb', 15),
                'status' => 'pending',
                'idempotency_key' => (string) Str::ulid(),
                'created_by' => $actor->id,
            ]);
        });

        CreateMailboxJob::dispatch($mailbox->id, $password);
        AccountActivity::record($d->account_id, 'email.mailbox_created', 'Mailbox created: '.$local.'@'.$d->domain,
            ['actor_id' => $actor->id, 'category' => 'email', 'icon' => 'envelope', 'meta' => ['mailbox_id' => $mailbox->id]]);

        return [$mailbox, $password];
    }

    /** Set a new password (generated when none given). Returns it, to show once. */
    public function resetPassword(Mailbox $m, User $actor, ?string $password = null): string
    {
        $this->guardUsable($m->emailDomain);
        $this->throttle('mailbox-password', $actor, (int) config('email.rate_limits.password_reset', 10));
        $password = $password !== null && $password !== '' ? $this->validPassword($password) : self::generatePassword();
        UpdateMailboxJob::dispatch($m->id, 'password', $password);
        AccountActivity::record($m->account_id, 'email.password_reset', 'Password reset for '.$m->address(),
            ['actor_id' => $actor->id, 'category' => 'email', 'icon' => 'key', 'meta' => ['mailbox_id' => $m->id]]);

        return $password;
    }

    public function rename(Mailbox $m, User $actor, string $displayName): void
    {
        $this->guardUsable($m->emailDomain);
        $displayName = trim($displayName);
        if (mb_strlen($displayName) > 120) {
            throw ValidationException::withMessages(['display_name' => 'Keep the display name under 120 characters.']);
        }
        $m->update(['display_name' => $displayName ?: null]);
        UpdateMailboxJob::dispatch($m->id, 'name', $displayName);
    }

    public function addAlias(Mailbox $m, User $actor, string $local): MailboxAlias
    {
        $d = $m->emailDomain;
        $this->guardUsable($d);
        $local = $this->validLocalPart($local, 'alias');
        $alias = Cache::lock('email-address:'.$d->id, 10)->block(5, function () use ($m, $d, $local) {
            if ($d->addressTaken($local)) {
                throw ValidationException::withMessages(['alias' => "{$local}@{$d->domain} is already in use (as a mailbox or alias)."]);
            }

            return MailboxAlias::create(['mailbox_id' => $m->id, 'email_domain_id' => $d->id, 'local_part' => $local, 'status' => 'pending']);
        });
        MailboxAliasJob::dispatch($alias->id, 'add');
        AccountActivity::record($m->account_id, 'email.alias_added', "Alias {$local}@{$d->domain} → ".$m->address(),
            ['actor_id' => $actor->id, 'category' => 'email', 'icon' => 'envelope', 'meta' => ['alias_id' => $alias->id]]);

        return $alias;
    }

    public function removeAlias(MailboxAlias $a, User $actor): void
    {
        $this->guardUsable($a->emailDomain);
        $a->update(['status' => 'deleting']);
        MailboxAliasJob::dispatch($a->id, 'remove');
    }

    /** Permanently delete a mailbox and all its mail. $typedAddress must match exactly. */
    public function deleteMailbox(Mailbox $m, User $actor, string $typedAddress): void
    {
        $this->guardUsable($m->emailDomain);
        if (strtolower(trim($typedAddress)) !== strtolower($m->address())) {
            throw ValidationException::withMessages(['confirm_address' => 'Type the full address exactly to confirm.']);
        }
        $m->update(['status' => 'deleting']);
        DeleteMailboxJob::dispatch($m->id);
        AccountActivity::record($m->account_id, 'email.mailbox_deleted', 'Mailbox deleted: '.$m->address(),
            ['actor_id' => $actor->id, 'category' => 'email', 'icon' => 'trash', 'meta' => ['mailbox_id' => $m->id]]);
    }

    // ── Subscription changes ───────────────────────────────────────

    /**
     * Subscription cancelled: suspend on our side only — management is locked
     * and the deletion countdown starts. Nothing changes at the provider until
     * the export window ends (then the purge sweep deletes).
     */
    public function suspendAccount(User $account): void
    {
        $days = (int) config('email.export_window_days', 30);
        $domains = EmailDomain::forAccount($account->id)->where('status', '!=', 'suspended')->get();
        foreach ($domains as $d) {
            $d->update(['status' => 'suspended', 'suspended_at' => now(), 'delete_after' => now()->addDays($days)]);
            $d->mailboxes()->whereNotIn('status', ['failed', 'deleting'])->update(['status' => 'suspended']);
            if ($d->site) {
                app(TaskLogger::class)->alert($d->site, "Business email for {$d->domain} is suspended", 'email_suspended', 'warning',
                    "Your subscription has ended. Your mailboxes keep their mail until {$d->delete_after->toFormattedDateString()} — export anything you need, or pick a plan to keep them.",
                    link: '/'.$d->site->name.'/mailboxes', dedupeKey: 'email_suspended:'.$d->id);
            }
        }
        if ($domains->isNotEmpty()) {
            AccountActivity::record($account->id, 'email.suspended', 'Business email suspended (subscription ended)',
                ['category' => 'email', 'icon' => 'pause', 'meta' => ['delete_after' => now()->addDays($days)->toDateString()]]);
        }
    }

    /** Back on a paid plan before the window ended: unsuspend. */
    public function restoreAccount(User $account): void
    {
        foreach (EmailDomain::forAccount($account->id)->where('status', 'suspended')->get() as $d) {
            if ($d->delete_after && $d->delete_after->isPast()) {
                continue;
            }
            $d->update(['status' => 'active', 'suspended_at' => null, 'delete_after' => null]);
            $d->mailboxes()->where('status', 'suspended')->update(['status' => 'active']);
        }
    }

    // ── helpers ────────────────────────────────────────────────────

    private function guardUsable(?EmailDomain $d): void
    {
        if (! $d) {
            throw ValidationException::withMessages(['domain' => 'Email isn\'t set up for this domain.']);
        }
        if ($d->isSuspended()) {
            throw ValidationException::withMessages(['domain' => 'Email is suspended while your subscription is inactive. Pick a plan to manage mailboxes again.']);
        }
    }

    private function validPassword(string $p): string
    {
        if (strlen($p) < 12 || strlen($p) > 128 || ! preg_match('/[a-z]/', $p) || ! preg_match('/[A-Z]/', $p) || ! preg_match('/\d/', $p)) {
            throw ValidationException::withMessages(['password' => 'Use at least 12 characters with upper- and lower-case letters and a number — or let us generate one.']);
        }

        return $p;
    }

    private function throttle(string $what, User $actor, int $perHour): void
    {
        $key = "{$what}:{$actor->id}";
        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            throw ValidationException::withMessages(['limit' => 'Too many attempts — try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.']);
        }
        RateLimiter::hit($key, 3600);
    }
}
