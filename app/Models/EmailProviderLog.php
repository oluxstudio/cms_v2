<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** One request to the email provider, for support. Secrets are redacted before saving. */
class EmailProviderLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['account_id', 'provider', 'action', 'method', 'path', 'request', 'response', 'http_status', 'duration_ms'];

    protected $casts = ['request' => 'array', 'response' => 'array'];
}
