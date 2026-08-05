<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteGithubSettings extends Model
{
    protected $fillable = [
        'site_id', 'token', 'owner', 'repo', 'branch', 'pages_url', 'last_pushed_at',
    ];

    protected $casts = [
        'token'          => 'encrypted',
        'last_pushed_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isConfigured(): bool
    {
        return filled($this->token) && filled($this->owner) && filled($this->repo);
    }

    /** Full https repo URL, e.g. https://github.com/owner/repo. */
    public function repoUrl(): ?string
    {
        return $this->isConfigured() ? "https://github.com/{$this->owner}/{$this->repo}" : null;
    }
}
