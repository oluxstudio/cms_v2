<?php

namespace App\Livewire;

use App\Access\Permissions;
use App\Mail\TeamInvitationMail;
use App\Models\AccountMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\Site;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\AccountActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Account team & access control. The team belongs to the CLIENT ACCOUNT that
 * owns this site (one team across all the account's sites): invite users by
 * email (verified via the invitation link), assign roles, and edit what each
 * role is allowed to do — permissions come from config/permissions.php.
 */
class SiteTeamPage extends Component
{
    public Site $site;

    /** The client account whose team this is (the site's owner). */
    public string $accountId;

    public string $tab = 'members'; // members | invites | roles

    // Invite form
    public string $inviteEmail = '';

    public ?string $inviteRoleId = null;

    // Role editor
    public ?string $editingRoleId = null;   // 0 = creating a new role

    public string $roleName = '';

    public string $roleDescription = '';

    /** permission key => bool */
    public array $rolePerms = [];

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'team.manage'), 403);
        $this->accountId = $site->user_id;

        // First touch seeds the default roles for this account.
        $roles = Role::forAccount($site->user);
        $this->inviteRoleId = $roles->firstWhere('slug', 'editor')?->id ?? $roles->first()?->id;
    }

    private function guard(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'team.manage'), 403);
    }

    // ── Members ──────────────────────────────────────────────────

    public function getAccountProperty(): User
    {
        return User::findOrFail($this->accountId);
    }

    public function getMembersProperty()
    {
        return AccountMember::with(['user', 'role'])
            ->where('account_id', $this->accountId)
            ->get()
            ->sortBy(fn ($m) => mb_strtolower($m->user->name ?? ''))
            ->values();
    }

    public function getRolesListProperty()
    {
        return Role::where('account_id', $this->accountId)->orderByDesc('is_system')->orderBy('name')->get();
    }

    public function getInvitationsProperty()
    {
        return TeamInvitation::with(['role', 'inviter'])
            ->where('account_id', $this->accountId)
            ->whereNull('accepted_at')
            ->latest()
            ->get();
    }

    public function updateMemberRole(string $memberId, string $roleId): void
    {
        $this->guard();
        $member = AccountMember::where('account_id', $this->accountId)->findOrFail($memberId);
        $role = Role::where('account_id', $this->accountId)->findOrFail($roleId);
        $member->update(['role_id' => $role->id]);
        $this->dispatch('toast', level: 'success', title: 'Role updated', message: ($member->user->name ?? 'Member').' is now '.$role->name.'.');
    }

    public function removeMember(string $memberId): void
    {
        $this->guard();
        $member = AccountMember::where('account_id', $this->accountId)->findOrFail($memberId);
        $name = $member->user->name ?? 'Member';
        $member->delete();
        $this->dispatch('toast', level: 'success', title: 'Removed', message: $name.' no longer has access to this account.');
    }

    // ── Invitations ──────────────────────────────────────────────

    public function sendInvite(): void
    {
        $this->guard();
        $this->errorMessage = '';
        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRoleId' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($this->inviteEmail));
        $role = Role::where('account_id', $this->accountId)->findOrFail($this->inviteRoleId);

        if ($email === mb_strtolower($this->account->email)) {
            $this->errorMessage = 'That is the account owner — they already have full access.';

            return;
        }

        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing && AccountMember::where('account_id', $this->accountId)->where('user_id', $existing->id)
            ->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $this->site->id))->exists()) {
            $this->errorMessage = $existing->name.' already has access to this site.';

            return;
        }

        // The invite CREATES the account: a brand-new address gets a user with
        // a temporary password (emailed); an existing user just gains access —
        // their password is never touched. Either way, all they do is log in.
        $temp = null;
        $user = $existing;
        if (! $user) {
            $temp = Str::password(12, symbols: false);
            $user = User::create([
                'name' => Str::of(Str::before($email, '@'))->replace(['.', '_', '-'], ' ')->title()->toString(),
                'email' => $email,
                'password' => Hash::make($temp),
                'email_verified_at' => now(),   // the owner vouches for the address
                'password_changed_at' => null,  // still on the temporary password
            ]);
        }

        // Access is scoped to THIS site only.
        AccountMember::create([
            'account_id' => $this->accountId, 'user_id' => $user->id,
            'role_id' => $role->id, 'site_id' => $this->site->id,
        ]);

        [$invitation] = TeamInvitation::issue($this->account, Auth::user(), $email, $role, $this->site->id);
        $invitation->update(['accepted_at' => now()]); // audit row — access already exists
        Mail::to($email)->send(new TeamInvitationMail($invitation, $this->site, $temp));
        AccountActivity::memberInvited($this->accountId, $email, $role->name);

        // Every new member starts with an onboarding task on the site's task
        // board, assigned to them and owned by the inviter — so the inviter
        // can monitor it like any other task, and the invitee lands with a
        // clear first step instead of an empty screen.
        $task = $this->site->todos()->create([
            'user_id' => Auth::id(),
            'assigned_user_id' => $user->id,
            'title' => 'Get set up on '.$this->site->name,
            'description' => 'Welcome aboard! Work through the checklist below, then mark this task done.',
            'priority' => 'normal',
            'status' => 'open',
            'due_at' => now()->addWeek()->endOfDay(),
        ]);
        foreach (['Log in and update your password', 'Fill in your profile', 'Look around the '.$this->site->name.' dashboard'] as $i => $label) {
            $task->items()->create(['label' => $label, 'sort' => $i + 1]);
        }
        Message::create([
            'site_id' => $this->site->id,
            'sender_id' => Auth::id(),
            'recipient_id' => $user->id,
            'body' => "You've been assigned a task: {$task->title}",
        ]);

        $this->reset('inviteEmail');
        $this->dispatch('toast', level: 'success', title: 'Member added', message: $user->name.' can now log in — their access details are on the way to '.$email.'.');
    }

    public function resendInvite(string $invitationId): void
    {
        $this->guard();
        $invitation = TeamInvitation::where('account_id', $this->accountId)->findOrFail($invitationId);
        $user = User::whereRaw('LOWER(email) = ?', [$invitation->email])->first();
        $site = $invitation->site_id ? Site::find($invitation->site_id) : $this->site;

        // Only a member still on their emailed temporary password gets a new
        // one — never reset a password someone actually chose.
        $temp = null;
        if ($user && $user->password_changed_at === null && $user->sites()->doesntExist()) {
            $temp = Str::password(12, symbols: false);
            $user->update(['password' => Hash::make($temp)]);
        }
        Mail::to($invitation->email)->send(new TeamInvitationMail($invitation, $site ?? $this->site, $temp));
        $this->dispatch('toast', level: 'success', title: 'Details re-sent', message: 'A fresh email went to '.$invitation->email.'.');
    }

    public function revokeInvite(string $invitationId): void
    {
        $this->guard();
        $invitation = TeamInvitation::where('account_id', $this->accountId)->findOrFail($invitationId);
        $user = User::whereRaw('LOWER(email) = ?', [$invitation->email])->first();
        if ($user && $invitation->site_id) {
            AccountMember::where('account_id', $this->accountId)->where('user_id', $user->id)
                ->where('site_id', $invitation->site_id)->delete();
        }
        $invitation->delete();
        $this->dispatch('toast', level: 'success', title: 'Access revoked', message: $invitation->email.' can no longer open this site.');
    }

    // ── Roles & permissions ──────────────────────────────────────

    public function openRoleEditor(string $roleId = ''): void
    {
        $this->guard();
        $this->editingRoleId = $roleId;
        $this->rolePerms = array_fill_keys(Permissions::keys(), false);

        if ($roleId) {
            $role = Role::where('account_id', $this->accountId)->findOrFail($roleId);
            $this->roleName = $role->name;
            $this->roleDescription = (string) $role->description;
            $all = in_array('*', $role->permissions ?? [], true);
            foreach (Permissions::keys() as $key) {
                $this->rolePerms[$key] = $all || $role->allows($key);
            }
        } else {
            $this->roleName = '';
            $this->roleDescription = '';
        }
    }

    public function closeRoleEditor(): void
    {
        $this->reset(['editingRoleId', 'roleName', 'roleDescription', 'rolePerms']);
    }

    public function saveRole(): void
    {
        $this->guard();
        $this->validate(['roleName' => ['required', 'string', 'max:60']]);

        // Only catalog keys can ever be stored.
        $permissions = array_values(array_filter(Permissions::keys(), fn ($k) => ! empty($this->rolePerms[$k])));

        if ($this->editingRoleId) {
            $role = Role::where('account_id', $this->accountId)->findOrFail($this->editingRoleId);
            $role->update([
                'name' => $this->roleName,
                'description' => $this->roleDescription ?: null,
                'permissions' => $permissions,
            ]);
            AccountActivity::roleSaved($this->accountId, $this->roleName, isNew: false);
        } else {
            Role::create([
                'account_id' => $this->accountId,
                'name' => $this->roleName,
                'slug' => Role::slugFor($this->accountId, $this->roleName),
                'description' => $this->roleDescription ?: null,
                'permissions' => $permissions,
            ]);
            AccountActivity::roleSaved($this->accountId, $this->roleName, isNew: true);
        }

        $this->dispatch('toast', level: 'success', title: 'Role saved', message: $this->roleName.' now has '.count($permissions).' permissions.');
        $this->closeRoleEditor();
    }

    public function deleteRole(string $roleId): void
    {
        $this->guard();
        $role = Role::where('account_id', $this->accountId)->findOrFail($roleId);
        if ($role->is_system) {
            $this->errorMessage = 'Built-in roles can be edited but not deleted.';

            return;
        }
        if ($role->members()->exists() || $role->invitations()->whereNull('accepted_at')->exists()) {
            $this->errorMessage = 'This role is still assigned to members or pending invitations.';

            return;
        }
        $role->delete();
        $this->dispatch('toast', level: 'success', title: 'Role deleted', message: 'The role was removed.');
    }

    public function render()
    {
        return view('livewire.site-team-page', [
            'permissionGroups' => Permissions::groups(),
        ]);
    }
}
