@php
    $c = $this->counts;
    $tabs = ['all' => 'All', 'open' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done', 'overdue' => 'Overdue', 'mine' => 'Mine'];
    $palette = [['#f6b26b', '#e69138'], ['#5bd18a', '#2fb463'], ['#f97b7b', '#e05252'], ['#7cc3ef', '#3f9fdc'], ['#5a6cf0', '#3d4bd6'], ['#c982e6', '#a95dd0'], ['#a88657', '#8a6b3f'], ['#f4c542', '#d9a81a'], ['#a06ef0', '#7f4ad6']];
    $prDot = ['high' => '#ef4444', 'normal' => '#6366f1', 'low' => '#94a3b8'];
    $statusCls = ['open' => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400', 'in_progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400', 'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'];
    $avatar = fn ($u, $size = 'w-7 h-7') => $u
        ? ($u->avatar
            ? '<img src="'.e($u->avatar).'" alt="" class="'.$size.' rounded-full object-cover ring-2 ring-white dark:ring-[#1d1e2a]" title="'.e($u->name).'">'
            : '<span class="'.$size.' rounded-full grid place-items-center text-[10px] font-bold text-white ring-2 ring-white dark:ring-[#1d1e2a]" style="background:linear-gradient(135deg,#6366f1,#a855f7)" title="'.e($u->name).'">'.e(\Illuminate\Support\Str::of($u->name)->substr(0, 2)->upper()).'</span>')
        : '';
@endphp
<div class="main-body p-5 lg:p-6 min-h-full bg-[#f3f2fb] dark:bg-transparent">

    <x-carousel :labels="['📊 Overview', '✅ Tasks']" :start="1" class="lg:flex-col">
    <x-carousel.slide class="lg:w-full pb-24 lg:pb-0 max-h-full overflow-y-auto lg:overflow-y-visible no-scrollbar">

    {{-- ── Header: title · tabs · actions ── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white flex items-center gap-2">Tasks
            @if ($c['overdue']) <span class="w-5 h-5 rounded-full bg-rose-500 text-white text-[10px] font-bold grid place-items-center" title="{{ $c['overdue'] }} overdue">{{ $c['overdue'] }}</span> @endif
        </h1>
        <div class="flex items-center gap-2">
            @if ($this->canManage())
                <a href="{{ url($this->site->name.'/team') }}" wire:navigate class="fx inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:border-indigo-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Invite people
                </a>
            @endif
            <button wire:click="$set('composing', true)" class="fx inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-sm" style="background:linear-gradient(120deg,#6366f1,#8b5cf6)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Create task
            </button>
        </div>
    </div>

    {{-- Overview tiles (mobile slide 1; also shown above the board on desktop) --}}
    <div class="grid grid-cols-2 gap-3 mb-4 lg:hidden">
        @foreach ($tabs as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="rounded-2xl bg-white dark:bg-[#1d1e2a] border {{ $filter === $key ? 'border-indigo-400 ring-2 ring-indigo-500/20' : 'border-gray-100 dark:border-white/[0.05]' }} shadow-sm p-4 text-left">
                <p class="text-2xl font-extrabold tracking-tight {{ $key === 'overdue' && $c[$key] ? 'text-rose-600' : 'text-gray-900 dark:text-white' }}">{{ $c[$key] }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ $label }}</p>
            </button>
        @endforeach
    </div>
    </x-carousel.slide>

    <x-carousel.slide class="lg:w-full pb-24 lg:pb-0 max-h-full overflow-y-auto lg:overflow-y-visible no-scrollbar">
    <div class="flex items-center gap-1 overflow-x-auto no-scrollbar border-b border-gray-100 dark:border-white/[0.06] mb-5">
        @foreach ($tabs as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="fx shrink-0 flex items-center gap-1.5 px-3 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors {{ $filter === $key ? 'border-indigo-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-400 hover:text-gray-600 dark:hover:text-gray-200' }}">
                {{ $label }}
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $key === 'overdue' && $c[$key] ? 'bg-rose-100 text-rose-600' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400' }}">{{ $c[$key] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ── Composer ── --}}
    @if ($composing)
        <form wire:submit="create" class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-4 mb-5 shadow-sm space-y-3">
            <input wire:model="title" type="text" placeholder="What needs doing?" autofocus
                   class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            @error('title')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-2">
                <select wire:model="assignee" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    <option value="">Unassigned</option>
                    @foreach ($this->members as $m)<option value="{{ $m['id'] }}">{{ $m['name'] }}{{ $m['role'] ? ' · '.ucfirst($m['role']) : '' }}</option>@endforeach
                </select>
                <select wire:model="priority" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    <option value="low">Low priority</option><option value="normal">Normal priority</option><option value="high">High priority</option>
                </select>
            </div>

            <x-panel-group label="More options" hint="details, dates, subtasks">
                <textarea wire:model="description" rows="2" placeholder="Details (optional)"
                          class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-400">Start
                        <input wire:model="startAt" type="date" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100"></label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-400">Finish
                        <input wire:model="dueAt" type="date" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100"></label>
                    @error('dueAt')<p class="text-xs text-red-500 w-full">{{ $message }}</p>@enderror
                </div>
                <textarea wire:model="items" rows="3" placeholder="Subtasks — one per line (optional)"
                          class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
            </x-panel-group>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('composing', false)" class="fx px-3 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]">Cancel</button>
                <button type="submit" class="fx px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:#6366f1">Create task</button>
            </div>
        </form>
    @endif

    {{-- ── Card grid ── --}}
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-14 pt-8 pb-6 fx-stagger">
        @forelse ($this->tasks as $t)
            @php
                $prog = $t->progress(); $overdue = $t->isOverdue();
                [$c1, $c2] = $palette[crc32($t->id) % count($palette)];
                $due = $t->due_at ? ($t->status === 'done' ? 'Done' : ($overdue ? $t->due_at->diffForHumans(null, true).' overdue' : $t->due_at->diffForHumans(null, true).' left')) : null;
                $dueCls = $t->status === 'done' ? 'bg-emerald-50 text-emerald-600' : ($overdue ? 'bg-rose-50 text-rose-600' : ($t->due_at && $t->due_at->diffInDays() <= 3 ? 'bg-amber-50 text-amber-600' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'));
            @endphp
            @php
                // People on this task: assignee, creator, then commenters — max 3 avatars, "+N" for the rest.
                $people = collect([$t->assignee, $t->creator])->merge($t->comments->pluck('author'))->filter()->unique('id')->values();
                $shown = $people->take(3); $extra = $people->count() - $shown->count();
            @endphp
            <div class="task-card relative fx-in">
                {{-- stacked-sheet effect --}}
                <span class="absolute inset-x-4 -bottom-2.5 h-4 rounded-2xl bg-white/70 dark:bg-white/[0.04] shadow-sm" aria-hidden="true"></span>
                <span class="absolute inset-x-8 -bottom-5 h-4 rounded-2xl bg-white/40 dark:bg-white/[0.02] shadow-sm" aria-hidden="true"></span>

                <div class="relative rounded-[1.35rem] bg-white dark:bg-[#1d1e2a] shadow-[0_10px_30px_-12px_rgba(30,27,75,.18)] pt-5 pb-4 px-5">
                    {{-- icon tile — top left, lifted over the card edge --}}
                    <button wire:click="open('{{ $t->id }}')" class="fx absolute -top-4 left-4 w-14 h-14 rounded-2xl grid place-items-center text-white shadow-[0_12px_22px_-8px_rgba(0,0,0,.35)]"
                            style="background:linear-gradient(145deg,{{ $c1 }},{{ $c2 }})" title="Open task">
                        @if ($t->status === 'done')
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 14l2 2 4-4"/></svg>
                        @endif
                    </button>

                    {{-- kebab — top right, out of the icon's way --}}
                    <div class="absolute top-3 right-3" x-data="{ open: false }">
                        <button @click="open = ! open" class="fx w-7 h-7 rounded-lg grid place-items-center text-gray-300 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.06]"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></button>
                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-1 w-44 rounded-xl bg-white dark:bg-[#262736] border border-gray-100 dark:border-white/[0.08] shadow-lg py-1 z-20 text-sm text-left">
                            @foreach (\App\Models\Todo::STATUSES as $k => $lbl)
                                @if ($k !== $t->status)
                                    <button wire:click="setStatus('{{ $t->id }}', '{{ $k }}')" @click="open = false" class="fx w-full text-left px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-white/[0.05] text-gray-700 dark:text-gray-200">Mark {{ strtolower($lbl) }}</button>
                                @endif
                            @endforeach
                            <button wire:click="open('{{ $t->id }}')" @click="open = false" class="fx w-full text-left px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-white/[0.05] text-gray-700 dark:text-gray-200">Open &amp; comment</button>
                            <button wire:click="deleteTask('{{ $t->id }}')" data-confirm="Delete this task?" class="fx w-full text-left px-3 py-1.5 hover:bg-rose-50 dark:hover:bg-rose-500/10 text-rose-600">Delete</button>
                        </div>
                    </div>

                    {{-- title + subtitle — centred, clear of the tile --}}
                    <button wire:click="open('{{ $t->id }}')" class="fx block w-full text-center mt-9">
                        <p class="text-[15px] font-bold text-gray-900 dark:text-white truncate {{ $t->status === 'done' ? 'line-through text-gray-400' : '' }}">{{ $t->title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5 truncate">
                            <span class="inline-block w-1.5 h-1.5 rounded-full align-middle mr-1" style="background:{{ $prDot[$t->priority] ?? '#6366f1' }}" title="{{ ucfirst($t->priority) }} priority"></span>{{ $t->assignee ? $t->assignee->name : 'Unassigned' }}@if ($due) · <span class="{{ $overdue ? 'text-rose-500 font-semibold' : '' }}">{{ $due }}</span>@endif
                        </p>
                    </button>

                    {{-- people on the task (max 3) --}}
                    <div class="flex justify-center items-center -space-x-2 mt-3 min-h-[1.75rem]">
                        @foreach ($shown as $u) {!! $avatar($u) !!} @endforeach
                        @if ($extra > 0)
                            <span class="w-7 h-7 rounded-full grid place-items-center text-[10px] font-bold bg-gray-100 text-gray-600 dark:bg-white/[0.08] dark:text-gray-300 ring-2 ring-white dark:ring-[#1d1e2a]" title="{{ $people->skip(3)->pluck('name')->implode(', ') }}">+{{ $extra }}</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between text-[13px] font-bold text-gray-900 dark:text-gray-100 mt-4">
                        <span>Progress</span><span class="text-xs">{{ $prog }}%</span>
                    </div>
                    <div class="h-[5px] rounded-full bg-gray-100 dark:bg-white/[0.06] mt-1.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-300" style="width:{{ $prog }}%;background:#22c55e"></div>
                    </div>

                    {{-- footer: subtasks · comments badge · time since created --}}
                    <div class="flex items-center justify-between mt-4 text-[11px] text-gray-400">
                        <span class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1" title="Subtasks done"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>{{ $t->items->where('done', true)->count() }}/{{ $t->items->count() }}</span>
                            <span class="inline-flex items-center gap-1 {{ $t->comments_count ? 'text-indigo-500 font-semibold' : '' }}" title="Comments"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.4-4 8-9 8a9.9 9.9 0 01-4-.8L3 20l1.3-3.9A7.4 7.4 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z"/></svg>{{ $t->comments_count }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400" title="Created {{ $t->created_at->toDayDateTimeString() }}"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ $t->created_at->diffForHumans(null, true) }} ago</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="sm:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-gray-200 dark:border-white/[0.08] p-12 text-center text-sm text-gray-400">No tasks in this view.</div>
        @endforelse
    </div>
    </x-carousel.slide>
    </x-carousel>

    {{-- ── Detail drawer ── --}}
    @if ($task = $this->openTask)
        <div class="fixed inset-0 z-40 flex justify-end" x-data x-on:keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/40" wire:click="close"></div>
            <aside class="relative w-full max-w-2xl h-full bg-white dark:bg-[#1d1e2a] shadow-2xl overflow-y-auto p-6 space-y-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $statusCls[$task->status] ?? $statusCls['open'] }}">{{ \App\Models\Todo::STATUSES[$task->status] ?? ucfirst($task->status) }}</span>
                        <h2 class="text-lg font-extrabold text-gray-900 dark:text-white mt-1.5">{{ $task->title }}</h2>
                        <p class="text-[11px] text-gray-400 mt-0.5">Created by {{ $task->creator?->name ?? 'someone' }} {{ $task->created_at->diffForHumans() }}
                            @if ($task->starts_at || $task->due_at) · {{ $task->starts_at?->format('j M') ?? '…' }} → {{ $task->due_at?->format('j M Y') ?? 'no finish date' }} @endif</p>
                    </div>
                    <button wire:click="close" class="fx text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
                </div>

                @if ($task->description)<p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $task->description }}</p>@endif

                <div class="grid grid-cols-2 gap-2">
                    <label class="text-[11px] font-semibold text-gray-400">Assignee
                        <select wire:change="assign('{{ $task->id }}', $event.target.value)" class="mt-1 w-full text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                            <option value="" @selected(! $task->assigned_user_id)>Unassigned</option>
                            @foreach ($this->members as $m)<option value="{{ $m['id'] }}" @selected($task->assigned_user_id === $m['id'])>{{ $m['name'] }}{{ $m['role'] ? ' · '.ucfirst($m['role']) : '' }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-[11px] font-semibold text-gray-400">Status
                        <select wire:change="setStatus('{{ $task->id }}', $event.target.value)" class="mt-1 w-full text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                            @foreach (\App\Models\Todo::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected($task->status === $k)>{{ $lbl }}</option>@endforeach
                        </select>
                    </label>
                </div>

                {{-- ── Timeline: only when the task or its items are dated ── --}}
                @if ($tl = $task->timeline())
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Timeline</p>
                            <p class="text-[11px] text-gray-400">{{ $tl['start']->format('j M') }} → {{ $tl['end']->format('j M Y') }} · {{ $tl['days'] }} {{ \Illuminate\Support\Str::plural('day', $tl['days']) }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] p-3 space-y-2 text-[11px]">
                            @php $rows = collect($tl['rows']); @endphp
                            {{-- whole task --}}
                            @if ($tl['task'])
                                <div class="flex items-center gap-3">
                                    <span class="w-32 shrink-0 font-bold text-gray-800 dark:text-gray-100 truncate">Whole task</span>
                                    <div class="relative flex-1 h-5 rounded-md bg-gray-50 dark:bg-white/[0.04]">
                                        @if ($tl['today'] !== null)<span class="absolute top-0 bottom-0 w-px bg-rose-400 z-10" style="left:{{ $tl['today'] }}%" title="Today"></span>@endif
                                        <span class="absolute top-1 bottom-1 rounded-md {{ $task->status === 'done' ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="left:{{ $tl['task']['left'] }}%;width:{{ $tl['task']['width'] }}%"></span>
                                    </div>
                                </div>
                            @endif
                            {{-- items with dates --}}
                            @forelse ($rows as $r)
                                <div class="flex items-center gap-3">
                                    <span class="w-32 shrink-0 pl-3 text-gray-600 dark:text-gray-300 truncate {{ $r['done'] ? 'line-through text-gray-400' : '' }}" title="{{ $r['label'] }}{{ $r['assignee'] ? ' · '.$r['assignee'] : '' }}">↳ {{ $r['label'] }}</span>
                                    <div class="relative flex-1 h-5 rounded-md bg-gray-50 dark:bg-white/[0.04]">
                                        @if ($tl['today'] !== null)<span class="absolute top-0 bottom-0 w-px bg-rose-400/60" style="left:{{ $tl['today'] }}%"></span>@endif
                                        <span class="absolute top-1 bottom-1 rounded-md {{ $r['done'] ? 'bg-emerald-400' : 'bg-sky-400' }}" style="left:{{ $r['left'] }}%;width:{{ $r['width'] }}%" title="{{ $r['from']->format('j M') }} → {{ $r['to']->format('j M') }}"></span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-400 pl-3">Give task items a start and finish to see them here.</p>
                            @endforelse
                            <div class="flex justify-between text-[10px] text-gray-400 pt-1 border-t border-gray-100 dark:border-white/[0.06]"><span>{{ $tl['start']->format('D j M') }}</span>@if ($tl['today'] !== null)<span class="text-rose-400">today</span>@endif<span>{{ $tl['end']->format('D j M') }}</span></div>
                        </div>
                    </div>
                @endif

                {{-- ── Breakdown: every task item with who / what / when ── --}}
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Task breakdown · {{ $task->items->where('done', true)->count() }}/{{ $task->items->count() }} done · {{ $task->progress() }}%</p>
                    <div class="space-y-2">
                        @forelse ($task->items as $item)
                            <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] p-3">
                                @if ($editingItem === $item->id)
                                    <form wire:submit="saveItem" class="space-y-2">
                                        <input wire:model="itemForm.label" type="text" class="w-full px-2.5 py-1.5 rounded-lg text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100" placeholder="What is this item?">
                                        @error('itemForm.label')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                        <textarea wire:model="itemForm.description" rows="2" class="w-full px-2.5 py-1.5 rounded-lg text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 resize-none" placeholder="Description — what exactly needs doing"></textarea>
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
                                            <select wire:model="itemForm.assignee" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                                <option value="">Unassigned</option>
                                                @foreach ($this->members as $m)<option value="{{ $m['id'] }}">{{ $m['name'] }}</option>@endforeach
                                            </select>
                                            <label class="inline-flex items-center gap-1">Start <input wire:model="itemForm.startsAt" type="date" class="text-sm px-2 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100"></label>
                                            <label class="inline-flex items-center gap-1">Finish <input wire:model="itemForm.endsAt" type="date" class="text-sm px-2 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100"></label>
                                        </div>
                                        @error('itemForm.endsAt')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                        <div class="flex justify-end gap-2">
                                            <button type="button" wire:click="cancelItem" class="fx px-3 py-1.5 rounded-lg text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]">Cancel</button>
                                            <button type="submit" class="fx px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:#6366f1">Save item</button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex items-start gap-2.5">
                                        <button type="button" wire:click="toggleItem('{{ $item->id }}')" class="fx mt-0.5 w-4 h-4 rounded border flex items-center justify-center shrink-0 {{ $item->done ? 'bg-emerald-500 border-emerald-500' : 'border-gray-300 dark:border-gray-600 hover:border-emerald-400' }}">
                                            @if ($item->done)<svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 {{ $item->done ? 'line-through text-gray-400' : '' }}">{{ $item->label }}</p>
                                            @if ($item->description)<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 whitespace-pre-line">{{ $item->description }}</p>@endif
                                            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-400 mt-1">
                                                <span class="inline-flex items-center gap-1">{!! $avatar($item->assignee, 'w-4 h-4') !!}{{ $item->assignee?->name ?? 'Unassigned' }}</span>
                                                @if ($item->starts_at || $item->ends_at)
                                                    <span class="inline-flex items-center gap-1 {{ (! $item->done && $item->ends_at?->isPast()) ? 'text-rose-500 font-semibold' : '' }}"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>{{ $item->starts_at?->format('j M') ?? '…' }} → {{ $item->ends_at?->format('j M') ?? '…' }}</span>
                                                @else
                                                    <span class="italic">no dates</span>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <button wire:click="editItem('{{ $item->id }}')" class="fx w-7 h-7 rounded-lg grid place-items-center text-gray-400 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-white/[0.06]" title="Edit item"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.2 4.8l4 4L8 20H4v-4L15.2 4.8z"/></svg></button>
                                            <button wire:click="deleteItem('{{ $item->id }}')" data-confirm="Remove this item?" class="fx w-7 h-7 rounded-lg grid place-items-center text-gray-300 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Remove"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">No items yet — break the task down below.</p>
                        @endforelse
                        <form wire:submit="addItem('{{ $task->id }}')">
                            <input wire:model="newItem.{{ $task->id }}" type="text" placeholder="+ add a task item, then edit it to set who / when"
                                   class="w-full mt-1 px-2.5 py-1.5 rounded-lg text-xs bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </form>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Comments · {{ $task->comments->count() }}</p>
                    <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                        @forelse ($task->comments as $cm)
                            <div class="flex items-start gap-2.5">
                                {!! $avatar($cm->author) !!}
                                <div class="min-w-0 flex-1 rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3 py-2">
                                    <p class="text-[11px] font-semibold text-gray-700 dark:text-gray-200">{{ $cm->author?->name ?? 'Someone' }} <span class="font-normal text-gray-400">· {{ $cm->created_at->diffForHumans() }}</span></p>
                                    <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $cm->body }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">No comments yet — start the conversation.</p>
                        @endforelse
                    </div>
                    <form wire:submit="addComment" class="mt-3 flex items-end gap-2">
                        <textarea wire:model="comment" rows="2" placeholder="Write a comment… teammates on this task are notified"
                                  class="flex-1 px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        <button type="submit" class="fx px-3.5 py-2 rounded-xl text-sm font-semibold text-white" style="background:#6366f1">Send</button>
                    </form>
                    @error('comment')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </aside>
        </div>
    @endif
</div>
