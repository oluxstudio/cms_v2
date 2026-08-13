<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Reset a user's onboarding so the welcome modal + checklist re-trigger — for
 * testing the first-run experience. NB: the checklist reflects real data, so a
 * pristine run also needs an account with no sites.
 */
class OnboardingReset extends Command
{
    protected $signature = 'onboarding:reset {email : The user\'s email}';

    protected $description = 'Reset a user\'s onboarding state (re-trigger the first-run welcome + checklist).';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->update(['onboarding' => null]);
        $this->info("Onboarding reset for {$user->email} — the welcome + checklist will show again on next login.");

        return self::SUCCESS;
    }
}
