@php
    use App\Support\Money;
    $statusStyles = [
        'new'       => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400',
        'contacted' => 'bg-blue-100 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400',
        'won'       => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        'lost'      => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
    ];
    $statusColor = ['new' => '#6366f1', 'contacted' => '#3b82f6', 'won' => '#10b981', 'lost' => '#9ca3af'];
    $trades = config('estimator.trades', []);
    $counts = $this->statusCounts;
    $canManage = $this->canManage;
    $currency = strtolower((string) (((array) $site->feature('estimator'))['currency'] ?? 'gbp'));
    $selected = $this->selected;
    $estimators = $this->estimators;
    $estimates = $this->estimates;
    $st = $this->stats;
    $total = $counts['all'] ?? 0;
    $topService = $st['byService'][0] ?? null;
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $pill = fn ($on) => 'shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors '.($on
        ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
        : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]');
    $serviceName = fn ($e) => $e->estimator?->name ?? ($trades[$e->trade]['name'] ?? ucfirst((string) $e->trade));
    $valueLabel = fn ($e) => $e->cost_high_cents > 0 ? $e->costLabel() : (collect($e->results ?? [])->firstWhere('format', 'money')['formatted'] ?? '—');
    $isOverdue = fn ($e) => $e->status === 'new' && $e->created_at->lt(now()->subHours(\App\Livewire\EstimatesPage::FOLLOW_UP_HOURS));
@endphp
<x-tri-layout title="Estimates" subtitle="Quote requests from your estimators — and the fields, formulas and emails behind them." :site-name="$site->name"
    :labels="['📊 Overview', '🧮 Estimates', '⚡ Summary']">

    <x-slot:header>
        <button wire:click="openEstimatorPage" type="button" class="{{ $btnSolid }} text-sm px-4 py-2">Open estimator page ↗</button>
    </x-slot:header>

    {{-- ── LEFT rail: leads at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$st['month']" label="Estimates this month"
                :sub="$st['lastMonth'] ? $st['lastMonth'].' last month' : $total.' all time'"
                icon="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        <x-tile accent="lime" :value="$st['conversion'] === null ? '—' : $st['conversion'].'%'" label="Converted to won"
                :sub="($counts['won'] ?? 0).' won · '.Money::format($st['wonCents'], $currency)"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$st['avgCents'] ? Money::format($st['avgCents'], $currency) : '—'" label="Average estimate" sub="mid-point of the quoted range"
                icon="M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8c1.11 0 2.08.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile :accent="$st['overdue'] ? 'rose' : 'sky'" :value="$counts['new'] ?? 0" label="Pending follow-ups"
                :sub="$st['overdue'] ? $st['overdue'].' waiting over 2 days' : 'all new leads are fresh'"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="cocoa" :value="$topService['name'] ?? '—'" label="Top service"
                :sub="$topService ? $topService['count'].' '.Str::plural('request', $topService['count']) : $estimators->count().' '.Str::plural('estimator', $estimators->count())"
                icon="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11 5.52.44a.56.56 0 01.32.99l-4.2 3.6 1.28 5.38a.56.56 0 01-.84.61L12 16.77l-4.73 2.86a.56.56 0 01-.84-.61l1.28-5.38-4.2-3.6a.56.56 0 01.32-.99l5.52-.44 2.13-5.11z" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    @if ($errorMessage)
        <p class="px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
    @endif

    {{-- ── Tabs ── --}}
    <div class="flex gap-2 overflow-x-auto no-scrollbar">
        <button type="button" wire:click="setTab('estimates')" class="{{ $pill($tab === 'estimates') }}">Requests <span class="opacity-60">{{ $total }}</span></button>
        <button type="button" wire:click="setTab('estimators')" class="{{ $pill($tab === 'estimators') }}">Estimators <span class="opacity-60">{{ $estimators->count() }}</span></button>
    </div>

    @if ($tab === 'estimators')
        {{-- ═══ ESTIMATORS: create · list · editor (fields / calculations / email) ═══ --}}
        @if ($canManage)
            <form wire:submit="createEstimator" class="{{ $panel }} !rounded-2xl p-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[12rem]">
                    <label class="block text-[11px] font-bold text-gray-500 dark:text-gray-400 mb-1">New estimator — name it first</label>
                    <input wire:model="newEstimatorName" type="text" placeholder="e.g. Cleaner" required class="bkf-input w-full">
                    @error('newEstimatorName')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Create
                </button>
            </form>
        @endif

        @if ($estimators->isEmpty())
            <div class="{{ $panel }} px-6 py-14 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center text-2xl" style="background:#d9f068">🧮</span>
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No estimators yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Create one per service you quote — e.g. <i>Home cleaning</i> — then give it fields, formulas and a customer email.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($estimators as $est)
                    <div class="flex flex-col {{ $panel }} !rounded-2xl overflow-hidden {{ $selectedId === $est->id ? 'ring-2 ring-indigo-500/40' : '' }}" wire:key="est-{{ $est->id }}">
                        <div class="p-5 flex-1 flex items-start gap-3">
                            <span class="w-11 h-11 rounded-xl grid place-items-center text-lg shrink-0" style="background:#d9f068">🧮</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate">{{ $est->name }}</span>
                                <span class="block text-[12px] text-gray-400 mt-0.5">{{ $est->fields_count }} {{ Str::plural('field', $est->fields_count) }} · {{ $est->calcs_count }} {{ Str::plural('calc', $est->calcs_count) }}</span>
                            </span>
                            <span class="text-right shrink-0">
                                <span class="block text-2xl font-extrabold tabular-nums leading-none {{ $est->estimates_count ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ $est->estimates_count }}</span>
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 mt-1">{{ Str::plural('lead', $est->estimates_count) }}</span>
                            </span>
                        </div>
                        @if ($canManage)
                        <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                            <button wire:click="select('{{ $est->id }}')"
                                    class="{{ $selectedId === $est->id ? 'inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-bold bg-indigo-600 text-white' : $btnSolid.' text-[12px] px-3 py-1.5' }}">
                                {{ $selectedId === $est->id ? 'Editing…' : 'Edit' }}
                            </button>
                            @if (! $est->fields_count || ! $est->calcs_count)
                                <span class="text-[11px] font-semibold text-amber-600">{{ ! $est->fields_count ? 'needs fields' : 'needs a calculation' }}</span>
                            @endif
                            <button wire:click="deleteEstimator('{{ $est->id }}')" data-confirm="Delete the {{ $est->name }} estimator? Its fields and calculations go with it (captured leads stay)."
                                    class="ml-auto p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($canManage && $selected)
        {{-- ═══ EDITOR for the selected estimator ═══ --}}
        <div class="{{ $panel }} !rounded-2xl overflow-hidden" wire:key="editor-{{ $selected->id }}">
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]" style="background:color-mix(in srgb, #d9f068 14%, transparent)">
                <span class="w-9 h-9 rounded-full flex items-center justify-center text-base" style="background:#d9f068">🧮</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $selected->name }}</p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Build the fields, click them like a calculator to write formulas, and draft the email.</p>
                </div>
                <button wire:click="closeEditor" class="{{ $btnSolid }} text-xs px-3 py-1.5">✕ Close</button>
            </div>
            <div class="px-5 pt-4 flex gap-2 overflow-x-auto no-scrollbar">
                @foreach (['fields' => ['1 · Fields', $this->fields->count()], 'calcs' => ['2 · Calculations', $this->calcs->count()], 'email' => ['3 · Customer email', null]] as $key => [$label, $n])
                    <button type="button" wire:click="setEditTab('{{ $key }}')" class="{{ $pill($editTab === $key) }}">{{ $label }}@if ($n !== null) <span class="opacity-60">{{ $n }}</span>@endif</button>
                @endforeach
            </div>
            <div class="p-5">
                @if ($editTab === 'calcs')
            {{-- ── 2 · CALCULATIONS — click fields like a calculator ── --}}
            <div>
                <div class="space-y-2">
                    @php $preview = $this->calcPreview; @endphp
                    @forelse ($this->calcs as $i => $c)
                    <div class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04]">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $c->name }}
                                <span class="text-[10px] font-bold uppercase text-gray-400">{{ $c->format }}</span></p>
                            <p class="text-[11px] text-gray-400 font-mono truncate">{{ $c->formula }}</p>
                        </div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full shrink-0" style="background:#d9f068;color:#2b3110" title="Preview with example values">{{ $preview[$i]['formatted'] ?? '' }}</span>
                        <button wire:click="openCalc('{{ $c->id }}')" class="text-xs font-semibold text-indigo-500 hover:text-indigo-600">Edit</button>
                        <button wire:click="deleteCalc('{{ $c->id }}')" data-confirm="Delete this calculation?" class="text-xs font-semibold text-gray-400 hover:text-rose-500">✕</button>
                    </div>
                    @empty
                    <p class="text-xs text-gray-400 py-2">No calculations yet — the first <span class="font-semibold">money</span> one becomes the headline price.</p>
                    @endforelse
                    @if ($calcEditingId === null)
                        <button wire:click="openCalc(0)" class="w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-400 hover:text-indigo-500 hover:border-indigo-300 transition-colors">+ Add calculation</button>
                    @endif
                </div>

                @if ($calcEditingId !== null)
                <form wire:submit="saveCalc" class="space-y-3 rounded-xl border border-gray-100 dark:border-white/[0.06] p-4 mt-3">
                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $calcEditingId ? 'Edit calculation' : 'New calculation' }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1">Name</label>
                            <input wire:model="cName" type="text" required placeholder="e.g. Estimated cost"
                                   class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1">Show result as</label>
                            <select wire:model="cFormat" class="w-full pr-7 pl-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                <option value="money">Money ({{ strtoupper($currency) }})</option>
                                <option value="hours">Hours (completion time)</option>
                                <option value="number">Plain number</option>
                            </select>
                        </div>
                    </div>
                    @error('cName')<p class="text-[11px] text-rose-500">{{ $message }}</p>@enderror

                    {{-- Formula display --}}
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Formula — tap the buttons below like a calculator</label>
                        <input wire:model="cFormula" type="text" placeholder="tap fields + keys below…"
                               class="w-full px-3 py-2.5 text-sm font-mono rounded-xl bg-gray-900 text-lime-300 dark:bg-black/40 border border-gray-700 dark:border-white/[0.1] focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        @error('cFormula')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- ── The calculator ── --}}
                    <div class="rounded-xl bg-gray-50 dark:bg-white/[0.03] p-3 space-y-2">
                        {{-- Field buttons --}}
                        @if ($this->fields->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($this->fields as $f)
                            <button type="button"
                                    @click="$wire.cFormula = (($wire.cFormula || '').trimEnd() + ' {{ $f->key }} ').trimStart()"
                                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold transition-transform active:scale-95"
                                    style="background:{{ $f->type === 'fixed' ? '#d7c3f5' : '#d9f068' }};color:{{ $f->type === 'fixed' ? '#33245c' : '#2b3110' }}"
                                    title="{{ $f->type === 'fixed' ? 'Set data: '.$f->value : 'Visitor field' }}">
                                {{ $f->label }}
                            </button>
                            @endforeach
                        </div>
                        @else
                        <p class="text-[11px] text-gray-400">Add fields first — they appear here as buttons.</p>
                        @endif
                        {{-- Keypad --}}
                        <div class="grid grid-cols-4 gap-1.5 max-w-[260px]">
                            @foreach ([['7','7'],['8','8'],['9','9'],['÷',' / '],['4','4'],['5','5'],['6','6'],['×',' * '],['1','1'],['2','2'],['3','3'],['−',' - '],['0','0'],['.','.'],['(',' ( '],['+',' + ']] as [$label, $tok])
                            <button type="button" @click="$wire.cFormula = ($wire.cFormula || '') + '{{ $tok }}'"
                                    class="py-2 rounded-lg text-sm font-bold bg-white dark:bg-white/[0.06] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:border-indigo-400 transition-all active:scale-95">
                                {{ $label }}
                            </button>
                            @endforeach
                            <button type="button" @click="$wire.cFormula = ($wire.cFormula || '') + ' ) '"
                                    class="py-2 rounded-lg text-sm font-bold bg-white dark:bg-white/[0.06] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:border-indigo-400 transition-all active:scale-95">)</button>
                            <button type="button" @click="$wire.cFormula = ($wire.cFormula || '').trimEnd().split(/\s+/).slice(0, -1).join(' ')"
                                    class="py-2 rounded-lg text-sm font-bold bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 hover:bg-amber-200 transition-all active:scale-95" title="Remove last">⌫</button>
                            <button type="button" @click="$wire.cFormula = ''"
                                    class="py-2 rounded-lg text-sm font-bold bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 hover:bg-rose-200 transition-all active:scale-95 col-span-2" title="Clear">C</button>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Save calculation</button>
                        <button type="button" wire:click="closeCalc" class="px-4 py-2 rounded-xl text-xs font-medium text-gray-500 border border-gray-200 dark:border-white/[0.08]">Cancel</button>
                    </div>
                </form>
                @endif
            </div>
                @elseif ($editTab === 'email')
            {{-- ── 3 · CUSTOMER EMAIL (per estimator) ── --}}
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2.5">3 · Customer email — sent on every successful {{ $selected->name }} submission</p>
                <form wire:submit="saveEstimatorSettings" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1">Estimator name</label>
                            <input wire:model="eName" type="text" required
                                   class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                            @error('eName')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1">Email subject</label>
                            <input wire:model="eEmailSubject" type="text" required
                                   class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                            @error('eEmailSubject')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <p class="text-[10px] text-gray-400">Placeholders:
                            @foreach (['{name}', '{reference}', '{service}', '{cost}', '{completion}', '{site}'] as $ph)
                                <span class="font-mono px-1 py-0.5 rounded bg-gray-100 dark:bg-white/[0.06] mr-1">{{ $ph }}</span>
                            @endforeach
                            — the calculated results table is appended automatically.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Message</label>
                        <textarea wire:model="eEmailBody" rows="6" required
                                  class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100"></textarea>
                        @error('eEmailBody')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                        <button type="submit" class="mt-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Save name &amp; email</button>
                    </div>
                </form>
            </div>
                @else
            {{-- ── 1 · FIELDS ── --}}
            <div>
                <div class="space-y-2">
                    @forelse ($this->fields as $f)
                    <div class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04]">
                        <span class="text-base">{{ ['number' => '🔢', 'select' => '📋', 'toggle' => '✅', 'text' => '✏️', 'fixed' => '🔒'][$f->type] ?? '🔢' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $f->label }}
                                @if($f->required)<span class="text-rose-400">*</span>@endif
                                @if($f->unit)<span class="text-xs text-gray-400 font-normal">({{ $f->unit }})</span>@endif
                            </p>
                            <p class="text-[11px] text-gray-400 font-mono truncate">{{ $f->key }}
                                @if($f->type === 'fixed') = {{ $f->value }} <span class="font-sans">· set data (hidden from visitors)</span>
                                @elseif($f->type === 'select') · {{ count($f->options ?? []) }} options
                                @else · visitor {{ $f->type }} @endif
                            </p>
                        </div>
                        <button wire:click="openField('{{ $f->id }}')" class="text-xs font-semibold text-indigo-500 hover:text-indigo-600">Edit</button>
                        <button wire:click="deleteField('{{ $f->id }}')" data-confirm="Delete this field?" class="text-xs font-semibold text-gray-400 hover:text-rose-500">✕</button>
                    </div>
                    @empty
                    <p class="text-xs text-gray-400 py-2">No fields yet — add visitor inputs (number, choice, yes/no) or fixed set data like your hourly rate.</p>
                    @endforelse
                    @if ($fieldEditingId === null)
                        <button wire:click="openField(0)" class="w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-400 hover:text-indigo-500 hover:border-indigo-300 transition-colors">+ Add field</button>
                    @endif
                </div>

                @if ($fieldEditingId !== null)
                <form wire:submit="saveField" class="space-y-3 rounded-xl border border-gray-100 dark:border-white/[0.06] p-4 mt-3">
                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $fieldEditingId ? 'Edit field' : 'New field' }}</p>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Label</label>
                        <input wire:model="fLabel" type="text" required placeholder="e.g. Area to clean"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        @error('fLabel')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1">Type</label>
                            <select wire:model.live="fType" class="w-full pr-7 pl-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                <option value="number">Number (visitor enters)</option>
                                <option value="select">Choice (visitor picks)</option>
                                <option value="toggle">Yes / No (visitor toggles)</option>
                                <option value="text">Text (visitor writes)</option>
                                <option value="fixed">Set data (you define the value)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1">Unit <span class="font-normal text-gray-400">(optional)</span></label>
                            <input wire:model="fUnit" type="text" placeholder="m², rooms, hrs"
                                   class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        </div>
                    </div>
                    @if ($fType === 'select')
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Options — one per line, <span class="font-mono">Label = value</span></label>
                        <textarea wire:model="fOptions" rows="3" placeholder="Small = 50&#10;Medium = 100&#10;Large = 180"
                                  class="w-full px-3 py-2 text-sm font-mono rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100"></textarea>
                    </div>
                    @endif
                    @if ($fType === 'fixed')
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Value <span class="font-normal text-gray-400">— visitors never see this</span></label>
                        <input wire:model="fValue" type="text" inputmode="decimal" placeholder="e.g. 45 (your hourly rate)"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    </div>
                    @else
                    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300 cursor-pointer">
                        <input type="checkbox" wire:model="fRequired" class="w-4 h-4 rounded border-gray-300 text-indigo-600"> Required
                    </label>
                    @endif
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Save field</button>
                        <button type="button" wire:click="closeField" class="px-4 py-2 rounded-xl text-xs font-medium text-gray-500 border border-gray-200 dark:border-white/[0.08]">Cancel</button>
                    </div>
                </form>
                @endif
            </div>
                @endif
            </div>
        </div>
        @endif

        <div class="space-y-4">
            {{-- ── Step-by-step tutorial ── --}}
            <div class="rounded-2xl overflow-hidden text-white" style="background:linear-gradient(150deg,#1f2330,#11131c)" x-data="{ step: 0 }">
                <div class="p-4 pb-3">
                    <p class="text-sm font-bold">📖 Estimator tutorial</p>
                    <p class="text-[11px] text-white/60 mt-0.5">From blank to paying customers, in 6 steps. Tap a step to expand it.</p>
                </div>
                @foreach([
                    ['1', 'Create your estimator', 'Type a name in <b>“New estimator”</b> at the top of the <b>Estimators</b> tab — one per service you quote, e.g. <i>Home cleaning</i> or <i>End-of-tenancy</i> — and hit <b>Create</b>. Its editor opens below the estimator cards; reopen it any time with <b>Edit</b> on the card.'],
                    ['2', 'Ask the right questions', 'In the <b>1 · Fields</b> tab add what visitors must answer:<br>· <b>Number</b> — “How many bedrooms?”<br>· <b>Choice</b> — “How big are the rooms?” with one option per line, each worth a number: <span class="font-mono text-[10px]">Small = 0.8</span>, <span class="font-mono text-[10px]">Average = 1</span>, <span class="font-mono text-[10px]">Large = 1.3</span><br>· <b>Yes/no</b> — “Deep clean?” (yes = 1, no = 0)<br>Tick <b>required</b> on anything you can\'t quote without.'],
                    ['3', 'Set your own numbers', 'Add <b>Set data</b> (fixed) fields for what only you control — <i>Hourly rate = 18</i>, <i>Base hours = 1.5</i>, <i>Callout fee = 25</i>. Visitors never see them, but your formulas can use them. Raise a rate here once and every future quote follows.'],
                    ['4', 'Write the formula', 'In the <b>2 · Calculations</b> tab, name a result line and tap your fields like calculator keys — e.g.<br><span class="font-mono text-[10px] block bg-white/10 rounded-lg px-2 py-1.5 mt-1">(base_hours + bedrooms × 0.75 + bathrooms × 0.5) × room_size × hourly_rate</span>Pick a format — <b>money</b>, <b>hours</b> or plain number — and Save. Add one calc per line you want on the quote (a time line AND a cost line works nicely). Typos are caught before saving.'],
                    ['5', 'Personalise the email', 'In the <b>3 · Customer email</b> tab draft what the visitor receives, using placeholders like <span class="font-mono text-[10px]">{name}</span>, <span class="font-mono text-[10px]">{service}</span>, <span class="font-mono text-[10px]">{cost}</span> and <span class="font-mono text-[10px]">{reference}</span> — they fill in automatically for every quote.'],
                    ['6', 'Go live & handle leads', 'You\'re done — the quote form is already live on your website\'s <b>Contact page</b> and the standalone page (<b>“Open estimator page ↗”</b> at the top; open it to test as a visitor). When someone requests a quote you\'re emailed, and the lead lands in the <b>Requests</b> tab — work it New → Contacted → Won.'],
                ] as [$n, $title, $body])
                <button type="button" @click="step = step === {{ $n }} ? 0 : {{ $n }}"
                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-left border-t border-white/[0.07] hover:bg-white/[0.04] transition-colors">
                    <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-extrabold shrink-0"
                          :class="step === {{ $n }} ? '' : 'bg-white/10'" :style="step === {{ $n }} ? 'background:#d9f068;color:#2b3110' : ''">{{ $n }}</span>
                    <span class="text-xs font-bold flex-1">{{ $title }}</span>
                    <svg class="w-3 h-3 opacity-50 transition-transform" :class="step === {{ $n }} ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="step === {{ $n }}" x-collapse x-cloak>
                    <p class="px-4 pb-3 pl-11 text-[11px] leading-relaxed text-white/75">{!! $body !!}</p>
                </div>
                @endforeach
                <div class="px-4 py-3 border-t border-white/[0.07]">
                    <p class="text-[10px] text-white/50">Changes go live instantly — the website quote form runs this same engine.</p>
                </div>
            </div>
        </div>
    @else
        {{-- ═══ REQUESTS: toolbar · cards / list ═══ --}}
        <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[12rem]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <x-field.text wire:model.live.debounce.250ms="search" placeholder="Search name, email, reference or service…" class="w-full" style="padding-left:2.25rem" />
                </div>
                <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="value">Highest value</option>
                </select>
                <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            </div>
            <div class="flex gap-2 overflow-x-auto no-scrollbar">
                @foreach (array_merge(['all'], \App\Livewire\EstimatesPage::STATUSES) as $s)
                    <button type="button" wire:click="setStatusFilter('{{ $s }}')" class="{{ $pill($statusFilter === $s) }} capitalize">
                        {{ $s }} <span class="opacity-60">{{ $counts[$s] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($estimates->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center text-2xl bg-gray-100 dark:bg-white/[0.06]">🧮</span>
                @if ($total === 0)
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">No estimates yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Visitors who use one of your estimators appear here — each request emails both of you and posts a dashboard notification.</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        @if ($estimators->isEmpty() && $canManage)
                            <button type="button" wire:click="setTab('estimators')" class="inline-flex text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Create your first estimator</button>
                        @endif
                        <button type="button" wire:click="openEstimatorPage" class="{{ $btnSolid }} text-sm px-4 py-2.5">Try it as a visitor ↗</button>
                    </div>
                @else
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">No estimates match your filters</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search or status.</p>
                    <button type="button" x-on:click="$wire.set('search', ''); $wire.setStatusFilter('all')" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all estimates</button>
                @endif
            </div>
        @elseif ($viewMode === 'grid')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($estimates as $e)
                    @php $trade = $trades[$e->trade] ?? null; @endphp
                    <div class="flex flex-col {{ $panel }} !rounded-2xl overflow-hidden" wire:key="lead-{{ $e->id }}">
                        <div class="p-5 flex-1">
                            <div class="flex items-start gap-3">
                                <span class="w-11 h-11 rounded-xl grid place-items-center text-lg shrink-0 bg-gray-50 dark:bg-white/[0.05]">{{ $trade['icon'] ?? '🧮' }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate">{{ $e->customer_name }}</span>
                                    <span class="block text-[12px] text-gray-400 truncate">{{ $serviceName($e) }} · <span class="font-mono">{{ $e->reference }}</span></span>
                                </span>
                                <span class="text-right shrink-0">
                                    <span class="block text-[15px] font-extrabold tabular-nums leading-tight text-gray-900 dark:text-white">{{ $valueLabel($e) }}</span>
                                    @if ($e->completion)<span class="block text-[11px] text-gray-400 mt-0.5">🕒 {{ $e->completion }}</span>@endif
                                </span>
                            </div>
                            <p class="mt-2.5 text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $e->customer_email }}{{ $e->customer_phone ? ' · '.$e->customer_phone : '' }}</p>
                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                @foreach (collect((array) $e->inputs)->take(4) as $k => $v)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.05] text-gray-500 dark:text-gray-400">{{ Str::headline($k) }}: {{ $v === true ? 'yes' : ($v === false ? 'no' : $v) }}</span>
                                @endforeach
                                @foreach ((array) ($e->results ?? []) as $r)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ $r['name'] }}: {{ $r['formatted'] }}</span>
                                @endforeach
                                @if ($e->notes)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 truncate max-w-full">“{{ $e->notes }}”</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                            <select @change.stop="$wire.updateStatus('{{ $e->id }}', $event.target.value)"
                                    class="text-xs font-semibold capitalize pr-7 pl-3 py-1.5 rounded-full border-0 cursor-pointer focus:ring-2 focus:ring-indigo-500 outline-none {{ $statusStyles[$e->status] ?? '' }}">
                                @foreach (\App\Livewire\EstimatesPage::STATUSES as $s)
                                    <option value="{{ $s }}" @selected($e->status === $s)>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                            <span class="text-[11px] {{ $isOverdue($e) ? 'font-bold text-rose-500' : 'text-gray-400' }}" title="{{ $e->created_at->format('M j, g:i A') }}">{{ $e->created_at->diffForHumans() }}</span>
                            <span class="ml-auto flex items-center gap-0.5">
                                <a href="mailto:{{ $e->customer_email }}" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10" title="Email {{ $e->customer_name }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </a>
                                @if ($canManage)
                                <button type="button" @click.stop wire:click="deleteEstimate('{{ $e->id }}')" data-confirm="Delete this estimate?"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" title="Delete">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                @endif
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
            <div class="{{ $panel }} !rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Service</th>
                                <th class="px-4 py-3 text-right">Estimate</th>
                                @unless ($compact)<th class="px-4 py-3">Received</th>@endunless
                                <th class="px-4 py-3">Status</th>
                                <th class="w-12 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach ($estimates as $e)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]" wire:key="row-{{ $e->id }}">
                                    <td class="{{ $pad }}">
                                        <span class="block font-semibold text-gray-900 dark:text-white truncate max-w-[12rem]">{{ $e->customer_name }}</span>
                                        @unless ($compact)<span class="block text-[11px] text-gray-400 truncate max-w-[12rem]">{{ $e->customer_email }}</span>@endunless
                                    </td>
                                    <td class="{{ $pad }} text-[12px] text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $serviceName($e) }} @unless ($compact)<span class="font-mono text-[10px] text-gray-400">{{ $e->reference }}</span>@endunless</td>
                                    <td class="{{ $pad }} text-right font-bold tabular-nums text-gray-900 dark:text-white whitespace-nowrap">{{ $valueLabel($e) }}</td>
                                    @unless ($compact)
                                        <td class="{{ $pad }} text-[12px] whitespace-nowrap {{ $isOverdue($e) ? 'font-bold text-rose-500' : 'text-gray-400' }}">{{ $e->created_at->format('M j, g:i A') }}</td>
                                    @endunless
                                    <td class="{{ $pad }}">
                                        <select @change.stop="$wire.updateStatus('{{ $e->id }}', $event.target.value)"
                                                class="text-xs font-semibold capitalize pr-7 pl-3 py-1 rounded-full border-0 cursor-pointer focus:ring-2 focus:ring-indigo-500 outline-none {{ $statusStyles[$e->status] ?? '' }}">
                                            @foreach (\App\Livewire\EstimatesPage::STATUSES as $s)
                                                <option value="{{ $s }}" @selected($e->status === $s)>{{ ucfirst($s) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="{{ $pad }} text-right">
                                        @if ($canManage)
                                        <button type="button" wire:click="deleteEstimate('{{ $e->id }}')" data-confirm="Delete this estimate?"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" title="Delete">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
    </div>

    {{-- ══ RIGHT rail: summary · needs attention · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Pipeline</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $total }}</b> {{ Str::plural('request', $total) }}@if ($st['winRate'] !== null) · <b class="text-gray-900 dark:text-white">{{ $st['winRate'] }}%</b> win rate @endif
            </p>
            @if ($total)
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach (\App\Livewire\EstimatesPage::STATUSES as $s)
                        @if ($counts[$s] ?? 0)
                            <span style="width:{{ round($counts[$s] / $total * 100, 2) }}%;background:{{ $statusColor[$s] }}" title="{{ ucfirst($s) }} · {{ $counts[$s] }}"></span>
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="mt-3 space-y-1.5">
                @foreach (\App\Livewire\EstimatesPage::STATUSES as $s)
                    <button type="button" wire:click="setStatusFilter('{{ $s }}')" class="w-full flex items-center gap-2 text-[12.5px] hover:underline">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $statusColor[$s] }}"></span>
                        <span class="text-gray-600 dark:text-gray-300 capitalize">{{ $s }}</span>
                        <span class="ml-auto font-bold text-gray-900 dark:text-white">{{ $counts[$s] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if (count($st['byService']))
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">By service</p>
            @php $svcMax = max(1, $st['byService'][0]['count']); @endphp
            <div class="space-y-2.5">
                @foreach (array_slice($st['byService'], 0, 5) as $svc)
                    <div>
                        <span class="flex items-center justify-between gap-2 text-[12.5px]">
                            <span class="truncate text-gray-700 dark:text-gray-200">{{ $svc['name'] }}</span>
                            <span class="shrink-0 tabular-nums"><b class="text-gray-900 dark:text-white">{{ $svc['count'] }}</b> <span class="text-gray-400">· {{ $svc['won'] }} won</span></span>
                        </span>
                        <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                            <span class="block h-full rounded-full" style="width:{{ round($svc['count'] / $svcMax * 100) }}%;background:var(--primary)"></span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @php $noCalc = $estimators->filter(fn ($e) => ! $e->fields_count || ! $e->calcs_count); @endphp
        @if ($st['overdue'] || $noCalc->isNotEmpty() || ($estimators->isEmpty() && $canManage))
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if ($st['overdue'])
                    <button type="button" wire:click="setStatusFilter('new')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $st['overdue'] }} new {{ Str::plural('estimate', $st['overdue']) }} not followed up</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70">Waiting over 2 days{{ $st['overdueList']->isNotEmpty() ? ' — oldest: '.$st['overdueList']->pluck('customer_name')->implode(', ') : '' }} →</p>
                    </button>
                @endif
                @if ($estimators->isEmpty() && $canManage)
                    <button type="button" wire:click="setTab('estimators')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">No estimators yet</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">Create one so visitors can request a quote →</p>
                    </button>
                @endif
                @foreach ($noCalc->take(3) as $est)
                    <button type="button" @if ($canManage) wire:click="select('{{ $est->id }}')" @endif class="w-full text-left rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $est->name }} isn't finished</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">{{ ! $est->fields_count ? 'Add fields' : 'Add a calculation' }} so it can quote a price →</p>
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach (array_filter([
                    ['Contacts', 'Your leads & customers', url($site->name.'/contacts')],
                    $site->hasFeature('invoices') ? ['Invoices', 'Bill a won quote', url($site->name.'/invoices')] : null,
                    $site->hasFeature('bookings') ? ['Bookings', 'Schedule the job', url($site->name.'/bookings')] : null,
                    ['Edit site', 'Place the quote form', url($site->name.'/connect')],
                ]) as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
