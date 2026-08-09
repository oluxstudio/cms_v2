{{--
    Upgrade prompt — shown when a plan limit is hit. Any Livewire component
    dispatches:  $this->dispatch('upgrade-required', reason: '…', cta: 'Add another site')
    and this modal slides in with the reason, the current plan, and a CTA to
    the subscription page. Include ONCE per layout (needs an authed user).
--}}
@auth
@php
    $sub = auth()->user()->currentSubscription();
@endphp
<div x-data="{ open: false, reason: '', cta: 'Upgrade your plan' }"
     x-on:upgrade-required.window="reason = $event.detail.reason || 'You\'ve reached a limit on your current plan.'; cta = $event.detail.cta || 'Upgrade your plan'; open = true"
     x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[2147483000] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="relative w-full max-w-md rounded-3xl overflow-hidden shadow-2xl bg-white dark:bg-[#1c1d27]">

            {{-- Gradient banner --}}
            <div class="relative px-7 pt-7 pb-6 text-white" style="background:linear-gradient(135deg,#6366f1,#a855f7)">
                <div class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <p class="text-[11px] font-bold uppercase tracking-[.16em] text-white/70">You're on {{ $sub->tier()['name'] }}</p>
                <h3 class="text-xl font-extrabold mt-1">Time to level up</h3>
            </div>

            <div class="px-7 py-6">
                <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed" x-text="reason"></p>

                <div class="mt-4 flex items-center gap-2 text-xs text-gray-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M3 12a9 9 0 1018 0 9 9 0 00-18 0z"/></svg>
                    <span>Sites used: {{ $sub->sitesUsage() }}</span>
                </div>

                <div class="mt-6 flex gap-3">
                    <a href="{{ route('account.subscription') }}"
                       class="flex-1 text-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm"
                       style="background:linear-gradient(135deg,#6366f1,#a855f7)"
                       x-text="cta"></a>
                    <button @click="open = false"
                            class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-gray-200 dark:border-white/[0.1] text-gray-600 dark:text-gray-300">
                        Not now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endauth
