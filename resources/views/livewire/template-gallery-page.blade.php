<div class="min-h-screen">
    {{-- Ambient drifting brand shapes (landing-style) --}}
    <div class="ambient" aria-hidden="true">
        @foreach (range(1, 30) as $i)
            @php $size = ($i * 7) % 36 + 10; @endphp
            <span class="{{ $i % 3 === 0 ? 'dot' : 'sq' }} c{{ $i % 6 }}"
                  style="top:{{ ($i * 37) % 93 + 3 }}%;left:{{ ($i * 53) % 94 + 2 }}%;width:{{ $size }}px;height:{{ $size }}px;--dur:{{ ($i % 16) + 14 }}s;--delay:-{{ $i % 12 }}s"></span>
        @endforeach
    </div>

    {{-- chrome --}}
    <header class="sticky top-0 z-30  backdrop-blur border-b border-gray-100 dark:border-white/[0.06]">
        <div class="max-w-8xl relative mx-auto px-4 h-16 flex items-center gap-4">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-extrabold text-gray-900 dark:text-white">
                <x-app-logo-icon class="w-6 h-6 fill-current" /> {{ config('app.name', 'Olux') }}
            </a>
            <span class="text-sm font-semibold text-gray-400">Templates</span>
            <span class="flex-1"></span>
            <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Switch between dark and light theme" title="Toggle theme"><span class="tt-sun">☀️</span><span class="tt-moon">🌙</span></button>
            @auth
                <a href="{{ route('home') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">My sites →</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-600 dark:text-gray-300 hover:underline">Log in</a>
                <a href="{{ route('start') }}" class="px-4 py-2 rounded-xl bg-gray-900 dark:bg-white dark:text-gray-900 hover:opacity-90 text-white text-sm font-semibold">Get started free</a>
            @endauth
        </div>
    </header>

    <div class="max-w-8xl relative mx-auto px-4 py-8 flex gap-10 items-start">

        {{-- ════ Sidebar filters ════ --}}
        <aside class="hidden md:block w-52 shrink-0 lg:sticky lg:top-20 space-y-7">
            <div>
                <p class="text-sm font-extrabold text-gray-900 dark:text-white mb-2.5">Category</p>
                <ul class="space-y-1.5 text-sm">
                    <li>
                        <button wire:click="$set('category','all')" class="flex w-full items-center gap-2 {{ $category === 'all' ? 'font-bold text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                            All <span class="ml-auto text-xs text-gray-400">({{ collect($this->categoryCounts)->sum() }})</span>
                        </button>
                    </li>
                    @foreach ($this->categoryCounts as $cat => $count)
                        <li>
                            <button wire:click="$set('category','{{ $cat }}')" class="flex w-full items-center gap-2 {{ strcasecmp($category, $cat) === 0 ? 'font-bold text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                                <span class="w-4 h-4 rounded border {{ strcasecmp($category, $cat) === 0 ? 'bg-gray-900 dark:bg-white border-gray-900 dark:border-white' : 'border-gray-300 dark:border-white/20' }} grid place-items-center">
                                    @if (strcasecmp($category, $cat) === 0)<svg class="w-2.5 h-2.5 text-white dark:text-gray-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                                </span>
                                {{ $cat }} <span class="ml-auto text-xs text-gray-400">({{ $count }})</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <p class="text-sm font-extrabold text-gray-900 dark:text-white mb-2.5">Price</p>
                <ul class="space-y-1.5 text-sm">
                    @foreach (['all' => 'Any price', 'free' => 'Free', 'paid' => 'Paid'] as $val => $label)
                        <li>
                            <button wire:click="$set('price','{{ $val }}')" class="flex w-full items-center gap-2 {{ $price === $val ? 'font-bold text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                                <span class="w-4 h-4 rounded-full border {{ $price === $val ? 'border-4 border-gray-900 dark:border-white' : 'border-gray-300 dark:border-white/20' }}"></span>
                                {{ $label }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- ════ Grid ════ --}}
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <h1 class="text-xl font-extrabold tracking-tight text-gray-900 dark:text-white">Website templates <span class="text-gray-400 font-semibold">({{ $this->cards->total() }})</span></h1>
                <input wire:model.live.debounce.400ms="search" type="search" placeholder="Search templates…"
                       class="px-4 py-2.5 rounded-xl text-sm bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-900/20 min-w-[220px]">
            </div>

            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-9">
                @forelse ($this->cards as $c)
                    <a href="{{ route('template.detail', $c['slug']) }}" wire:navigate
                       class="group block alt rounded-2xl p-3 shadow-sm hover:-translate-y-0.5 transition-all">
                        <div class="rounded-xl overflow-hidden border border-gray-200/70 dark:border-white/[0.08]">
                            @if ($c['screenshots'])
                                <img src="{{ $c['screenshots'][0] }}" alt="{{ $c['name'] }}" class="w-full aspect-[15/16] object-cover object-top">
                            @elseif ($c['thumbnail'])
                                <img src="{{ $c['thumbnail'] }}" alt="{{ $c['name'] }}" class="w-full aspect-[15/16] object-cover object-top">
                            @else
                                <div class="w-full aspect-[15/16] grid place-items-center text-4xl font-black text-white" style="background:{{ $c['accent'] }}">{{ strtoupper(substr($c['name'], 0, 1)) }}</div>
                            @endif
                        </div>
                        <div class="flex items-start justify-between gap-3 mt-3 px-1 pb-1">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white truncate group-hover:underline">{{ $c['name'] }}</p>
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ \Illuminate\Support\Str::limit($c['description'], 60) ?: 'Ready-made design' }}</p>
                            </div>
                            <span class="shrink-0 px-2.5 py-1 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.04] text-[11px] font-semibold {{ $c['priceCents'] > 0 ? 'text-gray-600 dark:text-gray-300' : 'text-gray-500' }}">
                                {{ $c['priceCents'] > 0 ? $c['priceLabel'] : $c['category'] }}
                            </span>
                        </div>
                    </a>
                @empty
                    <p class="sm:col-span-2 xl:col-span-3 text-center text-sm text-gray-400 py-16">No templates match — try a different search or filter.</p>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if ($this->cards->hasPages())
                <nav class="flex items-center justify-center gap-1.5 mt-10" aria-label="Template pages">
                    <button wire:click="previousPage" @disabled($this->cards->onFirstPage())
                            class="px-3.5 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 disabled:opacity-30 hover:border-indigo-400">← Prev</button>
                    @foreach (range(1, $this->cards->lastPage()) as $p)
                        <button wire:click="gotoPage({{ $p }})"
                                class="w-9 h-9 rounded-xl text-sm font-bold {{ $p === $this->cards->currentPage() ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-600 hover:border-indigo-400' }}">{{ $p }}</button>
                    @endforeach
                    <button wire:click="nextPage" @disabled(! $this->cards->hasMorePages())
                            class="px-3.5 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 disabled:opacity-30 hover:border-indigo-400">Next →</button>
                </nav>
            @endif
        </div>
    </div>

    {{-- ── Quick-look drawer (kept for in-grid detail without leaving the page) ── --}}
    @if ($d = $this->detail)
        <div class="fixed inset-0 z-40 flex justify-end" x-data x-on:keydown.escape.window="$wire.closeDetail()">
            <div class="absolute inset-0 bg-black/40" wire:click="closeDetail"></div>
            <aside class="relative w-full max-w-lg h-full bg-white dark:bg-[#1d1e2a] shadow-2xl overflow-y-auto">
                <div class="sticky top-0 bg-white dark:bg-[#1d1e2a] border-b border-gray-100 dark:border-white/[0.05] px-6 py-4 flex items-center justify-between z-10">
                    <div>
                        <h2 class="text-base font-extrabold text-gray-900 dark:text-white">{{ $d['name'] }}</h2>
                        <p class="text-[11px] text-gray-400">{{ $d['category'] }} · {{ $d['priceLabel'] }}</p>
                    </div>
                    <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <div class="p-6 space-y-5">
                    @if ($d['description'])<p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $d['description'] }}</p>@endif
                    <a href="{{ route('template.detail', $d['slug']) }}" class="inline-block text-sm font-bold text-indigo-500 hover:underline">Open full page →</a>
                    @if ($message)<p class="px-4 py-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $message }}</p>@endif
                    @guest
                        <div class="rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 p-4 text-center">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">Like this design?</p>
                            <a href="{{ route('start') }}" class="inline-block mt-3 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Sign up to use this →</a>
                        </div>
                    @else
                        @if ($this->mySites)
                            <div class="space-y-1.5">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Save to one of your sites</p>
                                @foreach ($this->mySites as $s)
                                    <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-100 dark:border-white/[0.06] px-3 py-2">
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $s['name'] }}</span>
                                        <span class="flex items-center gap-2 shrink-0">
                                            <button wire:click="saveToSite('{{ $s['id'] }}')" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">Save</button>
                                            <a href="{{ url($s['name'].'/designs') }}" class="text-xs font-semibold text-indigo-500 hover:underline">My Designs →</a>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <a href="{{ route('start') }}" class="inline-block px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Create a site to use this →</a>
                        @endif
                    @endguest
                </div>
            </aside>
        </div>
    @endif
</div>
