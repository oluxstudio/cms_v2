<?php

namespace App\Providers;

use App\Modules\Network\Contracts\ReferralBillingService;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\ReferralBilling;
use App\Modules\Network\ReferralManager;
use Illuminate\Support\ServiceProvider;

/** Referral Network: binds the engine + billing contracts and boots its observers/commands. */
class NetworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReferralService::class, ReferralManager::class);
        $this->app->singleton(ReferralBillingService::class, ReferralBilling::class);
    }

    public function boot(): void
    {
        // Conversion observers (engine) — registered by App\Modules\Network\ReferralManager::observe().
        if (method_exists(ReferralManager::class, 'observe')) {
            ReferralManager::observe();
        }
    }
}
