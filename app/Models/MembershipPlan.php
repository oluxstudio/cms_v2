<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    public const SETTINGS_KEY = '_settings';

    protected $fillable = ['key', 'data'];

    protected $casts = ['data' => 'array'];
}
