<?php

namespace App\Modules\Memberships;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** One line of a member's history (joined, renewed, payment failed…). */
class MembershipEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['site_id', 'member_id', 'type', 'detail', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function label(): string
    {
        return match ($this->type) {
            'joined' => 'Joined',
            'activated' => 'Membership activated',
            'checkout_started' => 'Started checkout',
            'checkout_expired' => 'Checkout expired',
            'renewed' => 'Payment received',
            'payment_failed' => 'Payment failed',
            'cancel_requested' => 'Cancellation requested',
            'cancelled' => 'Cancelled',
            'tier_changed' => 'Tier changed',
            'magic_link' => 'Sign-in link sent',
            'signed_in' => 'Signed in',
            'added' => 'Added by the team',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }
}
