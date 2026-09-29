<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * "View as this client" for super admins: full access as the client, for up
 * to 60 minutes, recorded in both accounts' activity. Super admins can't be
 * impersonated. The admin's own session (and its 2FA check) comes back on stop.
 */
class Impersonation
{
    public const ADMIN_KEY = 'impersonator_id';

    public const STARTED_KEY = 'impersonation_started_at';

    public const MINUTES = 60;

    /** While true, the Login listener skips its "Logged in" audit entry. */
    public static bool $quiet = false;

    public function start(User $admin, User $target): void
    {
        if (! $admin->isSuper()) {
            throw new RuntimeException('Only super admins can view as a client.');
        }
        if ($target->is($admin) || $target->isSuper()) {
            throw new RuntimeException('You can\'t view as yourself or another super admin.');
        }
        if (self::active()) {
            throw new RuntimeException('Stop the current "view as" session first.');
        }

        AccountActivity::record($admin->id, 'impersonation.started', 'Started viewing as '.$target->name, [
            'category' => 'Security', 'meta' => ['target_id' => $target->id],
        ]);
        AccountActivity::record($target->id, 'impersonation.started', 'Olux support started viewing your account', [
            'category' => 'Security', 'actor_id' => $admin->id, 'meta' => ['admin_id' => $admin->id],
        ]);

        $this->switchTo($target);
        session()->put(self::ADMIN_KEY, $admin->id);
        session()->put(self::STARTED_KEY, now()->toIso8601String());
    }

    /** Returns the admin who was restored, or null if nothing was active. */
    public function stop(string $reason = 'stopped'): ?User
    {
        $adminId = session()->pull(self::ADMIN_KEY);
        session()->forget(self::STARTED_KEY);
        if (! $adminId || ! ($admin = User::find($adminId))) {
            return null;
        }
        $target = Auth::user();
        if ($target) {
            AccountActivity::record($target->id, 'impersonation.stopped', 'Olux support stopped viewing your account', [
                'category' => 'Security', 'actor_id' => $admin->id, 'meta' => ['admin_id' => $admin->id, 'reason' => $reason],
            ]);
        }
        AccountActivity::record($admin->id, 'impersonation.stopped', 'Stopped viewing as '.($target?->name ?? 'a client'), [
            'category' => 'Security', 'meta' => ['target_id' => $target?->id, 'reason' => $reason],
        ]);
        $this->switchTo($admin);

        return $admin;
    }

    private function switchTo(User $user): void
    {
        self::$quiet = true;
        try {
            Auth::login($user);
        } finally {
            self::$quiet = false;
        }
    }

    public static function active(): bool
    {
        return session()->has(self::ADMIN_KEY);
    }

    public static function minutesLeft(): int
    {
        $started = session(self::STARTED_KEY);

        return $started ? max(0, self::MINUTES - (int) now()->diffInMinutes(Carbon::parse($started))) : 0;
    }

    public static function expired(): bool
    {
        $started = session(self::STARTED_KEY);

        return $started && Carbon::parse($started)->addMinutes(self::MINUTES)->isPast();
    }
}
