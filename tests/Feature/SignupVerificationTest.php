<?php

use App\Mail\VerificationCode;
use App\Models\User;
use App\Services\SignupVerification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

function startSignup(string $email = 'a@test.com'): array
{
    Mail::fake();
    $svc = app(SignupVerification::class);
    $token = $svc->start(['name' => 'Ada', 'phone' => '123', 'email' => $email, 'password' => 'secret123']);

    $code = null;
    Mail::assertSent(VerificationCode::class, function ($m) use (&$code, $email) {
        $code = $m->code;

        return $m->hasTo($email);
    });

    return [$svc, $token, $code];
}

test('start emails a code and creates no user', function () {
    [$svc, $token, $code] = startSignup();

    expect($token)->toBeString()
        ->and(strlen($code))->toBe(6)
        ->and(User::where('email', 'a@test.com')->exists())->toBeFalse();
});

test('verify returns the pending data (password decrypted) and forgets the token', function () {
    [$svc, $token, $code] = startSignup('bob@test.com');

    $data = $svc->verify($token, $code);
    expect($data)->toMatchArray(['name' => 'Ada', 'phone' => '123', 'email' => 'bob@test.com', 'password' => 'secret123']);

    // Token is single-use.
    expect(fn () => $svc->verify($token, $code))->toThrow(ValidationException::class);
});

test('a wrong code is rejected and dies after max attempts', function () {
    [$svc, $token, $code] = startSignup();

    for ($i = 0; $i < (int) config('signup.max_attempts'); $i++) {
        expect(fn () => $svc->verify($token, '000000'))->toThrow(ValidationException::class);
    }
    // Exhausted → the token is discarded, even the correct code now fails.
    expect(fn () => $svc->verify($token, $code))->toThrow(ValidationException::class);
});

test('an unknown/expired token throws', function () {
    expect(fn () => app(SignupVerification::class)->verify('nope', '123456'))->toThrow(ValidationException::class);
});

test('resend is blocked during the cooldown window', function () {
    [$svc, $token, $code] = startSignup();

    expect(fn () => $svc->resend($token))->toThrow(ValidationException::class); // just issued → cooldown
});

test('the code email carries the 6-digit code', function () {
    $mail = new VerificationCode('482915', 15);
    expect($mail->render())->toContain('482915');
});
