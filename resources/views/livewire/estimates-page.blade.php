@php
    use App\Support\Money;
    $statusStyles = [
        'new'       => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400',
        'contacted' => 'bg-blue-100 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400',
        'won'       => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        'lost'      => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
    ];
    $trades = config('estimator.trades', []);
    $counts = $this->statusCounts;
    $canManage = $this->canManage;
    $wonValue = \App\Models\Estimate::where('site_id', $site->id)->where('status', 'won')->sum('cost_high_cents');
    $currency = strtolower((string) (((array) $site->feature('estimator'))['currency'] ?? 'gbp'));
    $selected = $this->selected;
@endphp
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Estimates</h1>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">Create named estimators — each with its own fields, calculator-built formulas and customer email.</p>
        </div>
        <div class="relative w-full sm:w-auto">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search estimates…"
                   class="pl-9 pr-4 py-2 text-sm rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 w-full sm:w-64">
        </div>
    </div>

    @if ($errorMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
    @endif

    {{-- Stat tiles — app tile theme --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-tile accent="ink" :value="$counts['all'] ?? 0" label="estimate requests" sub="all time" />
        <x-tile accent="lime" :value="$counts['new'] ?? 0" label="new leads" sub="need a follow-up" />
        <x-tile accent="lavender" :value="$counts['won'] ?? 0" label="won" :sub="Money::format((int) $wonValue, $currency).' value'" />
        <x-tile accent="cocoa" :value="$this->estimators->count()" label="estimators"
                :sub="$this->estimators->sum('fields_count').' fields · '.$this->estimators->sum('calcs_count').' calcs'" />
    </div>

    @if ($canManage)
    {{-- ═══ ESTIMATORS ═══ --}}
    <div class="flex items-center gap-2 mb-3">
        <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">Estimators</p>
        <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ $this->estimators->count() }}</span>
        <div class="flex-1 border-t border-gray-100 dark:border-white/[0.06]"></div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-4">
        @foreach ($this->estimators as $est)
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border {{ $selectedId === $est->id ? 'border-indigo-400 dark:border-indigo-500/50 ring-2 ring-indigo-500/20' : 'border-gray-100 dark:border-white/[0.05]' }} shadow-sm p-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-white truncate">🧮 {{ $est->name }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $est->fields_count }} {{ Str::plural('field', $est->fields_count) }} · {{ $est->calcs_count }} {{ Str::plural('calc', $est->calcs_count) }} · {{ $est->estimates_count }} {{ Str::plural('lead', $est->estimates_count) }}</p>
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button wire:click="select('{{ $est->id }}')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold {{ $selectedId === $est->id ? 'bg-indigo-600 text-white' : 'border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600' }} transition-colors">
                    {{ $selectedId === $est->id ? 'Editing…' : 'Edit' }}
                </button>
                <button wire:click="deleteEstimator('{{ $est->id }}')" data-confirm="Delete the {{ $est->name }} estimator? Its fields and calculations go with it (captured leads stay)."
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold text-gray-400 hover:text-rose-500 transition-colors">Delete</button>
            </div>
        </div>
        @endforeach

        {{-- New estimator: name it first, build inside after --}}
        <form wire:submit="createEstimator"
              class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] p-4 flex flex-col justify-center gap-2 min-h-[104px]">
            <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400">New estimator — name it first</label>
            <div class="flex gap-2">
                <input wire:model="newEstimatorName" type="text" placeholder="e.g. Cleaner" required
                       class="flex-1 min-w-0 px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                <button type="submit" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shrink-0">Create</button>
            </div>
            @error('newEstimatorName')<p class="text-[11px] text-rose-500">{{ $message }}</p>@enderror
        </form>
    </div>

    {{-- ═══ EDITOR for the selected estimator ═══ --}}
    @if ($selected)
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-indigo-100 dark:border-indigo-500/20 shadow-sm mb-6 overflow-hidden" wire:key="editor-{{ $selected->id }}">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]" style="background:color-mix(in srgb, #d9f068 14%, transparent)">
            <span class="w-9 h-9 rounded-full flex items-center justify-center text-base" style="background:#d9f068">🧮</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $selected->name }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Build the fields, click them like a calculator to write formulas, and draft the email.</p>
            </div>
            <button wire:click="closeEditor" class="text-xs font-semibold text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">✕ Close</button>
        </div>

        <div class="p-5 grid grid-cols-1 xl:grid-cols-2 gap-8">

            {{-- ── 1 · FIELDS ── --}}
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2.5">1 · Fields</p>
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

            {{-- ── 2 · CALCULATIONS — click fields like a calculator ── --}}
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2.5">2 · Calculations</p>
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

            {{-- ── 3 · CUSTOMER EMAIL (per estimator) ── --}}
            <div class="xl:col-span-2 border-t border-gray-100 dark:border-white/[0.06] pt-5">
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
        </div>
    </div>
    @endif
    @endif

    {{-- ═══ LEADS ═══ --}}
    <div class="flex items-center gap-2 mb-3">
        <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">Requests</p>
        <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ $counts['all'] ?? 0 }}</span>
        <div class="flex-1 border-t border-gray-100 dark:border-white/[0.06]"></div>
        {{-- Status filter chips --}}
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach(array_merge(['all'], \App\Livewire\EstimatesPage::STATUSES) as $st)
                <button wire:click="setStatusFilter('{{ $st }}')"
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold capitalize border transition-colors
                            {{ $statusFilter === $st
                                ? 'border-transparent bg-indigo-600 text-white'
                                : 'border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400 hover:border-indigo-400' }}">
                    {{ $st }} <span class="opacity-60">{{ $counts[$st] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
        @forelse($this->estimates as $e)
        @php $trade = $trades[$e->trade] ?? null; @endphp
        <div class="flex flex-wrap items-center gap-4 px-5 py-4 border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
            <span class="w-10 h-10 rounded-xl grid place-items-center text-lg shrink-0 bg-gray-50 dark:bg-white/[0.05]">{{ $trade['icon'] ?? '🧮' }}</span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                    {{ $e->estimator?->name ?? ($trade['name'] ?? ucfirst($e->trade)) }}
                    <span class="ml-1.5 font-mono text-[10px] font-normal tracking-wide text-gray-400">{{ $e->reference }}</span>
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $e->customer_name }} · {{ $e->customer_email }}{{ $e->customer_phone ? ' · '.$e->customer_phone : '' }}</p>
            </div>

            <div class="hidden md:block text-right">
                <p class="text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ $e->cost_high_cents > 0 ? $e->costLabel() : (collect($e->results ?? [])->firstWhere('format', 'money')['formatted'] ?? '—') }}</p>
                @if($e->completion)<p class="text-[11px] text-gray-400 dark:text-gray-500">🕒 {{ $e->completion }}</p>@endif
            </div>

            <div class="hidden lg:block text-xs text-gray-400 dark:text-gray-500 w-24 text-right tabular-nums">
                {{ $e->created_at->format('M j, g:i A') }}
            </div>

            <select @change.stop="$wire.updateStatus('{{ $e->id }}', $event.target.value)"
                    class="text-xs font-semibold capitalize pr-7 pl-3 py-1.5 rounded-full border-0 cursor-pointer focus:ring-2 focus:ring-indigo-500 outline-none {{ $statusStyles[$e->status] ?? '' }}">
                @foreach(\App\Livewire\EstimatesPage::STATUSES as $st)
                    <option value="{{ $st }}" @selected($e->status === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>

            @if ($canManage)
            <button type="button" @click.stop wire:click="deleteEstimate('{{ $e->id }}')" data-confirm="Delete this estimate?"
                    class="p-1.5 rounded-lg text-gray-300 dark:text-gray-600 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
            @endif

            {{-- what they asked for + calculated results --}}
            <div class="basis-full flex flex-wrap gap-1.5 pl-14 -mt-1">
                @foreach((array) $e->inputs as $k => $v)
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.05] text-gray-500 dark:text-gray-400">
                        {{ \Illuminate\Support\Str::headline($k) }}: {{ $v === true ? 'yes' : ($v === false ? 'no' : $v) }}</span>
                @endforeach
                @foreach((array) ($e->results ?? []) as $r)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ $r['name'] }}: {{ $r['formatted'] }}</span>
                @endforeach
                @if($e->notes)
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 truncate max-w-xs">“{{ $e->notes }}”</span>
                @endif
            </div>
        </div>
        @empty
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <span class="text-3xl mb-3">🧮</span>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                {{ $search !== '' || $statusFilter !== 'all' ? 'No estimates match your filters.' : 'No estimates yet.' }}</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Visitors who use one of your estimators appear here — each request emails both of you and posts a dashboard notification.</p>
        </div>
        @endforelse
    </div>
</div>
