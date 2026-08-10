<?php

namespace App\Providers;

use App\Access\Permissions;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Media;
use App\Models\Page;
use App\Models\Site;
use App\Models\Todo;
use App\Models\User;
use App\Observers\FormObserver;
use App\Observers\FormResponseObserver;
use App\Observers\MediaObserver;
use App\Observers\PageObserver;
use App\Observers\TodoObserver;
use App\Services\AccountActivity;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Instagram\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        foreach (Permissions::keys() as $permission) {
            Gate::define(
                $permission,
                fn (User $user, Site $site) => $site->allows($user, $permission),
            );
        }

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('instagram', Provider::class);
            $event->extendSocialite('tiktok', \SocialiteProviders\TikTok\Provider::class);
        });

        // Account audit trail: record every login (form + social).
        Event::listen(function (Login $event) {
            if ($event->user instanceof User) {
                AccountActivity::loggedIn($event->user);
            }
        });

        $this->defineApiRateLimits();
    }

    /**
     * Tiered API rate limits — one flat limit invites both abuse (writes too
     * loose) and breakage (reads too tight). Applied per group in routes/api.php.
     */
    private function defineApiRateLimits(): void
    {
        $limiter = RateLimiter::class;

        // Baseline for EVERY /api route (overrides the framework's 60/min):
        // generous enough for static-site builds, still a real ceiling.
        $limiter::for('api', fn ($request) => Limit::perMinute(120)->by($request->ip()));
        $limiter::for('public-read', fn ($request) => Limit::perMinute(120)->by($request->ip()));
        $limiter::for('leads', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        $limiter::for('booking-write', fn ($request) => Limit::perMinute(6)->by($request->ip()));
        $limiter::for('engagement', fn ($request) => Limit::perMinute(30)->by($request->ip()));
        // Visitor-tracking beacon — page views can be frequent per visitor.
        $limiter::for('track', fn ($request) => Limit::perMinute(120)->by($request->ip()));
        $limiter::for('token-api', function ($request) {
            $token = ApiToken::findByBearer($request->bearerToken());

            return Limit::perMinute(120)->by($token ? 'tok:'.$token->id : $request->ip());
        });
    }
}
