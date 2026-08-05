<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A pending, email-verified invitation into a client account. The mailed
 * token is the proof of email ownership — only its sha256 hash is stored.
 */
class TeamInvitation extends Model
{
    protected $fillable = ['account_id', 'invited_by', 'role_id', 'email', 'token', 'expires_at', 'accepted_at'];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isValid(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * Create (or refresh) the invitation for an email + role and return
     * [$invitation, $plainToken]. The plain token goes into the email and
     * is never persisted.
     */
    public static function issue(User $account, User $inviter, string $email, Role $role): array
    {
        $plain = Str::random(48);

        $invitation = static::updateOrCreate(
            ['account_id' => $account->id, 'email' => mb_strtolower(trim($email))],
            [
                'invited_by' => $inviter->id,
                'role_id' => $role->id,
                'token' => hash('sha256', $plain),
                'expires_at' => now()->addDays((int) config('permissions.invite_expiry_days', 7)),
                'accepted_at' => null,
            ],
        );

        return [$invitation, $plain];
    }

    /** Look up a LIVE invitation by the plain token from the email link. */
    public static function findByToken(string $plain): ?self
    {
        $invitation = static::where('token', hash('sha256', $plain))->first();

        return $invitation?->isValid() ? $invitation : null;
    }
}
