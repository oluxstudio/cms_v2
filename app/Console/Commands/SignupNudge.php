<?php

namespace App\Console\Commands;

use App\Mail\FinishSetup;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Recover abandoned signups: anyone who started the wizard, hasn't finished,
 * and has been idle for a day gets ONE "finish setting up" email.
 *
 *   php artisan signup:nudge [--hours=24] [--dry-run]
 */
class SignupNudge extends Command
{
    protected $signature = 'signup:nudge {--hours=24 : Idle time before nudging} {--dry-run : List without sending}';

    protected $description = 'Email users who abandoned the signup wizard';

    public function handle(): int
    {
        $idle = now()->subHours((int) $this->option('hours'));
        $sent = 0;

        User::whereNotNull('onboarding')
            ->where('onboarding', 'like', '%"wizard"%')
            ->orderBy('created_at')
            ->chunkById(200, function ($users) use ($idle, &$sent) {
                foreach ($users as $user) {
                    $w = (array) (($user->onboarding ?? [])['wizard'] ?? []);
                    if (! $w || ! empty($w['done']) || ! empty($w['nudged_at'])) {
                        continue;
                    }
                    $updated = isset($w['updated_at']) ? Carbon::parse($w['updated_at']) : $user->created_at;
                    if ($updated->gt($idle)) {
                        continue;
                    }

                    $this->line(" → {$user->email} (step {$w['step']}, idle since {$updated->diffForHumans()})");
                    if (! $this->option('dry-run')) {
                        Mail::to($user->email)->send(new FinishSetup($user, $w['business'] ?? null, (int) ($w['step'] ?? 1)));
                        $user->setOnboarding(['wizard' => $w + ['nudged_at' => now()->toIso8601String()]]);
                    }
                    $sent++;
                }
            });

        $this->info(($this->option('dry-run') ? 'Would nudge ' : 'Nudged ').$sent.' user(s).');

        return self::SUCCESS;
    }
}
