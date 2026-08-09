<?php

namespace App\Livewire;

use App\Services\PlatformBilling;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Account subscription — the 5 plan tiers (free trial first) with instant
 * plan switching. Payment collection is the wiring point marked in choose().
 */
class SubscriptionPage extends Component
{
    /** Where the user came FROM — upgrading returns them there. */
    public string $backUrl = '';

    /** Plan key whose detail panel is open (null = closed). */
    public ?string $viewingPlan = null;

    public function viewPlan(string $plan): void
    {
        $this->viewingPlan = isset(config('plans.tiers')[$plan]) ? $plan : null;
    }

    public function closePlan(): void
    {
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

        // Real payment: hosted Stripe Checkout (subscription mode) at the
        // client's EFFECTIVE price (admin override or list price). Activation
        // happens on the verified success return and/or the webhook.
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

        // No platform billing configured (local dev) or a zero-priced custom
        // plan: switch instantly.
        $billing->activate($user, $plan);

        return $this->redirect($this->backUrl ?: route('home'));
    }

    public function render()
    {
        $sub = Auth::user()->currentSubscription();
        $tiers = collect(config('plans.tiers'))->sortBy('order');

        return view('livewire.subscription-page', ['sub' => $sub, 'tiers' => $tiers]);
    }
}
