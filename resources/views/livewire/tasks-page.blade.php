@php
    $c = $this->counts;
    $tabs = ['all' => 'All', 'open' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done', 'overdue' => 'Overdue', 'mine' => 'Mine'];
    $palette = [['#f6b26b', '#e69138'], ['#5bd18a', '#2fb463'], ['#f97b7b', '#e05252'], ['#7cc3ef', '#3f9fdc'], ['#5a6cf0', '#3d4bd6'], ['#c982e6', '#a95dd0'], ['#a88657', '#8a6b3f'], ['#f4c542', '#d9a81a'], ['#a06ef0', '#7f4ad6']];
    $prDot = ['high' => '#ef4444', 'normal' => '#6366f1', 'low' => '#94a3b8'];
    $statusCls = ['open' => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400', 'in_progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400', 'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'];
    // Avatar photo when it loads, the user's INITIALS otherwise — the img sits
    // on top of the initials badge and removes itself if the URL is broken,
    // so a dead avatar link can never show the browser's broken-image icon.
    $avatar = function ($u, $size = 'w-7 h-7') {
        if (! $u) {
            return '';
        }
        $initials = e(\Illuminate\Support\Str::of($u->name)->substr(0, 2)->upper());
        $badge = '<span class="'.$size.' rounded-full grid place-items-center text-[10px] font-bold text-white ring-2 ring-white dark:ring-[#1d1e2a] overflow-hidden relative shrink-0" style="background:linear-gradient(135deg,#6366f1,#a855f7)" title="'.e($u->name).'">'.$initials;
        if ($u->avatar) {
            $badge .= '<img src="'.e($u->avatar).'" alt="" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">';
        }

        return $badge.'</span>';
    };
@endphp
<div>
<x-tri-layout title="Tasks" subtitle="Assign, track and finish the work behind this site." :site-name="$this->site->name"
    :labels="['📊 Overview', '✅ Tasks', '🗓 Planner']" quick-width="lg:!w-[400px]"
    x-data="{ tview: localStorage.getItem('tasks-view') || 'grid' }"
    x-init="$watch('tview', v => localStorage.setItem('tasks-view', v))">

    {{-- ── LEFT rail: status tiles in the house style (cream surface,
         big number, accent chip; ink hero for "All") ── --}}
    <x-slot:rail>
    @php
        $railTiles = [
            'all'         => ['ink',      'background:#d9f068;color:#2b3110', 'everything on the board'],
            'open'        => ['lavender', 'background:#d7c3f5;color:#33245c', 'not started yet'],
            'in_progress' => ['sky',      'background:#bfdcf7;color:#173a5e', 'being worked on'],
            'done'        => ['lime',     'background:#d9f068;color:#2b3110', 'finished work'],
            'overdue'     => ['rose',     'background:#fecaca;color:#7f1d1d', 'past their due date'],
            'mine'        => ['cocoa',    'background:#e6d6c6;color:#4a3628', 'assigned to you'],
        ];
        $railTotal = max(1, $c['all']);
    @endphp
    <div class="grid grid-cols-2 gap-3">
        @foreach ($tabs as $key => $label)
            @php
                [$accent, $chip, $hint] = $railTiles[$key] ?? ['lime', 'background:#d9f068;color:#2b3110', ''];
                $ink = $accent === 'ink';
                $share = $key === 'all' ? 100 : (int) round($c[$key] / $railTotal * 100);
                $active = $filter === $key;
            @endphp
            {{-- "All" is the full-width ink hero; the rest pair up 2-per-row.
                 No borders/rings anywhere — the active tile shows through a
                 lifted shadow + solid primary progress line. --}}
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="fx {{ $key === 'all' ? 'col-span-2' : '' }} w-full text-left rounded-[1.6rem] {{ $key === 'all' ? 'p-5' : 'p-4' }} flex flex-col transition-all
                           {{ $ink ? 'bg-[#332433] text-white' : 'bg-[#f2efe8] dark:bg-[#282433] text-gray-900 dark:text-white' }}
                           {{ $active ? 'shadow-lg -translate-y-0.5' : 'shadow-sm hover:shadow-md hover:-translate-y-0.5 opacity-90' }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="{{ $key === 'all' ? 'text-[2.6rem]' : 'text-[1.9rem]' }} leading-none font-extrabold tracking-tight tabular-nums">{{ $c[$key] }}</p>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold" style="{{ $chip }}">{{ $label }}</span>
                </div>
                <p class="text-[11px] mt-2 {{ $ink ? 'text-white/60' : 'text-gray-500 dark:text-gray-400' }}">{{ $hint }}</p>
                <div class="mt-3 h-[5px] rounded-full {{ $ink ? 'bg-white/15' : 'bg-black/[0.06] dark:bg-white/[0.08]' }} overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width:{{ $share }}%;background:{{ $active ? 'var(--primary)' : ($ink ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.18)') }}"></div>
                </div>
            </button>
        @endforeach
    </div>
    </x-slot:rail>

<div class="max-w-[50rem] mx-auto">
    {{-- ── Header: actions ── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <span></span>
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

    <div class="flex flex-wrap items-center gap-2 mb-5">
        <span class="w-full flex items-center justify-between">
            <span class="text-sm font-extrabold text-gray-900 dark:text-white">Your tasks <span class="text-gray-400 font-semibold">· {{ $c['all'] }}</span></span>
            <span class="shrink-0 inline-flex rounded-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] p-0.5">
                <button @click="tview='grid'" title="Grid view" :class="tview==='grid' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-400'" class="px-2 py-1 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                </button>
                <button @click="tview='list'" title="List view" :class="tview==='list' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-400'" class="px-2 py-1 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </span>
        </span>
        @if ($onDay)
            <button wire:click="pickDay('{{ $onDay }}')" title="Show every task again"
                    class="fx shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[13px] font-semibold text-white" style="background:#1c1d29">
                🗓 {{ \Illuminate\Support\Carbon::parse($onDay)->format('D j M') }} <span class="opacity-70">✕</span>
            </button>
        @endif
        @foreach ($tabs as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="fx shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                           {{ $filter === $key
                                ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                                : 'bg-white dark:bg-[#1d1e2a] border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400 hover:border-gray-400' }}">
                @if($key === 'overdue')🔥 @endif{{ $label }}
                <span class="text-[10px] font-bold {{ $filter === $key ? 'opacity-70' : ($key === 'overdue' && $c[$key] ? 'text-rose-500' : 'text-gray-400') }}">{{ $c[$key] }}</span>
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

            {{-- breakdown is required — it's what drives the task's progress bar --}}
            <div>
                <label class="text-[11px] font-semibold text-gray-400">Task breakdown <span class="text-rose-400">*</span> <span class="font-normal">— one step per line, at least one</span></label>
                <textarea wire:model="items" rows="3" placeholder="e.g.&#10;Draft the copy&#10;Review with the team&#10;Publish"
                          class="mt-1 w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                @error('items')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <x-panel-group label="More options" hint="details, dates">
                <textarea wire:model="description" rows="2" placeholder="Details (optional)"
                          class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-400">Start
                        <input wire:model="startAt" type="date" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100"></label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-400">Finish
                        <input wire:model="dueAt" type="date" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100"></label>
                    @error('dueAt')<p class="text-xs text-red-500 w-full">{{ $message }}</p>@enderror
                </div>
            </x-panel-group>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('composing', false)" class="fx px-3 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]">Cancel</button>
                <button type="submit" class="fx px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:#6366f1">Create task</button>
            </div>
        </form>
    @endif

    {{-- ── Card grid — clean board cards: chip · title · blurb · checklist · people ── --}}
    <div :class="tview==='list' ? 'flex flex-col gap-3 pb-6' : 'grid sm:grid-cols-2 gap-5 pb-6'">
        @forelse ($this->tasks as $t)
            @php
                $overdue = $t->isOverdue();
                $chip = $t->status === 'done'
                    ? ['Done', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400']
                    : ($overdue
                        ? ['Overdue', 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400']
                        : match ($t->priority) {
                            'high' => ['High priority', 'bg-rose-50 text-rose-500 dark:bg-rose-500/10 dark:text-rose-400'],
                            'low' => ['Low priority', 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
                            default => ['In flight', 'bg-fuchsia-50 text-fuchsia-500 dark:bg-fuchsia-500/10 dark:text-fuchsia-400'],
                        });
                $due = $t->due_at ? ($t->status === 'done' ? null : ($overdue ? $t->due_at->diffForHumans(null, true).' overdue' : 'due in '.$t->due_at->diffForHumans(null, true))) : null;
                $people = collect([$t->assignee, $t->creator])->merge($t->comments->pluck('author'))->filter()->unique('id')->values();
                $shown = $people->take(5);
            @endphp
            <div x-show="tview==='grid'" class="relative rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm hover:shadow-md transition-shadow p-4 flex flex-col">

                {{-- chip + kebab --}}
                <div class="flex items-start justify-between gap-2">
                    <span class="text-[10px] font-bold px-2 py-1 rounded-md {{ $chip[1] }}">{{ $chip[0] }}</span>
                    <div x-data="{ open: false }" class="relative -mt-1 -mr-1">
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
                </div>

                {{-- title + blurb (click opens the drawer) --}}
                <button wire:click="open('{{ $t->id }}')" class="fx block w-full text-left mt-2">
                    <p class="text-[15px] font-extrabold text-gray-900 dark:text-white leading-snug {{ $t->status === 'done' ? 'line-through text-gray-400' : '' }}">{{ $t->title }}</p>
                    <p class="text-[12.5px] text-gray-400 mt-1 line-clamp-2 min-h-[2.4em]">{{ $t->description ?: ($t->assignee ? 'Assigned to '.$t->assignee->name : 'No description yet — open to add one.') }}</p>
                </button>

                {{-- progress bar — sample style: gradient line · % · days-left chip --}}
                @php $prog = $t->status === 'done' ? 100 : $t->progress(); @endphp
                <div class="mt-3">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-[5px] rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ $prog }}%;background:linear-gradient(90deg,#38bdf8,#a855f7,#f97316)"></div>
                        </div>
                        <span class="text-[11px] font-extrabold text-gray-600 dark:text-gray-300">{{ $prog }}%</span>
                    </div>
                </div>

                {{-- checklist progress + due --}}
                <div class="flex items-center gap-2 mt-3">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-gray-500 dark:text-gray-300 px-2 py-1 rounded-lg border border-gray-200 dark:border-white/[0.08]" title="Checklist">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/></svg>
                        {{ $t->items->where('done', true)->count() }}/{{ $t->items->count() }}
                    </span>
                    @if ($due)
                        <span class="text-[10px] font-bold px-2 py-1 rounded-lg {{ $overdue ? 'bg-rose-50 text-rose-500 dark:bg-rose-500/10' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10' }}">{{ $due }}</span>
                    @endif
                </div>

                {{-- footer: avatar stack + count · comments · checklist --}}
                <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-50 dark:border-white/[0.04]">
                    {{-- sample style: real avatars first (max 5), member-count bubble at the end --}}
                    <span class="flex items-center -space-x-2">
                        @foreach ($shown as $u) {!! $avatar($u) !!} @endforeach
                        <span class="relative z-10 w-7 h-7 rounded-full grid place-items-center text-[10px] font-extrabold text-white ring-2 ring-white dark:ring-[#1d1e2a]" style="background:var(--primary)" title="{{ $people->pluck('name')->implode(', ') ?: 'Nobody yet' }}">{{ $people->count() }}</span>
                    </span>
                    <span class="flex items-center gap-3 text-[11px] font-semibold text-gray-400">
                        <span class="inline-flex items-center gap-1 {{ $t->comments_count ? 'text-indigo-500' : '' }}" title="Comments"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.4-4 8-9 8a9.9 9.9 0 01-4-.8L3 20l1.3-3.9A7.4 7.4 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z"/></svg>{{ $t->comments_count }}</span>
                        <span class="inline-flex items-center gap-1" title="Subtasks"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>{{ $t->items->count() }}</span>
                    </span>
                </div>
            </div>

            {{-- LIST row — sample-2 style: title/meta · duration · progress line · dates --}}
            @php
                $prog = $t->status === 'done' ? 100 : $t->progress();
                $lineColor = $t->status === 'done' ? '#22c55e' : ($overdue ? '#ef4444' : 'var(--primary)');
                $duration = $t->starts_at && $t->due_at
                    ? $t->starts_at->diffForHumans($t->due_at, true)
                    : ($t->due_at ? $t->created_at->diffForHumans($t->due_at, true) : $t->created_at->diffForHumans(null, true));
            @endphp
            <button x-show="tview==='list'" x-cloak wire:click="open('{{ $t->id }}')"
                    class="fx w-full grid grid-cols-[minmax(0,1.4fr)_auto_minmax(120px,1fr)_auto] items-center gap-4 rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm hover:shadow-md transition-shadow px-4 py-3 text-left">
                <span class="min-w-0">
                    <span class="block text-sm font-extrabold text-gray-900 dark:text-white truncate {{ $t->status === 'done' ? 'line-through text-gray-400' : '' }}">{{ $t->title }}</span>
                    <span class="block text-[11px] text-gray-400 truncate mt-0.5">{{ $t->created_at->format('j M · H:i') }}{{ $t->assignee ? ' · '.$t->assignee->name : '' }}</span>
                </span>
                <span class="hidden sm:block text-right">
                    <span class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Duration</span>
                    <span class="block text-[12px] font-extrabold text-gray-800 dark:text-gray-100">{{ $duration }}</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="text-[12px] font-extrabold text-gray-800 dark:text-gray-100 w-9 text-right">{{ $prog }}%</span>
                    <span class="flex-1 h-[4px] rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                        <span class="block h-full rounded-full" style="width:{{ $prog }}%;background:{{ $lineColor }}"></span>
                    </span>
                </span>
                <span class="flex items-center gap-3 text-[11px] font-semibold text-gray-400">
                    <span class="inline-flex items-center gap-1" title="Checklist"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>{{ $t->items->where('done', true)->count() }} · {{ $t->items->count() }}</span>
                    @if ($t->due_at)
                        <span class="inline-flex items-center gap-1 {{ $overdue ? 'text-rose-500' : '' }}" title="Due date"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>{{ $t->due_at->format('j M') }}</span>
                    @endif
                </span>
            </button>
        @empty
            <div class="sm:col-span-2 rounded-2xl border border-dashed border-gray-200 dark:border-white/[0.08] p-12 text-center text-sm text-gray-400">No tasks in this view.</div>
        @endforelse
    </div>
</div>

    {{-- ── RIGHT rail: overall progress + team chat (sample layout) ── --}}
    <x-slot:quick>
        {{-- Overall progress ring — ink card (sample) --}}
        @php $op = $this->overallProgress; @endphp
        <div class="rounded-2xl p-5 mb-5 text-center shadow-lg text-white" style="background:linear-gradient(135deg,#1c1d29,#2b2d3f)">
            <h3 class="text-sm font-extrabold">Overall Progress</h3>
            <div class="relative w-28 h-28 mx-auto mt-4" title="Average completion across all tasks">
                <svg class="w-28 h-28 -rotate-90" viewBox="0 0 40 40">
                    <circle cx="20" cy="20" r="16.5" fill="none" stroke="rgba(255,255,255,.14)" stroke-width="3"/>
                    <circle cx="20" cy="20" r="16.5" fill="none" stroke="url(#opGrad)" stroke-width="3" stroke-linecap="round"
                            stroke-dasharray="{{ round($op / 100 * 103.7, 1) }} 103.7"/>
                    <defs><linearGradient id="opGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#f97316"/><stop offset="100%" stop-color="#ec4899"/>
                    </linearGradient></defs>
                </svg>
                <span class="absolute inset-0 grid place-items-center">
                    <span>
                        <span class="block text-2xl font-extrabold">{{ $op }}%</span>
                        <span class="block text-[10px] text-white/60 font-semibold">Progress</span>
                    </span>
                </span>
            </div>
        </div>

        {{-- Needs attention: overdue / due within 48h --}}
        @if ($this->needsAttention->isNotEmpty())
        <div class="rounded-2xl border-2 p-4 mb-5 bg-white dark:bg-[#1d1e2a] shadow-sm" style="border-color:color-mix(in srgb, #ef4444 35%, transparent)">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2.5">🔥 {{ $this->needsAttention->count() }} {{ Str::plural('task', $this->needsAttention->count()) }} need{{ $this->needsAttention->count() === 1 ? 's' : '' }} attention</h3>
            <div class="space-y-2">
                @foreach ($this->needsAttention as $na)
                    <button wire:click="open('{{ $na->id }}')" class="fx w-full flex items-center gap-2.5 text-left">
                        <span class="shrink-0 w-1 h-8 rounded-full" style="background:{{ $na->isOverdue() ? '#ef4444' : '#f59e0b' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100 truncate">{{ $na->title }}</span>
                            <span class="block text-[10px] {{ $na->isOverdue() ? 'text-rose-500 font-semibold' : 'text-amber-600' }}">{{ $na->isOverdue() ? 'Overdue '.$na->due_at->diffForHumans(null, true) : 'Due '.$na->due_at->diffForHumans() }}{{ $na->assignee ? ' · '.$na->assignee->name : '' }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Activity feed — read-only pulse of the board --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-3">Activity</h3>
            <div class="space-y-3">
                @forelse ($this->activity as $ev)
                    <button wire:click="open('{{ $ev['taskId'] }}')" class="fx w-full flex items-start gap-2.5 text-left">
                        <span class="shrink-0 w-7 h-7 rounded-full grid place-items-center text-[13px] bg-gray-50 dark:bg-white/[0.05]">{{ $ev['icon'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[12px] leading-snug text-gray-700 dark:text-gray-200">{{ $ev['text'] }}</span>
                            <span class="block text-[10px] text-gray-400 mt-0.5">{{ $ev['at']->diffForHumans() }}</span>
                        </span>
                    </button>
                @empty
                    <p class="text-[11px] text-gray-400 text-center py-3">Nothing yet — activity shows up here as the team works.</p>
                @endforelse
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>

    {{-- ── Detail drawer ── --}}
    @if ($task = $this->openTask)
        <x-side-drawer close="close">
            <x-slot:header>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $statusCls[$task->status] ?? $statusCls['open'] }}">{{ \App\Models\Todo::STATUSES[$task->status] ?? ucfirst($task->status) }}</span>
                <h2 class="text-lg font-extrabold text-gray-900 dark:text-white mt-1.5 truncate">{{ $task->title }}</h2>
                <p class="text-[11px] text-gray-400 mt-0.5">Created by {{ $task->creator?->name ?? 'someone' }} {{ $task->created_at->diffForHumans() }}
                    @if ($task->starts_at || $task->due_at) · {{ $task->starts_at?->format('j M') ?? '…' }} → {{ $task->due_at?->format('j M Y') ?? 'no finish date' }} @endif</p>
            </x-slot:header>

                {{-- sample-1 hero: big rounded gradient bar with the % inside --}}
                @php $dprog = $task->status === 'done' ? 100 : $task->progress(); @endphp
                <div class="relative h-9 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                    <div class="absolute inset-y-0 left-0 rounded-full flex items-center justify-end pr-3 min-w-[3.5rem]"
                         style="width:{{ max(12, $dprog) }}%;background:linear-gradient(90deg,{{ $task->status === 'done' ? '#22c55e,#4ade80' : ($task->isOverdue() ? '#ef4444,#f87171' : 'var(--primary),#f59e0b') }})">
                        <span class="text-[13px] font-extrabold text-white drop-shadow">{{ $dprog }}%</span>
                    </div>
                </div>

                {{-- ── Start / Due — always visible, editable in place ── --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/60 dark:bg-white/[0.03] px-3 py-2.5">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Start date</p>
                        <p class="text-sm font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $task->starts_at?->format('D j M Y') ?? 'Not set' }}</p>
                        <input type="date" value="{{ $task->starts_at?->toDateString() }}"
                               wire:change="setTaskDate('{{ $task->id }}', 'start', $event.target.value)"
                               class="mt-1.5 w-full text-xs px-2 py-1 rounded-lg bg-white dark:bg-white/[0.06] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200">
                    </div>
                    <div class="rounded-xl border {{ $task->isOverdue() ? 'border-rose-200 dark:border-rose-500/30 bg-rose-50/60 dark:bg-rose-500/[0.06]' : 'border-gray-100 dark:border-white/[0.06] bg-gray-50/60 dark:bg-white/[0.03]' }} px-3 py-2.5">
                        <p class="text-[10px] font-bold uppercase tracking-wide {{ $task->isOverdue() ? 'text-rose-500' : 'text-gray-400' }}">Due date{{ $task->isOverdue() ? ' · overdue' : '' }}</p>
                        <p class="text-sm font-extrabold {{ $task->isOverdue() ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }} mt-0.5">{{ $task->due_at?->format('D j M Y') ?? 'Not set' }}</p>
                        <input type="date" value="{{ $task->due_at?->toDateString() }}"
                               wire:change="setTaskDate('{{ $task->id }}', 'due', $event.target.value)"
                               class="mt-1.5 w-full text-xs px-2 py-1 rounded-lg bg-white dark:bg-white/[0.06] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200">
                    </div>
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

                {{-- ── Timeline: Gantt of the breakdown against a date axis ── --}}
                @if ($tl = $task->timeline())
                    @php
                        $rows = collect($tl['rows']);
                        // breakdown letter + palette colour for each item, matching the list below
                        $itemIndex = $task->items->pluck('id')->flip();
                        // date ticks across the top (at most ~8 labels)
                        $tickStep = max(1, (int) ceil($tl['days'] / 8));
                        $ticks = [];
                        for ($d = 0; $d < $tl['days']; $d += $tickStep) {
                            $ticks[] = ['left' => $tl['days'] > 1 ? $d / ($tl['days'] - 1) * 100 : 0, 'label' => $tl['start']->copy()->addDays($d)->format('d')];
                        }
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Timeline</p>
                            <p class="text-[11px] text-gray-400">{{ $tl['start']->format('j M') }} → {{ $tl['end']->format('j M Y') }} · {{ $tl['days'] }} {{ \Illuminate\Support\Str::plural('day', $tl['days']) }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] p-3">
                            {{-- date axis --}}
                            <div class="relative h-5 ml-9 mr-1 text-[10px] font-semibold text-gray-400">
                                @foreach ($ticks as $t)<span class="absolute top-0 -translate-x-1/2" style="left:{{ $t['left'] }}%">{{ $t['label'] }}</span>@endforeach
                            </div>
                            <div class="relative">
                                {{-- day gridlines + today marker span every row --}}
                                <div class="absolute inset-y-0 left-9 right-1 pointer-events-none">
                                    @foreach ($ticks as $t)<span class="absolute top-0 bottom-0 w-px bg-gray-100 dark:bg-white/[0.05]" style="left:{{ $t['left'] }}%"></span>@endforeach
                                    @if ($tl['today'] !== null)<span class="absolute top-0 bottom-0 w-px bg-rose-400 z-10" style="left:{{ $tl['today'] }}%" title="Today"></span>@endif
                                </div>
                                <div class="space-y-2 relative">
                                    {{-- whole task --}}
                                    @if ($tl['task'])
                                        <div class="flex items-center">
                                            <span class="w-9 shrink-0 text-[10px] font-bold text-gray-400" title="Whole task">Task</span>
                                            <div class="relative flex-1 h-7 mr-1">
                                                <span class="absolute inset-y-0 rounded-full flex items-center px-2.5 min-w-[3rem] overflow-hidden {{ $task->status === 'done' ? 'bg-emerald-500' : '' }}"
                                                      style="left:{{ $tl['task']['left'] }}%;width:{{ $tl['task']['width'] }}%;{{ $task->status === 'done' ? '' : 'background:linear-gradient(90deg,var(--primary),#f59e0b)' }}"
                                                      title="{{ $task->starts_at?->format('j M') ?? $task->created_at->format('j M') }} → {{ $task->due_at?->format('j M Y') ?? '…' }}">
                                                    <span class="text-[10px] font-extrabold text-white whitespace-nowrap drop-shadow">{{ $task->status === 'done' ? 100 : $task->progress() }}%</span>
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                    {{-- breakdown items with dates — sample-style lettered gradient pills --}}
                                    @forelse ($rows as $r)
                                        @php
                                            $idx = (int) ($itemIndex[$r['id']] ?? 0);
                                            [$gc1, $gc2] = $palette[$idx % count($palette)];
                                            // % = done, or how far today sits through the item's span
                                            $rp = $r['done'] ? 100
                                                : (now()->lte($r['from']) ? 0
                                                : (now()->gte($r['to']) ? 100
                                                : (int) round($r['from']->diffInSeconds(now()) / max(1, $r['from']->diffInSeconds($r['to'])) * 100)));
                                        @endphp
                                        <div class="flex items-center">
                                            <span class="w-9 shrink-0 grid place-items-center">
                                                <span class="w-5 h-5 rounded-full grid place-items-center text-[9px] font-extrabold text-white" style="background:{{ $r['done'] ? '#22c55e' : 'linear-gradient(135deg,'.$gc1.','.$gc2.')' }}">{{ chr(65 + ($idx % 26)) }}</span>
                                            </span>
                                            <div class="relative flex-1 h-7 mr-1">
                                                <span class="absolute inset-y-0 rounded-full flex items-center gap-1.5 px-2.5 min-w-[3rem] overflow-hidden"
                                                      style="left:{{ $r['left'] }}%;width:{{ $r['width'] }}%;background:{{ $r['done'] ? '#22c55e' : 'linear-gradient(90deg,'.$gc1.','.$gc2.')' }}"
                                                      title="{{ $r['label'] }}{{ $r['assignee'] ? ' · '.$r['assignee'] : '' }} · {{ $r['from']->format('j M') }} → {{ $r['to']->format('j M') }}">
                                                    <span class="text-[10px] font-bold text-white/95 truncate">{{ $r['label'] }}</span>
                                                    <span class="ml-auto text-[10px] font-extrabold text-white whitespace-nowrap drop-shadow">{{ $rp }}%</span>
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-[11px] text-gray-400 pl-9 py-1">Set a start and finish on the items below to plot them here.</p>
                                    @endforelse
                                </div>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 pt-2 mt-2 border-t border-gray-100 dark:border-white/[0.06]"><span>{{ $tl['start']->format('D j M') }}</span>@if ($tl['today'] !== null)<span class="text-rose-400">today</span>@endif<span>{{ $tl['end']->format('D j M') }}</span></div>
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
                                        {{-- lettered circle IS the done toggle (sample-1 A/B/C rows) --}}
                                        @php [$ic1, $ic2] = $palette[$loop->index % count($palette)]; @endphp
                                        <button type="button" wire:click="toggleItem('{{ $item->id }}')" title="{{ $item->done ? 'Mark not done' : 'Mark done' }}"
                                                class="fx mt-0.5 w-7 h-7 rounded-full grid place-items-center shrink-0 text-[11px] font-extrabold text-white shadow-sm"
                                                style="background:{{ $item->done ? '#22c55e' : 'linear-gradient(135deg,'.$ic1.','.$ic2.')' }}">
                                            @if ($item->done)<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @else{{ chr(65 + ($loop->index % 26)) }}@endif
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 {{ $item->done ? 'line-through text-gray-400' : '' }}">{{ $item->label }}</p>
                                            @if ($item->description)<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 whitespace-pre-line">{{ $item->description }}</p>@endif
                                            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-400 mt-1">
                                                <span class="inline-flex items-center gap-1">{!! $avatar($item->assignee, 'w-4 h-4') !!}{{ $item->assignee?->name ?? 'Unassigned' }}</span>
                                                @if (! $item->done && $item->ends_at?->isPast())<span class="text-rose-500 font-semibold">overdue</span>@endif
                                            </p>
                                            {{-- set this item's dates right here — feeds the timeline above --}}
                                            <div class="flex flex-wrap items-center gap-2 mt-1.5 text-[10px] font-semibold text-gray-400">
                                                <label class="inline-flex items-center gap-1">Start
                                                    <input type="date" value="{{ $item->starts_at?->toDateString() }}"
                                                           wire:change="setItemDate('{{ $item->id }}', 'start', $event.target.value)"
                                                           class="text-[11px] font-normal px-1.5 py-0.5 rounded-md bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200">
                                                </label>
                                                <label class="inline-flex items-center gap-1">Finish
                                                    <input type="date" value="{{ $item->ends_at?->toDateString() }}"
                                                           wire:change="setItemDate('{{ $item->id }}', 'end', $event.target.value)"
                                                           class="text-[11px] font-normal px-1.5 py-0.5 rounded-md bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200">
                                                </label>
                                            </div>
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

            <x-slot:footer>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Notes · {{ $task->comments->count() }}</p>
                <div class="space-y-2.5 max-h-40 overflow-y-auto pr-1">
                    @forelse ($task->comments as $cm)
                        <div class="flex items-start gap-2.5">
                            {!! $avatar($cm->author) !!}
                            <div class="min-w-0 flex-1 rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3 py-2">
                                <p class="text-[11px] font-semibold text-gray-700 dark:text-gray-200">{{ $cm->author?->name ?? 'Someone' }} <span class="font-normal text-gray-400">· {{ $cm->created_at->diffForHumans() }}</span></p>
                                <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $cm->body }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No notes yet — leave one for your teammates.</p>
                    @endforelse
                </div>
                <form wire:submit="addComment" class="mt-3 flex items-end gap-2">
                    <textarea wire:model="comment" rows="2" placeholder="Leave a note… teammates on this task are notified"
                              class="flex-1 px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                    <button type="submit" class="fx px-3.5 py-2 rounded-xl text-sm font-semibold text-white" style="background:var(--primary)">Send</button>
                </form>
                @error('comment')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </x-slot:footer>
        </x-side-drawer>
    @endif
</div>
