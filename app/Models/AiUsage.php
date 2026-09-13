<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** One assistant turn's token spend, per tenant — feeds cost analytics. */
class AiUsage extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'ai_usage';

    protected $fillable = ['site_id', 'user_id', 'driver', 'model', 'input_tokens', 'output_tokens', 'cache_creation_tokens', 'cache_read_tokens', 'tool_calls', 'created_at'];
}
