@php
    $statusStyles = [
        'new'       => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400',
        'contacted' => 'bg-blue-100 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400',
        'qualified' => 'bg-violet-100 text-violet-700 dark:bg-violet-400/10 dark:text-violet-400',
        'won'       => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        'lost'      => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
    ];
    $statuses = \App\Models\Contact::STATUSES;
    $counts   = $this->statusCounts;
@endphp

<div class="main-body p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-5">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Contacts</h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Leads captured from your forms, organised as a pipeline.</p>
        </div>
        <a href="{{ url($site->name.'/forms') }}"
           class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            View Forms
        </a>
    </div>

    {{-- Sub-view tabs --}}
    <div class="flex items-center gap-1 p-1 mb-5 bg-gray-100 dark:bg-white/[0.04] rounded-xl w-max">
        <button wire:click="setView('pipeline')"
                class="flex items-center gap-2 px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors
                    {{ $view === 'pipeline' ? 'bg-white dark:bg-[#22232f] text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
            Pipeline
        </button>
        <button wire:click="setView('sources')"
                class="flex items-center gap-2 px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors
                    {{ $view === 'sources' ? 'bg-white dark:bg-[#22232f] text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Sources
        </button>
    </div>

    @if($view === 'pipeline')
    {{-- Pipeline filter pills + search --}}
    <div class="flex flex-wrap items-center gap-2 mb-5">
        <button wire:click="setStatusFilter('all')"
            class="px-4 py-1.5 rounded-full text-sm font-medium border transition-colors
                {{ $statusFilter === 'all'
                    ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                    : 'bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.08]' }}">
            All <span class="opacity-60">{{ $counts['all'] ?? 0 }}</span>
        </button>
        @foreach($statuses as $st)
        <button wire:click="setStatusFilter('{{ $st }}')"
            class="px-4 py-1.5 rounded-full text-sm font-medium border transition-colors capitalize
                {{ $statusFilter === $st
                    ? 'border-transparent '.$statusStyles[$st]
                    : 'bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.08]' }}">
            {{ $st }} <span class="opacity-60">{{ $counts[$st] ?? 0 }}</span>
        </button>
        @endforeach

        <div class="ml-auto relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search contacts…"
                   class="pl-9 pr-4 py-2 w-full sm:w-56 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] rounded-xl text-sm text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>
    </div>

    {{-- Contacts table --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
        @forelse($this->contacts as $contact)
        <div class="flex items-center gap-4 px-5 py-3.5 border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
            <button wire:click="open({{ $contact->id }})" class="flex items-center gap-3 min-w-0 flex-1 text-left cursor-pointer">
                @include('partials.contact-avatar', ['contact' => $contact, 'size' => 'w-9 h-9', 'text' => 'text-xs'])
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $contact->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate">
                        {{ $contact->email ?: $contact->phone ?: 'No contact info' }}
                    </p>
                </div>
            </button>

            <div wire:click="open({{ $contact->id }})" class="hidden md:block w-36 truncate cursor-pointer">
                @if($contact->assignedUser)
                    <div class="flex items-center gap-1.5">
                        @php $ab = ['#6366f1','#8b5cf6','#ec4899','#0ea5e9','#10b981'][abs(crc32($contact->assignedUser->name)) % 5]; @endphp
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-white text-[9px] font-bold shrink-0" style="background:{{ $ab }}">
                            {{ strtoupper(\Illuminate\Support\Str::substr($contact->assignedUser->name, 0, 1)) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $contact->assignedUser->name }}</span>
                    </div>
                @else
                    <span class="text-xs text-gray-300 dark:text-gray-600">Unassigned</span>
                @endif
            </div>

            <div wire:click="open({{ $contact->id }})" class="hidden sm:block text-xs text-gray-400 dark:text-gray-500 w-20 text-center cursor-pointer">
                {{ $contact->responses_count }} {{ Str::plural('msg', $contact->responses_count) }}
            </div>

            {{-- Status dropdown --}}
            <select wire:change="updateStatus({{ $contact->id }}, $event.target.value)"
                    class="text-xs font-semibold capitalize pr-7 pl-3 py-1.5 rounded-full border-0 cursor-pointer focus:ring-2 focus:ring-indigo-500 outline-none {{ $statusStyles[$contact->status] ?? '' }}">
                @foreach($statuses as $st)
                <option value="{{ $st }}" @selected($contact->status === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>

            <button wire:click="deleteContact({{ $contact->id }})" data-confirm="Delete this contact?"
                    class="p-1.5 rounded-lg text-gray-300 dark:text-gray-600 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </div>
        @empty
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-14 h-14 rounded-full flex items-center justify-center mb-4 bg-gray-100 dark:bg-white/[0.05]">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            @if($search !== '' || $statusFilter !== 'all')
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">No contacts match your filters.</p>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">No contacts yet.</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Convert a form response into a contact to get started.</p>
                <a href="{{ url($site->name.'/forms') }}" class="mt-3 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Go to Form Responses →</a>
            @endif
        </div>
        @endforelse
    </div>
    @endif {{-- /pipeline --}}

    {{-- ════════════════ SOURCES (lead-source analytics) ════════════════ --}}
    @if($view === 'sources')
    @php $ins = $this->insights; @endphp

    {{-- Funnel KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-tile label="Form responses" :value="number_format($ins['totalResponses'])" sub="Total submissions" accent="ink" />
        <x-tile label="Converted" :value="number_format($ins['converted'])" :sub="$ins['convRate'].'% of responses'" accent="lavender" />
        <x-tile label="Contacts" :value="number_format($ins['contacts'])" :sub="$ins['qualified'].' qualified+'" accent="cocoa" />
        <x-tile label="Won" :value="number_format($ins['won'])" :sub="$ins['winRate'].'% win rate'" accent="lime" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6">

        {{-- Per-form conversion table --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Which form converts best</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Ranked by conversion rate (responses → contacts).</p>
            </div>

            {{-- Column header --}}
            <div class="hidden sm:flex items-center gap-4 px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 border-b border-gray-50 dark:border-white/[0.04]">
                <span class="flex-1">Form</span>
                <span class="w-20 text-right">Responses</span>
                <span class="w-20 text-right">Contacts</span>
                <span class="w-14 text-right">Won</span>
                <span class="w-28 text-right">Conversion</span>
            </div>

            @forelse($ins['perForm'] as $row)
            <div class="flex items-center gap-4 px-5 py-3.5 border-b border-gray-50 dark:border-white/[0.04] last:border-0">
                <span class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{{ $row['title'] }}</span>
                <span class="w-20 text-right text-sm text-gray-600 dark:text-gray-300">{{ number_format($row['responses']) }}</span>
                <span class="w-20 text-right text-sm text-gray-600 dark:text-gray-300">{{ number_format($row['contacts']) }}</span>
                <span class="w-14 text-right text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ $row['won'] }}</span>
                <div class="w-28 flex items-center gap-2 justify-end">
                    <div class="flex-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden max-w-[60px]">
                        <div class="h-full rounded-full bg-indigo-500" style="width:{{ min(100, $row['rate']) }}%"></div>
                    </div>
                    <span class="text-sm font-bold text-gray-900 dark:text-white w-9 text-right">{{ $row['rate'] }}%</span>
                </div>
            </div>
            @empty
            <div class="px-5 py-10 text-center text-sm text-gray-400 dark:text-gray-500">No forms yet.</div>
            @endforelse
        </div>

        {{-- Trend --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">New contacts</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">Last 14 days</p>
            @php $tMax = max(collect($ins['trend'])->max('value'), 1); @endphp
            <div class="flex items-end gap-1 h-32">
                @foreach($ins['trend'] as $bar)
                @php $h = $bar['value'] > 0 ? max(6, round($bar['value'] / $tMax * 100)) : 6; @endphp
                <div class="flex-1 flex flex-col items-center gap-1 group relative" title="{{ $bar['label'] }}: {{ $bar['value'] }}">
                    @if($bar['value'] > 0)
                    <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity">{{ $bar['value'] }}</span>
                    @endif
                    <div class="w-full rounded-t-md transition-all" style="height:{{ $h }}%; background:{{ $bar['value'] > 0 ? '#6366f1' : '#e5e7eb' }}"></div>
                    <span class="text-[9px] text-gray-400 dark:text-gray-500">{{ $bar['short'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif {{-- /sources --}}

    {{-- ════════ Detail drawer ════════ --}}
    @if($this->selected)
    @php $c = $this->selected; @endphp
    <div class="fixed inset-0 z-50 flex justify-end" wire:key="drawer-{{ $c->id }}">
        <div class="absolute inset-0 bg-black/40" wire:click="closeDetail"></div>

        <div class="relative w-full max-w-md h-full bg-white dark:bg-[#1d1e2a] border-l border-gray-100 dark:border-white/[0.05] shadow-2xl overflow-y-auto"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">

            {{-- Drawer header --}}
            <div class="sticky top-0 bg-white dark:bg-[#1d1e2a] border-b border-gray-100 dark:border-white/[0.05] px-6 py-4 flex items-center justify-between z-10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Contact details</h2>
                <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6">
                {{-- Identity --}}
                <div class="flex items-center gap-4 mb-4">
                    @include('partials.contact-avatar', ['contact' => $c, 'size' => 'w-14 h-14', 'text' => 'text-lg'])
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ $c->name }}</p>
                        <span class="inline-block mt-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full capitalize {{ $statusStyles[$c->status] ?? '' }}">{{ $c->status }}</span>
                    </div>
                </div>

                {{-- Avatar / logo URL (blank = Gravatar, else initials) --}}
                <div class="flex items-center gap-2 mb-6">
                    <input type="url" placeholder="Logo / photo URL (optional)" value="{{ $c->data['avatar'] ?? '' }}"
                           wire:change="setAvatar({{ $c->id }}, $event.target.value)"
                           class="flex-1 text-xs rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-100 dark:border-white/[0.06] px-3 py-2 text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    <a href="{{ url($site->name.'/media') }}" class="text-[11px] font-semibold text-indigo-500 hover:underline shrink-0">Media</a>
                </div>

                {{-- Lifecycle — the contact's journey from first contact to won / lost --}}
                @php
                    // First date each stage was reached: creation = "new"; later
                    // stages from the logged status_change activities (latest-first,
                    // so keep the last-seen = earliest occurrence).
                    $stageDates = [];
                    foreach ($c->activities->where('type', 'status_change') as $a) {
                        if ($to = $a->meta['to'] ?? null) $stageDates[$to] = $a->created_at;
                    }
                    $stageDates['new'] = $c->created_at;
                    $track   = ['new', 'contacted', 'qualified', 'won'];
                    $lost    = $c->status === 'lost';
                    $current = array_search($c->status, $track, true);
                    $reached = $lost ? -1 : ($current === false ? 0 : $current);
                @endphp
                <div class="mb-6 p-4 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.04]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3">Lifecycle</p>
                    <div class="flex items-start">
                        @foreach($track as $i => $stage)
                            @php $done = $lost ? isset($stageDates[$stage]) : $i <= $reached; @endphp
                            @if($i > 0)
                                <div class="flex-1 h-px mt-[13px] mx-1.5 {{ $done ? 'bg-indigo-400' : 'bg-gray-200 dark:bg-white/[0.08]' }}"></div>
                            @endif
                            <button type="button" wire:click="updateStatus({{ $c->id }}, '{{ $stage }}')"
                                    title="Move to {{ ucfirst($stage) }}" class="flex flex-col items-center shrink-0 group cursor-pointer">
                                <span class="w-[26px] h-[26px] rounded-full grid place-items-center text-[10px] font-bold transition-colors
                                    {{ $done ? 'bg-indigo-500 text-white shadow-sm' : 'bg-white dark:bg-white/[0.06] text-gray-400 ring-1 ring-gray-200 dark:ring-white/[0.1] group-hover:ring-indigo-400' }}">
                                    {{ $done ? '✓' : $i + 1 }}
                                </span>
                                <span class="mt-1 text-[9.5px] font-semibold uppercase tracking-wide {{ $done ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400' }}">{{ $stage }}</span>
                                <span class="text-[9px] text-gray-400 dark:text-gray-500 tabular-nums">{{ isset($stageDates[$stage]) ? $stageDates[$stage]->format('M j') : '—' }}</span>
                            </button>
                        @endforeach
                        {{-- Lost — terminal branch --}}
                        <div class="flex-1 h-px mt-[13px] mx-1.5 {{ $lost ? 'bg-rose-400' : 'bg-gray-200 dark:bg-white/[0.08]' }}"></div>
                        <button type="button" wire:click="updateStatus({{ $c->id }}, 'lost')"
                                title="Mark as lost" class="flex flex-col items-center shrink-0 group cursor-pointer">
                            <span class="w-[26px] h-[26px] rounded-full grid place-items-center text-[10px] font-bold transition-colors
                                {{ $lost ? 'bg-rose-500 text-white shadow-sm' : 'bg-white dark:bg-white/[0.06] text-gray-400 ring-1 ring-gray-200 dark:ring-white/[0.1] group-hover:ring-rose-400' }}">
                                {{ $lost ? '✕' : '·' }}
                            </span>
                            <span class="mt-1 text-[9.5px] font-semibold uppercase tracking-wide {{ $lost ? 'text-rose-500' : 'text-gray-400' }}">lost</span>
                            <span class="text-[9px] text-gray-400 dark:text-gray-500 tabular-nums">{{ $lost && isset($stageDates['lost']) ? $stageDates['lost']->format('M j') : '—' }}</span>
                        </button>
                    </div>
                </div>

                {{-- Assignment --}}
                <div class="flex items-center gap-3 mb-6 p-3 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.04]">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">Assigned to</span>
                    <select wire:change="assign({{ $c->id }}, $event.target.value)"
                            class="ml-auto text-xs font-medium pr-7 pl-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                        <option value="" @selected(! $c->assigned_user_id)>Unassigned</option>
                        @foreach($this->members as $m)
                        <option value="{{ $m->id }}" @selected($c->assigned_user_id === $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Fields --}}
                <div class="space-y-3 mb-6">
                    @foreach([
                        ['Email', $c->email, 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                        ['Phone', $c->phone, 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                        ['Source', $c->sourceForm ? ($c->sourceForm->title ?: $c->sourceForm->name) : null, 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ] as [$label, $value, $icon])
                    @if($value)
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        <span class="text-xs text-gray-400 dark:text-gray-500 w-14 shrink-0">{{ $label }}</span>
                        <span class="text-sm text-gray-700 dark:text-gray-200 truncate">{{ $value }}</span>
                    </div>
                    @endif
                    @endforeach
                </div>

                {{-- Extra data --}}
                @if(!empty($c->data))
                <div class="mb-6 p-4 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.04]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Additional fields</p>
                    <dl class="space-y-1.5">
                        @foreach($c->data as $k => $v)
                        <div class="flex justify-between gap-3 text-xs">
                            <dt class="text-gray-400 dark:text-gray-500 truncate">{{ $k }}</dt>
                            <dd class="text-gray-700 dark:text-gray-200 text-right truncate">{{ is_array($v) ? implode(', ', $v) : $v }}</dd>
                        </div>
                        @endforeach
                    </dl>
                </div>
                @endif

                {{-- Add note --}}
                <form wire:submit="addNote({{ $c->id }})" class="mb-6">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Add a note</p>
                    <textarea wire:model="note" rows="2" placeholder="Log a call, meeting, or any detail…"
                              class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-sm text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                    @error('note') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    <div class="flex justify-end mt-2">
                        <button type="submit"
                                class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition-colors">
                            <span wire:loading.remove wire:target="addNote({{ $c->id }})">Add note</span>
                            <span wire:loading wire:target="addNote({{ $c->id }})">Saving…</span>
                        </button>
                    </div>
                </form>

                {{-- Timeline --}}
                @php
                    $respById = $c->responses->keyBy('id');
                    $typeMeta = [
                        'note'           => ['dot' => 'bg-amber-400',   'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                        'status_change'  => ['dot' => 'bg-violet-500',  'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
                        'assigned'       => ['dot' => 'bg-blue-500',     'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                        'form_submitted' => ['dot' => 'bg-indigo-500',   'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        'created'        => ['dot' => 'bg-gray-300 dark:bg-gray-600', 'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6'],
                    ];
                @endphp
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3">Activity timeline</p>
                <div class="relative pl-5 space-y-5 border-l border-gray-200 dark:border-white/[0.08]">
                    @forelse($c->activities as $a)
                    @php $tm = $typeMeta[$a->type] ?? $typeMeta['created']; @endphp
                    <div class="relative">
                        <span class="absolute -left-[1.4rem] top-0.5 w-3 h-3 rounded-full {{ $tm['dot'] }} ring-4 ring-white dark:ring-[#1d1e2a]"></span>

                        @switch($a->type)
                            @case('note')
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">{{ $a->user?->name ?? 'Someone' }} added a note</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 whitespace-pre-line">{{ $a->body }}</p>
                                @break
                            @case('status_change')
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">
                                    {{ $a->user?->name ?? 'Someone' }} moved status
                                    <span class="capitalize">{{ data_get($a->meta, 'from') }}</span> →
                                    <span class="capitalize font-bold">{{ data_get($a->meta, 'to') }}</span>
                                </p>
                                @break
                            @case('assigned')
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">
                                    {{ $a->user?->name ?? 'Someone' }} assigned to
                                    <span class="font-bold">{{ data_get($a->meta, 'assigned_to_name', 'Unassigned') }}</span>
                                </p>
                                @break
                            @case('form_submitted')
                                @php $r = $respById->get(data_get($a->meta, 'response_id')); @endphp
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">
                                    Submitted {{ $r && $r->form ? ($r->form->title ?: $r->form->name) : 'a form' }}
                                </p>
                                @if($r && !empty($r->fields))
                                <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5 mt-1">
                                    @foreach(collect($r->fields)->take(3) as $fk => $fv)
                                    <p class="truncate"><span class="text-gray-400">{{ $fk }}:</span> {{ is_array($fv) ? implode(', ', $fv) : $fv }}</p>
                                    @endforeach
                                </div>
                                @endif
                                @break
                            @default
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">Contact created</p>
                        @endswitch

                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $a->created_at->format('M j, Y · g:i A') }}</p>
                    </div>
                    @empty
                    <div class="relative">
                        <span class="absolute -left-[1.4rem] top-0.5 w-3 h-3 rounded-full bg-gray-300 dark:bg-gray-600 ring-4 ring-white dark:ring-[#1d1e2a]"></span>
                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">Contact created</p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $c->created_at->format('M j, Y · g:i A') }}</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
