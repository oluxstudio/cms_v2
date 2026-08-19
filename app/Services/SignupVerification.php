<?php

namespace App\Services;

use App\Mail\VerificationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verify a new user's email BEFORE the account exists. A pending sign-up (name,
 * phone, email, encrypted password + a hashed code) lives only in the cache for
 * a few minutes; the User row is created by the caller once verify() succeeds.
 * No unverified users are ever written to the database.
 */
class SignupVerification
{
    private function key(string $token): string
    {
        return 'signup:'.$token;
    }

    /**
     * Stash a pending sign-up, email a code, return the lookup token.
     *
     * @param  array{name:string,phone:?string,email:string,password:string}  $data
     */
    public function start(array $data): string
    {
        $this->guardHourlyCap($data['email']);

        $token = Str::random(48);
        $code = $this->newCode();
        $ttl = (int) config('signup.ttl_minutes', 15);

        $this->sendCode($data['email'], $code, $ttl); // send first — if it fails, nothing is stored

        Cache::put($this->key($token), [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'password' => Crypt::encryptString($data['password']),
            'code_hash' => hash('sha256', $code),
            'attempts' => 0,
            'code_sent_at' => now()->timestamp,
            'expires_at' => now()->addMinutes($ttl)->timestamp,
        ], now()->addMinutes($ttl));

        return $token;
    }

    /**
     * Check the code; on success return the pending sign-up and forget it.
     *
     * @return array{name:string,phone:?string,email:string,password:string}
     */
    public function verify(string $token, string $code): array
    {
        $entry = Cache::get($this->key($token));
        if (! $entry) {
            throw ValidationException::withMessages(['code' => 'Your code has expired — please start over.']);
        }

        $entry['attempts']++;
        if ($entry['attempts'] > (int) config('signup.max_attempts', 5)) {
            Cache::forget($this->key($token));
            throw ValidationException::withMessages(['code' => 'Too many attempts — please start over.']);
        }

        if (! hash_equals($entry['code_hash'], hash('sha256', trim($code)))) {
            $this->reput($token, $entry);
            throw ValidationException::withMessages(['code' => 'That code is incorrect. Check your email and try again.']);
        }

        Cache::forget($this->key($token));

        return [
            'name' => $entry['name'],
            'phone' => $entry['phone'],
            'email' => $entry['email'],
            'password' => Crypt::decryptString($entry['password']),
        ];
    }

    /** Re-issue a fresh code for a pending sign-up (cooldown + hourly cap apply). */
    public function resend(string $token): void
    {
        $entry = Cache::get($this->key($token));
        if (! $entry) {
            throw ValidationException::withMessages(['code' => 'Your code has expired — please start over.']);
        }

        $cooldown = (int) config('signup.resend_cooldown_seconds', 60);
        $wait = $cooldown - (now()->timestamp - $entry['code_sent_at']);
        if ($wait > 0) {
            throw ValidationException::withMessages(['code' => "Please wait {$wait}s before requesting another code."]);
        }
        $this->guardHourlyCap($entry['email']);

        $code = $this->newCode();
        $this->sendCode($entry['email'], $code, (int) config('signup.ttl_minutes', 15));

        $entry['code_hash'] = hash('sha256', $code);
        $entry['code_sent_at'] = now()->timestamp;
        $this->reput($token, $entry);
    }

    /** Send the code, turning a mail-transport failure into a friendly error (no 500). */
    private function sendCode(string $email, string $code, int $ttl): void
    {
        try {
            Mail::to($email)->send(new VerificationCode($code, $ttl));
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'registerEmail' => "We couldn't send a code to {$email}. Please double-check the address and try again.",
            ]);
        }
    }

    private function newCode(): string
    {
        $len = (int) config('signup.code_length', 6);

        return str_pad((string) random_int(0, (10 ** $len) - 1), $len, '0', STR_PAD_LEFT);
    }

    /** Persist a mutated entry keeping the original expiry window. */
    private function reput(string $token, array $entry): void
    {
        $remaining = $entry['expires_at'] - now()->timestamp;
        if ($remaining <= 0) {
            Cache::forget($this->key($token));

            return;
        }
        Cache::put($this->key($token), $entry, now()->addSeconds($remaining));
    }

    private function guardHourlyCap(string $email): void
    {
        $rlKey = 'signup-code:'.sha1(Str::lower($email));
        if (RateLimiter::tooManyAttempts($rlKey, (int) config('signup.max_codes_per_hour', 5))) {
            throw ValidationException::withMessages([
                'registerEmail' => 'Too many verification codes requested for this email. Please try again later.',
            ]);
        }
        RateLimiter::hit($rlKey, 3600);
    }
}
