<?php

use App\Models\Site;
use App\Models\User;
use Livewire\Volt\Volt;

test('ticking several sites mints one scoped token per site', function () {
    $user = User::factory()->create();
    $a = Site::factory()->create(['user_id' => $user->id]);
    $b = Site::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);
    Volt::test('user-settings')
        ->set('new_token_name', 'Deploy')
        ->set('new_token_sites', [$a->id, $b->id])
        ->call('generateToken');

    $tokens = $user->apiTokens()->get();
    expect($tokens)->toHaveCount(2)
        ->and($tokens->pluck('site_id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

test('no sites ticked mints a single unscoped token', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    Volt::test('user-settings')
        ->set('new_token_name', 'CI')
        ->call('generateToken');

    $tokens = $user->apiTokens()->get();
    expect($tokens)->toHaveCount(1)
        ->and($tokens->first()->site_id)->toBeNull();
});

test("another user's site id is ignored when generating", function () {
    $user = User::factory()->create();
    $foreign = Site::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);
    Volt::test('user-settings')
        ->set('new_token_name', 'Sneaky')
        ->set('new_token_sites', [$foreign->id])
        ->call('generateToken');

    // The inaccessible site is filtered out → falls back to one unscoped token.
    expect($user->apiTokens()->whereNotNull('site_id')->count())->toBe(0)
        ->and($user->apiTokens()->count())->toBe(1);
});
