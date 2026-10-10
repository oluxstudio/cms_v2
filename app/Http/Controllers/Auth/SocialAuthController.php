<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Sign in — or create an account — with Google or Facebook. The same round
 * trip does both: an unknown social account becomes a new Olux account and
 * continues into the signup wizard (/start) to set up its business.
 */
class SocialAuthController extends Controller
{
    /** The only providers offered for Olux accounts. */
    public const PROVIDERS = ['google', 'facebook'];

    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        if (blank(config("services.{$provider}.client_id")) || blank(config("services.{$provider}.client_secret"))) {
            return redirect()->route('login')->withErrors([
                'email' => ucfirst($provider).' sign-in isn’t available yet. Please use your email instead.',
            ]);
        }

        // ?intent=signup → after OAuth, continue the signup wizard instead of the home page.
        session()->put('social.after', request()->query('intent') === 'signup' ? 'start' : null);

        return $this->driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        // The person pressed "Cancel" / "Not now" on the provider's screen.
        if (request()->filled('error') || request()->filled('error_code')) {
            return redirect()->route('login')->withErrors([
                'email' => ucfirst($provider).' sign-in was cancelled. You can try again or use your email instead.',
            ]);
        }

        try {
            $socialUser = $this->driver($provider)->user();
        } catch (\Throwable $e) {
            // Log the real reason (previously swallowed) so failures are diagnosable:
            //   invalid_client → wrong client secret; InvalidStateException → lost session/state.
            report($e);
            Log::warning('Social login failed', [
                'provider' => $provider,
                'type' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Unable to authenticate with '.ucfirst($provider).'. Please try again.',
            ]);
        }

        // Facebook accounts registered with a phone number have no email — we need one.
        if (blank($socialUser->getEmail())) {
            return redirect()->route('login')->withErrors([
                'email' => ucfirst($provider).' didn’t share an email address with us. Please sign up with your email instead.',
            ]);
        }

        $isNew = false;
        $user = User::where('social_type', $provider)
            ->where('social_id', $socialUser->getId())
            ->first();

        if (! $user) {
            // Check if email already exists (link accounts)
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                // Link social account to existing user
                // Keep a photo they already have; otherwise take the social one.
                $user->update(array_filter([
                    'social_id' => $socialUser->getId(),
                    'social_type' => $provider,
                    'avatar' => blank($user->avatar) ? $this->storeAvatar($socialUser->getAvatar()) : null,
                ]));
            } else {
                // Create new user
                $user = User::create([
                    'name' => $socialUser->getName() ?: Str::before($socialUser->getEmail(), '@'),
                    'email' => $socialUser->getEmail(),
                    'social_id' => $socialUser->getId(),
                    'social_type' => $provider,
                    'avatar' => $this->storeAvatar($socialUser->getAvatar()),
                    'email_verified_at' => now(),
                ]);

                event(new Registered($user));
                $isNew = true;
            }
        }

        Auth::login($user, remember: true);

        // New accounts (from the login page too) go on to set up their business.
        if (session()->pull('social.after') === 'start' || $isNew) {
            return redirect()->route('start');
        }

        return redirect()->intended($user->landingUrl());
    }

    /**
     * The provider driver, always returning to THIS app's callback (so a stale
     * *_REDIRECT_URI in .env can't send people to another host), and asking
     * only for what we use: name, email and profile picture.
     */
    private function driver(string $provider)
    {
        $driver = Socialite::driver($provider)->redirectUrl(route('social.callback', $provider));

        if ($provider === 'facebook') {
            // email + public_profile need no Meta App Review; gender/link (Socialite's defaults) do.
            $driver->scopes(['public_profile'])->fields(['name', 'email', 'picture.width(400)']);
        }

        return $driver;
    }

    /**
     * Copy the provider's profile picture into our own storage (avatars/ on the
     * public disk, same as an uploaded photo). Provider URLs are long, signed and
     * expire after a few weeks, so we never store the URL itself. Best effort:
     * any failure just leaves the account without a photo.
     */
    private function storeAvatar(?string $url): ?string
    {
        if (blank($url) || ! str_starts_with($url, 'https://')) {
            return null;
        }

        try {
            $res = Http::timeout(6)->get($url);
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][strtolower(trim(Str::before((string) $res->header('Content-Type'), ';')))] ?? null;
            $body = $res->body();
            if (! $res->successful() || ! $ext || $body === '' || strlen($body) > 4 * 1024 * 1024) {
                return null;
            }
            $path = 'avatars/'.Str::ulid()->toBase32().'.'.$ext;
            Storage::disk('public')->put($path, $body);

            return $path;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function validateProvider(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }
    }
}
