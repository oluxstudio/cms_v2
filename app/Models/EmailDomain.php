<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Business email switched on for one of an account's verified domains. */
class EmailDomain extends Model
{
    use HasUlids;

    public const STATUSES = ['pending_dns', 'verifying', 'active', 'suspended', 'failed'];

    protected $fillable = [
        'account_id', 'site_id', 'domain', 'source', 'provider_reference', 'status',
        'mx_change_confirmed_at', 'dns_report', 'dns_attempts', 'dns_checked_at',
        'suspended_at', 'delete_after', 'error',
    ];

    protected $casts = [
        'dns_report' => 'array', 'mx_change_confirmed_at' => 'datetime', 'dns_checked_at' => 'datetime',
        'suspended_at' => 'datetime', 'delete_after' => 'datetime',
    ];

    /** Tenant scope: only this account's rows. */
    public function scopeForAccount(Builder $q, string $accountId): Builder
    {
        return $q->where('account_id', $accountId);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MailboxAlias::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** Is an address (mailbox or alias) on this domain already taken? */
    public function addressTaken(string $localPart, ?string $exceptAliasId = null): bool
    {
        $localPart = strtolower($localPart);

        return $this->mailboxes()->where('local_part', $localPart)->exists()
            || $this->aliases()->where('local_part', $localPart)->when($exceptAliasId, fn ($q) => $q->where('id', '!=', $exceptAliasId))->exists();
    }
}
