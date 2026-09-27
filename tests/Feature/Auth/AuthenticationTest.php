<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    $this->get('/login')->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    LivewireVolt::test('auth.login')
        ->set('loginEmail', $user->email)
        ->set('loginPassword', 'password')
        ->call('login')
        ->assertHasNoErrors()
        // Every sign-in lands on the select-site page (User::landingUrl);
        // the picker resumes a mid-wizard signup itself.
        ->assertRedirect(route('home'));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    LivewireVolt::test('auth.login')
        ->set('loginEmail', $user->email)
        ->set('loginPassword', 'wrong-password')
        ->call('login')
        ->assertHasErrors('loginEmail');

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
