<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Team &amp; access</h1>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">
                One team for <span class="font-semibold text-gray-600 dark:text-gray-300">{{ $this->account->name }}</span>'s whole account — members, email invitations and role permissions.
            </p>
        </div>
        <div class="flex items-center gap-1 p-1 rounded-full bg-gray-100 dark:bg-white/[0.05]">
            @foreach (['members' => 'Members', 'invites' => 'Invitations', 'roles' => 'Roles'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors
                               {{ $tab === $key ? 'bg-white dark:bg-[#1d1e2a] text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">
                    {{ $label }}
                    @if ($key === 'invites' && $this->invitations->count())
                        <span class="ml-1 text-[10px] font-bold text-indigo-500">{{ $this->invitations->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    @if ($errorMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
    @endif

    {{-- ═══ MEMBERS ═══ --}}
    @if ($tab === 'members')
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
        {{-- The account owner — implicit full access, immutable. --}}
        <div class="flex flex-wrap items-center gap-4 px-5 py-4 border-b border-gray-50 dark:border-white/[0.04]">
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-sm font-bold shrink-0">
                {{ $this->account->initials() }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $this->account->name }}</p>
                <p class="text-xs text-gray-400 truncate">{{ $this->account->email }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-gray-900 text-white dark:bg-white dark:text-gray-900">Owner</span>
        </div>

        @forelse ($this->members as $member)
        <div class="flex flex-wrap items-center gap-4 px-5 py-4 border-b border-gray-50 dark:border-white/[0.04] last:border-0">
            <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-white/[0.08] flex items-center justify-center text-gray-600 dark:text-gray-300 text-sm font-bold shrink-0">
                {{ $member->user?->initials() ?? '?' }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $member->user->name ?? '—' }}</p>
                <p class="text-xs text-gray-400 truncate">{{ $member->user->email ?? '' }} · joined {{ $member->created_at->diffForHumans() }}</p>
            </div>
            <select wire:change="updateMemberRole({{ $member->id }}, $event.target.value)"
                    class="text-xs font-semibold pr-7 pl-3 py-1.5 rounded-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.05] text-gray-600 dark:text-gray-300 cursor-pointer focus:outline-none">
                @foreach ($this->rolesList as $role)
                    <option value="{{ $role->id }}" @selected($member->role_id === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <button wire:click="removeMember({{ $member->id }})"
                    data-confirm="Remove {{ $member->user->name ?? 'this member' }} from the account?"
                    class="w-8 h-8 flex items-center justify-center rounded-full text-gray-300 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors" title="Remove">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        @empty
        <p class="px-5 py-10 text-center text-sm text-gray-400">No team members yet — send an invitation below.</p>
        @endforelse
    </div>
    @endif

    {{-- ═══ INVITATIONS (list + invite form) ═══ --}}
    @if ($tab === 'invites' || $tab === 'members')
    <div class="mt-6 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Invite someone</h2>
        <form wire:submit="sendInvite" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">Email address</label>
                <input wire:model="inviteEmail" type="email" placeholder="name@company.com" required
                       class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                @error('inviteEmail')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">Role</label>
                <select wire:model="inviteRoleId"
                        class="pr-8 pl-3 py-2.5 rounded-xl text-sm font-semibold border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04] text-gray-700 dark:text-gray-200 focus:outline-none">
                    @foreach ($this->rolesList as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                <span wire:loading.remove wire:target="sendInvite">Send invitation</span>
                <span wire:loading wire:target="sendInvite">Sending…</span>
            </button>
        </form>
        <p class="text-[11px] text-gray-400 mt-2">They'll get an email with a secure link — opening it verifies their address before any access is granted. Links expire after {{ config('permissions.invite_expiry_days', 7) }} days.</p>

        @if ($this->invitations->count())
        <div class="mt-5 border-t border-gray-100 dark:border-white/[0.05] pt-4 space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Pending</h3>
            @foreach ($this->invitations as $invite)
            <div class="flex flex-wrap items-center gap-3 py-1.5">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{{ $invite->email }}</p>
                    <p class="text-[11px] text-gray-400">as {{ $invite->role->name ?? '—' }} ·
                        @if ($invite->expires_at->isPast())
                            <span class="text-rose-500 font-semibold">expired {{ $invite->expires_at->diffForHumans() }}</span>
                        @else
                            expires {{ $invite->expires_at->diffForHumans() }}
                        @endif
                    </p>
                </div>
                <button wire:click="resendInvite({{ $invite->id }})" class="text-xs font-semibold text-indigo-500 hover:text-indigo-600">Resend</button>
                <button wire:click="revokeInvite({{ $invite->id }})" data-confirm="Revoke this invitation?" class="text-xs font-semibold text-gray-400 hover:text-rose-500">Revoke</button>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ ROLES & PERMISSIONS ═══ --}}
    @if ($tab === 'roles')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->rolesList as $role)
        @php $isAll = in_array('*', $role->permissions ?? [], true); @endphp
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 flex flex-col">
            <div>
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $role->name }}
                    @if ($role->is_system)<span class="ml-1 text-[9px] font-bold uppercase text-gray-400">built-in</span>@endif
                </p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $isAll ? 'Full access' : count($role->permissions ?? []).' permissions' }} · {{ $role->members()->count() }} {{ Str::plural('member', $role->members()->count()) }}</p>
            </div>
            @if ($role->description)
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 flex-1">{{ $role->description }}</p>
            @endif
            <div class="flex gap-2 mt-4">
                <button wire:click="openRoleEditor({{ $role->id }})"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition-colors">Edit permissions</button>
                @unless ($role->is_system)
                    <button wire:click="deleteRole({{ $role->id }})" data-confirm="Delete the {{ $role->name }} role?"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-gray-400 hover:text-rose-500 transition-colors">Delete</button>
                @endunless
            </div>
        </div>
        @endforeach

        <button wire:click="openRoleEditor(0)"
                class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] p-5 text-sm font-semibold text-gray-400 hover:text-indigo-500 hover:border-indigo-300 transition-colors min-h-[120px]">
            + New role
        </button>
    </div>

    {{-- Role editor drawer --}}
    @if ($editingRoleId !== null)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" wire:click="closeRoleEditor"></div>
        <div class="relative bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white mb-4">{{ $editingRoleId ? 'Edit role' : 'New role' }}</h2>
            <form wire:submit="saveRole" class="space-y-5">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">Role name</label>
                        <input wire:model="roleName" type="text" required
                               class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        @error('roleName')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">Description</label>
                        <input wire:model="roleDescription" type="text" placeholder="What is this role for?"
                               class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    </div>
                </div>

                @foreach ($permissionGroups as $groupLabel => $perms)
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">{{ $groupLabel }}</p>
                    <div class="grid sm:grid-cols-2 gap-x-6 gap-y-1.5">
                        @foreach ($perms as $key => $label)
                        <label class="flex items-center gap-2.5 py-1 cursor-pointer select-none">
                            <input type="checkbox" wire:model="rolePerms.{{ $key }}"
                                   class="w-4 h-4 rounded border-gray-300 dark:border-white/20 text-indigo-600 focus:ring-indigo-500/40 bg-transparent">
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $label }}</span>
                            <span class="ml-auto text-[10px] font-mono text-gray-300 dark:text-gray-600">{{ $key }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeRoleEditor" class="px-4 py-2 rounded-xl text-sm font-medium text-gray-500 border border-gray-200 dark:border-white/[0.08]">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save role</button>
                </div>
            </form>
        </div>
    </div>
    @endif
    @endif
</div>
