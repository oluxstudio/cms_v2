<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved snapshot of everything the user built: the page's content blocks,
 * the layout tree wrapping them, and the site theme. Per USER, capped by
 * their subscription (users.template_limit — one free slot by default).
 * Applying one loads it into the current page to keep modifying anytime.
 */
class BuilderTemplate extends Model
{
    protected $fillable = ['user_id', 'name', 'is_default', 'payload'];

    protected $casts = ['payload' => 'array', 'is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
