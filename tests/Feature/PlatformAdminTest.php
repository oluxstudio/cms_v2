<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\AdminVerifyPage;
use App\Livewire\PlatformAccountPage;
use App\Livewire\PlatformDashboard;
use App\Models\AccountActivityLog;
use App\Models\Site;
use App\Models\SiteActivityLog;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function superAdmin(bool $enrolled = true): User
{
    $user = User::factory()->create(['is_super' => true]);
    if ($enrolled) {
        app(TwoFactor::class)->issueSecret($user);
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();
    }

    return $user->refresh();
}

function passedTwoFactor(): array
{
    return [EnsureSuperAdmin::SESSION_KEY => now()];
}

it('blocks guests and normal users from the admin area', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/admin/accounts')->assertForbidden();
});

it('sends a super admin without a fresh 2FA check to the verify page', function () {
    $this->actingAs(superAdmin())->get('/admin')
        ->assertRedirect();
    expect($this->actingAs(superAdmin())->get('/admin')->headers->get('Location'))
        ->toContain('/admin/verify');
});

it('lets a super admin with a fresh 2FA session open the dashboard', function () {
    $admin = superAdmin();
    User::factory()->count(2)->create();

    $this->actingAs($admin)->withSession(passedTwoFactor())
        ->get('/admin')->assertOk()->assertSee('Platform dashboard')->assertSee('Accounts');
});

it('enrolls and verifies with a real TOTP code', function () {
    $admin = superAdmin(enrolled: false);

    Livewire::actingAs($admin)->test(AdminVerifyPage::class)
        ->assertSet('enrolling', true)
        ->set('code', app(Google2FA::class)->getCurrentOtp(app(TwoFactor::class)->secretFor($admin->refresh())))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    expect($admin->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('rejects a wrong TOTP code', function () {
    $admin = superAdmin();

    Livewire::actingAs($admin)->test(AdminVerifyPage::class)
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors('code');
});

it('counts only accounts active within the window', function () {
    $admin = superAdmin();
    $recent = User::factory()->create();
    $stale = User::factory()->create();
    AccountActivityLog::create(['account_id' => $recent->id, 'actor_id' => $recent->id, 'action' => 'login', 'title' => 'Signed in', 'category' => 'Login', 'created_at' => now()->subDays(2)]);
    $old = AccountActivityLog::create(['account_id' => $stale->id, 'actor_id' => $stale->id, 'action' => 'login', 'title' => 'Signed in', 'category' => 'Login']);
    $old->forceFill(['created_at' => now()->subDays(20)])->save();

    $html = Livewire::actingAs($admin)->test(PlatformDashboard::class)->html();
    // 1 recent actor; the 20-day-old login must not count.
    expect($html)->toContain('Active accounts');
    $active = AccountActivityLog::where('created_at', '>=', now()->subDays(10))->distinct()->pluck('actor_id');
    expect($active)->toHaveCount(1)->and($active->first())->toBe($recent->id);
});

it('shows both account and site activity in the diary, scoped to the account', function () {
    $admin = superAdmin();
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $other = User::factory()->create();

    AccountActivityLog::create(['account_id' => $user->id, 'actor_id' => $user->id, 'action' => 'login', 'title' => 'Signed in from Chrome', 'category' => 'Login']);
    SiteActivityLog::create(['site_id' => $site->id, 'user_id' => $user->id, 'entity_type' => 'page', 'action' => 'created', 'title' => 'Created page About-Us-Diary']);
    AccountActivityLog::create(['account_id' => $other->id, 'actor_id' => $other->id, 'action' => 'login', 'title' => 'Other-Person-Login', 'category' => 'Login']);

    Livewire::actingAs($admin)->test(PlatformAccountPage::class, ['userId' => $user->id])
        ->assertSee('Signed in from Chrome')
        ->assertSee('Created page About-Us-Diary')
        ->assertDontSee('Other-Person-Login');
});

it('filters the diary to content-only entries', function () {
    $admin = superAdmin();
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    AccountActivityLog::create(['account_id' => $user->id, 'actor_id' => $user->id, 'action' => 'login', 'title' => 'Login-Row-Here', 'category' => 'Login']);
    SiteActivityLog::create(['site_id' => $site->id, 'user_id' => $user->id, 'entity_type' => 'page', 'action' => 'created', 'title' => 'Content-Row-Here']);

    Livewire::actingAs($admin)->test(PlatformAccountPage::class, ['userId' => $user->id])
        ->call('setFilter', 'content')
        ->assertSee('Content-Row-Here')
        ->assertDontSee('Login-Row-Here');
});

it('forbids the account detail page for non-supers', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)->get('/admin/accounts/'.$target->id)->assertForbidden();
});
