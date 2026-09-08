<div class="min-h-screen">
    {{-- Ambient drifting brand shapes (landing-style) --}}
    <div class="ambient" aria-hidden="true">
        @foreach (range(1, 24) as $i)
            @php $size = ($i * 7) % 36 + 10; @endphp
            <span class="{{ $i % 3 === 0 ? 'dot' : 'sq' }} c{{ $i % 6 }}"
                  style="top:{{ ($i * 37) % 93 + 3 }}%;left:{{ ($i * 53) % 94 + 2 }}%;width:{{ $size }}px;height:{{ $size }}px;--dur:{{ ($i % 16) + 14 }}s;--delay:-{{ $i % 12 }}s"></span>
        @endforeach
    </div>

    <header class="sticky top-0 z-30  backdrop-blur border-b border-gray-100 dark:border-white/[0.06]">
        <div class="max-w-7xl mx-auto px-4 h-14 flex items-center gap-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-extrabold text-gray-900 dark:text-white"><x-app-logo-icon class="w-5 h-5 fill-current" /></a>
            <a href="{{ route('templates') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white">← Back to templates</a>
            <span class="flex-1"></span>
            <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Switch between dark and light theme" title="Toggle theme"><span class="tt-sun">☀️</span><span class="tt-moon">🌙</span></button>
            @auth
                <a href="{{ route('home') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">My sites →</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-600 dark:text-gray-300 hover:underline">Log in</a>
            @endauth
        </div>
    </header>

    <div class="max-w-8xl relative mx-auto px-4 py-8 grid lg:grid-cols-[1fr_320px] gap-10 items-start">

        {{-- ════ LEFT: device toggle + framed preview + accordion ════ --}}
        <div wire:ignore
             x-data="{
                view: '{{ $card['previewUrl'] ? 'desktop' : 'shots' }}',
                shots: {{ \Illuminate\Support\Js::from($card['screenshots']) }},
                timer: null,
                advance() { if (this.shots.length > 1) this.shots.push(this.shots.shift()) },
                goTo(src) { let g = this.shots.length; while (this.shots[0] !== src && g--) this.advance() },
                start() { this.timer = setInterval(() => { if (this.view === 'shots') this.advance() }, 5000) },
             }" x-init="start()">

            {{-- Device / screenshots toggle --}}
            <div class="flex justify-center mb-5">
                <div class="inline-flex items-center gap-1 bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-sm border border-gray-100 dark:border-white/[0.06] p-1.5">
                    @if ($card['previewUrl'])
                        <button @click="view = 'desktop'" :class="view === 'desktop' ? 'bg-gray-100 dark:bg-white/[0.08] ring-1 ring-gray-200 dark:ring-white/10' : ''" class="w-10 h-9 rounded-xl grid place-items-center text-gray-600 dark:text-gray-300" title="Desktop preview">
                            <svg class="w-4.5 h-4.5 w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="12" rx="2"/><path stroke-linecap="round" d="M8 20h8m-4-4v4"/></svg>
                        </button>
                        <button @click="view = 'mobile'" :class="view === 'mobile' ? 'bg-gray-100 dark:bg-white/[0.08] ring-1 ring-gray-200 dark:ring-white/10' : ''" class="w-10 h-9 rounded-xl grid place-items-center text-gray-600 dark:text-gray-300" title="Mobile preview">
                            <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="8" y="3" width="8" height="18" rx="2"/><path stroke-linecap="round" d="M11 18h2"/></svg>
                        </button>
                    @endif
                    <template x-if="shots.length">
                        <button @click="view = 'shots'" :class="view === 'shots' ? 'bg-gray-100 dark:bg-white/[0.08] ring-1 ring-gray-200 dark:ring-white/10' : ''" class="w-10 h-9 rounded-xl grid place-items-center text-gray-600 dark:text-gray-300" title="Screenshots">
                            <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 15l4.5-4.5a2 2 0 012.8 0L15 15m-2-2l1.6-1.6a2 2 0 012.8 0L21 15"/><circle cx="9" cy="9" r="1.2" fill="currentColor" stroke="none"/></svg>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Browser-framed stage --}}
            <div class="rounded-2xl overflow-hidden shadow-[0_18px_50px_-20px_rgba(30,27,45,.35)] border border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a]">
                <div class="flex items-center gap-1.5 px-4 h-9 bg-white dark:bg-[#22232f] border-b border-gray-100 dark:border-white/[0.06]">
                    <span class="w-2.5 h-2.5 rounded-full bg-pink-300"></span><span class="w-2.5 h-2.5 rounded-full bg-amber-300"></span><span class="w-2.5 h-2.5 rounded-full bg-blue-300"></span>
                    <span class="flex-1 mx-6 h-4 rounded-full bg-gray-100 dark:bg-white/[0.06]"></span>
                </div>

                @if ($card['previewUrl'])
                    <div x-show="view === 'desktop'"><iframe src="{{ $card['previewUrl'] }}" class="w-full" style="height:560px;border:0" title="Desktop preview of {{ $card['name'] }}"></iframe></div>
                    <div x-show="view === 'mobile'" x-cloak class="flex justify-center bg-gray-50 dark:bg-black/30 py-6">
                        <iframe src="{{ $card['previewUrl'] }}" class="rounded-[1.6rem] border-8 border-gray-900 shadow-xl" style="width:390px;height:560px" title="Mobile preview of {{ $card['name'] }}"></iframe>
                    </div>
                @endif

                {{-- Screenshots mode: rail + main with the rotating queue --}}
                <div x-show="view === 'shots'" @if($card['previewUrl']) x-cloak @endif class="p-4 bg-gray-50 dark:bg-black/30">
                    <template x-if="shots.length > 1">
                        <div class="grid grid-cols-[84px_1fr] gap-3">
                            <div class="flex flex-col gap-3">
                                <template x-for="src in shots.slice(1)" :key="src">
                                    <button type="button" @click="goTo(src)" class="rounded-lg overflow-hidden border border-gray-200 dark:border-white/[0.08] hover:border-indigo-400 transition-colors">
                                        <img :src="src" alt="" class="w-full aspect-[4/3] object-cover object-top">
                                    </button>
                                </template>
                            </div>
                            <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-white/[0.08]">
                                <img :src="shots[0]" alt="{{ $card['name'] }} screenshot" class="w-full object-cover object-top">
                            </div>
                        </div>
                    </template>
                    <template x-if="shots.length === 1"><img :src="shots[0]" alt="{{ $card['name'] }}" class="w-full rounded-xl"></template>
                    <template x-if="! shots.length"><div class="h-64 grid place-items-center text-5xl font-black text-white rounded-xl" style="background:{{ $card['accent'] }}">{{ strtoupper(substr($card['name'], 0, 1)) }}</div></template>
                </div>
            </div>

            {{-- Accordion (About stays flat — no collapse on the first section) --}}
            <div class="mt-8 space-y-3">
                <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm px-5 py-4">
                    <h2 class="text-base font-extrabold text-gray-900 dark:text-white mb-2">About</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $card['description'] ?: 'A ready-made design for your site — preview it live, then make it yours.' }}</p>
                </div>
                <details class="group rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm">
                    <summary class="flex items-center justify-between px-5 py-4 cursor-pointer text-base font-extrabold text-gray-900 dark:text-white">What's inside
                        <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-5">
                        @if ($card['pages'])
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">{{ count($card['pages']) }} pages</p>
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                @foreach ($card['pages'] as $p)<span class="px-2.5 py-1 rounded-lg bg-gray-50 dark:bg-white/[0.06] border border-gray-100 dark:border-white/[0.06] text-xs font-semibold text-gray-600 dark:text-gray-300" title="/{{ ltrim($p['url'], '/') }}">{{ $p['name'] }}</span>@endforeach
                            </div>
                        @endif
                        @if ($card['tags'])
                            <div class="flex flex-wrap gap-1.5">@foreach ($card['tags'] as $tag)<span class="px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-[11px] font-semibold text-indigo-500">{{ $tag }}</span>@endforeach</div>
                        @endif
                        @unless ($card['pages'] || $card['tags'])<p class="text-sm text-gray-400">Page details appear once the design is published with content.</p>@endunless
                    </div>
                </details>
                <details class="group rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm">
                    <summary class="flex items-center justify-between px-5 py-4 cursor-pointer text-base font-extrabold text-gray-900 dark:text-white">How it works on {{ config('app.name', 'Olux') }}
                        <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <ul class="px-5 pb-5 text-sm text-gray-600 dark:text-gray-300 space-y-1.5">
                        <li>✓ Every word and image is editable from your dashboard — no code.</li>
                        <li>✓ Bookings, store, invoices, forms and the estimator work with any design.</li>
                        <li>✓ Switch designs any time — your content stays.</li>
                    </ul>
                </details>
            </div>
        </div>

        {{-- ════ RIGHT: title, CTA, designed-for, highlights, specs ════ --}}
        <aside class="lg:sticky lg:top-20 space-y-6">
            <div>
                <p class="flex items-center gap-1.5 text-xs font-semibold text-gray-400">
                    <a href="{{ route('templates') }}" class="hover:text-gray-600">Templates</a> <span>›</span> <span>{{ $card['category'] }}</span>
                </p>
                <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white mt-2 leading-tight">{{ $card['name'] }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-3 leading-relaxed">{{ \Illuminate\Support\Str::limit($card['description'] ?: 'A polished, ready-to-run design for your business.', 180) }}</p>
                <p class="flex items-center gap-2 text-xs font-semibold text-gray-400 mt-3">
                    <span class="w-4 h-4 rounded-full grid place-items-center text-[8px] font-black text-white" style="background:{{ $card['accent'] }}">{{ strtoupper(substr($card['author'], 0, 1)) }}</span>
                    {{ $card['author'] }} · {{ $card['installs'] ?? 0 }} {{ \Illuminate\Support\Str::plural('install', $card['installs'] ?? 0) }}
                    @if ($card['rating'])<span>· ★ {{ $card['rating'] }}</span>@endif
                </p>
            </div>

            @if (str_starts_with($message, 'saved:'))
                @php $savedSite = substr($message, 6); @endphp
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 p-3 text-sm">
                    <p class="font-bold text-emerald-700 dark:text-emerald-400">✓ Saved to {{ $savedSite }}</p>
                    <a href="{{ url($savedSite.'/designs') }}" class="text-xs font-semibold text-indigo-500 hover:underline">Open My Designs → make it the active look</a>
                </div>
            @elseif ($message)
                <p class="px-3 py-2 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-xs font-semibold text-rose-600">{{ $message }}</p>
            @endif

            <button wire:click="getTemplate" wire:loading.attr="disabled"
                    class="w-full py-3.5 rounded-2xl bg-gray-900 hover:bg-black dark:bg-white dark:text-gray-900 text-white text-sm font-bold shadow-lg transition-colors">
                <span wire:loading.remove wire:target="getTemplate">Use template{{ $card['priceCents'] > 0 ? ' — '.$card['priceLabel'] : '' }}</span>
                <span wire:loading wire:target="getTemplate">Creating your site…</span>
            </button>
            @if ($card['previewUrl'])
                <a href="{{ $card['previewUrl'] }}" target="_blank" rel="noopener" class="block text-center text-xs font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white -mt-3">View template in a new tab ↗</a>
            @endif

            <div>
                <p class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Designed for</p>
                <ul class="space-y-1.5">
                    @foreach ($card['designedFor'] as $d)
                        <li class="flex gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="text-gray-300">•</span>{{ $d }}</li>
                    @endforeach
                </ul>
            </div>

            @if ($card['highlights'])
                <div>
                    <p class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Key highlights</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($card['highlights'] as $h)
                            <span class="px-3 py-1.5 rounded-full bg-white dark:bg-white/[0.06] border border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-600 dark:text-gray-300">{{ $h }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Product specs --}}
            <div class="pt-4 border-t border-gray-200/70 dark:border-white/[0.05]">
                <p class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Product specs</p>
                <dl class="space-y-2 text-xs">
                    <div class="flex items-center gap-2"><dt class="text-gray-400">Framework</dt><dd class="ml-auto font-semibold text-gray-700 dark:text-gray-200">{{ $card['framework'] }}</dd></div>
                    @if ($card['createdAt'])<div class="flex items-center gap-2"><dt class="text-gray-400">Created</dt><dd class="ml-auto font-semibold text-gray-700 dark:text-gray-200">{{ $card['createdAt'] }}</dd></div>@endif
                    <div class="flex items-center gap-2"><dt class="text-gray-400">Creator</dt><dd class="ml-auto font-semibold text-gray-700 dark:text-gray-200">{{ $card['author'] }}</dd></div>
                    <div class="flex items-center gap-2"><dt class="text-gray-400">Category</dt><dd class="ml-auto font-semibold text-gray-700 dark:text-gray-200">{{ $card['category'] }}</dd></div>
                    <div class="flex items-center gap-2"><dt class="text-gray-400">Added to sites</dt><dd class="ml-auto font-semibold text-gray-700 dark:text-gray-200">{{ $card['installs'] ?? 0 }}</dd></div>
                    <div class="flex items-center gap-2"><dt class="text-gray-400">Price</dt><dd class="ml-auto font-semibold {{ $card['priceCents'] > 0 ? 'text-gray-700 dark:text-gray-200' : 'text-emerald-600' }}">{{ $card['priceLabel'] }}</dd></div>
                </dl>
            </div>

            @guest
                <p class="text-[11px] text-gray-400">You'll be asked to sign in (free) when you use a template — <a href="{{ route('start') }}" class="font-semibold text-indigo-500 hover:underline">or create your site now</a>.</p>
            @endguest
        </aside>
    </div>
</div>
