<div>
@if ($open)
    @php
        $slides = ['Welcome', 'Your 5 steps', 'Plans', 'What’s next'];
        $firstUndone = collect($steps)->firstWhere('done', false);
        $pct = $progress['total'] ? (int) round($progress['done'] / $progress['total'] * 100) : 0;
        $paid = collect($tiers)->filter(fn ($t) => ($t['price_cents'] ?? 0) > 0);
        $from = $paid->min('price_cents');
        $pillPrimary = 'inline-flex items-center gap-2 min-h-[44px] px-6 rounded-full text-[14px] font-bold shadow-md shadow-black/10 transition-transform hover:-translate-y-0.5';
        $pillOutline = 'inline-flex items-center gap-2 min-h-[44px] px-6 rounded-full text-[14px] font-bold border-2 bg-white dark:bg-[#1d1e2a] text-gray-900 dark:text-white transition-transform hover:-translate-y-0.5';
        $counter = 'font-display text-[28px] leading-none font-extrabold';
    @endphp
    <div id="olux-intro" class="relative my-5 overflow-hidden rounded-[2rem] border border-gray-100 dark:border-white/[0.06] shadow-sm bg-white dark:bg-[#1d1e2a]"
         style="background-image: radial-gradient(circle at 8% 0%, color-mix(in srgb, var(--primary) 16%, transparent), transparent 38%), radial-gradient(circle at 100% 100%, color-mix(in srgb, var(--secondary) 14%, transparent), transparent 40%)"
         x-data="{ i: 0, dir: 1, n: {{ count($slides) }}, x0: null, go(k) { k = Math.max(0, Math.min(this.n - 1, k)); if (k !== this.i) { this.dir = k > this.i ? 1 : -1; this.i = k } } }"
         x-init="$wire.$on('intro-reopened', () => { i = 0; $el.scrollIntoView({ behavior: 'smooth', block: 'start' }) })"
         @keydown.arrow-right.window="if (! $event.target.closest('input,textarea,select')) go(i + 1)"
         @keydown.arrow-left.window="if (! $event.target.closest('input,textarea,select')) go(i - 1)"
         role="region" aria-roledescription="carousel" aria-label="Welcome to Olux">

        {{-- Floating nav pill (like a site header): brand · slides · hide --}}
        <div class="flex justify-center px-4 pt-5">
            <nav class="flex items-center gap-1 sm:gap-2 max-w-full overflow-x-auto no-scrollbar rounded-2xl px-3 py-2 shadow-md shadow-black/5 bg-white/90 dark:bg-white/[0.06] backdrop-blur" aria-label="Introduction">
                <span class="font-display text-[17px] font-extrabold text-gray-900 dark:text-white pr-2 shrink-0">Olux<span style="color:var(--primary)">.</span></span>
                @foreach ($slides as $k => $label)
                    <button type="button" @click="go({{ $k }})" :aria-current="i === {{ $k }} ? 'step' : null"
                            class="shrink-0 px-2.5 py-1 text-[13px] font-semibold transition-colors border-b-2"
                            :class="i === {{ $k }} ? 'border-current' : 'border-transparent text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'"
                            :style="i === {{ $k }} ? 'color:var(--primary)' : ''">{{ $label }}</button>
                @endforeach
                <button wire:click="dismiss" title="Reopen any time with “Show introduction” above your sites"
                        class="shrink-0 ml-1 px-4 py-1.5 rounded-full text-[12.5px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                    {{ $progress['complete'] ? 'Done ✓' : 'Hide' }}
                </button>
            </nav>
        </div>

        {{-- Slides --}}
        <div class="relative grid px-6 sm:px-10 pt-6 pb-4 overflow-hidden"
             @touchstart.passive="x0 = $event.touches[0].clientX"
             @touchend.passive="if (x0 !== null) { const d = $event.changedTouches[0].clientX - x0; if (Math.abs(d) > 50) go(i + (d < 0 ? 1 : -1)); x0 = null }">

            {{-- 1 · Welcome hero: text left, illustration right --}}
            <section style="grid-area: 1 / 1" :aria-hidden="i !== 0" :inert="i !== 0" class="transition-all duration-500 ease-[cubic-bezier(.22,1,.36,1)] motion-reduce:transition-none" :class="i === 0 ? 'opacity-100 translate-x-0' : ((0 < i) ? 'opacity-0 -translate-x-8 pointer-events-none' : 'opacity-0 translate-x-8 pointer-events-none')" aria-label="Welcome"><div class="grid md:grid-cols-2 gap-6 items-center h-full">
                <div>
                    <div class="flex items-center gap-2" aria-hidden="true">
                        @foreach (['#ec4899', 'var(--primary)', '#6366f1', '#10b981'] as $c)
                            <span class="w-8 h-8 rounded-lg shadow-sm" style="background: {{ $c }}"></span>
                        @endforeach
                    </div>
                    <h2 class="mt-4 font-display text-[30px] sm:text-[40px] leading-[1.1] font-extrabold text-gray-900 dark:text-white">
                        Hi {{ $firstName ?: 'there' }}, welcome to <span style="color:var(--primary)">Olux Desk</span>
                    </h2>
                    <p class="mt-3 text-[15px] text-gray-600 dark:text-gray-300 leading-relaxed max-w-lg">
                        Your website builder and CRM in one: pick a design, make it yours by clicking on it,
                        capture every enquiry and booking in your contacts, and go live on your own domain.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="button" @click="go(1)" class="{{ $pillPrimary }}" style="background:var(--primary);color:var(--on-primary)">
                            Start setup <span aria-hidden="true">→</span>
                        </button>
                        <a href="{{ route('how-it-works') }}" wire:navigate class="{{ $pillOutline }}" style="border-color:var(--primary)">
                            Getting started guide
                        </a>
                    </div>
                </div>
                <x-intro-art variant="welcome" class="w-full max-w-[26rem] mx-auto" />
            </div></section>

            {{-- 2 · Your 5 steps: illustration left, text right (like an "About me") --}}
            <section style="grid-area: 1 / 1" :aria-hidden="i !== 1" :inert="i !== 1" class="transition-all duration-500 ease-[cubic-bezier(.22,1,.36,1)] motion-reduce:transition-none" :class="i === 1 ? 'opacity-100 translate-x-0' : ((1 < i) ? 'opacity-0 -translate-x-8 pointer-events-none' : 'opacity-0 translate-x-8 pointer-events-none')" aria-label="Your 5 steps"><div class="grid md:grid-cols-2 gap-6 items-center h-full">
                <x-intro-art variant="steps" class="w-full max-w-[24rem] mx-auto hidden md:block" />
                <div>
                    <h2 class="font-display text-[30px] sm:text-[38px] leading-tight font-extrabold text-gray-900 dark:text-white">
                        Your 5 <span style="color:var(--primary)">steps</span>
                    </h2>
                    <div class="mt-3 grid grid-cols-3 gap-2 max-w-sm">
                        <div><p class="{{ $counter }}" style="color:var(--primary)">5</p><p class="text-[12px] text-gray-600 dark:text-gray-400">Steps</p></div>
                        <div><p class="{{ $counter }}" style="color:var(--primary)">{{ $progress['done'] }}</p><p class="text-[12px] text-gray-600 dark:text-gray-400">Done</p></div>
                        <div><p class="{{ $counter }}" style="color:var(--primary)">{{ $pct }}%</p><p class="text-[12px] text-gray-600 dark:text-gray-400">Complete</p></div>
                    </div>
                    <div class="mt-4 space-y-1.5">
                        @foreach ($steps as $n => $step)
                            <div class="flex items-center gap-3 rounded-2xl px-3 py-2 {{ $step['done'] ? '' : 'bg-white/80 dark:bg-white/[0.04] shadow-sm' }}">
                                <span class="shrink-0 w-7 h-7 rounded-full grid place-items-center text-[12.5px] font-bold {{ $step['done'] ? '' : 'text-white' }}"
                                      style="{{ $step['done'] ? 'background:#16a34a;color:#fff' : 'background:var(--foreground);color:var(--background)' }}">{!! $step['done'] ? '&#10003;' : $n + 1 !!}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[14px] font-bold {{ $step['done'] ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white' }}">{{ $step['label'] }}</span>
                                    @unless ($step['done'])<span class="block text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $step['description'] }}</span>@endunless
                                </span>
                                @unless ($step['done'])
                                    @if ($step['key'] === 'create_site')
                                        <button wire:click="openCreate" class="shrink-0 px-3.5 py-1.5 rounded-full text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">{{ $step['cta_label'] }}</button>
                                    @elseif ($step['cta_url'])
                                        <a href="{{ $step['cta_url'] }}" wire:navigate class="shrink-0 px-3.5 py-1.5 rounded-full text-[12px] font-bold border-2 bg-white dark:bg-[#1d1e2a]" style="border-color:var(--primary);color:var(--primary)">{{ $step['cta_label'] }}</a>
                                    @else
                                        <span class="shrink-0 text-[11px] text-gray-400">after step 1</span>
                                    @endif
                                @endunless
                            </div>
                        @endforeach
                    </div>
                </div>
            </div></section>

            {{-- 3 · Plans: text left, illustration right --}}
            <section style="grid-area: 1 / 1" :aria-hidden="i !== 2" :inert="i !== 2" class="transition-all duration-500 ease-[cubic-bezier(.22,1,.36,1)] motion-reduce:transition-none" :class="i === 2 ? 'opacity-100 translate-x-0' : ((2 < i) ? 'opacity-0 -translate-x-8 pointer-events-none' : 'opacity-0 translate-x-8 pointer-events-none')" aria-label="Plans"><div class="grid md:grid-cols-2 gap-6 items-center h-full">
                <div>
                    <h2 class="font-display text-[30px] sm:text-[38px] leading-tight font-extrabold text-gray-900 dark:text-white">
                        Pick your <span style="color:var(--primary)">plan</span>
                    </h2>
                    <p class="mt-2 text-[14.5px] text-gray-600 dark:text-gray-300 leading-relaxed max-w-xl">
                        @if ($plan && $plan['on_trial'] && ! $plan['expired'])
                            You're on the free trial with everything switched on. Choose a plan before it ends; nothing you build is lost.
                        @elseif ($plan && $plan['expired'])
                            Your trial has ended. Choose a plan to unlock your sites again.
                        @else
                            You're on {{ $plan['tier'] ?? 'a plan' }}. Change it any time.
                        @endif
                    </p>
                    <div class="mt-3 grid grid-cols-3 gap-2 max-w-xl">
                        @if ($plan && $plan['on_trial'] && ! $plan['expired'])
                            <div><p class="{{ $counter }}" style="color:var(--primary)">{{ $plan['days_left'] }}</p><p class="text-[12px] text-gray-600 dark:text-gray-400">Trial days left</p></div>
                        @else
                            <div><p class="{{ $counter }} truncate" style="color:var(--primary)">{{ $plan['tier'] ?? '—' }}</p><p class="text-[12px] text-gray-600 dark:text-gray-400">Your plan</p></div>
                        @endif
                        <div><p class="{{ $counter }}" style="color:var(--primary)">{{ $tiers->count() }}</p><p class="text-[12px] text-gray-600 dark:text-gray-400">Plans</p></div>
                        <div><p class="{{ $counter }}" style="color:var(--primary)">{{ $from ? \App\Support\Money::format((int) $from, 'gbp') : 'Free' }}</p><p class="text-[12px] text-gray-600 dark:text-gray-400">From / month</p></div>
                    </div>
                    <div class="mt-4 rounded-2xl bg-white/80 dark:bg-white/[0.04] shadow-sm px-4 py-1.5 max-w-md">
                        @foreach ($tiers as $key => $t)
                            <div class="flex items-center gap-2.5 py-1.5 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.06]' }}">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $t['color'] ?? 'var(--primary)' }}"></span>
                                <span class="min-w-0 flex-1 text-[13.5px] font-bold text-gray-900 dark:text-white truncate">
                                    {{ $t['name'] ?? $key }}
                                    @if ($key === $planKey)<span class="ml-1 text-[10px] font-extrabold px-1.5 py-0.5 rounded-full" style="background:var(--primary);color:var(--on-primary)">yours</span>@endif
                                </span>
                                <span class="text-[13.5px] font-extrabold text-gray-900 dark:text-white">{{ ($t['price_cents'] ?? 0) ? \App\Support\Money::format((int) $t['price_cents'], 'gbp') : 'Free' }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('account.subscription') }}" class="{{ $pillPrimary }}" style="background:var(--primary);color:var(--on-primary)">
                            {{ ($plan['expired'] ?? false) ? 'Upgrade now' : (($plan['on_trial'] ?? false) ? 'Choose a plan' : 'Manage plan') }}
                        </a>
                        <a href="{{ route('how-it-works') }}#plans" wire:navigate class="{{ $pillOutline }}" style="border-color:var(--primary)">Compare plans</a>
                    </div>
                </div>
                <x-intro-art variant="plans" class="w-full max-w-[24rem] mx-auto hidden md:block" />
            </div></section>

            {{-- 4 · What's next (like a "Get in touch" section) --}}
            <section style="grid-area: 1 / 1" :aria-hidden="i !== 3" :inert="i !== 3" class="transition-all duration-500 ease-[cubic-bezier(.22,1,.36,1)] motion-reduce:transition-none" :class="i === 3 ? 'opacity-100 translate-x-0' : ((3 < i) ? 'opacity-0 -translate-x-8 pointer-events-none' : 'opacity-0 translate-x-8 pointer-events-none')" aria-label="What’s next"><div class="h-full flex flex-col justify-center">
                <div class="text-center">
                    <h2 class="font-display text-[28px] sm:text-[34px] font-extrabold text-gray-900 dark:text-white">What's <span style="color:var(--primary)">next</span></h2>
                    <p class="text-[14.5px] text-gray-600 dark:text-gray-300">Pick up where you left off, or read the full guide.</p>
                </div>
                <div class="mt-4 grid md:grid-cols-2 gap-6 items-center">
                    <x-intro-art variant="help" class="w-full max-w-[22rem] mx-auto hidden md:block" />
                    <div class="rounded-3xl bg-white dark:bg-white/[0.04] shadow-lg shadow-black/5 p-5 space-y-3">
                        <div class="rounded-2xl p-4" style="background:var(--primary-soft)">
                            <p class="text-[12px] font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ $firstUndone ? 'Your next step' : 'All done' }}</p>
                            <p class="text-[16px] font-extrabold text-gray-900 dark:text-white">{{ $firstUndone['label'] ?? 'Your site is live 🎉' }}</p>
                            <p class="text-[13px] text-gray-600 dark:text-gray-300 mt-0.5">{{ $firstUndone['description'] ?? 'Keep an eye on new contacts and bookings from your dashboard.' }}</p>
                        </div>
                        @if ($firstUndone && $firstUndone['key'] === 'create_site')
                            <button wire:click="openCreate" class="w-full min-h-[46px] rounded-full text-[14px] font-bold shadow-md" style="background:linear-gradient(90deg,var(--primary),var(--secondary));color:var(--on-primary)">{{ $firstUndone['cta_label'] }}</button>
                        @elseif ($firstUndone && $firstUndone['cta_url'])
                            <a href="{{ $firstUndone['cta_url'] }}" wire:navigate class="flex items-center justify-center w-full min-h-[46px] rounded-full text-[14px] font-bold shadow-md" style="background:linear-gradient(90deg,var(--primary),var(--secondary));color:var(--on-primary)">{{ $firstUndone['cta_label'] }}</a>
                        @endif
                        <a href="{{ route('how-it-works') }}" wire:navigate class="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 dark:border-white/[0.08] px-4 py-3">
                            <span>
                                <span class="block text-[14px] font-bold text-gray-900 dark:text-white">Getting started guide</span>
                                <span class="block text-[12px] text-gray-500 dark:text-gray-400">Every step in detail, plans, add-ons and answers.</span>
                            </span>
                            <span aria-hidden="true" style="color:var(--primary)">→</span>
                        </a>
                        <p class="text-[12.5px] text-gray-600 dark:text-gray-300">Questions? Write to <b>{{ config('mail.from.address') }}</b> and a real person will help.</p>
                    </div>
                </div>
            </div></section>
        </div>

        {{-- Back · dots · Next --}}
        <div class="flex items-center justify-between gap-3 px-6 sm:px-10 pb-6">
            <button type="button" @click="go(i - 1)" :disabled="i === 0" class="{{ $pillOutline }} !min-h-[40px] !px-4 disabled:opacity-30" style="border-color:color-mix(in srgb, var(--primary) 50%, transparent)">← Back</button>
            <div class="flex items-center gap-1.5" aria-hidden="true">
                @foreach ($slides as $k => $label)
                    <button type="button" tabindex="-1" @click="go({{ $k }})" class="h-2 rounded-full transition-all" :class="i === {{ $k }} ? 'w-7' : 'w-2 bg-gray-300 dark:bg-white/20'" :style="i === {{ $k }} ? 'background:var(--primary)' : ''"></button>
                @endforeach
            </div>
            <button type="button" x-show="i < n - 1" @click="go(i + 1)" class="{{ $pillPrimary }} !min-h-[40px] !px-5" style="background:var(--primary);color:var(--on-primary)">Next →</button>
            <button type="button" x-show="i === n - 1" x-cloak wire:click="dismiss" class="{{ $pillPrimary }} !min-h-[40px] !px-5" style="background:var(--primary);color:var(--on-primary)">Got it</button>
        </div>
    </div>
@endif
</div>
