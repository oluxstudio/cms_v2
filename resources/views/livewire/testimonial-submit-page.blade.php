{{-- Public "share your experience" page — anyone can write a testimonial about
     Olux; it waits for a super admin to publish it before it shows anywhere. --}}
@php
    $field = 'w-full px-4 py-3 rounded-xl text-[15px] bg-white dark:bg-white/[0.05] border border-gray-300 dark:border-white/15 text-gray-900 dark:text-white focus:outline-none focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary)]/25';
    $label = 'block text-[14px] font-bold text-gray-900 dark:text-white mb-1.5';
@endphp
<div class="min-h-screen">
    <div class="ambient" aria-hidden="true">
        @foreach (range(1, 22) as $i)
            @php $size = ($i * 7) % 36 + 10; @endphp
            <span class="{{ $i % 3 === 0 ? 'dot' : 'sq' }} c{{ $i % 6 }}"
                  style="top:{{ ($i * 37) % 93 + 3 }}%;left:{{ ($i * 53) % 94 + 2 }}%;width:{{ $size }}px;height:{{ $size }}px;--dur:{{ ($i % 16) + 14 }}s;--delay:-{{ $i % 12 }}s"></span>
        @endforeach
    </div>

    <header class="sticky top-0 z-30 backdrop-blur border-b border-gray-100 dark:border-white/[0.06]">
        <div class="max-w-6xl relative mx-auto px-4 h-16 flex items-center gap-4">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-extrabold text-gray-900 dark:text-white whitespace-nowrap">
                <x-app-logo-icon class="w-6 h-6 fill-current" /> {{ config('app.name', 'Olux') }}
            </a>
            <span class="hidden sm:inline text-sm font-semibold text-gray-400">Testimonials</span>
            <span class="flex-1"></span>
            <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Switch between dark and light theme" title="Toggle theme"><span class="tt-sun">☀️</span><span class="tt-moon">🌙</span></button>
            <a href="{{ url('/') }}#testimonials" class="text-sm font-semibold text-gray-600 dark:text-gray-300 hover:underline whitespace-nowrap">Back to site</a>
        </div>
    </header>

    <main class="max-w-6xl relative mx-auto px-4 py-10 grid lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] gap-10 items-start">
        <section aria-labelledby="ts-title">
            <p class="text-[13px] font-extrabold uppercase tracking-[.14em]" style="color:var(--primary)">Share your experience</p>
            <h1 id="ts-title" class="font-display text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white mt-2">How has Olux helped your business?</h1>
            <p class="text-[16px] leading-relaxed text-gray-600 dark:text-gray-300 mt-3 max-w-xl">
                A few honest sentences help other business owners decide. We read every one, and with your permission it may appear on our website.
                Your email stays private — we only use it if we need to check something with you.
            </p>

            @if ($sent)
                <div class="mt-8 rounded-2xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 p-6" role="status">
                    <p class="text-lg font-extrabold text-emerald-800 dark:text-emerald-300">Thank you!</p>
                    <p class="text-[15px] text-emerald-900/80 dark:text-emerald-200/90 mt-1">We’ve received your testimonial. Once our team has read it, it may appear on the Olux website.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ url('/') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-xl text-sm font-bold" style="background:var(--primary);color:var(--on-primary)">Back to Olux</a>
                        <button type="button" wire:click="writeAnother" class="inline-flex items-center min-h-[44px] px-5 rounded-xl text-sm font-bold border border-gray-300 dark:border-white/15 bg-white dark:bg-transparent text-gray-800 dark:text-gray-100">Write another</button>
                    </div>
                </div>
            @else
                <form wire:submit="submit" class="mt-8 space-y-5" novalidate>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="ts-name" class="{{ $label }}">Your name</label>
                            <input id="ts-name" type="text" wire:model="name" maxlength="80" autocomplete="name" class="{{ $field }}" aria-describedby="ts-name-err" required>
                            @error('name')<p id="ts-name-err" class="text-[13px] font-semibold text-rose-600 mt-1" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="ts-role" class="{{ $label }}">Business or role <span class="font-normal text-gray-500">(optional)</span></label>
                            <input id="ts-role" type="text" wire:model="role" maxlength="120" autocomplete="organization" placeholder="Owner, Bloom Salon" class="{{ $field }}">
                            @error('role')<p class="text-[13px] font-semibold text-rose-600 mt-1" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="ts-quote" class="{{ $label }}">Your testimonial</label>
                        <textarea id="ts-quote" wire:model="quote" rows="6" maxlength="600" class="{{ $field }}" aria-describedby="ts-quote-help ts-quote-err" required
                                  placeholder="What was it like before Olux, and what’s different now?"></textarea>
                        <p id="ts-quote-help" class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">20–600 characters.</p>
                        @error('quote')<p id="ts-quote-err" class="text-[13px] font-semibold text-rose-600 mt-1" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <fieldset>
                        <legend class="{{ $label }}">Your rating</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ([5, 4, 3, 2, 1] as $r)
                                <label class="cursor-pointer">
                                    <input type="radio" wire:model="rating" value="{{ $r }}" class="peer sr-only">
                                    <span class="inline-flex items-center gap-1 min-h-[42px] px-3.5 rounded-xl border text-[14px] font-bold border-gray-300 dark:border-white/15 text-gray-700 dark:text-gray-200 bg-white dark:bg-transparent
                                                 peer-checked:border-[color:var(--primary)] peer-checked:text-[color:var(--primary)] dark:peer-checked:text-[color:var(--primary)] peer-checked:bg-[color:var(--primary)]/10 peer-focus-visible:ring-2 peer-focus-visible:ring-[color:var(--primary)]">
                                        <span aria-hidden="true">{{ str_repeat('★', $r) }}</span><span class="sr-only">{{ $r }} out of 5 stars</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div>
                        <label for="ts-email" class="{{ $label }}">Email <span class="font-normal text-gray-500">(optional, never shown)</span></label>
                        <input id="ts-email" type="email" wire:model="email" maxlength="160" autocomplete="email" class="{{ $field }}">
                        @error('email')<p class="text-[13px] font-semibold text-rose-600 mt-1" role="alert">{{ $message }}</p>@enderror
                    </div>

                    {{-- Honeypot: hidden from people and screen readers --}}
                    <div class="hidden" aria-hidden="true">
                        <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center min-h-[48px] px-6 rounded-xl text-[15px] font-bold shadow-sm disabled:opacity-60" style="background:var(--primary);color:var(--on-primary)">
                            <span wire:loading.remove wire:target="submit">Send testimonial</span>
                            <span wire:loading wire:target="submit">Sending…</span>
                        </button>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Reviewed by our team before it appears anywhere.</p>
                    </div>
                </form>
            @endif
        </section>

        <aside aria-labelledby="ts-others" class="lg:sticky lg:top-24">
            <h2 id="ts-others" class="text-[13px] font-extrabold uppercase tracking-[.14em] text-gray-500 dark:text-gray-400">What others say</h2>
            <div class="mt-3 space-y-3">
                @forelse ($published as $t)
                    <figure class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-5">
                        @if ($t->rating)<p class="text-[14px] tracking-wider" style="color:var(--primary)" aria-label="{{ $t->rating }} out of 5 stars">{{ str_repeat('★', $t->rating) }}</p>@endif
                        <blockquote class="text-[15px] leading-relaxed text-gray-800 dark:text-gray-100 mt-1"><span class="font-display mr-1" style="color:var(--primary)" aria-hidden="true">“</span>{{ $t->quote }}<span class="font-display ml-1" style="color:var(--primary)" aria-hidden="true">”</span></blockquote>
                        <figcaption class="mt-3 flex items-center gap-3">
                            <span class="w-9 h-9 shrink-0 rounded-full grid place-items-center text-[12px] font-extrabold text-white" style="background:var(--primary)" aria-hidden="true">{{ $t->initials() }}</span>
                            <span class="text-[13px]"><span class="block font-bold text-gray-900 dark:text-white">{{ $t->name }}</span>@if ($t->role)<span class="block text-gray-500 dark:text-gray-400">{{ $t->role }}</span>@endif</span>
                        </figcaption>
                    </figure>
                @empty
                    <p class="text-[14px] text-gray-500 dark:text-gray-400">Be the first to share your experience.</p>
                @endforelse
            </div>
        </aside>
    </main>
</div>
