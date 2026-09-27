<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A template author shown in the store ("by Olux Studio"). */
class TemplateCreator extends Model
{
    protected $fillable = ['user_id', 'name', 'slug', 'bio', 'avatar_url'];

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'creator_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
