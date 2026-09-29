<?php

use App\Features\FeatureRegistry;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\MarketplacePage;
use App\Livewire\PlatformAddonsPage;
use App\Models\PlatformSetting;
use App\Models\Site;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\ConfigOverlay;
use Livewire\Livewire;

function adSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

beforeEach(fn () => $this->settingsBefore = (int) PlatformSetting::max('id'));
afterEach(function () {
    PlatformSetting::where('id', '>', $this->settingsBefore)->delete();
    ConfigOverlay::refresh();
});

test('the add-ons catalogue is super-only', function () {
    $this->actingAs(User::factory()->create())->get('/admin/addons')->assertForbidden();
    $this->actingAs(adSuper())->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->get('/admin/addons')->assertOk()->assertSee(FeatureRegistry::get('bookings')['name']);
});

test('editing an add-on changes its wording, tier and default settings everywhere', function () {
    $key = 'donations';
    $settings = FeatureRegistry::get($key)['settings'] ?? [];
    $firstSetting = array_key_first($settings);

    $lw = Livewire::actingAs(adSuper())->test(PlatformAddonsPage::class)
        ->call('edit', $key)
        ->set('form.name', 'Giving')
        ->set('form.description', 'Accept gifts online.')
        ->set('form.tier', 'premium');
    if ($firstSetting && ($settings[$firstSetting]['type'] ?? 'text') === 'text') {
        $lw->set("form.defaults.{$firstSetting}", 'Changed default');
    }
    $lw->call('save')->assertHasNoErrors();

    $f = FeatureRegistry::get($key);
    expect($f['name'])->toBe('Giving')
        ->and($f['description'])->toBe('Accept gifts online.')
        ->and($f['tier'])->toBe('premium')
        // Untouched parts of the definition survive the overlay.
        ->and($f['nav'] ?? null)->toBe(ConfigOverlay::fileValue("features.{$key}")['nav'] ?? null);
    if ($firstSetting && ($settings[$firstSetting]['type'] ?? 'text') === 'text') {
        expect(FeatureRegistry::defaults($key)[$firstSetting])->toBe('Changed default');
    }

    Livewire::actingAs(adSuper())->test(PlatformAddonsPage::class)->call('resetAddon', $key);
    expect(FeatureRegistry::get($key)['name'])->not->toBe('Giving');
});

test('a hidden add-on disappears from sites that do not use it and cannot be switched on', function () {
    $owner = User::factory()->create();
    $using = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'ad-'.uniqid().'.test']);
    $other = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'ad-'.uniqid().'.test']);
    $using->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $other->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $using->enableFeature('polls');

    Livewire::actingAs(adSuper())->test(PlatformAddonsPage::class)->call('toggleAvailable', 'polls');
    expect(FeatureRegistry::get('polls')['hidden'])->toBeTrue();

    $keys = fn (Site $s) => collect(Livewire::actingAs($owner)->test(MarketplacePage::class, ['site' => $s])->instance()->features)->pluck('key');
    expect($keys($using))->toContain('polls')
        ->and($keys($other))->not->toContain('polls');

    Livewire::actingAs($owner)->test(MarketplacePage::class, ['site' => $other])->call('toggle', 'polls');
    expect(Site::find($other->id)->hasFeature('polls'))->toBeFalse();
});
