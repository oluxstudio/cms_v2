<div>
@if ($open)
    <div class="mb-6 overflow-hidden rounded-3xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a] shadow-sm">

        {{-- Hero: friendly greeting + what Olux is + plan card --}}
        <div class="relative px-6 pt-6 pb-5 sm:px-8 sm:pt-8"
             style="background:linear-gradient(120deg,var(--primary),var(--primary-2));">
            <button wire:click="dismiss"
                    class="absolute top-4 right-4 text-[11px] font-semibold text-white/70 hover:text-white">
                {{ $progress['complete'] ? 'Done' : 'Dismiss' }}
            </button>

            <div class="grid lg:grid-cols-[1fr_auto] gap-5 items-start">
                {{-- Greeting --}}
                <div class="max-w-xl">
                    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-white/70">
                        {{ $progress['complete'] ? 'You’re all set' : 'Welcome to Olux' }}
                    </p>
                    <h2 class="mt-1 text-2xl sm:text-[26px] font-extrabold text-white leading-tight">
                        @if ($progress['complete'])
                            Nice work{{ $firstName ? ', '.$firstName : '' }} 🎉
                        @else
                            Hi {{ $firstName ?: 'there' }} 👋 Let’s get you set up
                        @endif
                    </h2>
                    <p class="mt-2 text-sm text-white/80 leading-relaxed">
                        Olux is your <strong class="text-white">website builder</strong> and
                        <strong class="text-white">CRM</strong> in one — build a site, capture every
                        lead and booking into your contacts, and grow from a single dashboard.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2.5">
                        <a href="{{ route('how-it-works') }}" wire:navigate
                           class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl bg-white text-gray-900 shadow-sm hover:-translate-y-0.5 transition-transform">
                            ▶ Take the 2-min tour
                        </a>
                        <a href="{{ route('account.subscription') }}"
                           class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl bg-white/15 text-white ring-1 ring-inset ring-white/30 hover:bg-white/25 transition-colors">
                            See plans
                        </a>
                    </div>
                </div>

                {{-- Plan / trial card --}}
                @if ($plan)
                    <div class="w-full lg:w-64 rounded-2xl bg-white/12 ring-1 ring-inset ring-white/25 p-4 backdrop-blur-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-white/60">Your plan</p>
                        <p class="mt-0.5 text-lg font-extrabold text-white">{{ $plan['tier'] }}</p>

                        @if ($plan['on_trial'] && ! $plan['expired'])
                            <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-amber-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                {{ $plan['days_left'] }} day{{ $plan['days_left'] == 1 ? '' : 's' }} left on trial
                            </div>
                            <p class="mt-2 text-[12px] text-white/75 leading-relaxed">
                                Enjoying it? Pick a plan to keep every feature after your trial.
                            </p>
                        @elseif ($plan['expired'])
                            <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-rose-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Trial ended
                            </div>
                            <p class="mt-2 text-[12px] text-white/75 leading-relaxed">
                                Upgrade to unlock your sites again.
                            </p>
                        @else
                            <p class="mt-2 text-[12px] text-white/75 leading-relaxed">
                                You’re on {{ $plan['tier'] }}. Manage or change it any time.
                            </p>
                        @endif

                        <a href="{{ route('account.subscription') }}"
                           class="mt-3 block text-center text-sm font-bold px-3 py-2 rounded-xl bg-white text-gray-900 hover:-translate-y-0.5 transition-transform">
                            {{ $plan['expired'] ? 'Upgrade now' : ($plan['on_trial'] ? 'Choose a plan' : 'Manage plan') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Checklist body --}}
        <div class="p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Get started</h3>
                    <p class="text-xs text-gray-400 mt-0.5">A few quick steps to your first result.</p>
                </div>
                <span class="text-xs font-semibold text-gray-500 shrink-0">{{ $progress['done'] }}/{{ $progress['total'] }} done</span>
            </div>

            {{-- Progress bar --}}
            <div class="h-2 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden mb-4">
                <div class="h-full rounded-full transition-all"
                     style="width: {{ $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 0 }}%;background:linear-gradient(90deg,var(--primary),var(--primary-2))"></div>
            </div>

            {{-- Steps --}}
            <div class="space-y-2">
                @foreach ($steps as $step)
                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 dark:border-white/[0.06] px-3 py-2.5 transition-colors hover:border-gray-200 dark:hover:border-white/[0.12]">
                        <span class="shrink-0 w-6 h-6 rounded-full grid place-items-center text-sm font-bold {{ $step['done'] ? '' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-400' }}"
                              @if ($step['done']) style="background:#d9f068;color:#2b3110" @endif>
                            {!! $step['done'] ? '&#10003;' : '' !!}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold {{ $step['done'] ? 'text-gray-400 line-through' : 'text-gray-800 dark:text-gray-200' }}">{{ $step['label'] }}</p>
                            @unless ($step['done'])<p class="text-xs text-gray-400 truncate">{{ $step['description'] }}</p>@endunless
                        </div>
                        @unless ($step['done'])
                            @if ($step['key'] === 'create_site')
                                <button wire:click="openCreate" class="shrink-0 text-xs font-semibold text-white px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700">{{ $step['cta_label'] }}</button>
                            @elseif ($step['cta_url'])
                                <a href="{{ $step['cta_url'] }}" wire:navigate class="shrink-0 text-xs font-semibold text-indigo-600 dark:text-indigo-400 px-3 py-1.5 rounded-lg border border-indigo-200 dark:border-indigo-500/40 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">{{ $step['cta_label'] }}</a>
                            @else
                                <span class="shrink-0 text-[11px] text-gray-300 dark:text-gray-600">Create a site first</span>
                            @endif
                        @endunless
                    </div>
                @endforeach
            </div>

            <p class="text-xs text-gray-400 mt-4">
                New to Olux? <a href="{{ route('how-it-works') }}" wire:navigate class="font-semibold" style="color:var(--primary)">See how it works →</a>
            </p>
        </div>
    </div>
@endif
</div>
