<?php

use App\Livewire\OnboardingChecklist;
use App\Livewire\SiteComponent;
use App\Livewire\WelcomeOnboarding;
use App\Models\Component;
use App\Models\Form;
use App\Models\Site;
use App\Models\User;
use App\Support\Onboarding;
use Livewire\Livewire;

function onboardingUser(): User
{
    return User::factory()->create(['onboarding' => null]);
}

test('a brand-new user needs the welcome; a backfilled user does not', function () {
    $fresh = onboardingUser();
    expect($fresh->needsWelcome())->toBeTrue();

    $existing = User::factory()->create(['onboarding' => ['welcomed_at' => now()->toIso8601String(), 'dismissed_at' => now()->toIso8601String()]]);
    expect($existing->needsWelcome())->toBeFalse()
        ->and($existing->onboardingDismissed())->toBeTrue();
});

test('the welcome modal saves role + goal and stops showing', function () {
    $user = onboardingUser();

    Livewire::actingAs($user)->test(WelcomeOnboarding::class)
        ->assertSet('show', true)
        ->set('role', 'owner')
        ->set('goal', 'leads')
        ->call('start')
        ->assertSet('show', false);

    $user->refresh();
    expect($user->onboardingRole())->toBe('owner')
        ->and($user->onboardingGoal())->toBe('leads')
        ->and($user->needsWelcome())->toBeFalse();
});

test('skipping records skipped + welcomed_at', function () {
    $user = onboardingUser();

    Livewire::actingAs($user)->test(WelcomeOnboarding::class)->call('skip')->assertSet('show', false);

    $user->refresh();
    expect($user->needsWelcome())->toBeFalse()
        ->and($user->onboarding['skipped'])->toBeTrue();
});

test('checklist step detection tracks real data', function () {
    $user = onboardingUser();

    // No site → only 5 undone steps.
    $steps = collect(Onboarding::steps($user))->keyBy('key');
    expect($steps['create_site']['done'])->toBeFalse()
        ->and($steps['capture_leads']['done'])->toBeFalse();
    expect(Onboarding::progress($user))->toMatchArray(['done' => 0, 'total' => 5, 'complete' => false]);

    // Create a site + a form → two steps flip.
    $site = Site::create(['user_id' => $user->id, 'name' => 'ob-'.uniqid(), 'domain' => 'ob.test', 'owner' => $user->name, 'description' => 't']);
    $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
    Form::create(['site_id' => $site->id, 'name' => 'contact', 'fields' => [['key' => 'email', 'type' => 'email']], 'is_active' => true]);

    $steps = collect(Onboarding::steps($user->fresh()))->keyBy('key');
    expect($steps['create_site']['done'])->toBeTrue()
        ->and($steps['capture_leads']['done'])->toBeTrue()
        ->and($steps['add_content']['done'])->toBeFalse();
});

test('the checklist hides for a dismissed user and can be dismissed', function () {
    $user = onboardingUser();
    $user->setOnboarding(['welcomed_at' => now()->toIso8601String()]); // welcomed, not dismissed

    Livewire::actingAs($user->fresh())->test(OnboardingChecklist::class)
        ->assertSet('open', true)
        ->call('dismiss')
        ->assertSet('open', false);

    expect($user->fresh()->onboardingDismissed())->toBeTrue();

    // A dismissed user: the widget starts closed.
    Livewire::actingAs($user->fresh())->test(OnboardingChecklist::class)->assertSet('open', false);
});

test('the reset command re-triggers onboarding', function () {
    $user = User::factory()->create(['onboarding' => ['welcomed_at' => now()->toIso8601String(), 'dismissed_at' => now()->toIso8601String()]]);

    $this->artisan('onboarding:reset', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->onboarding)->toBeNull()
        ->and($user->fresh()->needsWelcome())->toBeTrue();
});

test('creating a site with sample content populates it and advances the checklist', function () {
    $user = onboardingUser();

    Livewire::actingAs($user)->test(SiteComponent::class)
        ->set('addSample', true)
        ->set('form.name', 'sample-'.uniqid())
        ->set('form.domain', 'sample.test')
        ->set('form.owner', $user->name)
        ->call('create')
        ->assertDispatched('onboarding-updated');

    $site = $user->sites()->latest('id')->first();
    expect($site)->not->toBeNull()
        ->and($site->pages()->where('url', '/about')->exists())->toBeTrue()
        ->and(Component::where('site_id', $site->id)->count())->toBeGreaterThanOrEqual(2)
        ->and($site->collections()->where('name', 'Testimonials')->exists())->toBeTrue()
        ->and($site->forms()->where('name', 'contact')->exists())->toBeTrue();

    // Testimonials collection groups 3 components.
    $col = $site->collections()->where('name', 'Testimonials')->first();
    expect($col->components()->count())->toBe(3);

    // Checklist reflects it.
    $steps = collect(Onboarding::steps($user->fresh()))->keyBy('key');
    expect($steps['create_site']['done'])->toBeTrue()
        ->and($steps['add_content']['done'])->toBeTrue()
        ->and($steps['capture_leads']['done'])->toBeTrue();
});
