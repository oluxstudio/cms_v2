@php
    $levelTint = ['success'=>'#16a34a','error'=>'#dc2626','warning'=>'#d97706','info'=>'#2563eb'];
    $typeLabel = fn ($t) => ucwords(str_replace('_', ' ', $t));
    $s = $this->stats;
@endphp
<div class="min-h-full flex flex-col lg:flex-row">

    {{-- ════ LEFT: statistics ════ --}}
    <aside class="left-bar w-full lg:w-72 shrink-0 border-b lg:border-b-0 lg:border-r border-gray-100 dark:border-white/[0.05] p-5 space-y-5
                  lg:sticky lg:top-0 lg:self-start lg:max-h-screen lg:overflow-y-auto no-scrollbar">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">Alerts</h1>
            <p class="text-xs text-gray-400 mt-0.5">{{ $s['total'] }} total · {{ $s['unread'] }} unread</p>
        </div>

        {{-- summary tiles --}}
        <div class="grid grid-cols-2 gap-2.5">
            <div class="rounded-[1.3rem] bg-[#f2efe8] dark:bg-[#282433] p-3 shadow-sm">
                <p class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">{{ $s['total'] }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Total</p>
            </div>
            <div class="rounded-[1.3rem] p-3 shadow-sm" style="background:#d9f068">
                <p class="text-2xl font-extrabold tracking-tight" style="color:#2b3110">{{ $s['unread'] }}</p>
                <p class="text-[11px] font-semibold" style="color:#2b3110;opacity:.65">Unread</p>
            </div>
        </div>

        {{-- by category (type) --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Categories</p>
            <div class="space-y-1">
                <button wire:click="setFilter('all')" class="fx w-full flex items-center justify-between px-3 py-2 rounded-xl text-sm {{ $filter==='all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.05]' }}">
                    <span>All alerts</span><span class="text-xs font-bold">{{ $s['total'] }}</span>
                </button>
                <button wire:click="setFilter('unread')" class="fx w-full flex items-center justify-between px-3 py-2 rounded-xl text-sm {{ $filter==='unread' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.05]' }}">
                    <span>Unread</span><span class="text-xs font-bold">{{ $s['unread'] }}</span>
                </button>
                @foreach ($s['byType'] as $type => $count)
                    <button wire:click="setFilter('type:{{ $type }}')" class="fx w-full flex items-center justify-between px-3 py-2 rounded-xl text-sm {{ $filter==='type:'.$type ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.05]' }}">
                        <span>{{ $typeLabel($type) }}</span><span class="text-xs font-bold">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- by level --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">By severity</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($s['byLevel'] as $level => $count)
                    <button wire:click="setFilter('level:{{ $level }}')"
                            class="fx inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $filter==='level:'.$level ? 'border-gray-900 dark:border-white' : 'border-gray-200 dark:border-white/[0.08]' }}">
                        <span class="w-2 h-2 rounded-full" style="background:{{ $levelTint[$level] ?? '#3b82f6' }}"></span>
                        {{ ucfirst($level) }} {{ $count }}
                    </button>
                @endforeach
            </div>
        </div>
    </aside>

    {{-- ════ MAIN: list ════ --}}
    <section class="main-body flex-1 min-w-0 p-5">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                {{ $this->alerts->count() }} {{ \Illuminate\Support\Str::plural('alert', $this->alerts->count()) }}
                @unless ($filter === 'all')<span class="text-gray-400 font-normal">· filtered</span>@endunless
            </p>
            <span class="flex items-center gap-3">
                <button wire:click="markAllRead" class="fx text-xs font-semibold text-indigo-600 hover:underline">Mark all read</button>
                <button wire:click="clearRead" data-confirm="Delete all read alerts?"
                        class="fx text-xs font-semibold text-gray-400 hover:text-rose-500">Clear read</button>
            </span>
        </div>

        <div class="space-y-2 fx-stagger">
            @forelse ($this->alerts as $a)
                <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm fx-in">
                    <button wire:click="toggle({{ $a->id }})" class="w-full text-left flex items-start gap-3 p-4">
                        <span class="mt-1 w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $levelTint[$a->level] ?? '#3b82f6' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $a->title }}</span>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500">{{ $typeLabel($a->type) }}</span>
                                @unless ($a->read_at)<span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>@endunless
                            </span>
                            <span class="block text-xs text-gray-400 mt-0.5">{{ $a->created_at->diffForHumans() }}</span>
                        </span>
                        <svg class="w-4 h-4 text-gray-300 shrink-0 transition-transform {{ $expanded === $a->id ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    @if ($expanded === $a->id)
                        <div class="px-4 pb-4 pl-9 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                            {{ $a->body ?: 'No further details.' }}
                            @if ($a->link)<a href="{{ $a->link }}" class="block mt-2 text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Go to related item →</a>@endif
                            <p class="text-[11px] text-gray-400 mt-2">{{ $a->created_at->format('M j, Y · g:i A') }}</p>
                            <button wire:click="deleteAlert({{ $a->id }})" data-confirm="Delete this alert?"
                                    class="mt-2 text-xs font-semibold text-gray-400 hover:text-rose-500">Delete alert</button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-gray-100 dark:border-white/[0.05] p-12 text-center text-sm text-gray-400">No alerts in this view.</div>
            @endforelse
        </div>
    </section>
</div>
