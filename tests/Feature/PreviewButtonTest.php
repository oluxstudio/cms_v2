<?php

use App\Livewire\SiteDashboard;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

test('with a url the button links to the preview; without one it says there is nothing to preview', function () {
    $live = Blade::render('<x-preview-button href="https://example.test/p" />');
    expect($live)->toContain('href="https://example.test/p"')->toContain('Live preview')->not->toContain('Nothing to preview');

    $empty = Blade::render('<x-preview-button :href="null" />');
    expect($empty)->toContain('Nothing to preview')
        ->toContain('background:var(--foreground)')
        ->toContain('aria-disabled="true"')
        ->not->toContain('<a ');
});

test('a site without a template has no visitor preview, and its dashboard says so', function () {
    $user = User::factory()->create();
    $site = Site::create(['user_id' => $user->id, 'name' => 'prev-'.uniqid(), 'domain' => 'prev-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

    expect($site->hasTemplate())->toBeFalse()
        ->and($site->visitorPreviewUrl())->toBeNull();

    Livewire::actingAs($user)->test(SiteDashboard::class, ['site' => $site])
        ->assertSee('Nothing to preview');

    $site->update(['template' => 'graceway']);
    expect($site->fresh()->hasTemplate())->toBeTrue();
});
