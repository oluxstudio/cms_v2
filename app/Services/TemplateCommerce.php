<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Models\TemplatePurchase;
use App\Models\User;

/**
 * Entitlement logic for the marketplace (no Stripe dependency). Free templates are
 * gettable by anyone; paid ones require a completed purchase. Install is gated on
 * `entitled()`. Stripe flows (StripeConnect) call grant/revoke here on webhooks.
 */
class TemplateCommerce
{
    /** May this user install the template? Free → always; paid → owns an entitlement. */
    public function entitled(?User $user, Template $template): bool
    {
        if ($template->isFree()) {
            return true;
        }
        if (! $user) {
            return false;
        }
        // The creator can always install their own template.
        if ($template->user_id === $user->id) {
            return true;
        }

        return TemplateEntitlement::where('user_id', $user->id)
            ->where('template_id', $template->id)->exists();
    }

    /** Grant a free template to a user (no-op for paid). */
    public function grantFree(User $user, Template $template): ?TemplateEntitlement
    {
        if (! $template->isFree()) {
            return null;
        }

        return TemplateEntitlement::firstOrCreate(
            ['user_id' => $user->id, 'template_id' => $template->id],
            ['source' => 'free'],
        );
    }

    /** Grant from a paid purchase (called when a checkout completes). */
    public function grantFromPurchase(TemplatePurchase $purchase): TemplateEntitlement
    {
        return TemplateEntitlement::updateOrCreate(
            ['user_id' => $purchase->user_id, 'template_id' => $purchase->template_id],
            ['source' => 'purchase', 'purchase_id' => $purchase->id],
        );
    }

    /** Revoke a purchase-based entitlement (called on refund). */
    public function revokeForPurchase(TemplatePurchase $purchase): void
    {
        TemplateEntitlement::where('template_id', $purchase->template_id)
            ->where('user_id', $purchase->user_id)
            ->where('purchase_id', $purchase->id)
            ->delete();
    }

    /** Platform fee (cents) for a price, from config('services.stripe_platform.fee_percent'). */
    public function feeCents(int $priceCents): int
    {
        $pct = (float) config('services.stripe_platform.fee_percent', 20);

        return (int) round($priceCents * $pct / 100);
    }
}
