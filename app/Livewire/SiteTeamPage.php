<?php

namespace App\Livewire;

use App\Access\Permissions;
use App\Mail\TeamInvitationMail;
use App\Models\AccountMember;
use App\Models\Role;
use App\Models\Site;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
    public int $accountId;

    public string $tab = 'members'; // members | invites | roles

    // Invite form
    public string $inviteEmail = '';

    public ?int $inviteRoleId = null;

    // Role editor
    public ?int $editingRoleId = null;   // 0 = creating a new role

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

    public function updateMemberRole(int $memberId, int $roleId): void
    {
        $this->guard();
        $member = AccountMember::where('account_id', $this->accountId)->findOrFail($memberId);
        $role = Role::where('account_id', $this->accountId)->findOrFail($roleId);
        $member->update(['role_id' => $role->id]);
        $this->dispatch('toast', level: 'success', title: 'Role updated', message: ($member->user->name ?? 'Member').' is now '.$role->name.'.');
    }

    public function removeMember(int $memberId): void
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
            'inviteRoleId' => ['required', 'integer'],
        ]);

        $email = mb_strtolower(trim($this->inviteEmail));
        $role = Role::where('account_id', $this->accountId)->findOrFail($this->inviteRoleId);

        if ($email === mb_strtolower($this->account->email)) {
            $this->errorMessage = 'That is the account owner — they already have full access.';

            return;
        }

        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing && AccountMember::where('account_id', $this->accountId)->where('user_id', $existing->id)->exists()) {
            $this->errorMessage = $existing->name.' is already a member of this account.';

            return;
        }

        [$invitation, $plain] = TeamInvitation::issue($this->account, Auth::user(), $email, $role);
        Mail::to($email)->send(new TeamInvitationMail($invitation, $plain));

        $this->reset('inviteEmail');
        $this->dispatch('toast', level: 'success', title: 'Invitation sent', message: 'An email with a verification link is on its way to '.$email.'.');
    }

    public function resendInvite(int $invitationId): void
    {
        $this->guard();
        $invitation = TeamInvitation::where('account_id', $this->accountId)->findOrFail($invitationId);
        // Re-issue: fresh token + fresh expiry.
        [$invitation, $plain] = TeamInvitation::issue($this->account, Auth::user(), $invitation->email, $invitation->role);
        Mail::to($invitation->email)->send(new TeamInvitationMail($invitation, $plain));
        $this->dispatch('toast', level: 'success', title: 'Invitation re-sent', message: 'A fresh link went to '.$invitation->email.'.');
    }

    public function revokeInvite(int $invitationId): void
    {
        $this->guard();
        TeamInvitation::where('account_id', $this->accountId)->findOrFail($invitationId)->delete();
        $this->dispatch('toast', level: 'success', title: 'Invitation revoked', message: 'The link no longer works.');
    }

    // ── Roles & permissions ──────────────────────────────────────

    public function openRoleEditor(int $roleId = 0): void
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
        } else {
            Role::create([
                'account_id' => $this->accountId,
                'name' => $this->roleName,
                'slug' => Role::slugFor($this->accountId, $this->roleName),
                'description' => $this->roleDescription ?: null,
                'permissions' => $permissions,
            ]);
        }

        $this->dispatch('toast', level: 'success', title: 'Role saved', message: $this->roleName.' now has '.count($permissions).' permissions.');
        $this->closeRoleEditor();
    }

    public function deleteRole(int $roleId): void
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
