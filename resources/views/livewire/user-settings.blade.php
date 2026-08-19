<?php

use App\Models\ApiToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.home', ['withSiteNav' => true])] class extends Component {
    use WithFileUploads;

    // — Personal Info
    public string $name       = '';
    public string $email      = '';
    public string $job_title  = '';
    public string $department = '';
    public $photo;

    // — Security
    public string $current_password      = '';
    public string $new_password          = '';
    public string $new_password_confirm  = '';
    public bool   $showCurrentPw         = false;
    public bool   $showNewPw             = false;
    public bool   $two_factor_enabled    = false;

    // — Preferences
    public string $language    = 'en';
    public string $timezone    = 'UTC';
    public string $date_format = 'MM/DD/YYYY';
    public string $theme       = 'light';
    public bool   $notif_email = true;
    public bool   $notif_inapp = true;
    public bool   $notif_push  = false;

    // — API tokens
    public string $new_token_name  = '';
    public array $new_token_sites = [];      // site ids; [] = all my sites, several = one token per site
    public ?string $new_token_expiry = null; // days ('30'|'90'|'365') or null = never
    public array $new_token_abilities = [];  // [] = all my permissions
    /** @var array<int,array{site:?string,token:string}> shown once after generation */
    public array $generated_tokens = [];

    // — UI state
    public bool   $showDeleteConfirm = false;
    public string $successMessage    = '';
    public string $errorMessage      = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name              = $user->name;
        $this->email             = $user->email;
        $this->job_title         = $user->job_title  ?? '';
        $this->department        = $user->department ?? '';
        $this->language          = $user->language   ?? 'en';
        $this->timezone          = $user->timezone   ?? 'UTC';
        $this->date_format       = $user->date_format ?? 'MM/DD/YYYY';
        $this->theme             = $user->theme      ?? 'light';
        $this->notif_email       = (bool) ($user->notif_email ?? true);
        $this->notif_inapp       = (bool) ($user->notif_inapp ?? true);
        $this->notif_push        = (bool) ($user->notif_push  ?? false);
        $this->two_factor_enabled = (bool) ($user->two_factor_enabled ?? false);
    }

    public function savePersonal(): void
    {
        $user = Auth::user();
        $validated = $this->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'job_title'  => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        if ($this->photo) {
            $path = $this->photo->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        // A changed email must be re-verified.
        if ($validated['email'] !== $user->email) {
            $validated['email_verified_at'] = null;
        }

        $user->update($validated);
        \App\Services\AccountActivity::profileUpdated($user);
        $this->successMessage = 'Personal information updated successfully.';
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password'     => ['required', 'string'],
            'new_password'         => ['required', 'string', 'same:new_password_confirm', Password::min(8)],
            'new_password_confirm' => ['required', 'string'],
        ], [
            'new_password.same' => 'New passwords do not match.',
        ]);

        $user = Auth::user();

        if ($user->password && ! Hash::check($this->current_password, $user->password)) {
            $this->errorMessage = 'Current password is incorrect.';
            return;
        }

        $user->update(['password' => $this->new_password]);
        \App\Services\AccountActivity::passwordChanged($user);
        $this->current_password     = '';
        $this->new_password         = '';
        $this->new_password_confirm = '';
        $this->successMessage = 'Password changed successfully.';
    }

    public function savePreferences(): void
    {
        $this->validate([
            'language'    => ['required', 'string'],
            'timezone'    => ['required', 'string'],
            'date_format' => ['required', 'string'],
            'theme'       => ['required', 'in:light,dark,system'],
        ]);

        Auth::user()->update([
            'language'    => $this->language,
            'timezone'    => $this->timezone,
            'date_format' => $this->date_format,
            'theme'       => $this->theme,
            'notif_email' => $this->notif_email,
            'notif_inapp' => $this->notif_inapp,
            'notif_push'  => $this->notif_push,
        ]);

        $this->dispatch('theme-changed', theme: $this->theme);
        $this->successMessage = 'Preferences saved.';
    }

    public function generateToken(): void
    {
        $this->validate([
            'new_token_name' => ['required', 'string', 'max:80'],
            'new_token_sites' => ['array'],
            'new_token_expiry' => ['nullable', 'in:30,90,365'],
            'new_token_abilities' => ['array'],
        ]);

        // Only sites the user can actually access; [] = one unscoped token.
        $sites = \App\Models\Site::whereIn('id', $this->new_token_sites)->get()
            ->filter(fn ($s) => $s->accessibleBy(Auth::user()));
        $targets = $sites->isEmpty() ? collect([null]) : $sites;

        $this->generated_tokens = [];
        foreach ($targets as $site) {
            $raw = Str::random(64);
            Auth::user()->apiTokens()->create([
                'name' => $this->new_token_name,
                'token' => hash('sha256', $raw),
                'token_preview' => substr($raw, 0, 8),
                'site_id' => $site?->id,
                'abilities' => $this->new_token_abilities !== [] ? array_values($this->new_token_abilities) : null,
                'expires_at' => $this->new_token_expiry ? now()->addDays((int) $this->new_token_expiry) : null,
            ]);
            \App\Services\AccountActivity::apiKeyCreated(Auth::id(), $this->new_token_name, $site?->name);
            $this->generated_tokens[] = ['site' => $site?->name, 'token' => $raw];
        }

        $this->new_token_name = '';
        $this->new_token_sites = [];
        $this->new_token_expiry = null;
        $this->new_token_abilities = [];
        $this->successMessage = count($this->generated_tokens) > 1
            ? count($this->generated_tokens).' tokens generated — copy them now, they will not be shown again.'
            : 'Token generated — copy it now, it will not be shown again.';
    }

    public function revokeToken(string $id): void
    {
        $token = Auth::user()->apiTokens()->findOrFail($id);
        \App\Services\AccountActivity::apiKeyRevoked(Auth::id(), $token->name);
        $token->delete();
        $this->successMessage = 'Token revoked.';
    }

    public function logoutOtherDevices(): void
    {
        Auth::logoutOtherDevices(request()->session()->token() ?? '');
        $this->successMessage = 'All other sessions logged out.';
    }

    public function deleteAccount(): void
    {
        $user = Auth::user();
        Auth::logout();
        $user->delete();
        redirect()->route('login');
    }

    public function getSessions(): array
    {
        return \DB::table('sessions')
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'ip'         => $s->ip_address,
                'agent'      => substr($s->user_agent ?? 'Unknown', 0, 60),
                'last'       => \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                'current'    => $s->id === request()->session()->getId(),
            ])
            ->toArray();
    }

    /** Account audit trail, newest first, grouped by day (Today / Yesterday / date). */
    public function getActivityGroupsProperty()
    {
        return \App\Models\AccountActivityLog::where(fn ($q) => $q
                ->where('account_id', Auth::id())->orWhere('actor_id', Auth::id()))
            ->with('actor:id,name')
            ->latest()->limit(100)->get()
            ->groupBy(fn ($log) => $log->created_at->isToday() ? 'Today'
                : ($log->created_at->isYesterday() ? 'Yesterday'
                    : $log->created_at->format('F j, Y')));
    }
}; ?>

<div class="flex flex-col h-full" x-data="{ tab: 'personal', toast: '', toastType: 'success' }"
     x-init="
        $watch('$wire.successMessage', v => { if(v){ toast = v; toastType='success'; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
        $watch('$wire.errorMessage',   v => { if(v){ toast = v; toastType='error';   setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 4000) } });
     ">

    {{-- ── Top header ── --}}
    <header class="h-14 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between px-6 bg-white dark:bg-gray-900 shrink-0">
        <div class="flex items-center gap-2">
            <a href="{{ route('home') }}" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-sm font-semibold text-gray-900 dark:text-white">Account Settings</h1>
        </div>
        <span class="text-xs text-gray-400 dark:text-gray-500">{{ Auth::user()->email }}</span>
    </header>

    <div class="flex flex-1 overflow-hidden">

        {{-- ── Left sidebar ── --}}
        <aside class="w-56 shrink-0 border-r border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/60 flex flex-col py-4 overflow-y-auto">
            @php
            $navItems = [
                ['id'=>'personal',    'label'=>'Personal Info',       'icon'=>'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                ['id'=>'security',    'label'=>'Account Security',    'icon'=>'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
                ['id'=>'preferences', 'label'=>'Preferences',         'icon'=>'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
                ['id'=>'permissions', 'label'=>'Access & Permissions', 'icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ['id'=>'apikeys',     'label'=>'API Keys',            'icon'=>'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
                ['id'=>'activity',    'label'=>'Activity & Logs',     'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                ['id'=>'account',     'label'=>'Account Management',  'icon'=>'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
            ];
            @endphp
            @foreach($navItems as $item)
            <button @click="tab = '{{ $item['id'] }}'"
                class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all text-left"
                :class="tab === '{{ $item['id'] }}'
                    ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-200'
                    : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-100'">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                </svg>
                {{ $item['label'] }}
            </button>
            @endforeach

            <div class="mt-auto mx-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-medium text-red-400 hover:bg-red-50 hover:text-red-600 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        {{-- ── Main content ── --}}
        <main class="flex-1 overflow-y-auto bg-white dark:bg-gray-950">
            <div class="max-w-3xl mx-auto px-8 py-8 space-y-8">

                {{-- ════════════════ PERSONAL INFO ════════════════ --}}
                <div x-show="tab === 'personal'" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Personal Information</h2>
                        <p class="text-sm text-gray-400 mt-0.5">Update your name, photo and professional details.</p>
                    </div>

                    {{-- Avatar --}}
                    <div class="flex items-center gap-5 mb-8 p-5 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800">
                        <div class="relative">
                            <x-avatar
                                :src="Auth::user()->avatar ? Storage::url(Auth::user()->avatar) : null"
                                :initials="Auth::user()->initials()"
                                size="w-20 h-20"
                                textSize="text-2xl font-bold"
                                :ring="true"
                                shadow="shadow-md" />
                            <label class="absolute -bottom-1 -right-1 w-7 h-7 bg-indigo-600 rounded-full flex items-center justify-center cursor-pointer hover:bg-indigo-700 transition-colors shadow-sm">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <input type="file" wire:model="photo" class="hidden" accept="image/*">
                            </label>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">{{ Auth::user()->name }}</p>
                            <p class="text-sm text-gray-400">{{ Auth::user()->email }}</p>
                            @if($photo) <p class="text-xs text-indigo-500 mt-1">New photo selected — save to apply.</p> @endif
                        </div>
                    </div>

                    <form wire:submit="savePersonal" class="space-y-5">
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <x-field.text label="Full Name" model="name" />
                                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="bkf-label">Email Address</label>
                                <div class="relative">
                                    <x-field.text model="email" type="email" class="pr-24" />
                                    @if(Auth::user()->email_verified_at)
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1 text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        Verified
                                    </span>
                                    @endif
                                </div>
                                @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-5">
                            <x-field.text label="Job Title" model="job_title" placeholder="e.g. Product Designer" />
                            <x-field.select label="Department / Team" model="department" empty="Select department…"
                                            :options="['Engineering', 'Design', 'Product', 'Marketing', 'Sales', 'Operations', 'Finance', 'HR']" />
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200">
                                <span wire:loading.remove wire:target="savePersonal">Save Changes</span>
                                <span wire:loading wire:target="savePersonal">Saving…</span>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ════════════════ SECURITY ════════════════ --}}
                <div x-show="tab === 'security'" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Account Security</h2>
                        <p class="text-sm text-gray-400 mt-0.5">Manage your password, 2FA and active sessions.</p>
                    </div>

                    {{-- Change Password --}}
                    <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl mb-6">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Change Password</h3>
                        <form wire:submit="savePassword" class="space-y-4">
                            @foreach([['current_password','Current Password','showCurrentPw'],['new_password','New Password','showNewPw'],['new_password_confirm','Confirm New Password',null]] as [$field,$label,$toggle])
                            <div>
                                <label class="bkf-label">{{ $label }}</label>
                                <div class="relative">
                                    <x-field.text :model="$field" type="password" placeholder="••••••••••"
                                        ::type="{{ $toggle ? '$wire.'.$toggle.' ? \'text\' : \'password\'' : '\'password\'' }}"
                                        :class="$toggle ? 'pr-11' : ''" />
                                    @if($toggle)
                                    <button type="button" wire:click="$toggle('{{ $toggle }}')" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                                @error($field) <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            @endforeach
                            <div class="flex justify-end">
                                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
                                    <span wire:loading.remove wire:target="savePassword">Update Password</span>
                                    <span wire:loading wire:target="savePassword">Updating…</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- 2FA --}}
                    <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Two-Factor Authentication</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Add an extra layer of security to your account.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="$toggle('two_factor_enabled')"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                                    :class="$wire.two_factor_enabled ? 'bg-indigo-600' : 'bg-gray-200'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                          :class="$wire.two_factor_enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                                </button>
                                <span class="text-xs font-medium" :class="$wire.two_factor_enabled ? 'text-indigo-600' : 'text-gray-400'">
                                    {{ $two_factor_enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                                <button class="px-3 py-1.5 text-xs font-semibold text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">Setup</button>
                            </div>
                        </div>
                    </div>

                    {{-- Active Sessions --}}
                    <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Active Sessions</h3>
                            <button wire:click="logoutOtherDevices" data-confirm="Log out all other sessions?" class="text-xs font-medium text-red-500 hover:text-red-700 transition-colors">
                                Log out all other devices
                            </button>
                        </div>
                        <div class="space-y-3">
                            @foreach($this->getSessions() as $session)
                            <div class="flex items-center justify-between py-2.5 px-4 rounded-xl {{ $session['current'] ? 'bg-indigo-50 border border-indigo-100' : 'bg-gray-50' }}">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center {{ $session['current'] ? 'bg-indigo-100' : 'bg-gray-200' }}">
                                        <svg class="w-4 h-4 {{ $session['current'] ? 'text-indigo-600' : 'text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-800">{{ $session['ip'] }} {{ $session['current'] ? '· Current session' : '' }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $session['last'] }}</p>
                                    </div>
                                </div>
                                @if($session['current'])
                                <span class="text-xs font-medium text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded-full">Active</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ════════════════ PREFERENCES ════════════════ --}}
                <div x-show="tab === 'preferences'" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Preferences</h2>
                        <p class="text-sm text-gray-400 mt-0.5">Customize your experience and notification settings.</p>
                    </div>

                    <form wire:submit="savePreferences" class="space-y-6">
                        {{-- Locale --}}
                        <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl space-y-5">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Locale & Time</h3>
                            <div class="grid grid-cols-2 gap-5">
                                <x-field.select label="Language" model="language" :empty="null"
                                                :options="['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'de' => 'German', 'pt' => 'Portuguese', 'ar' => 'Arabic']" />
                                <x-field.select label="Timezone" model="timezone" :empty="null"
                                                :options="collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($tz) => [$tz => $tz])->all()" />
                                <x-field.select label="Date Format" model="date_format" :empty="null"
                                                :options="['MM/DD/YYYY' => 'MM/DD/YYYY', 'DD/MM/YYYY' => 'DD/MM/YYYY', 'YYYY-MM-DD' => 'YYYY-MM-DD']" />
                            </div>
                        </div>

                        {{-- Theme --}}
                        <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Theme</h3>
                            <div class="grid grid-cols-3 gap-3">
                                @foreach(['light'=>['☀️','Light'],'dark'=>['🌙','Dark'],'system'=>['💻','System']] as $val => [$emoji,$lbl])
                                <button type="button" wire:click="$set('theme','{{ $val }}')"
                                    class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all text-sm font-medium"
                                    :class="$wire.theme === '{{ $val }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 text-gray-600 dark:text-gray-400'">
                                    <span class="text-xl">{{ $emoji }}</span>
                                    {{ $lbl }}
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Notifications --}}
                        <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Notifications</h3>
                            <div class="space-y-4">
                                @foreach([['notif_email','Email Notifications','Receive updates via email'],['notif_inapp','In-App Notifications','Show notifications inside the dashboard'],['notif_push','Push Notifications','Browser push notifications']] as [$field,$label,$desc])
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $desc }}</p>
                                    </div>
                                    <button type="button" wire:click="$toggle('{{ $field }}')"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                        :class="$wire.{{ $field }} ? 'bg-indigo-600' : 'bg-gray-200'">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                              :class="$wire.{{ $field }} ? 'translate-x-6' : 'translate-x-1'"></span>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200">
                                <span wire:loading.remove wire:target="savePreferences">Save Preferences</span>
                                <span wire:loading wire:target="savePreferences">Saving…</span>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ════════════════ PERMISSIONS ════════════════ --}}
                <div x-show="tab === 'permissions'" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Access & Permissions</h2>
                        <p class="text-sm text-gray-400 mt-0.5">Your assigned roles and resource access summary.</p>
                    </div>

                    <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl mb-6">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">Assigned Roles</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach(Auth::user()->is_admin ?? false ? ['Super Admin','Site Manager','Content Editor'] : ['Site Manager','Content Editor'] as $role)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-100 text-indigo-700">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                                {{ $role }}
                            </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        @foreach([['Sites','Manage all sites','✓ Create ✓ Edit ✓ Delete','indigo'],['Pages','Build & publish pages','✓ Create ✓ Edit ✓ Publish','violet'],['Media','Upload & manage files','✓ Upload ✓ Delete','blue'],['Forms','Create & view responses','✓ Create ✓ View','sky']] as [$res,$desc,$perms,$color])
                        <div class="p-5 border border-gray-100 rounded-2xl">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-7 h-7 rounded-lg bg-{{ $color }}-100 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5 text-{{ $color }}-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="text-sm font-semibold text-gray-800">{{ $res }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mb-2">{{ $desc }}</p>
                            <p class="text-xs text-emerald-600 font-medium">{{ $perms }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- ════════════════ ACTIVITY ════════════════ --}}
                <div x-show="tab === 'activity'" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Activity & Logs</h2>
                        <p class="text-sm text-gray-400 mt-0.5">Recent actions on your account.</p>
                    </div>

                    {{-- Audit trail — real events, grouped by day, on a timeline rail --}}
                    <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl mb-6">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Recent Activity</h3>

                        @forelse($this->activityGroups as $day => $logs)
                        <div class="mb-6 last:mb-0">
                            {{-- Date heading --}}
                            <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-3">{{ $day }}</p>

                            {{-- Timeline: dots joined by a vertical rail --}}
                            <div class="relative pl-2">
                                <span class="absolute left-[0.9375rem] top-2 bottom-2 w-px bg-gradient-to-b from-indigo-200 via-gray-200 to-transparent dark:from-indigo-500/40 dark:via-gray-700"></span>
                                @foreach($logs as $log)
                                <div class="relative flex items-start gap-4 py-2.5">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 z-10 ring-4 ring-white dark:ring-[#1a1b26]"
                                         style="background:{{ $log->accent() }}1f">
                                        <div class="w-2 h-2 rounded-full" style="background:{{ $log->accent() }}"></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $log->title }}</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                            {{ $log->created_at->format('g:i A') }}
                                            @if($log->actor && $log->actor_id !== Auth::id()) · by {{ $log->actor->name }} @endif
                                            @if($log->description) · {{ $log->description }} @endif
                                        </p>
                                    </div>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-lg shrink-0">{{ $log->category }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-400 text-center py-8">No account activity recorded yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- ════════════════ API KEYS ════════════════ --}}
                <div x-show="tab === 'apikeys'" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">API Keys</h2>
                        <p class="text-sm text-gray-400 mt-0.5">Bearer tokens for the CMS API and MCP. Scope each to a site and only the abilities it needs.</p>
                    </div>

                    {{-- API Tokens --}}
                    <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Your API tokens</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Shown once at creation — copy it then. Send as <code class="font-mono">Authorization: Bearer &lt;token&gt;</code>.</p>
                            </div>
                        </div>

                        @if($generated_tokens)
                        <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl space-y-2">
                            <p class="text-xs font-semibold text-amber-800">⚠️ Copy {{ count($generated_tokens) > 1 ? 'these tokens' : 'this token' }} now — {{ count($generated_tokens) > 1 ? 'they' : 'it' }} will not be shown again.</p>
                            @foreach ($generated_tokens as $gen)
                                @if ($gen['site'])<p class="text-[11px] font-bold text-amber-700">{{ $gen['site'] }}</p>@endif
                                <code class="text-xs font-mono text-amber-900 bg-amber-100 px-3 py-2 rounded-lg block break-all">{{ $gen['token'] }}</code>
                            @endforeach
                        </div>
                        @endif

                        {{-- Generate new — scoped tokens: least privilege by default --}}
                        <div class="mb-5 p-4 bg-gray-50 dark:bg-white/[0.03] rounded-xl space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <div class="flex-1 min-w-[160px]"><x-field.text model="new_token_name" placeholder="Token name (e.g. CI Deploy)" /></div>
                                <select wire:model="new_token_expiry" class="px-3 py-2 pr-7 text-sm rounded-xl bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200">
                                    <option value="">Never expires</option>
                                    <option value="30">Expires in 30 days</option>
                                    <option value="90">Expires in 90 days</option>
                                    <option value="365">Expires in 1 year</option>
                                </select>
                            </div>
                            {{-- Site scope: none ticked = all sites; several = one token minted per site --}}
                            <details class="text-xs" open>
                                <summary class="cursor-pointer text-gray-500 dark:text-gray-400 font-semibold">Limit to sites <span class="font-normal text-gray-400">— none ticked = all my sites; tick several to mint one token per site</span></summary>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 mt-2">
                                    @foreach (\App\Models\Site::all()->filter(fn ($s) => $s->accessibleBy(Auth::user())) as $s)
                                        <label class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                                            <input type="checkbox" wire:model="new_token_sites" value="{{ $s->id }}" class="rounded border-gray-300"> {{ $s->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                            <details class="text-xs">
                                <summary class="cursor-pointer text-gray-500 dark:text-gray-400 font-semibold">Limit abilities <span class="font-normal text-gray-400">— none ticked = everything you can do</span></summary>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 mt-2">
                                    {{-- Site Connect abilities included so client-site tokens can be minted least-privilege (same pair the connect:token CLI issues). --}}
                                    @foreach (collect(config('permissions.groups', []))->flatMap(fn ($perms) => array_keys($perms))->merge(config('site_connect.abilities', []))->unique() as $perm)
                                        <label class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                                            <input type="checkbox" wire:model="new_token_abilities" value="{{ $perm }}" class="rounded border-gray-300"> {{ $perm }}
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                            <button wire:click="generateToken" class="px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors whitespace-nowrap">
                                <span wire:loading.remove wire:target="generateToken">Generate Token</span>
                                <span wire:loading wire:target="generateToken">…</span>
                            </button>
                            @error('new_token_name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        {{-- Token list --}}
                        @forelse(Auth::user()->apiTokens()->latest()->get() as $token)
                        <div class="flex items-center justify-between py-3 px-4 bg-gray-50 rounded-xl mb-2">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $token->name }}
                                    @if ($token->site)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-600 ml-1">{{ $token->site->name }}</span>@endif
                                    @if ($token->abilities)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 ml-1">{{ count($token->abilities) }} {{ Str::plural('ability', count($token->abilities)) }}</span>@endif
                                    @if ($token->isExpired())<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-600 ml-1">expired</span>
                                    @elseif ($token->expires_at)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 ml-1">expires {{ $token->expires_at->diffForHumans() }}</span>@endif
                                </p>
                                <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $token->maskedToken() }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">Created {{ $token->created_at->diffForHumans() }}{{ $token->last_used_at ? ' · Last used '.$token->last_used_at->diffForHumans() : '' }}</p>
                            </div>
                            <button wire:click="revokeToken('{{ $token->id }}')" data-confirm="Revoke this token?"
                                class="text-xs font-medium text-red-500 hover:text-red-700 px-3 py-1.5 hover:bg-red-50 rounded-lg transition-colors">
                                Revoke
                            </button>
                        </div>
                        @empty
                        <p class="text-sm text-gray-400 text-center py-4">No tokens yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- ════════════════ ACCOUNT MANAGEMENT ════════════════ --}}
                <div x-show="tab === 'account'" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Account Management</h2>
                        <p class="text-sm text-gray-400 mt-0.5">Manage your account settings and data.</p>
                    </div>

                    {{-- Save changes CTA --}}
                    <div class="p-6 border border-indigo-100 bg-indigo-50/50 rounded-2xl mb-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Save all changes</p>
                            <p class="text-xs text-gray-500 mt-0.5">Apply all pending updates to your profile and preferences.</p>
                        </div>
                        <button wire:click="savePersonal" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200">
                            Save Changes
                        </button>
                    </div>

                    {{-- Logout --}}
                    <div class="p-6 border border-gray-100 dark:border-gray-800 rounded-2xl mb-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Sign out</p>
                            <p class="text-xs text-gray-400 mt-0.5">Sign out of your account on this device.</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 border border-gray-200 text-sm font-semibold text-gray-700 rounded-xl hover:bg-gray-50 transition-colors">
                                Logout
                            </button>
                        </form>
                    </div>

                    {{-- Delete account --}}
                    <div class="p-6 border border-red-100 bg-red-50/40 rounded-2xl">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-semibold text-red-700">Delete Account</p>
                                <p class="text-xs text-red-400 mt-0.5 max-w-sm">Permanently delete your account and all associated data. This action cannot be undone.</p>
                            </div>
                            <button @click="$wire.showDeleteConfirm = !$wire.showDeleteConfirm"
                                class="px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-xl hover:bg-red-700 transition-colors shrink-0 ml-4">
                                Delete Account
                            </button>
                        </div>
                        <div x-show="$wire.showDeleteConfirm" x-transition class="mt-4 p-4 bg-red-100 rounded-xl border border-red-200">
                            <p class="text-sm font-semibold text-red-800 mb-3">Are you absolutely sure?</p>
                            <p class="text-xs text-red-600 mb-4">This will permanently delete your account, all your sites, pages, and data. There is no recovery.</p>
                            <div class="flex gap-2">
                                <button wire:click="deleteAccount" data-confirm="Permanently delete your account? This cannot be undone." class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors">
                                    Yes, delete my account
                                </button>
                                <button @click="$wire.showDeleteConfirm = false" class="px-4 py-2 bg-white text-sm font-semibold text-gray-700 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    {{-- ── Toast ── --}}
    <div x-show="toast" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium"
         :class="toastType === 'success' ? 'bg-gray-900 text-white' : 'bg-red-600 text-white'">
        <svg x-show="toastType==='success'" class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <svg x-show="toastType==='error'" class="w-4 h-4 text-white shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        <span x-text="toast"></span>
    </div>

</div>

<style>[x-cloak]{display:none!important}</style>
