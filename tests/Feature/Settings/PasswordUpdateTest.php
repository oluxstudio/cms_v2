<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('password can be updated', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $this->actingAs($user);

    Volt::test('user-settings')
        ->set('current_password', 'password')
        ->set('new_password', 'new-password-123')
        ->set('new_password_confirm', 'new-password-123')
        ->call('savePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $this->actingAs($user);

    Volt::test('user-settings')
        ->set('current_password', 'wrong-password')
        ->set('new_password', 'new-password-123')
        ->set('new_password_confirm', 'new-password-123')
        ->call('savePassword');

    // Wrong current password keeps the old one (surfaced as an inline banner).
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
