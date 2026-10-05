<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An extra address delivering into a mailbox. Doesn't count toward the mailbox limit. */
class MailboxAlias extends Model
{
    use HasUlids;

    protected $fillable = ['mailbox_id', 'email_domain_id', 'local_part', 'provider_reference', 'status', 'error'];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function emailDomain(): BelongsTo
    {
        return $this->belongsTo(EmailDomain::class);
    }
}
