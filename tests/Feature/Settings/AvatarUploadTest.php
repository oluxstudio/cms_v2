<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('picking a photo uploads it and makes it the avatar straight away, replacing the old file', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $this->actingAs($user);

    $c = Volt::test('user-settings')->set('photo', UploadedFile::fake()->image('me.png', 200, 200))
        ->assertHasNoErrors()
        ->assertDispatched('avatar-updated')
        ->assertSet('photo', null);

    $first = $user->fresh()->avatar;
    expect($first)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($first);
    expect($user->fresh()->avatarUrl())->toBe(Storage::disk('public')->url($first));

    $c->set('photo', UploadedFile::fake()->image('new.jpg', 200, 200))->assertHasNoErrors();
    $second = $user->fresh()->avatar;
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $c->call('removePhoto');
    expect($user->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

test('non-image and SVG files are rejected and the avatar is unchanged', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar' => null]);
    $this->actingAs($user);

    Volt::test('user-settings')
        ->set('photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))->assertHasErrors('photo')
        ->set('photo', UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'))->assertHasErrors('photo');

    expect($user->fresh()->avatar)->toBeNull();
});

test('avatarUrl handles uploaded paths and social-login URLs', function () {
    expect((new User(['avatar' => 'https://lh3.googleusercontent.com/a/x']))->avatarUrl())->toBe('https://lh3.googleusercontent.com/a/x')
        ->and((new User(['avatar' => 'avatars/abc.png']))->avatarUrl())->toBe(Storage::disk('public')->url('avatars/abc.png'))
        ->and((new User(['avatar' => null]))->avatarUrl())->toBeNull();
});
