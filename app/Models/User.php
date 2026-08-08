<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasUlids;

    /** Super admin — sees and accesses everything in the CMS. */
    public function isSuper(): bool
    {
        return (bool) ($this->is_super ?? false);
    }

    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'social_id', 'social_type', 'avatar',
        'email_verified_at',
        'job_title', 'department', 'timezone', 'language',
        'date_format', 'theme',
        'notif_email', 'notif_inapp', 'notif_push',
        'template_limit',
        'two_factor_enabled',
        'stripe_account_id', 'stripe_charges_enabled',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notif_email' => 'boolean',
            'notif_inapp' => 'boolean',
            'notif_push' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'stripe_charges_enabled' => 'boolean',
        ];
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /** Sites this user is a member of, with their pivot role. */
    public function memberSites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /** The account's platform subscription — auto-provisions the free trial. */
    public function subscription(): HasOne
    {
        return $this->hasOne(AccountSubscription::class);
    }

    /** Current subscription, creating the free trial on first touch. */
    public function currentSubscription(): AccountSubscription
    {
        return $this->subscription()->firstOrCreate([], [
            'plan' => 'trial',
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays((int) config('plans.trial_days', 14)),
            'started_at' => now(),
        ]);
    }

    /** Sites this account owns. */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Account team / RBAC
    // ─────────────────────────────────────────────────────────────

    /** Memberships this user holds in OTHER client accounts. */
    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMember::class);
    }

    /** Members of THIS user's account (this user as the client/owner). */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(AccountMember::class, 'account_id');
    }

    /** Pending/accepted invitations issued by this account. */
    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'account_id');
    }

    /** Roles defined in this account. */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'account_id');
    }

    /** Per-request memo: account_id => ?AccountMember. */
    private array $membershipMemo = [];

    /** This user's membership in the given account, if any. */
    public function membershipIn(string $accountId): ?AccountMember
    {
        return $this->membershipMemo[$accountId]
            ??= $this->memberships()->with('role')->where('account_id', $accountId)->first();
    }

    /**
     * Does this user hold a permission inside the given client account?
     * Owners of the account and super admins implicitly hold everything;
     * members are checked against their role's permission list.
     */
    public function canInAccount(string $accountId, string $permission): bool
    {
        if ($this->isSuper() || $this->id === $accountId) {
            return true;
        }

        return (bool) $this->membershipIn($accountId)?->role?->allows($permission);
    }
}
