@php
    $tier = $sub->tier();
    $fmtBytes = function (int $b): string {
        if ($b >= 1073741824) return number_format($b / 1073741824, 1).' GB';
        if ($b >= 1048576) return number_format($b / 1048576, 1).' MB';
        return number_format(max($b, 0) / 1024, 1).' KB';
    };
    $storageUsed = $sub->storageUsedBytes();
    $storageMb = $sub->storageLimitMb();
@endphp
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">

    <a href="{{ route('admin.dashboard') }}" wire:navigate class="text-xs font-semibold text-gray-400 hover:text-indigo-500">← Platform dashboard</a>

    {{-- ── Account header ── --}}
    <div class="mt-3 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-6">
        <div class="flex flex-wrap items-center gap-4">
            <x-avatar :src="$user->avatar" :initials="strtoupper(substr($user->name, 0, 1))" size="w-14 h-14" textSize="text-xl font-bold" />
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-extrabold text-gray-900 dark:text-white truncate">{{ $user->name }}
                    @if ($user->isSuper())<span class="ml-1 text-[10px] font-bold uppercase text-indigo-400 align-middle">admin</span>@endif
                </h1>
                <p class="text-sm text-gray-400 truncate">{{ $user->email }}</p>
            </div>
            <span class="px-3 py-1.5 rounded-full text-xs font-bold text-white" style="background: {{ $tier['color'] }}">{{ $sub->badgeLabel() }}</span>
            <a href="{{ route('admin.accounts', ['q' => $user->email]) }}" wire:navigate
               class="px-3 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600">
                Manage pricing
            </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 text-center">
            @foreach ([
                ['Joined', $user->created_at->format('M j, Y')],
                ['Last seen', $lastSeen ? $lastSeen->diffForHumans() : '—'],
                ['Sites', $sub->sitesUsage()],
                ['Storage', $fmtBytes($storageUsed).($storageMb ? ' / '.$storageMb.' MB' : '')],
            ] as [$label, $value])
                <div class="rounded-xl bg-gray-50 dark:bg-white/[0.03] px-3 py-2.5">
                    <p class="text-sm font-extrabold text-gray-900 dark:text-white truncate">{{ $value }}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $label }}</p>
                </div>
            @endforeach
        </div>
        @if ($tokens->isNotEmpty())
            <p class="mt-3 text-[11px] text-gray-400">
                {{ $tokens->count() }} API {{ Str::plural('token', $tokens->count()) }}
                @if ($tokens->first()?->last_used_at) · last used {{ $tokens->first()->last_used_at->diffForHumans() }} @endif
            </p>
        @endif
    </div>

    {{-- ── Sites ── --}}
    <h2 class="mt-6 mb-2 text-sm font-bold text-gray-900 dark:text-white">Sites ({{ $sites->count() }})</h2>
    @if ($sites->isEmpty() && $memberSites->isEmpty())
        <p class="text-sm text-gray-400">No sites yet.</p>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($sites as $s)
                <a href="{{ route('site.dashboard', ['siteID' => $s->name]) }}"
                   class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-4 hover:border-indigo-300 transition-colors">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $s->name }}</p>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold
                                     {{ $s->live ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                            {{ $s->live ? 'Live' : 'Draft' }}
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">{{ $s->pages_count }} pages · {{ $s->media_count }} media · created {{ $s->created_at->format('M j, Y') }}</p>
                    @if ($s->domain)<p class="text-[11px] text-indigo-400 truncate mt-0.5">{{ $s->domain }}</p>@endif
                </a>
            @endforeach
            @foreach ($memberSites as $s)
                <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-dashed border-gray-200 dark:border-white/[0.08] p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $s->name }}</p>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
                            Member · {{ $s->pivot->role ?? 'editor' }}
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">Owned by another account</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Diary / timeline ── --}}
    <div class="mt-7 flex flex-wrap items-center gap-2">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white mr-2">Activity diary</h2>
        @foreach (['all' => 'All', 'account' => 'Logins & security', 'content' => 'Sites & content', 'api' => 'API'] as $key => $label)
            <button wire:click="setFilter('{{ $key }}')"
                    class="px-3 py-1 rounded-full text-[11px] font-bold transition-colors
                           {{ $filter === $key ? 'text-white' : 'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-white/[0.05] hover:bg-gray-200 dark:hover:bg-white/[0.08]' }}"
                    @if ($filter === $key) style="background:var(--primary)" @endif>{{ $label }}</button>
        @endforeach
    </div>

    <div class="mt-3 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5 sm:p-6">
        @forelse ($days as $date => $entries)
            @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
            <div class="sticky top-0 z-10 -mx-2 px-2 py-1 bg-white/90 dark:bg-[#1d1e2a]/90 backdrop-blur">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400">
                    {{ $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('D, M j, Y')) }}
                </span>
            </div>
            <div class="mt-2 mb-5 space-y-0">
                @foreach ($entries as $entry)
                    @php $log = $entry['log']; @endphp
                    <div class="flex gap-3 relative pb-4 last:pb-1">
                        @unless ($loop->last)
                            <span class="absolute left-[13px] top-7 bottom-0 w-px bg-gray-100 dark:bg-white/[0.06]"></span>
                        @endunless
                        @if ($entry['kind'] === 'account')
                            <span class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center ring-4 ring-white dark:ring-[#1d1e2a]"
                                  style="background:color-mix(in srgb, {{ $log->accent() }} 18%, transparent)">
                                <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $log->accent() }}"></span>
                            </span>
                        @else
                            @php [$bg, $fg] = $log->iconColors(); @endphp
                            <span class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center ring-4 ring-white dark:ring-[#1d1e2a]"
                                  style="background: {{ $bg }}; color: {{ $fg }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $log->iconPath() }}"/></svg>
                            </span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $log->title }}</p>
                                @if ($entry['kind'] === 'account')
                                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase"
                                          style="background:color-mix(in srgb, {{ $log->accent() }} 15%, transparent); color: {{ $log->accent() }}">{{ $log->category }}</span>
                                @else
                                    @php [$badge, $bBg, $bFg] = $log->actionBadge(); @endphp
                                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase" style="background: {{ $bBg }}; color: {{ $bFg }}">{{ $badge }}</span>
                                    @if ($log->site)
                                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-300">{{ $log->site->name }}</span>
                                    @endif
                                @endif
                            </div>
                            @if ($log->description)
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ $log->description }}</p>
                            @endif
                            <p class="text-[10px] text-gray-400 mt-0.5 tabular-nums">{{ $entry['at']->format('g:i a') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="py-10 text-center text-sm text-gray-400">Nothing recorded for this account yet.</p>
        @endforelse

        @if ($hasMore)
            <button wire:click="loadMore"
                    class="mt-2 w-full py-2 rounded-xl text-xs font-bold text-gray-500 dark:text-gray-300 bg-gray-50 dark:bg-white/[0.04] hover:bg-gray-100 dark:hover:bg-white/[0.07]">
                Load older entries
                <span wire:loading wire:target="loadMore">…</span>
            </button>
        @endif
    </div>
</div>
