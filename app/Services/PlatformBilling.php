<?php

namespace App\Services;

use App\Mail\TutorialWelcome;
use App\Models\AccountSubscription;
use App\Models\User;
use App\Modules\Network\Contracts\ReferralBillingService;
use App\Modules\Network\NetworkStripe;
use App\Services\Domains\DomainPurchase;
use App\Services\Email\BusinessEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
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

    /** Inline card form available: the Payment Element needs the publishable key too. */
    public function inlineConfigured(): bool
    {
        return $this->configured() && (string) config('services.stripe_platform.key') !== '';
    }

    /** The account's Stripe customer (created on first payment). */
    public function customerFor(User $user): string
    {
        $sub = $user->currentSubscription();
        if ($sub->stripe_customer_id) {
            return $sub->stripe_customer_id;
        }
        $customer = $this->client()->customers->create([
            'email' => $user->email, 'name' => $user->name, 'metadata' => ['user_id' => $user->id],
        ]);
        $sub->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /** The fixed Stripe product for a plan (subscriptions need a product id). */
    public function planProduct(string $plan): string
    {
        $id = 'olux_plan_'.$plan;
        try {
            return $this->client()->products->retrieve($id)->id;
        } catch (InvalidRequestException) {
            return $this->client()->products->create(['id' => $id, 'name' => 'Olux '.config("plans.tiers.{$plan}.name", $plan).' hosting plan'])->id;
        }
    }

    /**
     * Start paying for a plan ON THE PAGE (Stripe Payment Element): an
     * incomplete monthly subscription whose first invoice the card form
     * confirms. A still-payable subscription for the same plan is reused, so
     * reopening the form never opens a second one.
     *
     * @return array{subscription: string, secret: string, amount: int}
     */
    public function preparePlanPayment(User $user, string $plan, ?string $reuse = null): array
    {
        $stripe = $this->client();
        if ($reuse) {
            try {
                $existing = $stripe->subscriptions->retrieve($reuse, ['expand' => ['latest_invoice.confirmation_secret']]);
                if ($existing->status === 'incomplete' && ($existing->metadata->plan ?? null) === $plan
                    && ($secret = $existing->latest_invoice->confirmation_secret->client_secret ?? null)) {
                    return ['subscription' => $existing->id, 'secret' => $secret, 'amount' => (int) ($existing->latest_invoice->amount_due ?? 0)];
                }
            } catch (\Throwable) {
                // gone or unreadable — start a fresh one below
            }
        }

        $cents = max(1, $user->currentSubscription()->priceFor($plan));
        $subscription = $stripe->subscriptions->create([
            'customer' => $this->customerFor($user),
            'items' => [['price_data' => [
                'currency' => 'gbp',
                'product' => $this->planProduct($plan),
                'unit_amount' => $cents,
                'recurring' => ['interval' => 'month'],
            ]]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'metadata' => ['kind' => 'plan', 'user_id' => $user->id, 'plan' => $plan],
            'expand' => ['latest_invoice.confirmation_secret'],
        ]);

        return [
            'subscription' => $subscription->id,
            'secret' => (string) $subscription->latest_invoice->confirmation_secret->client_secret,
            'amount' => (int) ($subscription->latest_invoice->amount_due ?? $cents),
        ];
    }

    /**
     * After the card form confirmed: check the subscription SERVER-side and
     * activate the plan when its first invoice is paid. Idempotent with the
     * invoice.paid webhook. Returns active | processing | unpaid.
     */
    public function completePlanPayment(User $user, string $subscriptionId): string
    {
        $stripe = $this->client();
        $subscription = $stripe->subscriptions->retrieve($subscriptionId, ['expand' => ['latest_invoice']]);
        if ((string) ($subscription->metadata->user_id ?? '') !== (string) $user->id) {
            return 'unpaid';
        }
        if (in_array($subscription->status, ['active', 'trialing'], true) || ($subscription->latest_invoice->status ?? null) === 'paid') {
            $this->activateSubscription($user, (string) $subscription->metadata->plan, $subscription);

            return 'active';
        }

        return $subscription->status === 'incomplete' && ($subscription->latest_invoice->status ?? null) === 'open' ? 'processing' : 'unpaid';
    }

    /**
     * Already on a paid plan with a card on file: move the existing Stripe
     * subscription to the new plan's price — no second form.
     *
     * UPGRADE (dearer plan): the prorated difference is invoiced and charged
     * to the saved card NOW; if the card is declined Stripe rejects the update
     * (error_if_incomplete), the plan does NOT change, and the CardException
     * propagates so the page can ask for another card.
     * DOWNGRADE: the unused difference is credited on the next invoice.
     */
    public function switchPaidPlan(User $user, string $plan): bool
    {
        $sub = $user->currentSubscription();
        if (! $sub->stripe_subscription_id || $sub->status !== 'active' || $sub->plan === 'trial') {
            return false;
        }
        $stripe = $this->client();
        $current = $stripe->subscriptions->retrieve($sub->stripe_subscription_id);
        if (! in_array($current->status, ['active', 'trialing'], true) || empty($current->items->data[0]->id)) {
            return false;
        }
        $newCents = max(1, $sub->priceFor($plan));
        $upgrade = $newCents > (int) ($current->items->data[0]->price->unit_amount ?? $sub->priceFor($sub->plan));
        $stripe->subscriptions->update($current->id, [
            'items' => [['id' => $current->items->data[0]->id, 'price_data' => [
                'currency' => 'gbp',
                'product' => $this->planProduct($plan),
                'unit_amount' => $newCents,
                'recurring' => ['interval' => 'month'],
            ]]],
            'proration_behavior' => $upgrade ? 'always_invoice' : 'create_prorations',
            'payment_behavior' => $upgrade ? 'error_if_incomplete' : 'allow_incomplete',
            'metadata' => ['kind' => 'plan', 'user_id' => $user->id, 'plan' => $plan],
        ]);
        $this->activate($user, $plan, (object) ['customer' => $current->customer, 'subscription' => $current->id]);

        return true;
    }

    /** Webhook: invoice.paid on an on-page plan subscription. */
    public function fulfilPlanInvoice(object $invoice): void
    {
        $subId = $invoice->parent->subscription_details->subscription ?? ($invoice->subscription ?? null);
        $subId = is_object($subId) ? $subId->id : $subId;
        $meta = $invoice->parent->subscription_details->metadata ?? null;
        if (! $subId || ($meta->kind ?? null) !== 'plan') {
            return;
        }
        $user = User::find((string) ($meta->user_id ?? ''));
        if ($user && ($plan = (string) ($meta->plan ?? '')) !== '') {
            $this->activateSubscription($user, $plan, (object) ['id' => $subId, 'customer' => $invoice->customer ?? null]);
        }
    }

    /** Activate from a paid plan subscription and retire the one it replaces (no double billing). */
    private function activateSubscription(User $user, string $plan, object $subscription): void
    {
        $previous = $user->currentSubscription()->stripe_subscription_id;
        $this->activate($user, $plan, (object) ['customer' => $subscription->customer ?? null, 'subscription' => $subscription->id]);
        if ($previous && $previous !== $subscription->id) {
            try {
                $this->client()->subscriptions->cancel($previous);
            } catch (\Throwable $e) {
                report($e); // already cancelled / not found
            }
        }
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
            $this->fulfilPlanInvoice($event->data->object);
            // Referral Network: referral fees on this bill are now collected → pay the referrers.
            try {
                $inv = $event->data->object;
                app(ReferralBillingService::class)
                    ->collectForStripeInvoice((string) $inv->id, app(NetworkStripe::class)->invoiceItemIdsFrom($inv));
            } catch (\Throwable $e) {
                report($e);
            }
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
