<?php

use App\Models\User;
use Livewire\Volt\Volt as LivewireVolt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the register url lands on the combined login/register screen', function () {
    $this->get('/register')->assertRedirect(route('login'));
});

test('new users can register', function () {
    LivewireVolt::test('auth.login')
        ->set('name', 'Test User')
        ->set('registerEmail', 'test@example.com')
        ->set('registerPassword', 'password123')
        ->set('registerPasswordConfirmation', 'password123')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});
