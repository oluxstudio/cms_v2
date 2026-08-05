<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplatePurchase;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Platform-level Stripe Connect for the template marketplace. Creators onboard an
 * Express account; buyers pay via a destination charge (platform fee +
 * payout to the creator); webhooks complete the purchase and grant entitlement.
 *
 * All Stripe calls are guarded by configured() so the app degrades gracefully when
 * platform keys aren't set (free templates keep working; paid is disabled).
 */
class StripeConnect
{
    public function __construct(private TemplateCommerce $commerce) {}

    public function configured(): bool
    {
        return ! empty(config('services.stripe_platform.secret'));
    }

    private function client(): StripeClient
    {
        if (! $this->configured()) {
            throw new RuntimeException('Marketplace payments are not configured.');
        }

        return new StripeClient([
            'api_key'        => config('services.stripe_platform.secret'),
            'stripe_version' => config('services.stripe.api_version'),
        ]);
    }

    // ── Creator onboarding ──────────────────────────────────────────────

    /** Create (if needed) the creator's Express account and return an onboarding URL. */
    public function onboardingLink(User $user, string $returnUrl, string $refreshUrl): string
    {
        $client = $this->client();

        if (! $user->stripe_account_id) {
            $account = $client->accounts->create([
                'type'         => 'express',
                'email'        => $user->email,
                'capabilities' => ['transfers' => ['requested' => true]],
                'metadata'     => ['user_id' => $user->id],
            ]);
            $user->update(['stripe_account_id' => $account->id]);
        }

        $link = $client->accountLinks->create([
            'account'     => $user->stripe_account_id,
            'return_url'  => $returnUrl,
            'refresh_url' => $refreshUrl,
            'type'        => 'account_onboarding',
        ]);

        return $link->url;
    }

    /** Refresh charges/payout-enabled status from Stripe. */
    public function syncAccount(User $user): void
    {
        if (! $user->stripe_account_id || ! $this->configured()) {
            return;
        }
        $account = $this->client()->accounts->retrieve($user->stripe_account_id);
        $user->update(['stripe_charges_enabled' => (bool) ($account->charges_enabled ?? false)]);
    }

    public function canSell(?User $user): bool
    {
        return $this->configured() && $user && $user->stripe_account_id && $user->stripe_charges_enabled;
    }

    // ── Checkout (buyer) ────────────────────────────────────────────────

    /** Start a Checkout Session for a paid template; returns the redirect URL. */
    public function checkout(User $buyer, Template $template, string $successUrl, string $cancelUrl): string
    {
        $creator = $template->user;
        if (! $creator || ! $this->canSell($creator)) {
            throw new RuntimeException('This template is not available for purchase right now.');
        }

        $price = (int) $template->price_cents;
        $fee   = $this->commerce->feeCents($price);

        $purchase = TemplatePurchase::create([
            'uuid'                 => (string) Str::uuid(),
            'template_id'          => $template->id,
            'template_version_id'  => $template->latest_version_id,
            'user_id'              => $buyer->id,
            'price_cents'          => $price,
            'currency'             => $template->currency ?: 'usd',
            'platform_fee_cents'   => $fee,
            'creator_amount_cents' => $price - $fee,
            'status'               => 'pending',
        ]);

        $session = $this->client()->checkout->sessions->create([
            'mode'        => 'payment',
            'line_items'  => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => $purchase->currency,
                    'unit_amount'  => $price,
                    'product_data' => ['name' => $template->name],
                ],
            ]],
            'payment_intent_data' => [
                'application_fee_amount' => $fee,
                'transfer_data'          => ['destination' => $creator->stripe_account_id],
            ],
            'customer_email' => $buyer->email,
            'metadata'       => ['purchase_uuid' => $purchase->uuid],
            'success_url'    => $successUrl,
            'cancel_url'     => $cancelUrl,
        ]);

        $purchase->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }

    // ── Webhooks ────────────────────────────────────────────────────────

    public function verifyWebhook(string $payload, string $sigHeader): \Stripe\Event
    {
        return Webhook::constructEvent($payload, $sigHeader, config('services.stripe_platform.webhook_secret'));
    }

    /** Dispatch a webhook event (or a hand-built one in tests). $object = data.object. */
    public function handleEvent(string $type, mixed $object): void
    {
        $obj = $this->toArray($object);

        match ($type) {
            'checkout.session.completed' => $this->completePurchase($obj),
            'charge.refunded'            => $this->refund((string) ($obj['payment_intent'] ?? '')),
            default                      => null,
        };
    }

    private function completePurchase(array $session): void
    {
        $uuid     = $session['metadata']['purchase_uuid'] ?? null;
        $purchase = $uuid
            ? TemplatePurchase::where('uuid', $uuid)->first()
            : TemplatePurchase::where('stripe_checkout_session_id', $session['id'] ?? '')->first();

        if (! $purchase || $purchase->status === 'paid') {
            return;
        }

        $purchase->update([
            'status'                   => 'paid',
            'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
            'purchased_at'             => now(),
        ]);
        $this->commerce->grantFromPurchase($purchase);
    }

    private function refund(string $paymentIntentId): void
    {
        if ($paymentIntentId === '') {
            return;
        }
        $purchase = TemplatePurchase::where('stripe_payment_intent_id', $paymentIntentId)
            ->where('status', 'paid')->first();
        if (! $purchase) {
            return;
        }
        $purchase->update(['status' => 'refunded']);
        $this->commerce->revokeForPurchase($purchase);
    }

    private function toArray(mixed $object): array
    {
        if (is_array($object)) {
            return $object;
        }
        if (is_object($object) && method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        return (array) $object;
    }
}
