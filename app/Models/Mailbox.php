<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One mailbox (address) on an EmailDomain. Its password is never stored. */
class Mailbox extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id', 'email_domain_id', 'local_part', 'display_name', 'provider_reference', 'provider_state',
        'quota_gb', 'quota_used_mb', 'status', 'idempotency_key', 'created_by', 'error', 'synced_at',
    ];

    protected $casts = ['synced_at' => 'datetime'];

    public function scopeForAccount(Builder $q, string $accountId): Builder
    {
        return $q->where('account_id', $accountId);
    }

    public function emailDomain(): BelongsTo
    {
        return $this->belongsTo(EmailDomain::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MailboxAlias::class);
    }

    public function address(): string
    {
        return $this->local_part.'@'.$this->emailDomain->domain;
    }
}
