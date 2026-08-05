<?php

use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('settings page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('user-settings')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('savePersonal')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull(); // new email must re-verify
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('user-settings')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('savePersonal')
        ->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});
