<?php

namespace App\Services;

use App\Mail\TutorialWelcome;
use App\Models\AccountSubscription;
use App\Models\User;
use App\Services\Domains\DomainPurchase;
use App\Services\Email\BusinessEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

        // Stripe rejects sending BOTH customer and customer_email — even a null one.
        $who = $sub->stripe_customer_id
            ? ['customer' => $sub->stripe_customer_id]
            : ['customer_email' => $user->email];

        $session = $this->client()->checkout->sessions->create($who + [
            'mode' => 'subscription',
            // Ad-hoc price_data has no product tax code — opt out of Managed Payments.
            'managed_payments' => ['enabled' => false],
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
        if (! $session || (string) ($session->metadata->user_id ?? '') !== (string) $user->id) {
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
            if (($session->metadata->kind ?? '') === 'domain') {
                app(DomainPurchase::class)->fulfilFromWebhookSession($session);

                return;
            }
            if (($session->metadata->kind ?? '') === 'template') {
                app(TemplateCommerce::class)->fulfilFromSession($session);

                return;
            }
            $user = User::find((string) ($session->metadata->user_id ?? ''));
            $plan = (string) ($session->metadata->plan ?? '');
            if ($user && $plan !== '') {
                $this->activate($user, $plan, $session);
            }
        }

        // Domain purchases paid on the Go-live page (Payment Element).
        if ($event->type === 'payment_intent.succeeded') {
            app(DomainPurchase::class)->fulfilFromPaymentIntent($event->data->object);
        }
        if ($event->type === 'invoice.paid') {
            app(DomainPurchase::class)->fulfilFromInvoice($event->data->object);
        }

        if ($event->type === 'charge.refunded') {
            // Template sales on the platform account (domains/plans are refunded by hand).
            app(TemplateCommerce::class)->refundByPaymentIntent((string) ($event->data->object->payment_intent ?? ''));
        }

        if ($event->type === 'customer.subscription.deleted') {
            $stripeSub = $event->data->object;
            $sub = AccountSubscription::where('stripe_subscription_id', $stripeSub->id)->first();
            if ($sub) {
                $sub->update(['status' => 'cancelled']);
                // Business email: suspend (dashboard-only) and start the export window.
                if ($sub->user) {
                    app(BusinessEmail::class)->suspendAccount($sub->user);
                }
            }
        }
    }

    /**
     * Why the account can't move to $plan right now, or null. Moving to a plan
     * with fewer mailboxes than the account uses is blocked until mailboxes
     * are deleted to fit.
     */
    public function downgradeBlocker(User $user, string $plan): ?string
    {
        $sub = $user->currentSubscription();
        if ($sub->mailboxesFit($plan)) {
            return null;
        }
        $used = $sub->mailboxesUsed();
        $allowed = $sub->mailboxLimitOn($plan);
        $name = config("plans.tiers.{$plan}.name", $plan);

        return "You have {$used} business email ".Str::plural('mailbox', $used)." but {$name} includes {$allowed}. "
            .'Delete '.($used - $allowed).' '.Str::plural('mailbox', $used - $allowed).' first (their mail is deleted too), then switch plans.';
    }

    /** Flip the account onto the plan (idempotent) and remember the Stripe ids. */
    public function activate(User $user, string $plan, ?object $session = null): void
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

        // Back on a paid plan inside the export window: email comes back.
        if ($plan !== 'trial') {
            app(BusinessEmail::class)->restoreAccount($user);
        }

        if ($isNewActivation) {
            $tier = config("plans.tiers.{$plan}");
            Mail::to($user->email)
                ->send(new TutorialWelcome($user, $tier['name'] ?? ''));
        }
    }
}
