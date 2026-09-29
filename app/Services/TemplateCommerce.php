<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Models\TemplatePurchase;
use App\Models\User;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;

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

    // ─────────────────────────────────────────────────────────────
    // Library (the account's owned templates)
    // ─────────────────────────────────────────────────────────────

    /**
     * Is the template in the account's library? Licence scope comes from
     * config('templates.licence_scope') — 'account' covers every site the
     * account owns. THE policy method: change scope logic only here.
     */
    public function inLibrary(?User $user, Template $template): bool
    {
        if (! $user) {
            return false;
        }
        if ($template->user_id === $user->id) {
            return true; // creators always own their work
        }

        // scope 'account': one entitlement row covers all the account's sites.
        return TemplateEntitlement::where('user_id', $user->id)
            ->where('template_id', $template->id)->exists();
    }

    /** Add a FREE template to the library. Idempotent; paid templates refused. */
    public function addFreeToLibrary(User $user, Template $template): TemplateEntitlement
    {
        abort_unless($template->isFree(), 422, 'This template is paid — buy it to add it to your library.');

        return TemplateEntitlement::firstOrCreate(
            ['user_id' => $user->id, 'template_id' => $template->id],
            ['source' => 'free'],
        );
    }

    /**
     * Stripe Checkout for a PAID template on the PLATFORM account — template
     * sales are our revenue, never the tenant's connected account.
     */
    public function checkoutUrl(User $user, Template $template, string $backUrl, string $siteName): string
    {
        abort_if($template->isFree(), 422, 'Free templates are added directly.');

        $session = app(PlatformBilling::class)->client()->checkout->sessions->create([
            'mode' => 'payment',
            'managed_payments' => ['enabled' => false],
            'customer_email' => $user->email,
            'metadata' => [
                'kind' => 'template', 'template_id' => $template->id,
                'user_id' => $user->id, 'back' => $backUrl,
            ],
            'success_url' => route('marketplace.template.success', [$siteName, $template->slug]).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $backUrl,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($template->currency ?: 'gbp'),
                    'product_data' => ['name' => $template->name.' template'],
                    'unit_amount' => (int) $template->price_cents,
                ],
                'quantity' => 1,
            ]],
        ]);

        return $session->url;
    }

    /**
     * Fulfil a completed template checkout — IDEMPOTENT on the session id, so
     * a twice-delivered webhook (or webhook + success-return) grants once.
     */
    public function fulfilFromSession(Session $session): ?TemplateEntitlement
    {
        $template = Template::find((string) ($session->metadata->template_id ?? ''));
        $user = User::find((string) ($session->metadata->user_id ?? ''));
        if (! $template || ! $user) {
            return null;
        }

        // Creator-published templates earn their creator a share (paid out
        // from /admin/sales); Olux Studio's own templates are all platform revenue.
        $price = (int) $template->price_cents;
        $creatorId = $this->creatorUserId($template);
        $fee = $creatorId ? $this->feeCents($price) : $price;

        $purchase = TemplatePurchase::firstOrCreate(
            ['stripe_checkout_session_id' => $session->id],
            [
                'uuid' => (string) Str::uuid(),
                'template_id' => $template->id,
                'template_version_id' => $template->latest_version_id,
                'user_id' => $user->id,
                'creator_user_id' => $creatorId,
                'price_cents' => $price,
                'currency' => $template->currency ?: 'gbp',
                'platform_fee_cents' => $fee,
                'creator_amount_cents' => $price - $fee,
                'stripe_payment_intent_id' => (string) ($session->payment_intent ?? ''),
                'status' => 'paid',
                'purchased_at' => now(),
            ],
        );

        return TemplateEntitlement::updateOrCreate(
            ['user_id' => $user->id, 'template_id' => $template->id],
            [
                'source' => 'purchase', 'purchase_id' => $purchase->id,
                'price_paid_cents' => (int) $template->price_cents,
                'stripe_session_id' => $session->id, 'purchased_at' => now(),
            ],
        );
    }

    /** The user who earns from sales of this template, or null when the platform owns it. */
    public function creatorUserId(Template $template): ?string
    {
        if ($template->source !== 'custom' || ! $template->user_id) {
            return null;
        }
        $owner = User::find($template->user_id);

        return $owner && ! $owner->isSuper() ? $owner->id : null;
    }

    /**
     * A platform-checkout template sale was refunded in Stripe: mark it,
     * take the template back out of the buyer's library. If the creator was
     * already paid for it, the next payout claws their share back.
     */
    public function refundByPaymentIntent(string $paymentIntentId): ?TemplatePurchase
    {
        if ($paymentIntentId === '') {
            return null;
        }
        $purchase = TemplatePurchase::where('stripe_payment_intent_id', $paymentIntentId)
            ->where('status', 'paid')->first();
        if (! $purchase) {
            return null;
        }
        $purchase->update(['status' => 'refunded', 'refunded_at' => now()]);
        $this->revokeForPurchase($purchase);

        return $purchase;
    }

    /** Platform fee (cents) for a price, from config('services.stripe_platform.fee_percent'). */
    public function feeCents(int $priceCents): int
    {
        $pct = (float) config('services.stripe_platform.fee_percent', 20);

        return (int) round($priceCents * $pct / 100);
    }
}
