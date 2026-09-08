<?php

use App\Livewire\SiteTeamPage;
use App\Mail\TeamInvitationMail;
use App\Models\AccountMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\Site;
use App\Models\TeamInvitation;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function rbacAccount(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'rbac-demo-'.$owner->id,
        'domain' => 'rbac-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);

    return [$owner, $site];
}

test('inviting a new email creates their account, scoped to this site, and mails a temporary password', function () {
    Mail::fake();
    [$owner, $site] = rbacAccount();
    $email = 'newhire-'.uniqid().'@example.com';

    Livewire::actingAs($owner)
        ->test(SiteTeamPage::class, ['site' => $site])
        ->set('inviteEmail', $email)
        ->call('sendInvite');

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password_changed_at)->toBeNull();

    $member = AccountMember::where('account_id', $owner->id)->where('user_id', $user->id)->first();
    expect($member?->site_id)->toBe($site->id);
    expect(TeamInvitation::where('account_id', $owner->id)->where('email', $email)->first()?->accepted_at)->not->toBeNull();

    $temp = null;
    Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $m) use ($email, &$temp) {
        $temp = $m->tempPassword;

        return $m->hasTo($email);
    });
    expect($temp)->not->toBeNull()
        ->and(Hash::check($temp, $user->password))->toBeTrue();
});

test('inviting an existing user adds site access without touching their password', function () {
    Mail::fake();
    [$owner, $site] = rbacAccount();
    $user = User::factory()->create(['password_changed_at' => now()]);
    $before = $user->password;

    Livewire::actingAs($owner)->test(SiteTeamPage::class, ['site' => $site])
        ->set('inviteEmail', $user->email)->call('sendInvite');

    expect($user->fresh()->password)->toBe($before)
        ->and(AccountMember::where('user_id', $user->id)->where('site_id', $site->id)->exists())->toBeTrue();
    Mail::assertQueued(TeamInvitationMail::class, fn ($m) => $m->hasTo($user->email) && $m->tempPassword === null);
});

test('duplicates are rejected but the same user can join a sibling site', function () {
    Mail::fake();
    [$owner, $site] = rbacAccount();
    $sibling = Site::create(['user_id' => $owner->id, 'name' => 'rbac-sib-'.uniqid(), 'domain' => 'sib-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 't']);
    $email = 'twice-'.uniqid().'@example.com';

    $page = Livewire::actingAs($owner)->test(SiteTeamPage::class, ['site' => $site]);
    $page->set('inviteEmail', $email)->call('sendInvite');
    $page->set('inviteEmail', $email)->call('sendInvite');
    expect($page->get('errorMessage'))->toContain('already has access');

    Livewire::actingAs($owner)->test(SiteTeamPage::class, ['site' => $sibling])
        ->set('inviteEmail', $email)->call('sendInvite');
    $user = User::where('email', $email)->first();
    expect(AccountMember::where('user_id', $user->id)->count())->toBe(2);
});

test('a site-scoped member can open only the invited site; account-wide members still see all', function () {
    Mail::fake();
    [$owner, $site] = rbacAccount();
    $sibling = Site::create(['user_id' => $owner->id, 'name' => 'rbac-other-'.uniqid(), 'domain' => 'oth-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 't']);
    $editor = Role::forAccount($owner)->firstWhere('slug', 'editor');

    $scoped = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $scoped->id, 'role_id' => $editor->id, 'site_id' => $site->id]);
    expect($site->accessibleBy($scoped))->toBeTrue()
        ->and($site->allows($scoped, 'pages.manage'))->toBeTrue()
        ->and($sibling->accessibleBy($scoped->fresh()))->toBeFalse()
        ->and($sibling->allows($scoped->fresh(), 'pages.manage'))->toBeFalse();
    $this->actingAs($scoped)->get("/{$site->name}/pages")->assertOk();
    $this->actingAs($scoped)->get("/{$sibling->name}/pages")->assertForbidden();

    $wide = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $wide->id, 'role_id' => $editor->id, 'site_id' => null]);
    expect($site->accessibleBy($wide))->toBeTrue()->and($sibling->accessibleBy($wide->fresh()))->toBeTrue();
});

test('an invited member logging in lands on the invited site dashboard, not the wizard', function () {
    Mail::fake();
    [$owner, $site] = rbacAccount();
    $email = 'landing-'.uniqid().'@example.com';
    Livewire::actingAs($owner)->test(SiteTeamPage::class, ['site' => $site])
        ->set('inviteEmail', $email)->call('sendInvite');

    $member = User::where('email', $email)->first();
    expect($member->landingUrl())->toBe(url("/{$site->name}/dashboard"));

    // /start refuses to wizard them.
    $this->actingAs($member)->get('/start')->assertRedirect(url("/{$site->name}/dashboard"));

    // The owner still gets owner behaviour.
    expect($owner->fresh()->landingUrl())->toBe(route('home'));
});

test('resend regenerates the temp password only while unchanged; revoke removes access', function () {
    Mail::fake();
    [$owner, $site] = rbacAccount();
    $email = 'resend-'.uniqid().'@example.com';
    $page = Livewire::actingAs($owner)->test(SiteTeamPage::class, ['site' => $site]);
    $page->set('inviteEmail', $email)->call('sendInvite');
    $user = User::where('email', $email)->first();
    $inv = TeamInvitation::where('email', $email)->first();

    $first = $user->password;
    $page->call('resendInvite', $inv->id);
    expect($user->fresh()->password)->not->toBe($first);

    // Once they've set their own password, resends never reset it.
    $user->update(['password_changed_at' => now()]);
    $kept = $user->fresh()->password;
    $page->call('resendInvite', $inv->id);
    expect($user->fresh()->password)->toBe($kept);

    $page->call('revokeInvite', $inv->id);
    expect(AccountMember::where('user_id', $user->id)->where('site_id', $site->id)->exists())->toBeFalse()
        ->and($site->fresh()->accessibleBy($user->fresh()))->toBeFalse();
});

test('old invite links redirect to the login page', function () {
    $this->get('/invite/some-old-token')->assertRedirect(route('login'));
});

test('viewer role can open permitted pages but is blocked elsewhere', function () {
    [$owner, $site] = rbacAccount();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id, 'site_id' => $site->id]);

    $this->actingAs($member)->get("/{$site->name}/pages")->assertOk();
    $this->actingAs($member)->get("/{$site->name}/team")->assertForbidden();
    $this->actingAs($member)->get("/{$site->name}/marketplace")->assertForbidden();
});

test('editing a roles permissions changes what its members can access', function () {
    [$owner, $site] = rbacAccount();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id, 'site_id' => $site->id]);

    expect($site->allows($member, 'team.manage'))->toBeFalse();
    $viewer->update(['permissions' => array_merge($viewer->permissions, ['team.manage'])]);
    expect($site->fresh()->allows($member->fresh(), 'team.manage'))->toBeTrue();
    $this->actingAs($member->fresh())->get("/{$site->name}/team")->assertOk();
});

test('a user with no membership gets 403 on account pages', function () {
    [, $site] = rbacAccount();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get("/{$site->name}/pages")->assertForbidden();
    expect($site->accessibleBy($stranger))->toBeFalse();
});

test('only team.manage holders can open the team page component', function () {
    [$owner, $site] = rbacAccount();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id, 'site_id' => $site->id]);

    Livewire::actingAs($member)->test(SiteTeamPage::class, ['site' => $site])->assertStatus(403);
    Livewire::actingAs($owner)->test(SiteTeamPage::class, ['site' => $site])->assertStatus(200);
});

test('sending an invite creates an onboarding task for the new member and notifies them', function () {
    Mail::fake();
    [$owner, $site] = rbacAccount();
    $email = 'newhire-'.uniqid().'@example.com';

    Livewire::actingAs($owner)
        ->test(SiteTeamPage::class, ['site' => $site])
        ->set('inviteEmail', $email)
        ->call('sendInvite');

    $user = User::where('email', $email)->first();
    $task = Todo::where('site_id', $site->id)->where('assigned_user_id', $user->id)->first();
    expect($task)->not->toBeNull()
        ->and($task->user_id)->toBe($owner->id)          // inviter owns + monitors it
        ->and($task->status)->toBe('open')
        ->and($task->title)->toContain($site->name)
        ->and($task->items()->count())->toBe(3);

    expect(Message::where('site_id', $site->id)
        ->where('recipient_id', $user->id)->where('sender_id', $owner->id)
        ->where('body', 'like', '%assigned a task%')->exists())->toBeTrue();
});
