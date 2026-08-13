<?php

use App\Mail\VerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt as LivewireVolt;

uses(RefreshDatabase::class);

test('the register url lands on the combined login/register screen', function () {
    $this->get('/register')->assertRedirect(route('login'));
});

test('new users register in two steps: email a code, then verify to create the account', function () {
    Mail::fake();

    $component = LivewireVolt::test('auth.login')
        ->set('name', 'Test User')
        ->set('registerPhone', '+44 7700 900000')
        ->set('registerEmail', 'test@example.com')
        ->set('registerPassword', 'password123')
        ->set('registerPasswordConfirmation', 'password123')
        ->call('startVerification')
        ->assertHasNoErrors();

    // Step 1: no account yet, a code was emailed.
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
    $code = null;
    Mail::assertSent(VerificationCode::class, function ($m) use (&$code) {
        $code = $m->code;

        return $m->hasTo('test@example.com');
    });

    // Step 2: entering the code creates the (verified) account and logs in.
    $component->set('code', $code)
        ->call('verifyCode')
        ->assertHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('a wrong code does not create the account', function () {
    Mail::fake();

    $component = LivewireVolt::test('auth.login')
        ->set('name', 'Test User')
        ->set('registerPhone', '+44 7700 900000')
        ->set('registerEmail', 'nope@example.com')
        ->set('registerPassword', 'password123')
        ->set('registerPasswordConfirmation', 'password123')
        ->call('startVerification');

    $component->set('code', '000000')->call('verifyCode')->assertHasErrors('code');

    expect(User::where('email', 'nope@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});
