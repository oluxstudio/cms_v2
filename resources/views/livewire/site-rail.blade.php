@php
    $levelDot = ['success'=>'#22c55e','error'=>'#ef4444','warning'=>'#f59e0b','info'=>'#3b82f6'];
@endphp
<div class="h-full flex flex-col min-h-0">

    {{-- Tabs --}}
    <div class="shrink-0 p-3">
        <div class="flex gap-1 bg-white/70 dark:bg-white/[0.05] rounded-2xl p-1">
            @foreach (['alerts'=>['Alerts','#ef4444'], 'messages'=>['Messages','#6366f1'], 'todos'=>['Todos','#10b981']] as $key => [$label,$badge])
                <button type="button" wire:click="$set('tab','{{ $key }}')"
                        class="fx flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-xl text-xs font-semibold transition-colors
                               {{ $tab === $key
                                   ? 'bg-gray-900 text-white shadow dark:bg-white dark:text-gray-900'
                                   : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]' }}">
                    {{ $label }}
                    @if (($counts[$key] ?? 0) > 0)
                        <span class="text-[10px] font-bold px-1.5 rounded-full text-white" style="background:{{ $badge }}">{{ $counts[$key] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto no-scrollbar px-3 pb-3 fx-stagger">

        {{-- ════ ALERTS ════ --}}
        @if ($tab === 'alerts')
            <div class="flex items-center justify-between px-1 mb-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Notifications</span>
                <button wire:click="markAllAlertsRead" class="text-[11px] font-medium text-indigo-600 hover:underline">Mark all read</button>
            </div>
            @forelse ($this->alerts as $a)
                <button wire:click="open('alert', {{ $a->id }})"
                        class="fx w-full text-left flex items-start gap-2.5 px-2.5 py-2.5 rounded-xl hover:bg-white dark:hover:bg-white/[0.04] {{ $a->read_at ? 'opacity-60' : '' }}">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0" style="background:{{ $levelDot[$a->level] ?? '#3b82f6' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $a->title }}</span>
                        @if ($a->body)<span class="block text-[11px] text-gray-400 truncate">{{ $a->body }}</span>@endif
                        <span class="block text-[10px] text-gray-400 mt-0.5">{{ $a->created_at->diffForHumans() }}</span>
                    </span>
                    @unless ($a->read_at)<span class="mt-1 w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>@endunless
                </button>
            @empty
                <p class="text-center text-xs text-gray-400 py-10">No new alerts.</p>
            @endforelse
            <a href="{{ url($this->site->name.'/alerts') }}" wire:navigate
               class="fx mt-2 flex items-center justify-center gap-1.5 w-full py-2 rounded-xl text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-white dark:hover:bg-white/[0.04]">
                See all alerts
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        @endif

        {{-- ════ MESSAGES ════ --}}
        @if ($tab === 'messages')
            <div class="space-y-1">
                @forelse ($this->messages as $msg)
                    <button wire:click="open('message', {{ $msg->id }})"
                            class="fx w-full text-left flex items-start gap-2.5 px-2.5 py-2.5 rounded-xl hover:bg-white dark:hover:bg-white/[0.04]">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold text-white shrink-0"
                              style="background:linear-gradient(135deg,var(--primary),var(--primary-2))">
                            {{ \Illuminate\Support\Str::of($msg->sender->name ?? '?')->substr(0,2)->upper() }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $msg->sender->name ?? 'Unknown' }}
                                    @if (is_null($msg->recipient_id))<span class="text-[10px] font-normal text-gray-400">· team</span>@endif
                                </span>
                                <span class="text-[10px] text-gray-400 shrink-0">{{ $msg->created_at->diffForHumans(null, true) }}</span>
                            </span>
                            <span class="block text-[11px] text-gray-400 truncate">{{ $msg->body }}</span>
                        </span>
                    </button>
                @empty
                    <p class="text-center text-xs text-gray-400 py-10">No messages yet.</p>
                @endforelse
            </div>

            {{-- bottom link → Messages page --}}
            <a href="{{ url($this->site->name.'/messages') }}" wire:navigate
               class="fx mt-2 flex items-center justify-center gap-1.5 w-full py-2 rounded-xl text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-white dark:hover:bg-white/[0.04]">
                Open Messages
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        @endif

        {{-- ════ TODOS (compact: title · status · progress) ════ --}}
        @if ($tab === 'todos')
            <div class="space-y-2.5">
                @forelse ($this->todos as $todo)
                    @php
                        $prog  = $todo->progress();
                        $done  = $todo->items->where('done', true)->count();
                        $total = $todo->items->count();
                        $isDone = $todo->status === 'done' || ($total > 0 && $prog >= 100);
                        [$badgeLabel, $badgeCls] = $isDone
                            ? ['Done',        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400']
                            : ($prog > 0
                                ? ['In progress', 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400']
                                : ['To do',       'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400']);
                    @endphp
                    <button wire:click="open('todo', {{ $todo->id }})"
                            class="fx w-full text-left rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-3 shadow-sm hover:shadow-md transition-shadow fx-in">
                        {{-- title + status --}}
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate {{ $isDone ? 'line-through text-gray-400' : '' }}">{{ $todo->title }}</span>
                            <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $badgeCls }}">{{ $badgeLabel }}</span>
                        </div>

                        {{-- progress bar --}}
                        <div class="flex items-center gap-2 mt-2">
                            <div class="flex-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300 {{ $isDone ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="width:{{ $prog }}%"></div>
                            </div>
                            <span class="text-[10px] font-medium text-gray-400 shrink-0">{{ $total ? $done.'/'.$total : $prog.'%' }}</span>
                        </div>
                    </button>
                @empty
                    <p class="text-center text-xs text-gray-400 py-10">No tasks yet.</p>
                @endforelse
            </div>

            {{-- bottom link → Todos page --}}
            <a href="{{ url($this->site->name.'/todos') }}" wire:navigate
               class="fx mt-2 flex items-center justify-center gap-1.5 w-full py-2 rounded-xl text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-white dark:hover:bg-white/[0.04]">
                Open Todos
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        @endif
    </div>
</div>
