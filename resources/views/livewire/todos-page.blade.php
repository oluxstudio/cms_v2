@php $s = $this->stats; $prTint = ['high'=>'#ef4444','normal'=>'#6366f1','low'=>'#94a3b8']; @endphp
<div class="min-h-full flex flex-col lg:flex-row">

    {{-- ════ LEFT: statistics ════ --}}
    <aside class="left-bar w-full lg:w-72 shrink-0 border-b lg:border-b-0 lg:border-r border-gray-100 dark:border-white/[0.05] p-5 space-y-5
                  lg:sticky lg:top-0 lg:self-start lg:max-h-screen lg:overflow-y-auto no-scrollbar">
        <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">Todos</h1>

        {{-- completion ring --}}
        @php $c = $s['completion']; $R=30; $C=2*M_PI*$R; @endphp
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-4 shadow-sm flex items-center gap-4">
            <div class="relative w-20 h-20 shrink-0">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 72 72">
                    <circle cx="36" cy="36" r="{{ $R }}" fill="none" stroke="rgba(148,163,184,.2)" stroke-width="8"/>
                    <circle cx="36" cy="36" r="{{ $R }}" fill="none" stroke="#10b981" stroke-width="8" stroke-linecap="round" stroke-dasharray="{{ $C*$c/100 }} {{ $C }}"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center text-base font-extrabold text-gray-900 dark:text-white">{{ $c }}%</div>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $s['done'] }}/{{ $s['total'] }} done</p>
                <p class="text-xs text-gray-400">{{ $s['doneItems'] }}/{{ $s['items'] }} subtasks</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2.5">
            <button wire:click="$set('filter','open')" class="fx rounded-2xl p-3 text-left border {{ $filter==='open' ? 'border-amber-400 bg-amber-50 dark:bg-amber-500/10' : 'bg-white dark:bg-[#1d1e2a] border-gray-100 dark:border-white/[0.05]' }} shadow-sm">
                <p class="text-2xl font-extrabold text-amber-600">{{ $s['open'] }}</p><p class="text-[11px] text-gray-400">Open</p>
            </button>
            <button wire:click="$set('filter','done')" class="fx rounded-2xl p-3 text-left border {{ $filter==='done' ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-500/10' : 'bg-white dark:bg-[#1d1e2a] border-gray-100 dark:border-white/[0.05]' }} shadow-sm">
                <p class="text-2xl font-extrabold text-emerald-600">{{ $s['done'] }}</p><p class="text-[11px] text-gray-400">Done</p>
            </button>
        </div>

        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">By priority</p>
            <div class="space-y-1.5">
                @foreach (['high','normal','low'] as $p)
                    <div class="flex items-center justify-between text-sm">
                        <span class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-300"><span class="w-2 h-2 rounded-full" style="background:{{ $prTint[$p] }}"></span>{{ ucfirst($p) }}</span>
                        <span class="text-xs font-bold text-gray-500">{{ $s['byPriority'][$p] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <button wire:click="$set('filter','all')" class="fx w-full text-xs font-medium text-indigo-600 hover:underline {{ $filter==='all' ? 'opacity-40' : '' }}">Show all</button>
    </aside>

    {{-- ════ MAIN: list + create ════ --}}
    <section class="main-body flex-1 min-w-0 p-5">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $this->todos->count() }} {{ \Illuminate\Support\Str::plural('task', $this->todos->count()) }}</p>
            <button wire:click="$set('composing', true)" class="fx inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-sm" style="background:#10b981">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New todo
            </button>
        </div>

        {{-- create --}}
        @if ($composing)
            <form wire:submit="create" class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-4 mb-4 shadow-sm space-y-3">
                <input wire:model="title" type="text" placeholder="Task title…"
                       class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                @error('title')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-2">
                    <select wire:model="assignee" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        <option value="">Unassigned</option>
                        @foreach ($this->members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                    </select>
                    <select wire:model="priority" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option>
                    </select>
                </div>
                <textarea wire:model="items" rows="3" placeholder="Subtasks — one per line (optional)"
                          class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('composing', false)" class="fx px-3 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]">Cancel</button>
                    <button type="submit" class="fx px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:#10b981">Create task</button>
                </div>
            </form>
        @endif

        <div class="grid sm:grid-cols-2 gap-3 fx-stagger">
            @forelse ($this->todos as $todo)
                @php $prog = $todo->progress(); @endphp
                <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-4 shadow-sm fx-in">
                    <div class="flex items-start gap-2.5">
                        <button wire:click="toggleTodo('{{ $todo->id }}')" class="fx mt-0.5 w-5 h-5 rounded-md border-2 flex items-center justify-center shrink-0 {{ $todo->status==='done' ? 'bg-emerald-500 border-emerald-500' : 'border-gray-300 dark:border-gray-600 hover:border-emerald-400' }}">
                            @if ($todo->status==='done')<svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                        </button>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white {{ $todo->status==='done' ? 'line-through text-gray-400' : '' }}">{{ $todo->title }}</p>
                            <p class="text-[10px] text-gray-400">
                                <span class="inline-block w-1.5 h-1.5 rounded-full align-middle" style="background:{{ $prTint[$todo->priority] ?? '#6366f1' }}"></span>
                                {{ ucfirst($todo->priority) }}{{ $todo->assignee ? ' · '.$todo->assignee->name : '' }}
                            </p>
                        </div>
                        <button wire:click="deleteTodo('{{ $todo->id }}')" data-confirm="Delete this todo?" class="fx text-gray-300 hover:text-rose-500 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
                    </div>

                    @if ($todo->items->isNotEmpty())
                        <div class="h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] mt-2.5 overflow-hidden"><div class="h-full rounded-full bg-emerald-500 transition-all duration-300" style="width:{{ $prog }}%"></div></div>
                    @endif

                    <div class="mt-2 space-y-1">
                        @foreach ($todo->items as $item)
                            <label class="flex items-center gap-2 text-xs cursor-pointer group">
                                <button type="button" wire:click="toggleItem('{{ $item->id }}')" class="fx w-4 h-4 rounded border flex items-center justify-center shrink-0 {{ $item->done ? 'bg-emerald-500 border-emerald-500' : 'border-gray-300 dark:border-gray-600 group-hover:border-emerald-400' }}">
                                    @if ($item->done)<svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                                </button>
                                <span class="text-gray-600 dark:text-gray-300 {{ $item->done ? 'line-through text-gray-400' : '' }}">{{ $item->label }}</span>
                            </label>
                        @endforeach
                        <form wire:submit="addItem('{{ $todo->id }}')">
                            <input wire:model="newItem.{{ $todo->id }}" type="text" placeholder="+ subtask"
                                   class="w-full mt-1 px-2 py-1 rounded-lg text-[11px] bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </form>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 rounded-2xl border border-gray-100 dark:border-white/[0.05] p-12 text-center text-sm text-gray-400">No tasks in this view.</div>
            @endforelse
        </div>
    </section>
</div>
