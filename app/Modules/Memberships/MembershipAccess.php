<?php

namespace App\Modules\Memberships;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Marks one post / page / collection as members-only. `tier_ids` lists the
 * tiers that may see it — empty means any member with access.
 */
class MembershipAccess extends Model
{
    use HasUlids;

    public const TYPES = ['post', 'page', 'collection'];

    protected $table = 'membership_access';

    protected $fillable = ['site_id', 'content_type', 'content_id', 'tier_ids'];

    protected $casts = ['tier_ids' => 'array'];

    public function tierIds(): array
    {
        return array_values(array_filter((array) $this->tier_ids));
    }

    public function allowsTier(?string $tierId): bool
    {
        $ids = $this->tierIds();

        return $ids === [] || ($tierId !== null && in_array($tierId, $ids, true));
    }
}
