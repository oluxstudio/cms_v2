<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Models\Page;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Todo;
use App\Models\Media;
use App\Observers\PageObserver;
use App\Observers\FormObserver;
use App\Observers\FormResponseObserver;
use App\Observers\TodoObserver;
use App\Observers\MediaObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Page::observe(PageObserver::class);
        Form::observe(FormObserver::class);
        FormResponse::observe(FormResponseObserver::class);
        Todo::observe(TodoObserver::class);
        Media::observe(MediaObserver::class);

        // RBAC: every catalog permission becomes a Gate — usable anywhere as
        // Gate::authorize('pages.manage', $site) or @can('pages.manage', $site).
        foreach (\App\Access\Permissions::keys() as $permission) {
            \Illuminate\Support\Facades\Gate::define(
                $permission,
                fn (\App\Models\User $user, \App\Models\Site $site) => $site->allows($user, $permission),
            );
        }

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('instagram', \SocialiteProviders\Instagram\Provider::class);
            $event->extendSocialite('tiktok', \SocialiteProviders\TikTok\Provider::class);
        });

        $this->defineApiRateLimits();
    }

    /**
     * Tiered API rate limits — one flat limit invites both abuse (writes too
     * loose) and breakage (reads too tight). Applied per group in routes/api.php.
     */
    private function defineApiRateLimits(): void
    {
        $limiter = \Illuminate\Support\Facades\RateLimiter::class;

        // Baseline for EVERY /api route (overrides the framework's 60/min):
        // generous enough for static-site builds, still a real ceiling.
        $limiter::for('api', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($request->ip()));
        $limiter::for('public-read', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($request->ip()));
        $limiter::for('leads', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip()));
        $limiter::for('booking-write', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(6)->by($request->ip()));
        $limiter::for('engagement', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip()));
        $limiter::for('token-api', function ($request) {
            $token = \App\Models\ApiToken::findByBearer($request->bearerToken());

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($token ? 'tok:'.$token->id : $request->ip());
        });
    }
}
