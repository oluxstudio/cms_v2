<?php

use App\Livewire\AcceptInvitePage;
use App\Livewire\SiteTeamPage;
use App\Mail\TeamInvitationMail;
use App\Models\AccountMember;
use App\Models\Role;
use App\Models\Site;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function rbacAccount(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'rbac-demo-'.$owner->id,
        'domain' => 'rbac-demo.test', 'owner' => $owner->name, 'description' => 'test',
    ]);

    return [$owner, $site];
}

test('owner can send an email invitation with a role', function () {
    Mail::fake();
    [$owner, $site] = rbacAccount();
    $email = 'newhire-'.uniqid().'@example.com'; // testing DB persists across runs

    Livewire::actingAs($owner)
        ->test(SiteTeamPage::class, ['site' => $site])
        ->set('inviteEmail', $email)
        ->call('sendInvite');

    $invitation = TeamInvitation::where('account_id', $owner->id)->where('email', $email)->first();
    expect($invitation)->not->toBeNull()
        ->and($invitation->isValid())->toBeTrue();
    Mail::assertSent(TeamInvitationMail::class, fn ($mail) => $mail->hasTo($email));
});

test('accepting an invitation creates a verified user scoped to the account', function () {
    [$owner, $site] = rbacAccount();
    $role = Role::forAccount($owner)->firstWhere('slug', 'editor');
    $email = 'invitee-'.uniqid().'@example.com'; // testing DB persists across runs
    [$invitation, $plain] = TeamInvitation::issue($owner, $owner, $email, $role);

    Livewire::test(AcceptInvitePage::class, ['token' => $plain])
        ->set('name', 'Jesse Invitee')
        ->set('password', 'secret-pass-123')
        ->set('password_confirmation', 'secret-pass-123')
        ->call('acceptAndRegister');

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull(); // token link = verification

    $membership = AccountMember::where('account_id', $owner->id)->where('user_id', $user->id)->first();
    expect($membership?->role_id)->toBe($role->id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    // Access is limited to the client account: the member can reach the
    // account's site, and holds only the role's permissions there.
    expect($site->accessibleBy($user))->toBeTrue()
        ->and($site->allows($user, 'pages.manage'))->toBeTrue()
        ->and($site->allows($user, 'team.manage'))->toBeFalse();
});

test('an expired or reused invitation token is rejected', function () {
    [$owner] = rbacAccount();
    $role = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $email = 'late-'.uniqid().'@example.com';
    [$invitation, $plain] = TeamInvitation::issue($owner, $owner, $email, $role);
    $invitation->update(['expires_at' => now()->subDay()]);

    expect(TeamInvitation::findByToken($plain))->toBeNull();
    Livewire::test(AcceptInvitePage::class, ['token' => $plain])
        ->set('name', 'Too Late')
        ->set('password', 'secret-pass-123')
        ->set('password_confirmation', 'secret-pass-123')
        ->call('acceptAndRegister');
    expect(User::where('email', $email)->exists())->toBeFalse();
});

test('viewer role can open permitted pages but is blocked elsewhere', function () {
    [$owner, $site] = rbacAccount();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

    $this->actingAs($member)->get("/{$site->name}/pages")->assertOk();       // viewer holds pages.view
    $this->actingAs($member)->get("/{$site->name}/team")->assertForbidden(); // no team.manage
    $this->actingAs($member)->get("/{$site->name}/marketplace")->assertForbidden(); // no addons.manage
});

test('editing a roles permissions changes what its members can access', function () {
    [$owner, $site] = rbacAccount();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

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
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

    Livewire::actingAs($member)->test(SiteTeamPage::class, ['site' => $site])->assertStatus(403);
    Livewire::actingAs($owner)->test(SiteTeamPage::class, ['site' => $site])->assertStatus(200);
});
