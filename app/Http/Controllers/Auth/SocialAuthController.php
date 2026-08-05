<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Unable to authenticate with ' . ucfirst($provider) . '. Please try again.',
            ]);
        }

        $user = User::where('social_type', $provider)
            ->where('social_id', $socialUser->getId())
            ->first();

        if (! $user) {
            // Check if email already exists (link accounts)
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                // Link social account to existing user
                $user->update([
                    'social_id'   => $socialUser->getId(),
                    'social_type' => $provider,
                    'avatar'      => $socialUser->getAvatar(),
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name'              => $socialUser->getName(),
                    'email'             => $socialUser->getEmail(),
                    'social_id'         => $socialUser->getId(),
                    'social_type'       => $provider,
                    'avatar'            => $socialUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);

                event(new Registered($user));
            }
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('home'));
    }

    private function validateProvider(string $provider): void
    {
        if (! in_array($provider, ['google', 'facebook', 'twitter', 'instagram', 'tiktok'])) {
            abort(404);
        }
    }
}
