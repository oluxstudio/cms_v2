<?php

namespace App\Services;

use App\Models\AccountActivityLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Records account-level audit events (Settings → Activity & Logs).
 *
 * `accountId` is the account the event belongs to — for personal events
 * (login, password, profile) that's the user themselves; for site/team
 * events it's the account owner. `actor_id` is who performed it.
 */
class AccountActivity
{
    public static function record(string $accountId, string $action, string $title, array $opts = []): AccountActivityLog
    {
        return AccountActivityLog::create([
            'account_id' => $accountId,
            'actor_id' => $opts['actor_id'] ?? Auth::id(),
            'action' => $action,
            'title' => $title,
            'description' => $opts['description'] ?? null,
            'category' => $opts['category'] ?? 'general',
            'icon' => $opts['icon'] ?? null,
            'meta' => $opts['meta'] ?? null,
        ]);
    }

    /* ── Personal ─────────────────────────────────────────────────────── */

    public static function loggedIn(User $user): void
    {
        static::record($user->id, 'login', 'Logged in', ['actor_id' => $user->id, 'category' => 'Login']);
    }

    public static function passwordChanged(User $user): void
    {
        static::record($user->id, 'password_changed', 'Changed password', ['actor_id' => $user->id, 'category' => 'Security']);
    }

    public static function profileUpdated(User $user): void
    {
        static::record($user->id, 'profile_updated', 'Updated profile', ['actor_id' => $user->id, 'category' => 'Profile']);
    }

    /* ── Sites & team (account-scoped) ────────────────────────────────── */

    public static function siteCreated(Site $site): void
    {
        static::record($site->user_id, 'site_created', 'Created site “'.$site->name.'”', ['category' => 'Sites']);
    }

    public static function memberInvited(string $accountId, string $email, string $roleName): void
    {
        static::record($accountId, 'member_invited', 'Invited '.$email, [
            'description' => 'Role: '.$roleName, 'category' => 'Team',
        ]);
    }

    public static function memberJoined(string $accountId, User $member): void
    {
        static::record($accountId, 'member_joined', $member->name.' joined the account', [
            'actor_id' => $member->id, 'category' => 'Team',
        ]);
    }

    public static function roleSaved(string $accountId, string $roleName, bool $isNew): void
    {
        static::record($accountId, $isNew ? 'role_created' : 'role_updated',
            ($isNew ? 'Created role “' : 'Updated role “').$roleName.'”', ['category' => 'Team']);
    }

    /* ── API ──────────────────────────────────────────────────────────── */

    public static function apiKeyCreated(string $accountId, string $name, ?string $siteName): void
    {
        static::record($accountId, 'api_key_created', 'Created API key “'.$name.'”', [
            'description' => $siteName ? 'Scoped to '.$siteName : 'All sites', 'category' => 'API',
        ]);
    }

    public static function apiKeyRevoked(string $accountId, string $name): void
    {
        static::record($accountId, 'api_key_revoked', 'Revoked API key “'.$name.'”', ['category' => 'API']);
    }

    /** A token-authenticated write (POST/PATCH/DELETE) — recorded from the auth middleware. */
    public static function apiCall(string $accountId, string $actorId, string $method, string $path): void
    {
        static::record($accountId, 'api_call', strtoupper($method).' '.$path, [
            'actor_id' => $actorId, 'category' => 'API',
        ]);
    }
}
