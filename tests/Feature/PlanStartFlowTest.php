<?php

use App\Mail\TutorialWelcome;
use App\Models\User;
use App\Services\PlatformBilling;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt as LivewireVolt;

test('guest choosing a plan is remembered and sent to sign up', function () {
    $this->get('/get-started/pro')
        ->assertRedirect(route('register'));

    expect(session('intended_plan'))->toBe('pro');
});

test('signed-in user choosing a plan goes to checkout with the plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/get-started/business')
        ->assertRedirect(route('account.subscription', ['plan' => 'business']));
});

test('an unknown plan is ignored', function () {
    $this->get('/get-started/not-a-plan')
        ->assertRedirect(route('register'));

    expect(session('intended_plan'))->toBeNull();
});

test('the tutorial page renders publicly', function () {
    $this->get('/tutorial')
        ->assertOk()
        ->assertSee('Get your first site live', false);
});

test('activating a paid plan sends the tutorial welcome email once', function () {
    Mail::fake();
    $user = User::factory()->create();
    $billing = app(PlatformBilling::class);

    $billing->activate($user, 'pro');
    Mail::assertSent(TutorialWelcome::class, 1);

    // Re-activating the same active plan does not resend.
    $billing->activate($user, 'pro');
    Mail::assertSent(TutorialWelcome::class, 1);
});

test('new users can register with a phone number (via the code step)', function () {
    Mail::fake();
    $email = 'jane'.uniqid().'@example.com';

    $component = LivewireVolt::test('auth.login')
        ->set('name', 'Jane Doe')
        ->set('registerPhone', '+44 7700 900123')
        ->set('registerEmail', $email)
        ->set('registerPassword', 'password123')
        ->set('registerPasswordConfirmation', 'password123')
        ->call('startVerification');

    $code = null;
    Mail::assertSent(App\Mail\VerificationCode::class, function ($m) use (&$code) {
        $code = $m->code;

        return true;
    });

    $component->set('code', $code)->call('verifyCode');

    expect(User::where('phone', '+44 7700 900123')->where('email', $email)->exists())->toBeTrue();
});
