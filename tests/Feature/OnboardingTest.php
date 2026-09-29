<?php

use App\Livewire\OnboardingChecklist;
use App\Livewire\SiteComponent;
use App\Livewire\WelcomeOnboarding;
use App\Models\AccountMember;
use App\Models\Component;
use App\Models\ContentVersion;
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

test('checklist steps are detected from real data, from site to live', function () {
    $user = onboardingUser();

    expect(collect(Onboarding::steps($user))->pluck('key')->all())
        ->toBe(['create_site', 'choose_template', 'update_content', 'get_domain', 'go_live'])
        ->and(Onboarding::progress($user))->toMatchArray(['done' => 0, 'total' => 5, 'complete' => false]);

    $site = Site::create(['user_id' => $user->id, 'name' => 'ob-'.uniqid(), 'domain' => 'ob-'.uniqid().'.test', 'owner' => $user->name, 'description' => 't']);
    $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
    $done = fn () => collect(Onboarding::steps($user->fresh()))->mapWithKeys(fn ($s) => [$s['key'] => $s['done']])->all();

    expect($done())->toMatchArray(['create_site' => true, 'choose_template' => false, 'update_content' => false, 'get_domain' => false, 'go_live' => false]);

    $site->update(['template' => 'graceway']);
    expect($done()['choose_template'])->toBeTrue();

    ContentVersion::create(['site_id' => $site->id, 'subject_type' => 'component', 'subject_id' => '1', 'payload' => [], 'label' => 'edit']);
    expect($done()['update_content'])->toBeTrue();

    $site->update(['domain_verified_at' => now()]);
    expect($done()['get_domain'])->toBeTrue();

    $site->update(['live' => true]);
    expect($done()['go_live'])->toBeTrue()
        ->and(Onboarding::progress($user->fresh())['complete'])->toBeTrue();
});

test('the intro pack shows once: the owner\'s first visit, then never again', function () {
    $user = onboardingUser();

    // First visit (and the rest of that session): shown.
    Livewire::actingAs($user)->test(OnboardingChecklist::class)->assertSet('open', true);
    Livewire::actingAs($user->fresh())->test(OnboardingChecklist::class)->assertSet('open', true);

    // A later session: gone for good.
    session()->forget(OnboardingChecklist::SESSION_KEY);
    Livewire::actingAs($user->fresh())->test(OnboardingChecklist::class)->assertSet('open', false);
    expect(($user->fresh()->onboarding ?? [])['intro_shown_at'] ?? null)->not->toBeNull();
});

test('invited teammates never get the owner intro pack', function () {
    $owner = onboardingUser();
    $mate = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $mate->id]);

    expect($mate->isAccountOwner())->toBeFalse();
    Livewire::actingAs($mate)->test(OnboardingChecklist::class)->assertSet('open', false);
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

    // Checklist reflects it: the site exists; the pages haven't been edited yet.
    $steps = collect(Onboarding::steps($user->fresh()))->keyBy('key');
    expect($steps['create_site']['done'])->toBeTrue()
        ->and($steps['update_content']['done'])->toBeFalse();
});
