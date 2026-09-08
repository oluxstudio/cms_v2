<?php

use App\Mail\VerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt as LivewireVolt;

uses(RefreshDatabase::class);

test('the register url lands on the register panel of the combined login screen', function () {
    $this->get('/register')->assertRedirect(route('start'));
    $this->get('/start')->assertRedirect(route('login', ['mode' => 'register']));
    $this->get('/login?mode=register')->assertOk()->assertSee("mode: 'register'", false);
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
    Mail::assertQueued(VerificationCode::class, function ($m) use (&$code) {
        $code = $m->code;

        return $m->hasTo('test@example.com');
    });

    // Step 2: entering the code creates the (verified) account and logs in.
    $component->set('code', $code)
        ->call('verifyCode')
        ->assertHasNoErrors()
        ->assertRedirect(route('start', absolute: false));

    $this->assertAuthenticated();
    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('throwaway mailboxes and unreachable phone numbers are refused before any code is sent', function () {
    Mail::fake();

    LivewireVolt::test('auth.login')
        ->set('name', 'Spam Bot')->set('registerPhone', '+44 7700 900000')
        ->set('registerEmail', 'x@mailinator.com')
        ->set('registerPassword', 'password123')->set('registerPasswordConfirmation', 'password123')
        ->call('startVerification')->assertHasErrors('registerEmail');

    LivewireVolt::test('auth.login')
        ->set('name', 'Real Person')->set('registerPhone', 'call me')
        ->set('registerEmail', 'real@example.com')
        ->set('registerPassword', 'password123')->set('registerPasswordConfirmation', 'password123')
        ->call('startVerification')->assertHasErrors('registerPhone');

    Mail::assertNothingQueued();
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
