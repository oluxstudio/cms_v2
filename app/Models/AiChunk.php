<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** One chunk of a site's own content in the tenant knowledge base (RAG). */
class AiChunk extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'source_type', 'source_id', 'chunk_no', 'content', 'embedding', 'content_hash'];

    protected $casts = ['embedding' => 'array'];
}
