<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * First-run welcome modal. Greets the new user, collects a light role + goal,
 * then steps out of the way. Renders nothing once the user has been welcomed
 * (or skipped), so it can live globally in the layout.
 */
class WelcomeOnboarding extends Component
{
    public bool $show = false;

    public string $role = '';

    public string $goal = '';

    public function mount(): void
    {
        $this->show = Auth::check() && Auth::user()->needsWelcome();
    }

    public function start(): void
    {
        Auth::user()->setOnboarding([
            'role' => $this->role ?: null,
            'goal' => $this->goal ?: null,
            'welcomed_at' => now()->toIso8601String(),
        ]);
        $this->show = false;
        $this->dispatch('onboarding-updated'); // refresh the checklist widget
    }

    public function skip(): void
    {
        Auth::user()->setOnboarding([
            'welcomed_at' => now()->toIso8601String(),
            'skipped' => true,
        ]);
        $this->show = false;
        $this->dispatch('onboarding-updated');
    }

    public function render()
    {
        return view('livewire.welcome-onboarding', [
            'roles' => config('onboarding.roles'),
            'goals' => config('onboarding.goals'),
            'firstName' => Auth::check() ? strtok((string) Auth::user()->name, ' ') : '',
        ]);
    }
}
