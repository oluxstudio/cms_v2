<?php

namespace App\Support;

use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Models\TemplateUpload;
use App\Models\User;
use App\Services\AccountActivity;
use App\Templates\TemplateAppRegistry;

/**
 * THE template privacy policy. A template is visible/usable to an ACCOUNT
 * (the site owner): public templates to everyone once published; private
 * ones only to the accounts they're assigned to (a library entitlement), the
 * account that uploaded them, and platform admins. Every listing, detail
 * page, preview and apply path asks here.
 */
class TemplateAccess
{
    /** The account a user acts for: the site's owner in a site context, else themselves. */
    public static function accountId(?User $user, ?Site $site = null): ?string
    {
        return $site?->user_id ?? $user?->id;
    }

    /** May this user (acting for $site's account) see the template at all? */
    public static function canSee(?User $user, Template $t, ?Site $site = null): bool
    {
        if (! $t->isPrivate()) {
            return $t->status === 'published'
                || ($user && ($t->user_id === $user->id || $user->isSuper()));
        }
        if (! $user) {
            return false;
        }
        if ($user->isSuper()) {
            return true;
        }
        $account = self::accountId($user, $site);

        return $account !== null && ($t->user_id === $account
            || TemplateEntitlement::where('user_id', $account)->where('template_id', $t->id)->exists());
    }

    /** An uploaded app template (u-… key) — usable only by its own account. */
    public static function canUseUploadKey(?User $user, string $key, ?Site $site = null): bool
    {
        if (! TemplatePaths::isUpload($key)) {
            return true;
        }
        if (! $user) {
            return false;
        }
        if ($user->isSuper()) {
            return true;
        }
        $account = self::accountId($user, $site);

        return TemplateUpload::where('key', $key)->where('user_id', $account)->exists()
            || Template::where(fn ($q) => $q->where('builtin_key', $key)->orWhere('slug', $key))
                ->where(fn ($q) => $q->where('user_id', $account)->orWhereHas('entitlements', fn ($e) => $e->where('user_id', $account)))
                ->exists();
    }

    /** Assign a private template to an account (adds it to their library). */
    public static function assign(Template $t, User $account, User $by): TemplateEntitlement
    {
        $e = TemplateEntitlement::firstOrCreate(
            ['user_id' => $account->id, 'template_id' => $t->id],
            ['source' => 'granted', 'granted_by' => $by->id, 'price_paid_cents' => 0, 'purchased_at' => now()],
        );
        AccountActivity::record($account->id, 'template.assigned', 'Private template "'.$t->name.'" added to your library',
            ['actor_id' => $by->id, 'category' => 'templates', 'icon' => 'template', 'meta' => ['template_id' => $t->id]]);

        return $e;
    }

    /**
     * Take a private template away from an account. Sites already using it
     * keep their current design; the account just can't apply it again.
     */
    public static function unassign(Template $t, User $account, User $by): void
    {
        TemplateEntitlement::where('user_id', $account->id)->where('template_id', $t->id)->where('source', 'granted')->delete();
        AccountActivity::record($account->id, 'template.unassigned', 'Private template "'.$t->name.'" removed from your library',
            ['actor_id' => $by->id, 'category' => 'templates', 'icon' => 'template', 'meta' => ['template_id' => $t->id]]);
    }

    // ── Signed preview tokens (private previews on the public preview API) ──

    public static function previewToken(string $key, int $ttlSeconds = 7200): string
    {
        $exp = time() + $ttlSeconds;

        return $exp.'.'.substr(hash_hmac('sha256', $key.'|'.$exp, (string) config('app.key')), 0, 32);
    }

    public static function validPreviewToken(string $key, ?string $token): bool
    {
        if (! $token || ! preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $token, $m) || (int) $m[1] < time()) {
            return false;
        }

        return hash_equals(substr(hash_hmac('sha256', $key.'|'.$m[1], (string) config('app.key')), 0, 32), $m[2]);
    }

    /** Is this registry key a private (account-uploaded) app? */
    public static function isPrivateKey(string $key): bool
    {
        $t = Template::where('builtin_key', $key)->orWhere('slug', $key)->first();
        if (TemplatePaths::isUpload($key)) {
            // Uploads are private unless an admin published one to the store as Public.
            return ! ($t && $t->status === 'published' && ! $t->isPrivate() && ! $t->isAccountUpload());
        }

        return $t?->isPrivate() ?? false;
    }

    /** Registry apps anyone may see (no account uploads, no private catalog apps). */
    public static function publicAppKeys(): array
    {
        $private = Template::where('visibility', 'private')->pluck('builtin_key')->filter()->all();

        return collect(array_keys(TemplateAppRegistry::all()))
            ->reject(fn ($k) => TemplatePaths::isUpload($k) || in_array($k, $private, true))
            ->values()->all();
    }
}
