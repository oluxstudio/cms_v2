<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\Onboarding;
use App\Support\PlanCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The first-login intro pack on the sites dashboard: what Olux is, plans at a
 * glance, and the get-started checklist (App\Support\Onboarding). Shown once;
 * the getting started guide page expands on it afterwards.
 */
class OnboardingChecklist extends Component
{
    public bool $open = false;

    public const SESSION_KEY = 'onboarding.intro-session';

    public function mount(): void
    {
        $this->open = self::shouldShow(Auth::user());
    }

    /**
     * The intro pack shows ONCE: during the account owner's first visit
     * (the rest of that browser session), never again afterwards — the
     * getting started guide covers it from then on. Teammates never see it.
     */
    public static function shouldShow(?User $user): bool
    {
        if (! $user || $user->onboardingDismissed() || ! $user->isAccountOwner()) {
            return false;
        }
        if (session(self::SESSION_KEY) === true) {
            return true;
        }
        if (filled(($user->onboarding ?? [])['intro_shown_at'] ?? null)) {
            return false;
        }
        $user->setOnboarding(['intro_shown_at' => now()->toIso8601String()]);
        session()->put(self::SESSION_KEY, true);

        return true;
    }

    /** The "Show introduction" button above the sites list reopens the panel. */
    #[On('show-intro')]
    public function reopen(): void
    {
        $this->open = true;
        $this->dispatch('intro-reopened');
    }

    /** Re-render when the welcome finishes or a site is created. */
    #[On('onboarding-updated')]
    public function refresh(): void {}

    public function dismiss(): void
    {
        Auth::user()->setOnboarding(['dismissed_at' => now()->toIso8601String()]);
        session()->forget(self::SESSION_KEY);
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
            'tiers' => PlanCatalog::publicTiers($sub?->plan),
            'planKey' => $sub?->plan,
            'steps' => $steps,
            'progress' => $progress,
            'firstName' => $user ? trim(explode(' ', (string) $user->name)[0]) : '',
            'plan' => $plan,
        ]);
    }
}
