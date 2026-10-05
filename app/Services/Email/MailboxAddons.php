<?php

namespace App\Services\Email;

use App\Models\User;
use App\Services\AccountActivity;
use App\Services\PlatformBilling;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Extra mailboxes on top of the plan, billed monthly as an extra item on the
 * account's Stripe subscription (config email.addon.stripe_price). Without a
 * configured price or a paid subscription, extras are granted by platform
 * admins instead (Admin › Accounts).
 */
class MailboxAddons
{
    public function __construct(private PlatformBilling $billing) {}

    public function available(User $account): bool
    {
        $sub = $account->currentSubscription();

        return $this->billing->configured()
            && filled(config('email.addon.stripe_price'))
            && filled($sub->stripe_subscription_id)
            && $sub->status === 'active' && $sub->plan !== 'trial';
    }

    /** Add $quantity mailboxes to the account's allowance (billed monthly). */
    public function buy(User $account, User $actor, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 50) {
            throw ValidationException::withMessages(['quantity' => 'Choose between 1 and 50 extra mailboxes.']);
        }
        if (! $this->available($account)) {
            throw ValidationException::withMessages(['quantity' => 'Extra mailboxes can\'t be bought online for this account yet — contact us and we\'ll add them.']);
        }

        $sub = $account->currentSubscription();
        $stripe = $this->billing->client();
        $newTotal = (int) $sub->extra_mailboxes + $quantity;

        if ($sub->mailbox_addon_item_id) {
            $stripe->subscriptionItems->update($sub->mailbox_addon_item_id, ['quantity' => $newTotal, 'proration_behavior' => 'create_prorations']);
        } else {
            $item = $stripe->subscriptionItems->create([
                'subscription' => $sub->stripe_subscription_id,
                'price' => config('email.addon.stripe_price'),
                'quantity' => $newTotal,
                'proration_behavior' => 'create_prorations',
            ], ['idempotency_key' => 'mailbox-addon:'.$sub->id.':'.$newTotal]);
            $sub->mailbox_addon_item_id = $item->id;
        }

        $sub->extra_mailboxes = $newTotal;
        $sub->save();

        AccountActivity::record($account->id, 'email.addon_bought', "Added {$quantity} extra ".Str::plural('mailbox', $quantity),
            ['actor_id' => $actor->id, 'category' => 'email', 'icon' => 'envelope', 'meta' => ['quantity' => $quantity, 'total_extra' => $newTotal]]);
    }
}
