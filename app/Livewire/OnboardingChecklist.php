<?php

namespace App\Livewire;

use App\Support\Onboarding;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The get-started checklist on the sites dashboard. Steps auto-complete from the
 * user's real data (App\Support\Onboarding). Hidden once the user dismisses it
 * (or was backfilled as already-onboarded).
 */
class OnboardingChecklist extends Component
{
    public bool $open = false;

    public function mount(): void
    {
        $this->open = Auth::check() && ! Auth::user()->onboardingDismissed();
    }

    /** Re-render when the welcome finishes or a site is created. */
    #[On('onboarding-updated')]
    public function refresh(): void {}

    public function dismiss(): void
    {
        Auth::user()->setOnboarding(['dismissed_at' => now()->toIso8601String()]);
        $this->open = false;
    }

    /** Ask the sites component to open its create modal. */
    public function openCreate(): void
    {
        $this->dispatch('open-create-site');
    }

    public function render()
    {
        $user = Auth::user();
        $steps = $user ? Onboarding::steps($user) : [];
        $progress = $user ? Onboarding::progress($user) : ['done' => 0, 'total' => 0, 'complete' => false];

        // Plan/trial context for the welcome hero's subscription card.
        $sub = $user?->currentSubscription();
        $plan = $sub ? [
            'label' => $sub->badgeLabel(),
            'tier' => $sub->tier()['name'] ?? 'Free',
            'on_trial' => $sub->onTrial(),
            'expired' => $sub->trialExpired(),
            'days_left' => $sub->onTrial() ? $sub->trialDaysLeft() : null,
        ] : null;

        return view('livewire.onboarding-checklist', [
            'steps' => $steps,
            'progress' => $progress,
            'firstName' => $user ? trim(explode(' ', (string) $user->name)[0]) : '',
            'plan' => $plan,
        ]);
    }
}
