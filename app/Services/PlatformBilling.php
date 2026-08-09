<?php

namespace App\Services;

use App\Mail\TutorialWelcome;
use App\Models\AccountSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Platform subscription billing — charges ACCOUNT plan upgrades on the
 * PLATFORM Stripe account (services.stripe_platform), completely separate
 * from each site's own Stripe keys. Uses Checkout in subscription mode with
 * inline recurring price_data, honouring per-client price overrides.
 *
 * Without platform keys configured (local dev), the SubscriptionPage falls
 * back to instant plan switching.
 */
class PlatformBilling
{
    public function configured(): bool
    {
        return filled(config('services.stripe_platform.secret'));
    }

    public function client(): StripeClient
    {
        return new StripeClient([
            'api_key' => config('services.stripe_platform.secret'),
            'stripe_version' => config('services.stripe.api_version'),
        ]);
    }

    /** Hosted Checkout for a plan at the client's effective monthly price. */
    public function checkoutUrl(User $user, string $plan, string $backUrl): string
    {
        $sub = $user->currentSubscription();
        $tier = config("plans.tiers.{$plan}");
        $cents = $sub->priceFor($plan);

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $sub->stripe_customer_id ? null : $user->email,
            'customer' => $sub->stripe_customer_id,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'gbp',
                    'product_data' => ['name' => 'Olux '.$tier['name'].' plan'],
                    'unit_amount' => max(1, $cents),
                    'recurring' => ['interval' => 'month'],
                ],
                'quantity' => 1,
            ]],
            'metadata' => ['user_id' => $user->id, 'plan' => $plan, 'back' => $backUrl],
            'subscription_data' => ['metadata' => ['user_id' => $user->id, 'plan' => $plan]],
            'success_url' => route('account.subscription.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('account.subscription'),
        ]);

        return $session->url;
    }

    /**
     * Success-return path: verify the session SERVER-side and activate.
     * Idempotent — the webhook may have activated already. Returns the
     * back-URL stored at checkout time (or null when not paid/found).
     */
    public function activateFromSession(User $user, string $sessionId): ?string
    {
        $session = $this->client()->checkout->sessions->retrieve($sessionId);
        if (! $session || (int) ($session->metadata->user_id ?? 0) !== $user->id) {
            return null;
        }
        if (! in_array($session->payment_status, ['paid', 'no_payment_required'], true)) {
            return null;
        }
        $this->activate($user, (string) $session->metadata->plan, $session);

        return (string) ($session->metadata->back ?? '') ?: null;
    }

    /** Webhook: completed checkouts activate; cancelled subscriptions expire. */
    public function handleWebhook(string $payload, string $signature): void
    {
        $event = Webhook::constructEvent($payload, $signature, (string) config('services.stripe_platform.webhook_secret'));

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $user = User::find((int) ($session->metadata->user_id ?? 0));
            $plan = (string) ($session->metadata->plan ?? '');
            if ($user && $plan !== '') {
                $this->activate($user, $plan, $session);
            }
        }

        if ($event->type === 'customer.subscription.deleted') {
            $stripeSub = $event->data->object;
            AccountSubscription::where('stripe_subscription_id', $stripeSub->id)
                ->update(['status' => 'cancelled']);
        }
    }

    /** Flip the account onto the plan (idempotent) and remember the Stripe ids. */
    public function activate(User $user, string $plan, ?Session $session = null): void
    {
        if (! config("plans.tiers.{$plan}")) {
            return;
        }
        $sub = $user->currentSubscription();

        // A genuine new activation (not an idempotent re-run of the same active
        // plan) — used to send the welcome/tutorial email exactly once.
        $isNewActivation = ! ($sub->plan === $plan && $sub->status === 'active');

        $sub->update(array_filter([
            'plan' => $plan,
            'status' => 'active',
            'started_at' => $isNewActivation ? now() : null,
            'stripe_customer_id' => $session->customer ?? null,
            'stripe_subscription_id' => $session->subscription ?? null,
        ], fn ($v) => $v !== null));

        if ($isNewActivation) {
            $tier = config("plans.tiers.{$plan}");
            Mail::to($user->email)
                ->send(new TutorialWelcome($user, $tier['name'] ?? ''));
        }
    }
}
