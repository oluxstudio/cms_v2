<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-site declarative module — an AI/admin-created capability backed by a
 * Collection (its entity store). Merged with built-in modules by ModuleRegistry.
 */
class Module extends Model
{
    use HasUlids;

    protected $fillable = [
        'site_id', 'key', 'name', 'description', 'icon', 'collection_id',
        'schema', 'capabilities', 'frontend', 'intents', 'created_by', 'enabled',
    ];

    protected $casts = [
        'schema' => 'array',
        'capabilities' => 'array',
        'frontend' => 'array',
        'intents' => 'array',
        'enabled' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function adminUrl(): string
    {
        return url($this->site->name.'/collections');
    }
}
