<?php

namespace App\Models;

use App\Access\Permissions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An account-scoped role: a named bundle of permission keys from the
 * config/permissions.php catalog. Access checks always go through
 * permissions, never the role name.
 */
class Role extends Model
{
    use HasUlids;

    protected $fillable = ['account_id', 'name', 'slug', 'description', 'permissions', 'is_system'];

    protected $casts = ['permissions' => 'array', 'is_system' => 'boolean'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(AccountMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** Does this role grant a permission? ('*' = everything) */
    public function allows(string $permission): bool
    {
        $perms = $this->permissions ?? [];

        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    /**
     * Ensure the default roles (config permissions.roles) exist for an
     * account, then return every role the account has. Called lazily —
     * first touch of the team page seeds them.
     */
    public static function forAccount(User $account)
    {
        foreach (Permissions::defaultRoles() as $slug => $tpl) {
            static::firstOrCreate(
                ['account_id' => $account->id, 'slug' => $slug],
                [
                    'name' => $tpl['name'],
                    'description' => $tpl['description'] ?? null,
                    'permissions' => $tpl['permissions'] ?? [],
                    'is_system' => true,
                ],
            );
        }

        return static::where('account_id', $account->id)->orderByDesc('is_system')->orderBy('name')->get();
    }

    /** Unique slug for a new custom role within an account. */
    public static function slugFor(string $accountId, string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $n = 1;
        while (static::where('account_id', $accountId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
