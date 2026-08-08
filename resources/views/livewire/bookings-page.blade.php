<div class="h-full overflow-y-auto p-5 sm:p-6" wire:key="bookings-{{ $site->id }}">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-lg font-extrabold tracking-tight text-gray-900 dark:text-white">Bookings</h1>
            <p class="text-xs text-gray-400">One engine, three kinds — appointments (slot), stays (rooms/houses) and trips (transport).</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="startCreate"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white">
                ＋ Create
            </button>
            <a href="{{ route('public.book', ['siteName' => $site->name]) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-gray-900 text-white dark:bg-white dark:text-gray-900">
                Open booking page ↗
            </a>
        </div>
    </div>

    {{-- ════════ CREATION WIZARD: one guided flow for every booking type ════════ --}}
    @if($wizOpen)
    <div class="mb-6 bg-white dark:bg-white/[0.03] rounded-2xl border-2 border-indigo-200 dark:border-indigo-500/30 p-5" wire:key="wizard">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                @foreach(['Type', 'Basics', 'Availability', ['slot' => 'Staff', 'stay' => 'Rooms', 'trip' => 'Vehicles'][$kind] ?? 'Resources'] as $i => $stepLabel)
                    <div class="flex items-center gap-1.5">
                        <span class="w-6 h-6 rounded-full grid place-items-center text-[10px] font-bold
                            {{ $wizStep > $i + 1 ? 'bg-emerald-500 text-white' : ($wizStep === $i + 1 ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-400') }}">
                            {{ $wizStep > $i + 1 ? '✓' : $i + 1 }}</span>
                        <span class="text-[11px] font-semibold {{ $wizStep === $i + 1 ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">{{ $stepLabel }}</span>
                        @if($i < 3)<span class="w-5 h-px bg-gray-200 dark:bg-white/10"></span>@endif
                    </div>
                @endforeach
            </div>
            <button type="button" wire:click="closeWizard" class="text-gray-300 hover:text-gray-600 dark:hover:text-gray-200" title="Close">✕</button>
        </div>

        {{-- STEP 1 · Type --}}
        @if($wizStep === 1)
            <p class="text-sm font-bold mb-1">What are you offering?</p>
            <p class="text-[11px] text-gray-400 mb-3">Pick a built-in type, one of your own, or define a new reusable type.</p>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
                @foreach(\App\Models\BookingType::builtins() as $b)
                    <button type="button" wire:click="wizPickType('{{ $b['engine'] }}')"
                            class="text-left p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] hover:border-indigo-400 hover:-translate-y-px transition-all">
                        <span class="text-xl">{{ $b['icon'] }}</span>
                        <span class="block text-xs font-bold mt-1">{{ $b['name'] }}</span>
                        <span class="block text-[10px] text-gray-400 mt-0.5 leading-snug">{{ $b['hint'] }}</span>
                    </button>
                @endforeach
                @foreach($this->bookingTypes->where('is_active', true) as $type)
                    <button type="button" wire:click="wizPickType('{{ $type->engine }}', {{ $type->id }})"
                            class="text-left p-3.5 rounded-xl border border-indigo-200 dark:border-indigo-500/30 bg-indigo-50/50 dark:bg-indigo-500/[0.06] hover:border-indigo-400 hover:-translate-y-px transition-all">
                        <span class="text-xl">{{ $type->icon }}</span>
                        <span class="block text-xs font-bold mt-1">{{ $type->name }}</span>
                        <span class="block text-[10px] text-gray-400 mt-0.5">your type · {{ ['slot' => 'appointments', 'stay' => 'stays', 'trip' => 'trips'][$type->engine] }}</span>
                    </button>
                @endforeach
                <button type="button" wire:click="$set('ntOpen', {{ $ntOpen ? 'false' : 'true' }})"
                        class="text-left p-3.5 rounded-xl border-2 border-dashed border-gray-300 dark:border-white/[0.15] hover:border-indigo-400 transition-all">
                    <span class="text-xl">＋</span>
                    <span class="block text-xs font-bold mt-1">New type</span>
                    <span class="block text-[10px] text-gray-400 mt-0.5">e.g. Dentist visit, Boat rental — reusable with its own defaults.</span>
                </button>
            </div>

            @if($ntOpen)
                <div class="mt-3 p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-gray-50/60 dark:bg-white/[0.02]">
                    <p class="text-xs font-bold mb-2">Define a new type</p>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 mb-2.5">
                        <x-field.text label="Name" model="ntName" placeholder="Dentist visit" />
                        <x-field.text label="Icon (emoji)" model="ntIcon" placeholder="🦷" />
                        <div><x-field.radio label="Engine" model="ntEngine" :live="true" name="nt-engine"
                                        :options="['slot' => 'Slots', 'stay' => 'Nights', 'trip' => 'Seats']" /></div>
                        <x-field.text label="Resource name" model="ntNoun" :placeholder="['slot' => 'e.g. dentist', 'stay' => 'e.g. room', 'trip' => 'e.g. boat'][$ntEngine]" hint="What staff/units are called." />
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 mb-2.5">
                        @if($ntEngine === 'slot')<x-field.text label="Default duration (min)" model="duration" type="number" min="5" />@endif
                        <x-field.text label="Default price" model="price" type="number" step="0.01" min="0" :live="true" />
                        <div><x-field.radio label="Default deposit" model="depositMode" :live="true" name="nt-dep"
                                        :options="['none' => 'None', 'fixed' => 'Fixed', 'pct' => '%']" /></div>
                        @if($depositMode !== 'none')
                            <x-field.text :label="$depositMode === 'pct' ? 'Deposit %' : 'Deposit amount'" model="depositValue" type="number" step="0.01" min="0" />
                        @endif
                    </div>
                    {{-- Field composition: tick which parameters this type uses --}}
                    <p class="text-[11px] font-bold text-gray-500 mb-1.5">Fields this type uses <span class="font-semibold text-gray-400">(untick to hide from the wizard — none ticked = all)</span></p>
                    <div class="flex flex-wrap gap-1.5 mb-2.5">
                        @foreach(\App\Models\BookingType::fieldCatalog($ntEngine) as $fk => $fl)
                            <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[11px] font-semibold cursor-pointer transition-all
                                {{ in_array($fk, $ntFields) ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300' : 'border-gray-200 dark:border-white/[0.08] text-gray-500' }}">
                                <input type="checkbox" wire:model.live="ntFields" value="{{ $fk }}" class="sr-only">
                                <span>{{ in_array($fk, $ntFields) ? '✓' : '＋' }}</span>{{ $fl }}
                            </label>
                        @endforeach
                    </div>
                    <button type="button" wire:click="saveNewType" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Create type &amp; continue →</button>
                    @error('ntName')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
            @endif
        @endif

        {{-- STEP 2 · Basics --}}
        @if($wizStep === 2)
            <p class="text-sm font-bold mb-3">Basics
                <span class="text-[10px] font-semibold text-gray-400 ml-1">{{ $wizTypeId ? $this->bookingTypes->firstWhere('id', $wizTypeId)?->name : ['slot' => 'Appointment', 'stay' => 'Stay', 'trip' => 'Trip'][$kind] }}</span></p>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-3">
                <div>
                    <x-field.text label="Name" model="name" :placeholder="match($kind){'stay' => 'e.g. Deluxe Double Room', 'trip' => 'e.g. Accra Express', default => 'e.g. Haircut'}" />
                    @error('name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
                @if($this->wizFieldOn('price'))
                    <x-field.text :label="$kind === 'stay' ? 'Price per night (optional, 0 = free)' : ($kind === 'trip' ? 'Default seat price (optional)' : 'Price (optional, 0 = free)')"
                                  model="price" type="number" step="0.01" min="0" :live="true" />
                @endif
                @if($kind === 'slot' && $this->wizFieldOn('duration'))<x-field.text label="Duration (min)" model="duration" type="number" min="5" />@endif
            </div>
            @if($this->wizFieldOn('deposit'))
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-3 mb-3 items-end">
                <div><x-field.radio label="Deposit (optional)" model="depositMode" :live="true" name="wiz-dep"
                                :options="['none' => 'None', 'fixed' => 'Fixed amount', 'pct' => '% of total']" /></div>
                @if($depositMode !== 'none')
                    <div>
                        <x-field.text :label="$depositMode === 'pct' ? 'Deposit %' : 'Deposit amount'" model="depositValue" type="number" step="0.01" min="0"
                                      hint="Customer pays only this online; the balance is due at arrival." />
                        @error('depositValue')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <x-field.text label="Full payment under (hours)" model="depositLead" type="number" min="0"
                                  hint="Short-notice bookings inside this window pay the FULL amount. 0 = deposit always." />
                @endif
                <x-field.check model="requiresPayment" text="Require online payment to confirm"
                               :hint="$site->stripeReady() ? null : 'Connect Stripe in Marketplace to collect payments.'" />
            </div>
            @else
            <div class="mb-3"><x-field.check model="requiresPayment" text="Require online payment to confirm"
                               :hint="$site->stripeReady() ? null : 'Connect Stripe in Marketplace to collect payments.'" /></div>
            @endif
            <div class="mb-3"><x-field.check model="autoConfirm" text="Auto-confirm bookings"
                           hint="Successful bookings are confirmed instantly — no manual approval needed." /></div>
            <x-field.textarea model="description" rows="2" placeholder="Short description shown to customers (optional)" />
        @endif

        {{-- STEP 3 · Availability --}}
        @if($wizStep === 3)
            <p class="text-sm font-bold mb-3">Availability — when can customers book?</p>
            @if($kind === 'slot')
                @php $preview = app(\App\Services\BookingService::class)->settings($site); @endphp
                <p class="text-[11px] text-gray-400 mb-3">
                    Site schedule: <span class="font-semibold text-gray-600 dark:text-gray-300">{{ strtoupper(implode(' · ', $preview['days'])) }} — {{ $preview['open'] }}–{{ $preview['close'] }}, every {{ $preview['slot'] }} min</span>.
                    This service follows it unless you override below; block specific days/slots in the <button type="button" wire:click="closeWizard" x-on:click="setTimeout(() => { var t = document.getElementById('availability'); if (t) { t.querySelector('button')?.click(); t.scrollIntoView({ behavior: 'smooth' }); } }, 250)" class="text-indigo-500 font-semibold hover:underline">Availability section</button>.
                </p>
                @if($this->wizFieldOn('schedule'))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <x-field.days label="Days override" model="slotDays" hint="None selected = site schedule." />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field.text label="Opens (override)" model="slotOpen" type="time" hint="Blank = site opening time." />
                        <x-field.text label="Closes (override)" model="slotClose" type="time" hint="Blank = site closing time." />
                    </div>
                </div>
                @endif
                @if($this->wizFieldOn('buffers'))
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                    <x-field.text label="Buffer before (min)" model="bufferBefore" type="number" min="0" hint="Gap kept free BEFORE each booking (setup/travel time) — e.g. 10 means a 10:00 booking also blocks 09:50–10:00." />
                    <x-field.text label="Buffer after (min)" model="bufferAfter" type="number" min="0" hint="Gap kept free AFTER each booking (cleanup) before the next one can start." />
                </div>
                @endif
            @elseif($kind === 'stay')
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <x-field.text label="Identical units" model="capacity" type="number" min="1" hint="Used unless you name rooms in the next step." />
                    @if($this->wizFieldOn('nights'))
                    <x-field.text label="Min nights" model="minNights" type="number" min="1" />
                    <x-field.text label="Max nights" model="maxNights" type="number" min="1" />
                    @endif
                    @if($this->wizFieldOn('guests'))<x-field.text label="Max guests" model="maxGuests" type="number" min="1" />@endif
                </div>
            @else
                <p class="text-[11px] text-gray-400 mb-3">Add the first departure now (optional) — more can be added anytime in the Services section.</p>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    <x-field.text label="Origin" model="depOrigin" placeholder="Accra" />
                    <x-field.text label="Destination" model="depDestination" placeholder="Kumasi" />
                    <x-field.text label="Date" model="depDate" type="date" />
                    <x-field.text label="Time" model="depTime" type="time" />
                    <x-field.text label="Seats" model="depSeats" type="number" min="1" />
                </div>
            @endif
            @if($kind === 'slot' && $this->wizFieldOn('capacity'))
                <div class="mt-3 w-56"><x-field.text label="Parallel bookings" model="capacity" type="number" min="1" hint="How many customers can book the SAME time at once (e.g. 3 barber chairs = 3). Ignored if you name staff in the next step." /></div>
            @endif
        @endif

        {{-- STEP 4 · Parallel availability (staff / rooms / vehicles) --}}
        @if($wizStep === 4)
            @php $noun = ['slot' => 'staff member', 'stay' => 'room / house', 'trip' => 'vehicle'][$kind]; @endphp
            @if(! $this->wizFieldOn('resources'))
                <p class="text-sm font-bold mb-1">All set</p>
                <p class="text-[11px] text-gray-400 mb-3">This type doesn't use named {{ $noun }}s — hit create to go live.</p>
            @else
            <p class="text-sm font-bold mb-1">{{ ['slot' => 'Staff', 'stay' => 'Rooms & houses', 'trip' => 'Vehicles'][$kind] }} <span class="text-[10px] font-semibold text-gray-400">optional</span></p>
            <p class="text-[11px] text-gray-400 mb-3">
                @if($kind === 'slot') Name each {{ $noun }} for parallel bookings — each can have their own days/hours; customers pick one or “Any”. Skip to use the plain parallel-bookings number.
                @elseif($kind === 'stay') Name each unit so customers can pick a specific one; skip to use the identical-units count.
                @else Optionally name vehicles to assign to departures later.
                @endif
            </p>
            <div class="space-y-1.5 mb-3">
                @forelse($wizResources as $i => $res)
                    <div class="flex items-center gap-2 text-xs bg-gray-50 dark:bg-white/[0.04] rounded-lg px-2.5 py-2">
                        <span class="font-semibold">{{ $res['name'] }}</span>
                        @if($kind === 'slot' && $res['days'])<span class="text-gray-400">{{ $res['days'] }} {{ $res['open'] ? '· '.$res['open'].'–'.$res['close'] : '' }}</span>@endif
                        <button type="button" wire:click="wizRemoveResource({{ $i }})" class="ml-auto text-gray-300 hover:text-rose-500">✕</button>
                    </div>
                @empty
                    <p class="text-xs text-gray-400">None added yet.</p>
                @endforelse
            </div>
            <div class="grid {{ $kind === 'slot' ? 'grid-cols-1 lg:grid-cols-2' : 'grid-cols-1 lg:grid-cols-2' }} gap-2.5">
                <x-field.text label="Name" model="wizResName" :placeholder="'e.g. '.['slot' => 'Bella', 'stay' => 'Villa Rosa', 'trip' => 'Bus 12'][$kind]" />
                @if($kind === 'slot')
                    <x-field.days label="Works on (override)" model="wizResDays" hint="None = service days." />
                    <x-field.text label="Starts (override)" model="wizResOpen" type="time" />
                    <x-field.text label="Ends (override)" model="wizResClose" type="time" />
                @endif
            </div>
            <button type="button" wire:click="wizAddResource" class="mt-2 px-3 py-1.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold">＋ Add {{ $noun }}</button>
            {{-- Attach an existing shared site resource with one click --}}
            @php $wizNames = collect($wizResources)->pluck('name')->all(); @endphp
            @if($this->siteResources->whereNotIn('name', $wizNames)->isNotEmpty())
                <p class="text-[11px] font-bold text-gray-500 mt-3 mb-1.5">Or attach an existing resource <span class="font-semibold text-gray-400">(shared — conflicts are checked across all its services)</span></p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($this->siteResources->whereNotIn('name', $wizNames) as $sr)
                        <button type="button" wire:click="$set('wizResName', '{{ addslashes($sr->name) }}')" x-on:click="$nextTick(() => $wire.wizAddResource())"
                                class="px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.08] text-[11px] font-semibold text-gray-600 dark:text-gray-300 hover:border-indigo-400 transition-all">
                            ＋ {{ $sr->name }}@if($sr->capacity > 1) <span class="text-gray-400">×{{ $sr->capacity }}</span>@endif
                        </button>
                    @endforeach
                </div>
            @endif
            @endif
        @endif

        {{-- Wizard footer --}}
        @if($wizStep > 1)
            <div class="flex items-center justify-between mt-5 pt-4 border-t border-gray-100 dark:border-white/[0.06]">
                <button type="button" wire:click="wizBack" class="px-4 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">← Back</button>
                @if($wizStep < 4)
                    <button type="button" wire:click="wizNext" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Next →</button>
                @else
                    <button type="button" wire:click="finishWizard" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">✓ Create &amp; go live</button>
                @endif
            </div>
        @endif
    </div>
    @endif

    {{-- Stat tiles — app-wide tile format (click opens the matching list) --}}
    @php $t = $this->tiles; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <x-tile label="new bookings received today" :value="$t['today']" sub="Today" accent="ink"
                wire:click="openTile('today')" class="cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all" />
        <x-tile label="upcoming bookings" :value="$t['upcoming']" sub="Next 7 days" accent="lime"
                wire:click="openTile('upcoming')" class="cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all" />
        <x-tile label="awaiting confirmation" :value="$t['pending']" sub="Pending" accent="cocoa"
                wire:click="openTile('pending')" class="cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all" />
        <x-tile label="upcoming confirmed bookings" :value="$t['month']" sub="Confirmed" accent="lavender"
                wire:click="openTile('month')" class="cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all" />
    </div>

    {{-- Main (stacked sections, left) · Calendar rail (right) --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">

        {{-- ══════════ LEFT: every section gets its own space ══════════ --}}
        <div class="xl:col-span-2 min-w-0">

            {{-- ── ALL BOOKINGS ── --}}
            <div class="flex items-center gap-2 mb-3">
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">All bookings</p>
                <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ $this->bookings->total() }}</span>
                <div class="flex-1 border-t border-gray-100 dark:border-white/[0.06]"></div>
            </div>
            <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/[0.06]">
                    <h2 class="text-sm font-bold">All bookings</h2>
                </div>
                {{-- Minimal rows: service · date · slot. Everything else
                     (customer, price, status, actions) lives in the detail
                     lightbox — click a row to review it. --}}
                @forelse($this->bookings as $b)
                @php $p = (array) ($b->params ?? []); $bkind = $b->service?->kind ?? 'slot'; @endphp
                <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 dark:border-white/[0.04] last:border-0 cursor-pointer hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
                     wire:click="viewBooking('{{ $b->id }}')" title="View details">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white flex-1 min-w-0 truncate">
                        {{ $b->service?->typeIcon() }} {{ $b->service?->name ?? 'Service' }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 shrink-0 tabular-nums">
                        @if($bkind === 'stay')
                            {{ $p['check_in'] ?? '?' }} <span class="text-gray-300 dark:text-gray-600">→</span> {{ $p['check_out'] ?? '?' }}
                        @else
                            {{ $b->starts_at?->format('D, M j, Y') }} <span class="text-gray-300 dark:text-gray-600">·</span> {{ $b->starts_at?->format('g:i A') }}
                        @endif
                    </p>
                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
                @empty
                <div class="px-4 py-12 text-center text-sm text-gray-400">No bookings yet.</div>
                @endforelse
                @if($this->bookings->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.06]">{{ $this->bookings->links() }}</div>
                @endif
            </div>
            {{-- ── SERVICES — click-to-review tile ── --}}
            @php $activeServices = $this->services->where('is_active', true)->count(); @endphp
            <div class="mt-6 bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden"
                 x-data="{ open: false }">
                <button type="button" @click="open = ! open" class="w-full flex items-center gap-3.5 px-5 py-4 text-left group">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-base shrink-0" style="background:#d7c3f5">⚙</span>
                    <span class="flex-1 min-w-0">
                        <span class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">Services</span>
                            <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d7c3f5;color:#33245c">{{ $this->services->count() }}</span>
                        </span>
                        <span class="block text-xs text-gray-400 truncate mt-0.5">
                            @if($this->services->isEmpty())
                                No services yet — click to add your first one.
                            @else
                                {{ $activeServices }} active · {{ $this->services->take(3)->map(fn ($s) => $s->typeIcon().' '.$s->name)->implode(' · ') }}@if($this->services->count() > 3) · +{{ $this->services->count() - 3 }} more @endif
                            @endif
                        </span>
                    </span>
                    <span class="text-[11px] font-semibold text-indigo-500 shrink-0" x-text="open ? 'Close' : 'Review'"></span>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-500 transition-transform shrink-0" :class="open ? 'rotate-180' : ''"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="px-5 pb-5 border-t border-gray-100 dark:border-white/[0.06] pt-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4">
                    <h2 class="text-sm font-bold mb-3">{{ $editingId ? 'Edit service' : 'Add a service' }}</h2>
                    <form wire:submit="saveService" class="space-y-3">
                        {{-- Type picker: built-in engines + every custom type --}}
                        <div>
                            <label class="bkf-label">Type</label>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach(\App\Models\BookingType::builtins() as $b)
                                    <button type="button" wire:click="pickSvcType('{{ $b['engine'] }}')"
                                            class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold transition-colors
                                                {{ $svcType === $b['engine'] ? 'bg-indigo-600 text-white' : 'bg-gray-50 dark:bg-white/[0.04] text-gray-500 border border-gray-200 dark:border-white/[0.08]' }}">
                                        {{ $b['icon'] }} {{ $b['name'] }}
                                    </button>
                                @endforeach
                                @foreach($this->bookingTypes->where('is_active', true) as $t)
                                    <button type="button" wire:click="pickSvcType('type:{{ $t->id }}')"
                                            class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold transition-colors
                                                {{ $svcType === 'type:'.$t->id ? 'bg-indigo-600 text-white' : 'bg-gray-50 dark:bg-white/[0.04] text-gray-500 border border-gray-200 dark:border-white/[0.08]' }}">
                                        {{ $t->icon }} {{ $t->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <x-field.text model="name" :placeholder="match($kind){'stay' => 'e.g. Deluxe Double Room', 'trip' => 'e.g. Accra Express', default => 'e.g. Haircut'}" />
                            @error('name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        @if($kind === 'slot')
                            <div class="grid grid-cols-2 gap-3">
                                @if($this->svcFieldOn('duration'))<x-field.text label="Duration (min)" model="duration" type="number" min="5" />@endif
                                @if($this->svcFieldOn('price'))<x-field.text label="Price (0 = free)" model="price" type="number" step="0.01" min="0" :live="true" />@endif
                            </div>
                            @if($this->svcFieldOn('capacity'))
                            <x-field.text label="Parallel bookings" model="capacity" type="number" min="1" hint="How many customers can book the SAME time at once (e.g. 3 chairs = 3). Ignored when staff are named below." />
                            @endif
                            @if($this->svcFieldOn('schedule'))
                            <x-field.days label="Days override" model="slotDays" hint="None selected = site availability." />
                            <div class="grid grid-cols-2 gap-3">
                                <x-field.text label="Opens (override)" model="slotOpen" type="time" hint="Blank = site opening time." />
                                <x-field.text label="Closes (override)" model="slotClose" type="time" hint="Blank = site closing time." />
                            </div>
                            @endif
                            @if($this->svcFieldOn('buffers'))
                            <div class="grid grid-cols-2 gap-3">
                                <x-field.text label="Buffer before (min)" model="bufferBefore" type="number" min="0" hint="Gap kept free BEFORE each booking (setup/travel time)." />
                                <x-field.text label="Buffer after (min)" model="bufferAfter" type="number" min="0" hint="Gap kept free AFTER each booking (cleanup) before the next can start." />
                            </div>
                            @endif
                        @endif

                        @if($kind === 'stay')
                            <div class="grid grid-cols-2 gap-3">
                                @if($this->svcFieldOn('price'))<x-field.text label="Price per night" model="price" type="number" step="0.01" min="0" :live="true" />@endif
                                <x-field.text label="Units available" model="capacity" type="number" min="1" hint="Identical rooms/houses of this type." />
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                @if($this->svcFieldOn('nights'))
                                <x-field.text label="Min nights" model="minNights" type="number" min="1" />
                                <x-field.text label="Max nights" model="maxNights" type="number" min="1" />
                                @endif
                                @if($this->svcFieldOn('guests'))<x-field.text label="Max guests" model="maxGuests" type="number" min="1" />@endif
                            </div>
                        @endif

                        @if($kind === 'trip')
                            @if($this->svcFieldOn('price'))
                            <x-field.text label="Default seat price" model="price" type="number" step="0.01" min="0"
                                          hint="Departures can override this per departure." />
                            @endif
                        @endif

                        <x-field.textarea model="description" rows="2" placeholder="Short description (optional)" />

                        {{-- Booking form: basic 4 + owner-defined custom fields --}}
                        <div class="pt-1">
                            <label class="bkf-label">Booking form</label>
                            <div class="flex flex-wrap gap-1.5 mb-1.5">
                                @foreach(['Full name', 'Email', 'Phone', 'Message'] as $base)
                                    <span class="px-2 py-1 rounded-md bg-gray-100 dark:bg-white/[0.06] text-[10px] font-semibold text-gray-400">{{ $base }}</span>
                                @endforeach
                                @foreach($formFields as $i => $ff)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-indigo-50 dark:bg-indigo-500/10 text-[10px] font-semibold text-indigo-600 dark:text-indigo-300">
                                        {{ $ff['label'] }}{{ $ff['required'] ? ' *' : '' }}
                                        <button type="button" wire:click="removeFormField({{ $i }})" class="text-indigo-300 hover:text-rose-500">✕</button>
                                    </span>
                                @endforeach
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_auto_auto] gap-2 items-center">
                                <x-field.text model="ffLabel" placeholder="Custom field label (e.g. Case type)" />
                                <x-field.select model="ffType" :live="true" :empty="null" :options="\App\Livewire\BookingsPage::FORM_FIELD_TYPES" />
                                <x-field.check model="ffRequired" text="Required" />
                                <button type="button" wire:click="addFormField" class="px-3 py-2 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold">＋</button>
                            </div>
                            @if($ffType === 'select')
                                <x-field.text model="ffOptions" placeholder="Dropdown options, comma-separated (e.g. Divorce, Custody, Adoption)" />
                                @error('ffOptions')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                            @endif
                            <p class="bkf-hint">Basic fields are always shown; custom fields are added to the customer booking form.</p>
                        </div>

                        @if($this->svcFieldOn('deposit'))
                        <div class="grid grid-cols-2 gap-3 items-end">
                            <div><x-field.radio label="Deposit (optional)" model="depositMode" :live="true" name="svc-dep"
                                            :options="['none' => 'None', 'fixed' => 'Fixed', 'pct' => '%']" /></div>
                            @if($depositMode !== 'none')
                                <x-field.text :label="$depositMode === 'pct' ? 'Deposit %' : 'Deposit amount'" model="depositValue" type="number" step="0.01" min="0" />
                            @endif
                        </div>
                        @if($depositMode !== 'none')
                            <x-field.text label="Full payment under (hours)" model="depositLead" type="number" min="0"
                                          hint="Short-notice bookings inside this window pay the FULL amount online. 0 = deposit always." />
                        @endif
                        @endif
                        <x-field.check model="requiresPayment" text="Require payment (Stripe) to confirm" />
                        <x-field.check model="autoConfirm" text="Auto-confirm bookings"
                                       hint="Successful bookings are confirmed instantly (no manual approval). Paid bookings always confirm once payment completes." />

                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">{{ $editingId ? 'Update' : 'Add service' }}</button>
                            @if($editingId)
                            <button type="button" wire:click="resetForm" class="px-4 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Cancel</button>
                            @endif
                        </div>
                    </form>

                    {{-- Resources: staff (slot) / rooms (stay) / vehicles (trip),
                         each with its OWN availability. --}}
                    @if($editingId)
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/[0.06]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                                {{ ['slot' => 'Staff', 'stay' => 'Rooms / houses', 'trip' => 'Vehicles'][$kind] }}
                            </h3>
                            <p class="text-[10px] text-gray-400 mb-2">
                                @if($kind === 'slot') Each staff member has their own schedule — customers pick one or “Any”. With staff listed, capacity comes from the roster.
                                @elseif($kind === 'stay') Name each unit — customers pick a specific one or “Any”. With rooms listed, they replace the unit count.
                                @else Assign a vehicle to each departure below (optional).
                                @endif
                            </p>
                            <div class="space-y-1.5 mb-3">
                                @forelse($this->serviceResources as $res)
                                    <div class="flex items-center gap-2 text-xs bg-gray-50 dark:bg-white/[0.04] rounded-lg px-2.5 py-2">
                                        <span class="font-semibold {{ $res->is_active ? '' : 'text-gray-400 line-through' }}">{{ $res->name }}</span>
                                        @if($kind === 'slot' && $res->configValue('days'))
                                            <span class="text-gray-400">{{ $res->configValue('days') }}
                                                {{ $res->configValue('open_time') ? '· '.$res->configValue('open_time').'–'.$res->configValue('close_time', '?') : '' }}</span>
                                        @endif
                                        <span class="ml-auto text-gray-400">{{ $res->active_bookings_count }} upcoming</span>
                                        <button wire:click="toggleResource('{{ $res->id }}')"
                                                class="text-[10px] px-1.5 py-0.5 rounded {{ $res->is_active ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10' : 'text-gray-400 bg-gray-100 dark:bg-white/5' }}">{{ $res->is_active ? 'On' : 'Off' }}</button>
                                        <button wire:click="deleteResource('{{ $res->id }}')"
                                                data-confirm="Remove “{{ $res->name }}”? Its bookings are kept (unassigned)."
                                                class="text-gray-300 hover:text-rose-500" title="Remove">✕</button>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400">None yet — the service uses its plain capacity number.</p>
                                @endforelse
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <x-field.text label="Name" model="resName" :placeholder="['slot' => 'e.g. Bella', 'stay' => 'e.g. Villa Rosa', 'trip' => 'e.g. Bus 12'][$kind]" />
                                @if($kind === 'slot')
                                    <x-field.days label="Works on (override)" model="resDays" hint="None = service days." />
                                    <div class="grid grid-cols-2 gap-2">
                                        <x-field.text label="Starts (override)" model="resOpen" type="time" />
                                        <x-field.text label="Ends (override)" model="resClose" type="time" />
                                    </div>
                                @endif
                            </div>
                            <button type="button" wire:click="saveResource"
                                    class="mt-2 px-3 py-1.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold">＋ Add</button>
                            @error('resName')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    @if($editingId && $kind === 'trip')
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/[0.06]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Departures</h3>
                            <div class="space-y-1.5 mb-3">
                                @forelse($this->departures as $dep)
                                    <div class="flex items-center gap-2 text-xs bg-gray-50 dark:bg-white/[0.04] rounded-lg px-2.5 py-2">
                                        <span class="font-semibold">{{ $dep->routeLabel() }}</span>
                                        <span class="text-gray-400">{{ $dep->departs_at->format('D, M j · g:i A') }}</span>
                                        <span class="ml-auto text-gray-400">{{ $dep->seatsLeft() }}/{{ $dep->seats }} seats</span>
                                        <span class="font-semibold">{{ number_format($dep->effectivePriceCents() / 100, 2) }}</span>
                                        <button wire:click="deleteDeparture('{{ $dep->id }}')" data-confirm="Delete this departure? Its bookings are kept."
                                                class="text-gray-300 hover:text-rose-500" title="Delete departure">✕</button>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400">No departures yet — add the first one below.</p>
                                @endforelse
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <x-field.text model="depOrigin" placeholder="Origin" />
                                <x-field.text model="depDestination" placeholder="Destination" />
                                <x-field.text model="depDate" type="date" />
                                <x-field.text model="depTime" type="time" />
                                <x-field.text model="depSeats" type="number" min="1" placeholder="Seats" />
                                <x-field.text model="depPrice" type="number" step="0.01" min="0" placeholder="Price (blank = default)" />
                            </div>
                            <button type="button" wire:click="saveDeparture"
                                    class="mt-2 px-3 py-1.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold">＋ Add departure</button>
                            @error('depOrigin')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                            @error('depDate')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    {{-- Seasonal / date-range pricing rules --}}
                    @if($editingId)
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/[0.06]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Seasonal pricing</h3>
                            <p class="text-[10px] text-gray-400 mb-2">Date-range price overrides — a rule on a specific {{ strtolower($this->serviceResources->isNotEmpty() ? 'resource' : 'resource') }} beats a service-wide rule; stays price each night by its own rule.</p>
                            <div class="space-y-1.5 mb-3">
                                @forelse($this->priceRules as $rule)
                                    <div class="flex items-center gap-2 text-xs bg-gray-50 dark:bg-white/[0.04] rounded-lg px-2.5 py-2">
                                        <span class="font-semibold">{{ \App\Support\Money::format((int) $rule->price_cents, $site->currency) }}</span>
                                        <span class="text-gray-400">{{ $rule->starts_on->format('M j') }} – {{ $rule->ends_on->format('M j, Y') }}</span>
                                        @if($rule->resource)<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ $rule->resource->name }}</span>@endif
                                        @if($rule->label)<span class="text-gray-400 italic">{{ $rule->label }}</span>@endif
                                        <button wire:click="deletePriceRule('{{ $rule->id }}')" data-confirm="Delete this price rule?"
                                                class="ml-auto text-gray-300 hover:text-rose-500" title="Delete rule">✕</button>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400">No rules — the base price applies year-round.</p>
                                @endforelse
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <x-field.text model="prStart" type="date" label="From" />
                                <x-field.text model="prEnd" type="date" label="To" />
                                <x-field.text model="prPrice" type="number" step="0.01" min="0" :label="$kind === 'stay' ? 'Price per night' : 'Price'" />
                                <x-field.text model="prLabel" label="Label" placeholder="High season" />
                                @if($this->serviceResources->isNotEmpty())
                                    <div class="col-span-2">
                                        <x-field.select label="Applies to" model="prResourceId"
                                            :options="['' => 'Whole service'] + $this->serviceResources->pluck('name', 'id')->all()" />
                                    </div>
                                @endif
                            </div>
                            <button type="button" wire:click="addPriceRule"
                                    class="mt-2 px-3 py-1.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold">＋ Add rule</button>
                            @error('prStart')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                            @error('prEnd')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                            @error('prPrice')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </div>

                <div class="space-y-2">
                    @forelse($this->services as $svc)
                    <div class="flex items-center gap-3 bg-white dark:bg-white/[0.03] rounded-xl border border-gray-100 dark:border-white/[0.06] px-3 py-2.5">
                        <span class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded
                            {{ ['slot' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
                                'stay' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                'trip' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'][$svc->kind] ?? '' }}">{{ $svc->typeIcon() }} {{ $svc->typeLabel() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold truncate {{ $svc->is_active ? '' : 'text-gray-400 line-through' }}">{{ $svc->name }}</p>
                            <p class="text-[11px] text-gray-400">
                                @if($svc->kind === 'stay') {{ $svc->capacity }} unit(s) · {{ $svc->formattedPrice() }}/night
                                @elseif($svc->kind === 'trip') {{ $svc->departures_count }} departure(s) · from {{ $svc->formattedPrice() }}
                                @else {{ $svc->duration_min }} min · {{ $svc->formattedPrice() }} @endif
                                @if($svc->requires_payment) · 💳 paid @endif
                            </p>
                        </div>
                        <button wire:click="toggleService('{{ $svc->id }}')" class="text-[11px] px-2 py-1 rounded-lg {{ $svc->is_active ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10' : 'text-gray-400 bg-gray-100 dark:bg-white/5' }}">{{ $svc->is_active ? 'Active' : 'Off' }}</button>
                        <button wire:click="editService('{{ $svc->id }}')" class="text-gray-400 hover:text-indigo-600" title="Edit">✎</button>
                        <button wire:click="deleteService('{{ $svc->id }}')" data-confirm="Delete this service?" class="text-gray-400 hover:text-rose-500" title="Delete">✕</button>
                    </div>
                    @empty
                    <p class="text-xs text-gray-400 px-1">No services yet. Add one on the left so visitors can book.</p>
                    @endforelse

                    {{-- Site-level shared RESOURCES tile --}}
                    <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4 !mt-4">
                        <h2 class="text-sm font-bold mb-1">Resources</h2>
                        <p class="text-[10px] text-gray-400 mb-2.5">Staff, rooms and vehicles shared across services — a resource booked through one service is automatically unavailable everywhere else.</p>
                        <div class="space-y-1.5 mb-3">
                            @forelse($this->siteResources as $r)
                                <div class="text-xs bg-gray-50 dark:bg-white/[0.04] rounded-lg px-2.5 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold {{ $r->is_active ? '' : 'text-gray-400 line-through' }}">{{ $r->name }}</span>
                                        @if($r->capacity > 1)<span class="text-gray-400">cap {{ $r->capacity }}</span>@endif
                                        @if($r->price_cents !== null)<span class="text-gray-400">{{ \App\Support\Money::format((int) $r->price_cents, $site->currency) }}</span>@endif
                                        <span class="ml-auto text-gray-400">{{ $r->active_bookings_count }} upcoming</span>
                                        <button wire:click="editSiteResource('{{ $r->id }}')" class="text-gray-400 hover:text-indigo-600" title="Edit">✎</button>
                                        <button wire:click="toggleSiteResource('{{ $r->id }}')"
                                                class="text-[10px] px-1.5 py-0.5 rounded {{ $r->is_active ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10' : 'text-gray-400 bg-gray-100 dark:bg-white/5' }}">{{ $r->is_active ? 'On' : 'Off' }}</button>
                                        <button wire:click="deleteSiteResource('{{ $r->id }}')"
                                                data-confirm="Delete “{{ $r->name }}” everywhere? It is removed from every service; bookings are kept (unassigned)."
                                                class="text-gray-300 hover:text-rose-500" title="Delete resource">✕</button>
                                    </div>
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        @foreach($this->services as $svc)
                                            @php $on = $r->services->contains('id', $svc->id); @endphp
                                            <button wire:click="toggleResourceService('{{ $r->id }}', {{ $svc->id }})"
                                                    class="text-[10px] px-1.5 py-0.5 rounded-md border transition-all
                                                        {{ $on ? 'border-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 font-semibold' : 'border-gray-200 dark:border-white/[0.08] text-gray-400' }}"
                                                    title="{{ $on ? 'Unassign from' : 'Assign to' }} {{ $svc->name }}">{{ $on ? '✓ ' : '' }}{{ $svc->name }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400">No shared resources yet.</p>
                            @endforelse
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <x-field.text model="srName" placeholder="Name (e.g. Bella)" />
                            <x-field.text model="srCapacity" type="number" min="1" placeholder="Capacity" hint="Parallel bookings it can hold." />
                            <x-field.text model="srPrice" type="number" step="0.01" min="0" placeholder="Price override" hint="Blank = service price." />
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button type="button" wire:click="saveSiteResource"
                                    class="px-3 py-1.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold">{{ $srEditingId ? 'Update' : '＋ Add resource' }}</button>
                            @if($srEditingId)
                                <button type="button" wire:click="$set('srEditingId', null)" class="px-3 py-1.5 rounded-xl text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Cancel</button>
                            @endif
                        </div>
                        @error('srName')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
                </div>
            </div>

            {{-- ── AVAILABILITY — click-to-review tile ── --}}
            @php
                $availDaysLabel = $availDays
                    ? collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])->filter(fn ($d) => in_array($d, $availDays, true))->map(fn ($d) => ucfirst($d))->implode(', ')
                    : 'No open days set';
            @endphp
            <div id="availability" class="mt-6 scroll-mt-24 bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden"
                 x-data="{ open: false }">
                <button type="button" @click="open = ! open" class="w-full flex items-center gap-3.5 px-5 py-4 text-left group">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-base shrink-0" style="background:#d9f068">🕑</span>
                    <span class="flex-1 min-w-0">
                        <span class="text-sm font-bold text-gray-900 dark:text-white">Availability</span>
                        <span class="block text-xs text-gray-400 truncate mt-0.5">
                            {{ $availDaysLabel }} · {{ $availOpen }}–{{ $availClose }}@if(count($dayHours)) · {{ count($dayHours) }} custom {{ Str::plural('day', count($dayHours)) }} @endif · schedules &amp; blocked dates
                        </span>
                    </span>
                    <span class="text-[11px] font-semibold text-indigo-500 shrink-0" x-text="open ? 'Close' : 'Review'"></span>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-500 transition-transform shrink-0" :class="open ? 'rotate-180' : ''"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="px-5 pb-5 border-t border-gray-100 dark:border-white/[0.06] pt-4">
            {{-- Scope: whole site or one service --}}
            <div class="flex flex-wrap items-center gap-1.5 mb-4">
                <button type="button" wire:click="pickAvailService('')"
                        class="px-3 py-1.5 rounded-xl text-[11px] font-bold transition-colors
                            {{ $availServiceId === '' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-white/[0.03] text-gray-500 border border-gray-100 dark:border-white/[0.06]' }}">
                    🌐 Whole site
                </button>
                @foreach($this->services->where('is_active', true)->where('kind', 'slot') as $asvc)
                    <button type="button" wire:click="pickAvailService('{{ $asvc->id }}')"
                            class="px-3 py-1.5 rounded-xl text-[11px] font-bold transition-colors
                                {{ $availServiceId === (string) $asvc->id ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-white/[0.03] text-gray-500 border border-gray-100 dark:border-white/[0.06]' }}">
                        {{ $asvc->typeIcon() }} {{ $asvc->name }}
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                @if($availServiceId !== '')
                {{-- Per-service schedule + confirmation mode --}}
                <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5">
                    <h2 class="text-sm font-bold mb-1">{{ $this->availService()?->name }} — schedule</h2>
                    <p class="text-[11px] text-gray-400 mb-4">Overrides for this service only. Leave everything empty to follow the site schedule on the left of the Whole-site view.</p>
                    <x-field.days label="Open days (override)" model="asDays" hint="None selected = site days." />
                    <div class="grid grid-cols-2 gap-3 mt-3 mb-3">
                        <x-field.text label="Opens (override)" model="asOpen" type="time" hint="Blank = site opening." />
                        <x-field.text label="Closes (override)" model="asClose" type="time" hint="Blank = site closing." />
                    </div>
                    {{-- Per-weekday hour overrides (scope-aware) --}}
                    <div class="mb-4">
                        <label class="bkf-label">Weekday hours</label>
                        <p class="text-[10px] text-gray-400 mb-1.5">Different hours on certain weekdays — e.g. short Fridays. Days without an entry use the Opens/Closes above.</p>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            @forelse($dayHours as $dhd => $dh)
                                <span class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400">
                                    {{ ucfirst($dhd) }} <span class="font-normal opacity-70">{{ $dh['open'] }}–{{ $dh['close'] }}</span>
                                    <button type="button" wire:click="removeDayHour('{{ $dhd }}')" class="w-4 h-4 rounded-full grid place-items-center opacity-50 hover:opacity-100" title="Remove">✕</button>
                                </span>
                            @empty
                                <span class="text-[11px] text-gray-400">None — every open day uses the same hours.</span>
                            @endforelse
                        </div>
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="w-28"><x-field.select label="Day" model="dhDay" :empty="null"
                                :options="['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday']" /></div>
                            <div class="w-28"><x-field.text label="Opens" model="dhOpen" type="time" /></div>
                            <div class="w-28"><x-field.text label="Closes" model="dhClose" type="time" /></div>
                            <button type="button" wire:click="addDayHour" class="px-3 py-2 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold">＋ Add</button>
                        </div>
                        @error('dhOpen')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4"><x-field.check model="asAutoConfirm" text="Auto-confirm bookings"
                                   hint="On: successful bookings confirm instantly. Off: you confirm each one manually." /></div>
                    <button wire:click="saveServiceAvailability" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save service availability</button>
                </div>
                @else
                <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5">
                    <h2 class="text-sm font-bold mb-1">Bookable slots</h2>
                    <p class="text-[11px] text-gray-400 mb-4">
                        Site-wide schedule for appointment services — customers can only pick times inside it.
                        Individual services can override days/hours in the Services section above.
                    </p>

                    <p class="bkf-label">Open days</p>
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        @foreach(['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'] as $key => $label)
                            <button type="button" wire:click="toggleDay('{{ $key }}')"
                                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-colors
                                        {{ in_array($key, $availDays, true)
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-50 dark:bg-white/[0.04] text-gray-400 border border-gray-200 dark:border-white/[0.08]' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <x-field.text label="Opens" model="availOpen" type="time" />
                        <x-field.text label="Closes" model="availClose" type="time" />
                    </div>
                    {{-- Per-weekday hour overrides (scope-aware) --}}
                    <div class="mb-4">
                        <label class="bkf-label">Weekday hours</label>
                        <p class="text-[10px] text-gray-400 mb-1.5">Different hours on certain weekdays — e.g. short Fridays. Days without an entry use the Opens/Closes above.</p>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            @forelse($dayHours as $dhd => $dh)
                                <span class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400">
                                    {{ ucfirst($dhd) }} <span class="font-normal opacity-70">{{ $dh['open'] }}–{{ $dh['close'] }}</span>
                                    <button type="button" wire:click="removeDayHour('{{ $dhd }}')" class="w-4 h-4 rounded-full grid place-items-center opacity-50 hover:opacity-100" title="Remove">✕</button>
                                </span>
                            @empty
                                <span class="text-[11px] text-gray-400">None — every open day uses the same hours.</span>
                            @endforelse
                        </div>
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="w-28"><x-field.select label="Day" model="dhDay" :empty="null"
                                :options="['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday']" /></div>
                            <div class="w-28"><x-field.text label="Opens" model="dhOpen" type="time" /></div>
                            <div class="w-28"><x-field.text label="Closes" model="dhClose" type="time" /></div>
                            <button type="button" wire:click="addDayHour" class="px-3 py-2 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold">＋ Add</button>
                        </div>
                        @error('dhOpen')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <x-field.text label="Slot length (min)" model="availSlot" type="number" min="5" step="5" hint="Times offered every N minutes." />
                        <x-field.text label="Lead time (hours)" model="availLead" type="number" min="0" hint="Earliest a customer can book." />
                        <x-field.text label="Horizon (days)" model="availHorizon" type="number" min="1" hint="How far ahead bookings open." />
                    </div>
                    @error('availOpen')<p class="text-xs text-rose-500 mb-2">{{ $message }}</p>@enderror
                    @error('availSlot')<p class="text-xs text-rose-500 mb-2">{{ $message }}</p>@enderror

                    <button wire:click="saveAvailability" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save availability</button>

                    @php $preview = app(\App\Services\BookingService::class)->settings($site); @endphp
                    <p class="text-[11px] text-gray-400 mt-4">
                        Currently live: {{ strtoupper(implode(' · ', $preview['days'])) }} — {{ $preview['open'] }}–{{ $preview['close'] }},
                        every {{ $preview['slot'] }} min, {{ $preview['lead'] }}h lead, {{ $preview['horizon'] }} days ahead.
                    </p>
                </div>
                @endif

                {{-- Day & slot exceptions --}}
                <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5">
                    <h2 class="text-sm font-bold mb-1">Day &amp; slot exceptions
                        @if($availServiceId !== '')<span class="text-[10px] font-semibold text-indigo-500 ml-1">{{ $this->availService()?->name }} only</span>@endif
                    </h2>
                    <p class="text-[11px] text-gray-400 mb-4">
                        Overrides for specific dates — close a whole day (holiday) or switch single
                        slots off (lunch, personal appointment). Click a slot to toggle it.
                        @if($availServiceId !== '') Blocks here affect only this service; site-wide blocks still apply on top. @endif
                    </p>

                    {{-- MONTH SLIDER — one month at a time, big cells --}}
                    @php $month = $this->planMonths[0]; @endphp
                    <div class="rounded-xl border border-gray-300 dark:border-white/[0.06] bg-gray-50/80 dark:bg-white/[0.02] p-3.5 mb-4">
                        <div class="flex items-center justify-between mb-2.5">
                            <button type="button" wire:click="planShiftBy(-1)" @disabled($planShift === 0)
                                    class="w-8 h-8 rounded-lg text-sm font-bold {{ $planShift === 0 ? 'bg-gray-100 text-gray-300 dark:bg-white/5 dark:text-gray-700' : 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 hover:opacity-80' }}"
                                    aria-label="Previous month">‹</button>
                            <div class="text-center">
                                <p class="text-sm font-bold">{{ $month['label'] }}</p>
                                <div class="flex items-center justify-center gap-1 mt-1">
                                    @for($i = 0; $i < 3; $i++)
                                        <span class="w-1.5 h-1.5 rounded-full {{ $planShift === $i ? 'bg-indigo-500' : 'bg-gray-200 dark:bg-white/10' }}"></span>
                                    @endfor
                                </div>
                            </div>
                            <button type="button" wire:click="planShiftBy(1)" @disabled($planShift === 2)
                                    class="w-8 h-8 rounded-lg text-sm font-bold {{ $planShift === 2 ? 'bg-gray-100 text-gray-300 dark:bg-white/5 dark:text-gray-700' : 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 hover:opacity-80' }}"
                                    aria-label="Next month">›</button>
                        </div>
                        <div class="grid grid-cols-7 gap-1.5 text-center">
                            @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow)
                                <span class="text-[10px] font-bold text-gray-700 dark:text-gray-300">{{ $dow }}</span>
                            @endforeach
                            @foreach($month['cells'] as $cell)
                                @if($cell === null)
                                    <span></span>
                                @elseif($cell['past'])
                                    <span class="py-2.5 text-sm text-gray-300 dark:text-gray-700">{{ $cell['day'] }}</span>
                                @else
                                    <button type="button" wire:click="pickBlockDate('{{ $cell['date'] }}')"
                                            class="relative py-2.5 rounded-xl text-sm font-semibold transition-all
                                                {{ $blockDate === $cell['date'] ? 'ring-2 ring-indigo-500 ring-offset-1 dark:ring-offset-gray-900 ' : '' }}
                                                {{ ! $cell['open'] ? 'text-gray-400 dark:text-gray-600'
                                                    : ($cell['blocked'] ? 'bg-rose-500 text-white shadow-sm'
                                                    : 'bg-emerald-50 dark:bg-white/[0.06] text-emerald-800 dark:text-emerald-400 border-2 border-emerald-300 dark:border-emerald-500/30 hover:border-emerald-500 hover:-translate-y-px') }}
                                                {{ $cell['today'] ? 'font-extrabold' : '' }}"
                                            title="{{ $cell['date'] }}{{ ! $cell['open'] ? ' — closed by weekly schedule' : ($cell['blocked'] ? ' — closed (exception)' : '') }}">
                                        {{ $cell['day'] }}
                                        @if($cell['slotBlocks'] && ! $cell['blocked'])
                                            <span class="absolute top-1 right-1.5 w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                        @endif
                                        @if(($cell['hours'] ?? false) && ! $cell['blocked'])
                                            <span class="absolute top-1 left-1.5 w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                                        @endif
                                    </button>
                                @endif
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-[11px] font-semibold text-gray-600 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-emerald-50 border-2 border-emerald-300"></span> open</span>
                            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-rose-500"></span> closed (exception)</span>
                            <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> some slots off</span>
                            <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span> custom hours</span>
                            <span class="inline-flex items-center gap-1.5 font-normal"><span class="w-2.5 h-2.5 rounded bg-gray-100 border border-gray-200"></span> closed by weekly schedule</span>
                        </div>
                    </div>

                    {{-- SELECTED DAY — headline + whole-day switch + slot grid --}}
                    @if($blockDate !== '')
                    <div class="rounded-xl border {{ $this->blockDayOff ? 'border-rose-200 dark:border-rose-500/30' : 'border-gray-300 dark:border-white/[0.06]' }} p-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <p class="text-sm font-bold">{{ \Carbon\Carbon::parse($blockDate)->format('l, F j, Y') }}</p>
                                <p class="text-[10px] {{ $this->blockDayOff ? 'text-rose-500 font-semibold' : 'text-gray-400' }}">
                                    {{ $this->blockDayOff ? 'Whole day closed — no slots are offered.' : 'Open — click slots below to block individual times.' }}
                                </p>
                            </div>
                            <button type="button" wire:click="toggleDayBlock"
                                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-colors
                                        {{ $this->blockDayOff
                                            ? 'bg-rose-600 hover:bg-rose-700 text-white'
                                            : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30 hover:bg-emerald-100' }}">
                                {{ $this->blockDayOff ? 'Reopen day' : 'Close whole day' }}
                            </button>
                        </div>
                        {{-- Custom opening hours for THIS date --}}
                        <div class="flex flex-wrap items-end gap-2 mb-3 {{ $this->blockDayOff ? 'opacity-40 pointer-events-none' : '' }}">
                            <div class="w-32"><x-field.text label="Opens (this date)" model="bdOpen" type="time" /></div>
                            <div class="w-32"><x-field.text label="Closes (this date)" model="bdClose" type="time" /></div>
                            <button type="button" wire:click="saveDayHours"
                                    class="px-3 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold">Set hours</button>
                            @if(($this->blockDaySlots[0]['label'] ?? null) && $bdOpen !== '')
                                <button type="button" wire:click="clearDayHours"
                                        class="px-3 py-2 rounded-xl text-xs font-semibold text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Reset to schedule</button>
                            @endif
                            <p class="basis-full text-[10px] text-gray-400 -mt-1">Only this date opens within these hours — e.g. a short Friday. Blank days follow the normal schedule.</p>
                            @error('bdOpen')<p class="basis-full text-xs text-rose-500">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-4 sm:grid-cols-5 gap-1.5 {{ $this->blockDayOff ? 'opacity-40 pointer-events-none' : '' }}">
                            @forelse($this->blockDaySlots as $slot)
                                <button type="button" wire:click="toggleSlotBlock('{{ $slot['time'] }}')"
                                        class="px-2 py-2 rounded-lg text-xs font-semibold transition-colors
                                            {{ $slot['blocked']
                                                ? 'bg-rose-100 text-rose-500 dark:bg-rose-500/15 dark:text-rose-400 line-through'
                                                : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 hover:bg-emerald-100' }}"
                                        title="{{ $slot['blocked'] ? 'Blocked — click to make available' : 'Available — click to block' }}">
                                    {{ $slot['label'] }}
                                </button>
                            @empty
                                <p class="col-span-full text-xs text-gray-400">No slots on this day.</p>
                            @endforelse
                        </div>
                    </div>
                    @else
                        <p class="text-xs text-gray-400">Pick a day above to edit it.</p>
                    @endif

                    @if($this->blockedDates->isNotEmpty())
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/[0.06]">
                            <p class="bkf-label">Upcoming exceptions</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($this->blockedDates as $ex)
                                    <span class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1.5 rounded-full text-[11px] font-semibold
                                        {{ $ex['dayOff'] ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' : (($ex['hours'] ?? null) && ! $ex['slots'] ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400') }}">
                                        <button type="button" x-on:click="$wire.set('blockDate', '{{ $ex['date'] }}')" class="hover:underline">
                                            {{ \Carbon\Carbon::parse($ex['date'])->format('D, M j') }}</button>
                                        <span class="font-normal opacity-70">{{ $ex['dayOff'] ? 'closed' : (($ex['hours'] ?? null) ? $ex['hours'] : $ex['slots'].' slot(s)') }}</span>
                                        <button type="button" wire:click="clearBlocks('{{ $ex['date'] }}')"
                                                data-confirm="Remove all exceptions on {{ $ex['date'] }}? The day returns to the normal schedule."
                                                class="w-4 h-4 rounded-full grid place-items-center opacity-50 hover:opacity-100" title="Clear exceptions">✕</button>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
                </div>
            </div>
        </div>

        {{-- ══════════ RIGHT RAIL: calendar + selected day's bookings below ══════════ --}}
        <div class="space-y-4">
            {{-- Calendar — colored from the SITE THEME accent so it matches the
                 owner's brand and contrasts the white workspace tiles. --}}
            @php
                $accent = $site->theme['accent'] ?? '#4f46e5';
                // HSL shade helpers: deep (darker, punchy) and vivid (max
                // saturation, brighter) versions of the theme accent.
                $shade = function (string $hex, float $dSat, float $dLig): string {
                    [$r, $g, $b] = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
                    $max = max($r, $g, $b); $min = min($r, $g, $b); $l = ($max + $min) / 2; $d = $max - $min;
                    $s = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));
                    $h = $d == 0 ? 0 : ($max === $r ? fmod(($g - $b) / $d, 6) : ($max === $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4)) * 60;
                    if ($h < 0) $h += 360;
                    $s = max(0, min(1, $s + $dSat)); $l = max(0, min(1, $l + $dLig));
                    $c = (1 - abs(2 * $l - 1)) * $s; $x = $c * (1 - abs(fmod($h / 60, 2) - 1)); $m = $l - $c / 2;
                    [$r, $g, $b] = match (true) {
                        $h < 60 => [$c, $x, 0], $h < 120 => [$x, $c, 0], $h < 180 => [0, $c, $x],
                        $h < 240 => [0, $x, $c], $h < 300 => [$x, 0, $c], default => [$c, 0, $x],
                    };
                    return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
                };
                $ok = strlen($accent) === 7;
                $deep  = $ok ? $shade($accent, +0.10, -0.10) : $accent; // dark, saturated
                $vivid = $ok ? $shade($accent, +0.18, +0.03) : $accent; // bright, punchy
            @endphp
            <style>
                #bk-cal { --bk-cal-bg:{{ $deep }}; --bk-cal-bg2:{{ $vivid }}; --bk-cal-day:{{ $accent }};
                          background:linear-gradient(135deg, var(--bk-cal-bg) 0%, var(--bk-cal-bg) 45%, var(--bk-cal-bg2) 100%);
                          color:#fff; box-shadow:0 10px 25px -5px {{ $deep }}66; }
                #bk-cal .bkcal-booked { background:var(--bk-cal-day); }
                #bk-cal .bkcal-sel    { color:var(--bk-cal-bg); }
                #bk-cal .bkcal-badge  { color:var(--bk-cal-bg); }
            </style>
            <div id="bk-cal" class="rounded-2xl p-4 text-white shadow-lg">
                <div class="flex items-center justify-between mb-3">
                    <button wire:click="calShift(-1)" class="w-7 h-7 rounded-lg bg-white/15 hover:bg-white/25 text-white text-sm transition-colors" aria-label="Previous month">‹</button>
                    <h2 class="text-sm font-bold">{{ \Carbon\Carbon::parse($calMonth.'-01')->format('F Y') }}</h2>
                    <button wire:click="calShift(1)" class="w-7 h-7 rounded-lg bg-white/15 hover:bg-white/25 text-white text-sm transition-colors" aria-label="Next month">›</button>
                </div>
                <div class="grid grid-cols-7 gap-1 mb-1">
                    @foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $dow)
                        <span class="text-center text-[10px] font-bold text-white/50">{{ $dow }}</span>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-1">
                    @foreach($this->calendarDays as $cell)
                        <button wire:click="pickDate('{{ $cell['date'] }}')"
                                class="relative aspect-square rounded-lg text-xs transition-colors
                                    {{ ! $cell['inMonth'] ? 'opacity-40' : '' }}
                                    {{ $calDate === $cell['date'] ? 'bg-white bkcal-sel font-bold shadow-md' : ($cell['count'] > 0 ? 'bkcal-booked text-white font-bold ring-1 ring-white/60 shadow-md' : ($cell['isToday'] ? 'ring-1 ring-white/70 text-white font-bold hover:bg-white/15' : 'text-white/85 hover:bg-white/15')) }}">
                                {{ $cell['day'] }}
                                @if($cell['count'] > 0)
                                    <span class="absolute -top-1 -right-1 min-w-[15px] h-[15px] px-0.5 rounded-full text-[8px] font-bold leading-[15px] bg-white shadow bkcal-badge">{{ $cell['count'] }}</span>
                                @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Selected date's bookings — below the calendar --}}
            <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/[0.06] flex items-baseline justify-between">
                    <h2 class="text-sm font-bold">{{ \Carbon\Carbon::parse($calDate)->isToday() ? 'Today' : \Carbon\Carbon::parse($calDate)->format('D, M j') }}</h2>
                    <span class="text-[11px] text-gray-400">{{ $this->dayBookings->count() }} booking(s)</span>
                </div>
                <div class="max-h-[360px] overflow-y-auto">
                    @forelse($this->dayBookings as $b)
                        @php $p = (array) ($b->params ?? []); $bkind = $b->service?->kind ?? 'slot';
                             $hue = ['#6366f1','#0ea5e9','#f59e0b','#10b981','#ec4899'][abs(crc32($b->customer_name)) % 5]; @endphp
                        <div class="flex items-center gap-3 px-4 py-2.5 border-b border-gray-50 dark:border-white/[0.04] last:border-0 cursor-pointer hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors" wire:click="viewBooking('{{ $b->id }}')" title="View details">
                            <span class="shrink-0 w-8 h-8 rounded-full grid place-items-center text-white text-[10px] font-bold" style="background:{{ $hue }}">
                                {{ strtoupper(\Illuminate\Support\Str::of($b->customer_name)->substr(0, 2)) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold truncate">{{ $b->customer_name }}
                                    @if($b->status === 'pending')<span class="text-amber-500 font-semibold">· pending</span>@endif</p>
                                <p class="text-[10px] text-gray-400 truncate">
                                    {{ $b->service?->name }}{{ ($p['resource'] ?? false) ? ' · '.$p['resource'] : '' }}
                                    @if($bkind === 'stay') · {{ $p['nights'] ?? '?' }} night(s)
                                    @elseif($bkind === 'trip') · {{ $p['origin'] ?? '' }} → {{ $p['destination'] ?? '' }} @endif
                                </p>
                            </div>
                            <span class="shrink-0 text-[11px] font-bold tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $bkind === 'stay' ? 'stay' : $b->starts_at?->format('g:i A') }}</span>
                        </div>
                    @empty
                        <p class="px-4 py-10 text-center text-xs text-gray-400">Nothing booked on this day.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ BOOKING DETAIL — dashboard-card lightbox ══════════ --}}
    @if($this->viewedBooking)
        @php
            $vb = $this->viewedBooking;
            $vAccent = $site->theme["accent"] ?? "#6366f1";
        @endphp
        <div class="fixed inset-0 z-50 grid place-items-center p-6" wire:key="booking-detail"
             style="background:rgba(10,10,12,.85); backdrop-filter:blur(6px)" wire:click.self="closeBooking">

            <div class="relative w-full max-w-lg">
                <button type="button" wire:click="closeBooking" aria-label="Close"
                        class="absolute -top-5 -right-5 z-10 w-12 h-12 rounded-full grid place-items-center text-white text-xl font-bold transition-transform hover:scale-110"
                        style="background:{{ $vAccent }}; box-shadow:0 8px 24px rgba(0,0,0,.35)">✕</button>

                <x-booking-card :booking="$vb" :accent="$vAccent">
                    @if(! in_array($vb->status, ["confirmed", "awaiting_payment", "cancelled"], true))
                        <button type="button" wire:click="setStatus('{{ $vb->id }}', 'confirmed')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white hover:opacity-90"
                                style="background:{{ $vAccent }}">Confirm booking</button>
                    @endif
                    @if($vb->balanceCents() > 0 && $vb->status !== "cancelled")
                        <button type="button" wire:click="markFullyPaid" data-confirm="Record the {{ $vb->formattedBalance() }} balance as collected?"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold bg-white text-[#211d15] border shadow-sm hover:bg-gray-50"
                                style="border-color:rgba(51,44,31,.14)">Record balance</button>
                    @endif
                    @if($vb->status !== "cancelled")
                        <button type="button" wire:click="setStatus('{{ $vb->id }}', 'cancelled')" data-confirm="Cancel this booking? The customer is emailed about the cancellation."
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold bg-white text-rose-600 border shadow-sm hover:bg-rose-50"
                                style="border-color:rgba(51,44,31,.14)">Cancel</button>
                    @endif
                    <a href="mailto:{{ $vb->customer_email }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold bg-white text-[#211d15] border shadow-sm hover:bg-gray-50"
                       style="border-color:rgba(51,44,31,.14)">Email customer</a>
                </x-booking-card>
            </div>
        </div>
    @endif
</div>