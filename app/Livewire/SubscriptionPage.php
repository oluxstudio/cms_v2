<?php

namespace App\Livewire;

use App\Services\AiQuota;
use App\Services\PlatformBilling;
use App\Support\PlanCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Stripe\Exception\CardException;

/**
 * Account subscription — the 5 plan tiers (free trial first) with instant
 * plan switching. Every paid plan is paid for: first plan → card form (or
 * hosted Checkout); upgrade → the difference is charged to the saved card now.
 */
class SubscriptionPage extends Component
{
    /** Where the user came FROM — upgrading returns them there. */
    public string $backUrl = '';

    /** Why the last plan change was refused (e.g. too many mailboxes for the new plan). */
    public ?string $blocker = null;

    /** Plan key whose detail panel is open (null = closed). */
    public ?string $viewingPlan = null;

    // ── On-page payment (Stripe Payment Element) for the plan being bought ──
    public ?string $payPlan = null;

    public ?string $paySubscription = null;

    public ?string $paySecret = null;

    public int $payAmount = 0;

    public ?string $payError = null;

    /** Set once the first payment went through — the panel shows a thank-you. */
    public ?string $paidPlan = null;

    /** Which side of the plan window shows: 'about' (details + buy) or 'specs' (every spec). */
    public string $planTab = 'about';

    public function viewPlan(string $plan, string $tab = 'about'): void
    {
        $this->viewingPlan = isset(config('plans.tiers')[$plan]) ? $plan : null;
        $this->planTab = $tab === 'specs' ? 'specs' : 'about';
    }

    public function closePlan(): void
    {
        // payPlan + paySubscription stay: reopening the same plan reuses its pending payment.
        $this->reset(['paySecret', 'payAmount', 'payError', 'paidPlan']);
        $this->viewingPlan = null;
    }

    public function mount(): void
    {
        $prev = url()->previous();
        // Only remember an in-app page that isn't this one; else fall back home.
        $this->backUrl = ($prev && $prev !== url()->current() && str_starts_with($prev, url('/')))
            ? $prev
            : route('home');

        // A plan chosen on the landing page (?plan= or the remembered intent)
        // opens straight to that tier's detail so the user can confirm & pay.
        $intended = request()->query('plan') ?: session('intended_plan');
        if ($intended && isset(config('plans.tiers')[$intended]) && $intended !== 'trial') {
            $this->viewingPlan = $intended;
        }
        session()->forget('intended_plan');
    }

    public function choose(string $plan)
    {
        $tiers = config('plans.tiers');
        if (! isset($tiers[$plan]) || $plan === 'trial') {
            return null; // the trial is entered automatically, never chosen
        }

        $user = Auth::user();
        $sub = $user->currentSubscription();
        if ($sub->plan === $plan && $sub->status === 'active') {
            return null;
        }

        $billing = app(PlatformBilling::class);
        if ($blocker = $billing->downgradeBlocker($user, $plan)) {
            $this->dispatch('toast', level: 'error', title: 'Delete mailboxes first', message: $blocker);
            $this->blocker = $blocker;

            return null;
        }

        if ($billing->configured() && $sub->priceFor($plan) > 0) {
            // Already paying monthly with a card on file → move that
            // subscription to the new plan (prorated), no second form.
            $upgrade = $sub->priceFor($plan) > $sub->priceFor($sub->plan);
            try {
                if ($billing->switchPaidPlan($user, $plan)) {
                    $this->viewingPlan = $plan;
                    $this->paidPlan = $plan;
                    $this->dispatch('toast', level: 'success', title: 'Plan changed',
                        message: 'You\'re on '.config("plans.tiers.{$plan}.name").' now — '.($upgrade
                            ? 'the difference for the rest of this month has been charged to your card.'
                            : 'the unused difference is credited on your next invoice.'));

                    return null;
                }
            } catch (CardException $e) {
                // Saved card declined for the upgrade: nothing changed — take a new card below.
                $this->dispatch('toast', level: 'error', title: 'Card declined',
                    message: 'Your saved card was declined, so your plan hasn\'t changed. Enter another card to upgrade.');
            } catch (\Throwable $e) {
                report($e);
            }

            // First paid plan: the card form opens right here, on the page.
            if ($billing->inlineConfigured()) {
                try {
                    $pay = $billing->preparePlanPayment($user, $plan, $this->payPlan === $plan ? $this->paySubscription : null);
                    $this->viewingPlan = $plan;
                    $this->payPlan = $plan;
                    $this->paySubscription = $pay['subscription'];
                    $this->paySecret = $pay['secret'];
                    $this->payAmount = $pay['amount'];
                    $this->payError = null;

                    return null;
                } catch (\Throwable $e) {
                    report($e);
                    $this->dispatch('toast', level: 'error', title: 'Payment unavailable',
                        message: 'The payment form could not be started — please try again shortly.');

                    return null;
                }
            }
        }

        // Fallback while the publishable key isn't set: hosted Stripe
        // Checkout (subscription mode) at the client's EFFECTIVE price.
        if ($billing->configured() && $sub->priceFor($plan) > 0) {
            try {
                return $this->redirect($billing->checkoutUrl($user, $plan, $this->backUrl ?: route('home')));
            } catch (\Throwable $e) {
                report($e);
                $this->dispatch('toast', level: 'error', title: 'Payment unavailable',
                    message: 'Checkout could not be started — please try again shortly.');

                return null;
            }
        }

        // A paid plan is never handed out without payment: with no platform
        // billing configured, only local development / tests switch instantly.
        if ($sub->priceFor($plan) > 0 && ! $billing->configured() && ! app()->environment('local', 'testing')) {
            report(new \RuntimeException('Platform billing is not configured (STRIPE_PLATFORM_SECRET) — paid plan refused.'));
            $this->dispatch('toast', level: 'error', title: 'Payment unavailable',
                message: 'We can\'t take payments right now, so your plan hasn\'t changed. Please try again shortly.');

            return null;
        }

        // Zero-priced (custom/free) plan, or local development: switch instantly.
        $billing->activate($user, $plan);

        return $this->redirect($this->backUrl ?: route('home'));
    }

    /** The card form confirmed — verify with Stripe and switch the plan on. */
    public function paymentConfirmed(): void
    {
        if (! $this->paySubscription) {
            return;
        }
        $state = app(PlatformBilling::class)->completePlanPayment(Auth::user(), $this->paySubscription);
        if ($state === 'active') {
            $this->paidPlan = $this->payPlan;
            $this->reset(['paySecret', 'payError']);
            $this->dispatch('toast', level: 'success', title: 'Welcome to '.config("plans.tiers.{$this->paidPlan}.name"),
                message: 'Your plan is active — a receipt is on its way.');

            return;
        }
        $this->payError = $state === 'processing'
            ? 'Your payment is being processed — your plan switches on as soon as it clears.'
            : 'The payment didn\'t go through — check your card details and try again.';
    }

    /** Back from the card form to the plan details. */
    public function cancelPay(): void
    {
        $this->reset(['paySecret', 'payAmount', 'payError']);
    }

    public function render()
    {
        $user = Auth::user();
        $sub = $user->currentSubscription();
        $tiers = PlanCatalog::publicTiers($sub->plan);
        $quota = app(AiQuota::class);

        return view('livewire.subscription-page', [
            'sub' => $sub,
            'tiers' => $tiers,
            'siteCount' => $user->sites()->count(),
            'storageUsed' => $sub->storageUsedBytes(),
            'aiUsed' => $quota->usedThisMonth($user),
            'aiLimit' => $quota->limitFor($user),
            'stripeKey' => (string) config('services.stripe_platform.key'),
        ]);
    }
}
