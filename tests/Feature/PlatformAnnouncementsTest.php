<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\PlatformAnnouncementsPage;
use App\Models\AccountActivityLog;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Site;
use App\Models\User;
use App\Services\Announcements;
use App\Services\Impersonation;
use App\Services\TwoFactor;
use Livewire\Livewire;

function anSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

beforeEach(function () {
    // Announcements are global: each test starts from none being active.
    Announcement::query()->update(['ends_at' => now()->subMinute()]);
    Announcements::flush();
});

test('everyone, plan and account audiences reach the right people', function () {
    $trial = User::factory()->create();
    $pro = User::factory()->create();
    $pro->currentSubscription()->update(['plan' => 'pro', 'status' => 'active']);
    $tag = uniqid();

    Announcement::create(['title' => "All {$tag}", 'audience' => 'all']);
    Announcement::create(['title' => "Pro {$tag}", 'audience' => 'plans', 'plans' => ['pro']]);
    Announcement::create(['title' => "Just trial {$tag}", 'audience' => 'accounts', 'account_ids' => [$trial->id]]);
    Announcement::create(['title' => "Later {$tag}", 'audience' => 'all', 'starts_at' => now()->addDay()]);
    Announcements::flush();

    $titles = fn (User $u) => app(Announcements::class)->activeFor($u)->pluck('title')->all();
    expect($titles($trial))->toEqualCanonicalizing(["All {$tag}", "Just trial {$tag}"])
        ->and($titles($pro))->toEqualCanonicalizing(["All {$tag}", "Pro {$tag}"]);
});

test('a dismissed banner stays hidden for that person only; non-dismissible ones cannot be dismissed', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $soft = Announcement::create(['title' => 'Soft '.uniqid(), 'audience' => 'all']);
    $hard = Announcement::create(['title' => 'Hard '.uniqid(), 'audience' => 'all', 'dismissible' => false]);
    Announcements::flush();

    $this->actingAs($a)->postJson(route('announcements.dismiss', $soft))->assertNoContent();
    $this->actingAs($a)->postJson(route('announcements.dismiss', $hard))->assertNoContent();

    expect(app(Announcements::class)->activeFor($a)->pluck('id')->all())->toBe([$hard->id])
        ->and(app(Announcements::class)->activeFor($b)->pluck('id'))->toContain($soft->id);
});

test('the banner shows on signed-in pages', function () {
    $user = User::factory()->create();
    $title = 'Maintenance tonight '.uniqid();
    Announcement::create(['title' => $title, 'audience' => 'all', 'level' => 'warning']);
    Announcements::flush();

    $this->actingAs($user)->get('/settings')->assertOk()->assertSee($title);
});

test('publishing from the admin validates, and can copy into site notifications', function () {
    $owner = User::factory()->create(['email' => 'an-'.uniqid().'@example.com']);
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'an-'.uniqid().'.test']);
    $title = 'New feature '.uniqid();

    Livewire::actingAs(anSuper())->test(PlatformAnnouncementsPage::class)
        ->call('create')
        ->set('form.audience', 'accounts')->set('form.emails', 'nobody-'.uniqid().'@example.com')
        ->set('form.title', $title)
        ->call('save')->assertHasErrors('form.emails')
        ->set('form.emails', $owner->email)
        ->set('form.add_to_alerts', true)
        ->call('save')->assertHasNoErrors();

    $a = Announcement::where('title', $title)->firstOrFail();
    expect($a->account_ids)->toBe([$owner->id])->and($a->alerts_sent_at)->not->toBeNull()
        ->and(Alert::where('site_id', $site->id)->where('dedupe_key', 'announce:'.$a->id)->exists())->toBeTrue();

    $this->actingAs(User::factory()->create())->get('/admin/announcements')->assertForbidden();
    $this->actingAs(anSuper())->withSession([EnsureSuperAdmin::SESSION_KEY => now()])->get('/admin/announcements')->assertOk()->assertSee($title);
});

test('impersonation: start, act as the client, stop back to admin, all recorded', function () {
    $admin = anSuper();
    $client = User::factory()->create();

    $this->actingAs($admin)->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->post(route('admin.impersonate', $client->id))->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($client);
    expect(session(Impersonation::ADMIN_KEY))->toBe($admin->id);

    // The client isn't an admin: the admin area bounces while viewing as them.
    $this->get('/admin')->assertRedirect();
    $this->assertAuthenticatedAs($client);

    $this->post(route('impersonate.stop'))->assertRedirect(route('admin.account', $client->id));
    $this->assertAuthenticatedAs($admin);
    expect(session()->has(Impersonation::ADMIN_KEY))->toBeFalse();

    $actions = AccountActivityLog::where('account_id', $client->id)->pluck('action')->all();
    expect($actions)->toContain('impersonation.started')->toContain('impersonation.stopped')
        ->not->toContain('login'); // no fake "Logged in" for the client
    expect(AccountActivityLog::where('account_id', $admin->id)->where('action', 'impersonation.started')->exists())->toBeTrue();
});

test('super admins cannot be impersonated and only supers can start', function () {
    $admin = anSuper();
    $otherAdmin = anSuper();
    $client = User::factory()->create();

    $this->actingAs($admin)->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->post(route('admin.impersonate', $otherAdmin->id))->assertRedirect();
    $this->assertAuthenticatedAs($admin);

    $this->actingAs($client)->post(route('admin.impersonate', User::factory()->create()->id))->assertForbidden();
});

test('a view-as session ends by itself after 60 minutes', function () {
    $admin = anSuper();
    $client = User::factory()->create();

    $this->actingAs($admin)->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->post(route('admin.impersonate', $client->id));
    $this->assertAuthenticatedAs($client);

    $this->travel(61)->minutes();
    $this->get('/settings')->assertRedirect(route('admin.accounts'));
    $this->assertAuthenticatedAs($admin);
});
