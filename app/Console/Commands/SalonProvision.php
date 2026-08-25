<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\Site;
use App\Models\User;
use App\Services\Blueprints\SalonBlueprint;
use Illuminate\Console\Command;

/**
 * Spin up a salon/barbershop demo site in seconds (the demo-led sales motion):
 *
 *   php artisan salon:provision demo-smithsbarbers --user=you@example.com
 *
 * Creates the site (if new) for the given user (default: the first super
 * admin) and applies the Salon & Barber blueprint. Re-running is safe.
 */
class SalonProvision extends Command
{
    protected $signature = 'salon:provision {site : Site name/slug} {--user= : Owner email (default: first super admin)}';

    protected $description = 'Create a site and apply the Salon & Barber blueprint';

    public function handle(SalonBlueprint $blueprint): int
    {
        $email = $this->option('user');
        $user = $email
            ? User::where('email', $email)->first()
            : User::where('is_super', true)->first();
        if (! $user) {
            $this->error($email ? "No user with email {$email}." : 'No super admin found — pass --user=email.');

            return self::FAILURE;
        }

        $name = str($this->argument('site'))->slug()->toString();
        $site = Site::where('name', $name)->first();
        if (! $site) {
            $site = Site::create([
                'name' => $name,
                'domain' => $name.'.test',
                'owner' => $user->name,
                'user_id' => $user->id,
            ]);
            $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
            $this->info("Created site {$name} for {$user->email}.");
        } else {
            $this->info("Site {$name} exists — re-applying the blueprint.");
        }

        $blueprint->apply($site);

        $this->info('Salon blueprint applied: '.
            $site->pages()->count().' pages · '.
            Service::where('site_id', $site->id)->count().' services · bookings enabled.');
        $this->line('Dashboard: '.url("/{$name}"));

        return self::SUCCESS;
    }
}
