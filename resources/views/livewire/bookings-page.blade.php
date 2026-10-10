@php
    $panelCls = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $s = $this->stats;
    $cur = $site->currency;
    $money = fn (int $c) => \App\Support\Money::format($c, $cur);
    $bookPage = route('public.book', ['siteName' => $site->name]);

    // status → [label, pill classes, dot class, bar colour] — matches the booking card
    $statusMeta = [
        'confirmed' => ['Confirmed', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'bg-emerald-500', '#10b981'],
        'pending' => ['Pending', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'bg-amber-500', '#f59e0b'],
        'awaiting_payment' => ['Awaiting payment', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', 'bg-sky-500', '#0ea5e9'],
        'no_show' => ['No-show', 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300', 'bg-orange-500', '#f97316'],
        'cancelled' => ['Cancelled', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300', 'bg-rose-500', '#f43f5e'],
    ];
    $statusOf = fn ($st) => $statusMeta[$st] ?? [ucfirst(str_replace('_', ' ', (string) $st)), 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300', 'bg-gray-400', '#9ca3af'];
    // payment state → [label, classes]
    $payOf = function ($b) {
        if ($b->total_cents <= 0) {
            return ['Free', 'text-gray-400 dark:text-gray-500'];
        }

        return $b->balanceCents() === 0 ? ['Paid', 'text-emerald-600 dark:text-emerald-400']
            : ($b->paid_cents > 0 ? ['Deposit paid', 'text-sky-600 dark:text-sky-400'] : ['Unpaid', 'text-amber-600 dark:text-amber-400']);
    };
    // when-line: slot → time range · stay → nights · trip → route
    $whenOf = function ($b) {
        $p = (array) ($b->params ?? []);
        $kind = $b->service?->kind ?? 'slot';
        if ($kind === 'stay') {
            $in = $b->starts_at?->format('M j');
            $out = $b->ends_at?->format('M j');
            $n = $p['nights'] ?? ($b->starts_at && $b->ends_at ? (int) $b->starts_at->copy()->startOfDay()->diffInDays($b->ends_at->copy()->startOfDay()) : null);

            return trim($in.' → '.$out.($n ? ' · '.$n.' '.Str::plural('night', (int) $n) : ''));
        }
        if ($kind === 'trip') {
            return trim(($p['origin'] ?? '').' → '.($p['destination'] ?? '').' · '.$b->starts_at?->format('g:i A'));
        }

        return $b->starts_at?->format('g:i A').($b->ends_at && $b->ends_at->gt($b->starts_at) ? ' – '.$b->ends_at->format('g:i A') : '');
    };
    $initialsOf = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
    $hueOf = fn ($name) => ['#6366f1', '#0ea5e9', '#f59e0b', '#10b981', '#ec4899'][abs(crc32((string) $name)) % 5];

    $filterCounts = [
        'all' => $s['total'], 'upcoming' => $s['upcoming'], 'today' => $s['today'], 'pending' => $s['pending'],
        'confirmed' => $s['confirmed'], 'past' => $s['past'], 'cancelled' => $s['cancelled'], 'no_show' => $s['no_show'],
        'unpaid' => $s['unpaid'],
    ];
    $tileFx = 'cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all';
    $tileOn = 'ring-2 ring-offset-2 ring-gray-900 dark:ring-white dark:ring-offset-[#16171f]';
    $att = $this->attention;
@endphp
<x-tri-layout title="Bookings" subtitle="Appointments, stays and trips — confirm, track payments and manage what customers can book." :site-name="$site->name"
    :labels="['📊 Stats', '📅 Bookings', '⚡ Summary']">

    <x-slot:header>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="startCreate" class="{{ $btnSolid }} text-[13px] px-3.5 py-2.5">＋ New service</button>
            <a href="{{ $bookPage }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl text-[13px] font-semibold bg-gray-900 text-white dark:bg-white dark:text-gray-900 hover:opacity-90">
                Open booking page ↗
            </a>
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: actionable stat tiles (click filters the list) ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$s['today']" label="Today's bookings"
                :sub="$att['next'] ? 'Next '.$att['next']->starts_at->format('g:i A') : ($s['today'] ? 'all done for today' : 'nothing booked')"
                icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                wire:click="openTile('today')" class="{{ $tileFx }} {{ $tab === 'bookings' && $filter === 'today' ? $tileOn : '' }}" />
        <x-tile accent="lime" :value="$s['next7']" label="Next 7 days" sub="upcoming"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                wire:click="openTile('upcoming')" class="{{ $tileFx }} {{ $tab === 'bookings' && $filter === 'upcoming' ? $tileOn : '' }}" />
        <x-tile :accent="$s['pending'] ? 'rose' : 'sky'" :value="$s['pending']" label="Awaiting confirmation"
                :sub="$s['pending'] ? 'confirm to notify' : 'all confirmed'"
                icon="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"
                wire:click="openTile('pending')" class="{{ $tileFx }} {{ $tab === 'bookings' && $filter === 'pending' ? $tileOn : '' }}" />
        <x-tile accent="lavender" :value="$money($s['collected_month'])" label="Collected this month"
                :sub="$s['month_count'].' '.Str::plural('booking', $s['month_count']).' in '.now()->format('M')"
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                :href="route('site.payments', $site->name)" wire:navigate />
        <x-tile accent="cocoa" :value="$s['noshow_month']" label="No-shows this month"
                :sub="$s['no_show'] ? $s['no_show'].' all time' : 'none yet'"
                icon="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"
                wire:click="openTile('noshow')" class="{{ $tileFx }} {{ $tab === 'bookings' && $filter === 'no_show' ? $tileOn : '' }}" />
        <x-tile accent="sky" :value="$s['busiest']['name'] ?? '—'" label="Busiest service"
                :sub="$s['busiest'] ? $s['busiest']['count'].' '.Str::plural('booking', $s['busiest']['count']).' ±30 days' : 'no bookings yet'"
                icon="M3 13.5L9 7.5l4 4L21 3.5M21 3.5h-5m5 0v5M4 20h16"
                wire:click="openTile('busiest')" class="{{ $s['busiest'] ? $tileFx : '' }}" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    {{-- ── Pill tabs: Bookings · Calendar · Services ── --}}
    <div class="flex gap-2 overflow-x-auto no-scrollbar">
        @foreach (['bookings' => ['Bookings', $s['total']], 'calendar' => ['Calendar', $s['month_count']], 'services' => ['Services', $this->services->count()]] as $key => [$label, $n])
            <button type="button" wire:click="setTab('{{ $key }}')"
                class="shrink-0 px-4 py-2 rounded-full text-[13px] font-bold border transition-colors
                    {{ $tab === $key
                        ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                        : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                {{ $label }} <span class="opacity-60">{{ $n }}</span>
            </button>
        @endforeach
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

    {{-- ════════ BOOKINGS TAB ════════ --}}
    @if ($tab === 'bookings')
    {{-- Toolbar: search · filter · sort · layout · new booking --}}
    <div class="{{ $panelCls }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.300ms="search" placeholder="Search customer, reference or service…" class="w-full" style="padding-left:2.25rem" />
            </div>
            <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                @foreach (\App\Livewire\BookingsPage::SORTS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            <a href="{{ $bookPage }}" target="_blank" rel="noopener" title="Book on a customer's behalf through your booking page"
               class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New booking
            </a>
        </div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            @foreach (\App\Livewire\BookingsPage::FILTERS as $key => $label)
                @continue($key === 'unpaid' && ! $s['unpaid'] && $filter !== 'unpaid')
                <button type="button" wire:click="setFilter('{{ $key }}')"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                        {{ $filter === $key
                            ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                            : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                    {{ $label }} <span class="opacity-60">{{ $filterCounts[$key] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @php $list = $this->bookings; @endphp
    @if ($list->isEmpty())
        <div class="{{ $panelCls }} px-6 py-16 text-center">
            <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            @if ($s['total'] === 0)
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No bookings yet</p>
                @if ($this->services->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Create the first thing customers can book — an appointment, a room or a trip — then share your booking page.</p>
                    <button type="button" wire:click="startCreate" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">＋ Create your first service</button>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Share your booking page — new bookings land here, ready to confirm.</p>
                    <a href="{{ $bookPage }}" target="_blank" rel="noopener" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Open booking page ↗</a>
                @endif
            @else
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    No {{ $filter === 'all' ? '' : strtolower(\App\Livewire\BookingsPage::FILTERS[$filter]).' ' }}bookings{{ trim($search) !== '' ? ' for “'.$search.'”' : '' }}.
                </p>
                <button type="button" wire:click="clearFilters" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all bookings</button>
            @endif
        </div>
    @elseif ($viewMode === 'grid')
        {{-- ── Cards ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($list as $b)
                @php
                    [$stLabel, $stCls, $stDot] = $statusOf($b->status);
                    [$payLabel, $payCls] = $payOf($b);
                    $past = $b->starts_at?->isPast() && ! $b->starts_at?->isToday();
                    $dimmed = in_array($b->status, ['cancelled', 'no_show'], true);
                @endphp
                <button type="button" wire:click="viewBooking('{{ $b->id }}')" wire:key="bk-card-{{ $b->id }}"
                        class="group text-left flex flex-col {{ $panelCls }} !rounded-2xl hover:shadow-md hover:-translate-y-0.5 transition-all overflow-hidden {{ $dimmed ? 'opacity-70' : '' }}">
                    <span class="p-4 flex items-start gap-3.5 w-full">
                        {{-- date tile --}}
                        <span class="shrink-0 w-14 rounded-xl overflow-hidden text-center border {{ $past ? 'border-gray-200 dark:border-white/[0.08]' : 'border-transparent' }}">
                            <span class="block text-[10px] font-bold uppercase tracking-wider py-0.5 {{ $past ? 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' : '' }}"
                                  @unless($past) style="background:var(--primary);color:var(--on-primary)" @endunless>{{ $b->starts_at?->format('M') }}</span>
                            <span class="block py-1 bg-gray-50 dark:bg-white/[0.04]">
                                <span class="block text-xl font-extrabold leading-none tabular-nums {{ $past ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $b->starts_at?->format('j') }}</span>
                                <span class="block text-[10px] font-semibold text-gray-400 mt-0.5">{{ $b->starts_at?->isToday() ? 'Today' : $b->starts_at?->format('D') }}</span>
                            </span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate group-hover:underline">{{ $b->service?->typeIcon() }} {{ $b->service?->name ?? 'Service' }}</span>
                            <span class="block text-[12.5px] font-semibold text-gray-600 dark:text-gray-300 mt-0.5 tabular-nums truncate">{{ $whenOf($b) }}</span>
                            <span class="mt-2 flex items-center gap-2 min-w-0">
                                <span class="shrink-0 w-6 h-6 rounded-full grid place-items-center text-white text-[9px] font-bold" style="background:{{ $hueOf($b->customer_name) }}">{{ $initialsOf($b->customer_name) }}</span>
                                <span class="text-[13px] text-gray-700 dark:text-gray-200 truncate">{{ $b->customer_name }}</span>
                            </span>
                            @if ($b->resource || ($b->params['resource'] ?? false))
                                <span class="block mt-1 text-[11.5px] text-gray-400 truncate">with {{ $b->resource?->name ?? $b->params['resource'] }}</span>
                            @endif
                        </span>
                    </span>
                    <span class="mt-auto w-full flex items-center justify-between gap-2 px-4 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold {{ $stCls }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $stDot }}"></span>{{ $stLabel }}
                        </span>
                        <span class="text-[12px] font-semibold tabular-nums truncate">
                            <span class="{{ $payCls }}">{{ $payLabel }}</span>
                            @if ($b->total_cents > 0)<span class="text-gray-400"> · {{ $b->formattedTotal() }}</span>@endif
                        </span>
                    </span>
                </button>
            @endforeach
        </div>
    @else
        {{-- ── List & Compact (table) ── --}}
        @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
        <div class="{{ $panelCls }} !rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Service</th>
                            @unless ($compact)<th class="px-4 py-3">Staff</th>@endunless
                            <th class="px-4 py-3">Status</th>
                            @unless ($compact)<th class="px-4 py-3">Payment</th>@endunless
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach ($list as $b)
                            @php [$stLabel, $stCls, $stDot] = $statusOf($b->status); [$payLabel, $payCls] = $payOf($b); @endphp
                            <tr class="cursor-pointer hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors" wire:click="viewBooking('{{ $b->id }}')" wire:key="bk-row-{{ $b->id }}" title="View details">
                                <td class="{{ $pad }} whitespace-nowrap">
                                    <span class="block font-semibold text-gray-900 dark:text-white tabular-nums">{{ $b->starts_at?->isToday() ? 'Today' : $b->starts_at?->format('D, M j') }}</span>
                                    @unless ($compact)<span class="block text-[11.5px] text-gray-400 tabular-nums">{{ $whenOf($b) }}</span>@else<span class="text-[11.5px] text-gray-400 tabular-nums"> {{ $b->starts_at?->format('g:i A') }}</span>@endunless
                                </td>
                                <td class="{{ $pad }}">
                                    <span class="block font-semibold text-gray-800 dark:text-gray-100 truncate max-w-[12rem]">{{ $b->customer_name }}</span>
                                    @unless ($compact)<span class="block text-[11px] text-gray-400 font-mono">{{ $b->reference }}</span>@endunless
                                </td>
                                <td class="{{ $pad }} text-gray-700 dark:text-gray-200"><span class="block truncate max-w-[12rem]">{{ $b->service?->typeIcon() }} {{ $b->service?->name ?? '—' }}</span></td>
                                @unless ($compact)<td class="{{ $pad }} text-[12.5px] text-gray-500 dark:text-gray-400">{{ $b->resource?->name ?? ($b->params['resource'] ?? '—') }}</td>@endunless
                                <td class="{{ $pad }}">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap {{ $stCls }}"><span class="w-1.5 h-1.5 rounded-full {{ $stDot }}"></span>{{ $stLabel }}</span>
                                </td>
                                @unless ($compact)<td class="{{ $pad }} text-[12.5px] font-semibold whitespace-nowrap {{ $payCls }}">{{ $payLabel }}</td>@endunless
                                <td class="{{ $pad }} text-right font-bold tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $b->formattedTotal() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    @if ($list->hasPages())
        <div>{{ $list->links() }}</div>
    @endif
    @endif

    {{-- ════════ CALENDAR TAB: month grid + the selected day's agenda ════════ --}}
    @if ($tab === 'calendar')
    @php $chips = $this->calendarChips; $selDay = \Carbon\Carbon::parse($calDate); @endphp
    <div class="{{ $panelCls }} !rounded-2xl p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
                <button type="button" wire:click="calShift(-1)" class="{{ $btnSolid }} w-9 h-9" aria-label="Previous month">‹</button>
                <h2 class="text-[15px] font-extrabold text-gray-900 dark:text-white min-w-[9rem] text-center">{{ \Carbon\Carbon::parse($calMonth.'-01')->format('F Y') }}</h2>
                <button type="button" wire:click="calShift(1)" class="{{ $btnSolid }} w-9 h-9" aria-label="Next month">›</button>
            </div>
            <button type="button" wire:click="openDay('{{ now()->format('Y-m-d') }}')" class="{{ $btnSolid }} text-[13px] px-3.5 py-2">Today</button>
        </div>
        <div class="grid grid-cols-7 gap-1 mb-1">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                <span class="text-center text-[11px] font-bold text-gray-400">{{ $dow }}</span>
            @endforeach
        </div>
        <div class="grid grid-cols-7 gap-1">
            @foreach ($this->calendarDays as $cell)
                @php $dayChips = $chips[$cell['date']] ?? ['items' => [], 'more' => 0]; $sel = $calDate === $cell['date']; @endphp
                <button type="button" wire:click="pickDate('{{ $cell['date'] }}')" wire:key="cal-{{ $cell['date'] }}"
                        class="text-left min-h-[3.25rem] sm:min-h-[5.75rem] rounded-xl p-1.5 border transition-colors
                            {{ $sel ? 'border-gray-900 dark:border-white bg-gray-50 dark:bg-white/[0.06]' : 'border-gray-100 dark:border-white/[0.05] hover:bg-gray-50 dark:hover:bg-white/[0.04]' }}
                            {{ $cell['inMonth'] ? '' : 'opacity-40' }}">
                    <span class="flex items-center justify-between">
                        <span class="text-[12px] font-bold tabular-nums w-6 h-6 rounded-full grid place-items-center {{ $cell['isToday'] ? '' : 'text-gray-700 dark:text-gray-200' }}"
                              @if($cell['isToday']) style="background:var(--primary);color:var(--on-primary)" @endif>{{ $cell['day'] }}</span>
                        @if ($cell['count'] > 0)
                            <span class="sm:hidden w-1.5 h-1.5 rounded-full" style="background:var(--primary)"></span>
                            <span class="hidden sm:inline text-[10px] font-bold text-gray-400">{{ $cell['count'] }}</span>
                        @endif
                    </span>
                    <span class="hidden sm:block mt-1 space-y-0.5">
                        @foreach ($dayChips['items'] as $chip)
                            <span class="block truncate rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold
                                {{ $chip['status'] === 'pending' ? 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300' : ($chip['status'] === 'no_show' ? 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300') }}">
                                {{ $chip['time'] }} {{ $chip['name'] }}
                            </span>
                        @endforeach
                        @if ($dayChips['more'] > 0)
                            <span class="block text-[10px] font-semibold text-gray-400 px-1">+{{ $dayChips['more'] }} more</span>
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Day agenda --}}
    <div class="{{ $panelCls }} !rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-white/[0.06] flex items-baseline justify-between">
            <h2 class="text-[15px] font-extrabold text-gray-900 dark:text-white">{{ $selDay->isToday() ? 'Today' : $selDay->format('l, F j') }}</h2>
            <span class="text-[12px] text-gray-400">{{ $this->dayBookings->count() }} {{ Str::plural('booking', $this->dayBookings->count()) }}</span>
        </div>
        @forelse ($this->dayBookings as $b)
            @php [$stLabel, $stCls, $stDot] = $statusOf($b->status); $bkind = $b->service?->kind ?? 'slot'; @endphp
            <button type="button" wire:click="viewBooking('{{ $b->id }}')" wire:key="agenda-{{ $b->id }}"
                    class="w-full text-left flex items-center gap-3 px-5 py-3 border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors">
                <span class="shrink-0 w-16 text-[13px] font-bold tabular-nums text-gray-900 dark:text-white">{{ $bkind === 'stay' ? 'Stay' : $b->starts_at?->format('g:i A') }}</span>
                <span class="shrink-0 w-8 h-8 rounded-full grid place-items-center text-white text-[10px] font-bold" style="background:{{ $hueOf($b->customer_name) }}">{{ $initialsOf($b->customer_name) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13.5px] font-bold text-gray-900 dark:text-white truncate">{{ $b->customer_name }}</span>
                    <span class="block text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $b->service?->name }} · {{ $whenOf($b) }}</span>
                </span>
                <span class="shrink-0 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold {{ $stCls }}"><span class="w-1.5 h-1.5 rounded-full {{ $stDot }}"></span>{{ $stLabel }}</span>
            </button>
        @empty
            <div class="px-5 py-12 text-center">
                <p class="text-[14px] font-bold text-gray-900 dark:text-white">Nothing booked on this day</p>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">Pick another day, or close it for bookings in <button type="button" wire:click="openException('{{ $calDate }}')" class="font-semibold underline">day &amp; slot exceptions</button>.</p>
            </div>
        @endforelse
    </div>
    @endif

    {{-- ════════ SERVICES TAB: services · resources · availability ════════ --}}
    @if ($tab === 'services')
    <div class="{{ $panelCls }} !rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">What customers can book</p>
            <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Appointments (slot), stays (rooms/houses) and trips (transport) — one engine, three kinds.</p>
        </div>
        <button type="button" wire:click="startCreate"
                class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">＋ Guided setup</button>
    </div>
    <div>
            {{-- ── SERVICES — list only; add/edit opens the right-side panel ── --}}
            @php $activeServices = $this->services->where('is_active', true)->count(); @endphp
            <div class="flex items-center gap-2 mb-3">
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-600 dark:text-gray-300">Services</p>
                <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d7c3f5;color:#33245c">{{ $this->services->count() }}</span>
                <div class="flex-1 border-t border-gray-100 dark:border-white/[0.06]"></div>
                <button type="button" wire:click="newService"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white">＋ Add service</button>
            </div>
            <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                <div class="flex items-center gap-3.5 px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-base shrink-0" style="background:#d7c3f5">⚙</span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold">Services</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $activeServices }} active · click ✎ on a service to edit it in the side panel</p>
                    </div>
                </div>
                <div class="p-3 space-y-2">
                    @forelse($this->services as $svc)
                    <div class="flex items-center gap-3 bg-white dark:bg-white/[0.03] rounded-xl border border-gray-100 dark:border-white/[0.06] px-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <span class="inline-flex mb-0.5 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded
                                {{ ['slot' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
                                    'stay' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                    'trip' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'][$svc->kind] ?? '' }}">{{ $svc->typeIcon() }} {{ $svc->typeLabel() }}</span>
                            <p class="text-sm font-semibold truncate {{ $svc->is_active ? '' : 'text-gray-400 line-through' }}">{{ $svc->name }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
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
                    <p class="text-xs text-gray-400 px-1">No services yet — click “＋ Add service” to create your first one.</p>
                    @endforelse
                </div>
            </div>

            {{-- ── RESOURCES — shared staff / rooms / vehicles; add/edit in the panel ── --}}
            <div class="mt-6 bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4">
                <div class="flex items-center justify-between gap-3 mb-1">
                    <h2 class="text-sm font-bold">Resources</h2>
                    <button type="button" wire:click="newSiteResource"
                            class="px-3 py-1.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold">＋ Add resource</button>
                </div>
                        <p class="text-[10px] text-gray-400 mb-2.5">Staff, rooms and vehicles shared across services — a resource booked through one service is automatically unavailable everywhere else.</p>
                        <div class="space-y-1.5 mb-3">
                            @forelse($this->siteResources as $r)
                                <div class="text-xs bg-gray-50 dark:bg-white/[0.04] rounded-lg px-2.5 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
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
                                <p class="text-xs text-gray-500 dark:text-gray-400">No shared resources yet.</p>
                            @endforelse
                        </div>
            </div>

            {{-- ── AVAILABILITY — summary; schedule & exceptions edit in the panel ── --}}
            @php
                $availDaysLabel = $availDays
                    ? collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])->filter(fn ($d) => in_array($d, $availDays, true))->map(fn ($d) => ucfirst($d))->implode(', ')
                    : 'No open days set';
            @endphp
            @php $live = app(\App\Services\BookingService::class)->settings($site); @endphp
            <div id="availability" class="mt-6 scroll-mt-24 bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <span class="w-10 h-10 rounded-full flex items-center justify-center text-base shrink-0" style="background:#d9f068">🕑</span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold">Availability</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $availDaysLabel }} · {{ $availOpen }}–{{ $availClose }}@if(count($dayHours)) · {{ count($dayHours) }} custom {{ Str::plural('day', count($dayHours)) }}@endif</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" wire:click="openPanel('schedule')"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white">✎ Edit schedule</button>
                        <button type="button" wire:click="openPanel('exceptions')"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:opacity-90">📅 Day &amp; slot exceptions</button>
                    </div>
                </div>

                {{-- Scope: whole site or one service — drives both panels --}}
                <p class="bkf-label">Scope</p>
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

                {{-- At-a-glance schedule for the chosen scope --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3.5 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Open days</p>
                        <p class="text-[13px] font-semibold mt-0.5 leading-snug break-words">{{ $availServiceId !== '' && trim($asDays) !== '' ? strtoupper(str_replace(',', ' · ', $asDays)) : strtoupper(implode(' · ', $live['days'])) }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3.5 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Hours</p>
                        <p class="text-sm font-semibold mt-0.5">{{ $availServiceId !== '' && $asOpen !== '' ? $asOpen : $live['open'] }}–{{ $availServiceId !== '' && $asClose !== '' ? $asClose : $live['close'] }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3.5 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Slots</p>
                        <p class="text-sm font-semibold mt-0.5">every {{ $live['slot'] }} min</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3.5 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Booking window</p>
                        <p class="text-sm font-semibold mt-0.5">{{ $live['lead'] }}h lead · {{ $live['horizon'] }}d ahead</p>
                    </div>
                </div>

                @if($this->blockedDates->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/[0.06]">
                        <p class="bkf-label">Upcoming exceptions <span class="font-normal normal-case tracking-normal text-gray-400">— click one to edit it</span></p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($this->blockedDates as $ex)
                                <button type="button" wire:click="openException('{{ $ex['date'] }}')"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-full text-[11px] font-semibold transition-opacity hover:opacity-80
                                        {{ $ex['dayOff'] ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' : (($ex['hours'] ?? null) && ! $ex['slots'] ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400') }}">
                                    {{ \Carbon\Carbon::parse($ex['date'])->format('D, M j') }}
                                    <span class="font-normal opacity-70">{{ $ex['dayOff'] ? 'closed' : (($ex['hours'] ?? null) ? $ex['hours'] : $ex['slots'].' slot(s)') }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
    </div>
    @endif

    </div>{{-- /space-y-5 --}}

    {{-- ══════════ SIDE PANELS — every editor opens on the right over a grey overlay ══════════ --}}
    @if($panel === 'service')
    <x-lightbox close="closePanel" :drawer="true" max-width="max-w-xl" icon="⚙"
                :title="$editingId ? 'Edit service' : 'Add a service'"
                :subtitle="$editingId ? $name : 'Type, pricing and the customer booking form'"
                wire:key="panel-service-{{ $editingId ?? 'new' }}">
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
                        @endif

                        @if($kind === 'stay')
                            <div class="grid grid-cols-2 gap-3">
                                @if($this->svcFieldOn('price'))<x-field.text label="Price per night" model="price" type="number" step="0.01" min="0" :live="true" />@endif
                                <x-field.text label="Units available" model="capacity" type="number" min="1" hint="Identical rooms/houses of this type." />
                            </div>
                        @endif

                        @if($kind === 'trip')
                            @if($this->svcFieldOn('price'))
                            <x-field.text label="Default seat price" model="price" type="number" step="0.01" min="0"
                                          hint="Departures can override this per departure." />
                            @endif
                        @endif

                        {{-- Payment — part of the default settings --}}
                        @if($this->svcFieldOn('deposit'))
                        <div class="grid grid-cols-2 gap-3 items-end">
                            <div><x-field.radio label="Deposit (optional)" model="depositMode" :live="true" name="svc-dep"
                                            :options="['none' => 'None', 'fixed' => 'Fixed', 'pct' => '%']" /></div>
                            @if($depositMode !== 'none')
                                <x-field.text :label="$depositMode === 'pct' ? 'Deposit %' : 'Deposit amount'" model="depositValue" type="number" step="0.01" min="0" />
                            @endif
                        </div>
                        @endif
                        <x-field.check model="requiresPayment" text="Require payment (Stripe) to confirm"
                                       :hint="$site->stripeReady() ? null : 'Connect Stripe on the Payments page to collect money.'" />
                        <x-field.check model="autoConfirm" text="Auto-confirm bookings"
                                       hint="Successful bookings are confirmed instantly (no manual approval). Paid bookings always confirm once payment completes." />

                        {{-- Essentials end here — save is one click away. --}}
                        <div class="flex gap-2 pt-1">
                            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">{{ $editingId ? 'Update' : 'Add service' }}</button>
                            @if($editingId)
                            <button type="button" wire:click="closePanel" class="px-4 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Cancel</button>
                            @endif
                        </div>

                        <div class="olx-adv-lead">More options</div>

                        <x-panel-group label="Description" hint="shown to customers">
                            <x-field.textarea model="description" rows="2" placeholder="Short description (optional)" />
                        </x-panel-group>

                        <x-panel-group label="Booking form" hint="what customers fill in">
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
                        </x-panel-group>


                        <x-panel-group label="Capacity, buffers & custom hours" hint="parallel bookings, gaps, per-service schedule">
                            @if($kind === 'slot')
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
                                <div class="grid grid-cols-3 gap-3">
                                    @if($this->svcFieldOn('nights'))
                                    <x-field.text label="Min nights" model="minNights" type="number" min="1" />
                                    <x-field.text label="Max nights" model="maxNights" type="number" min="1" />
                                    @endif
                                    @if($this->svcFieldOn('guests'))<x-field.text label="Max guests" model="maxGuests" type="number" min="1" />@endif
                                </div>
                            @endif
                            @if($kind === 'trip')
                                <p class="text-xs text-gray-400">Seats and per-departure limits are set on each departure below.</p>
                            @endif
                        </x-panel-group>
                    </form>

                    {{-- Resources: staff (slot) / rooms (stay) / vehicles (trip),
                         each with its OWN availability. --}}
                    @if($editingId)
                        <x-panel-group :label="['slot' => 'Staff', 'stay' => 'Rooms / houses', 'trip' => 'Vehicles'][$kind]" hint="named people/units with their own schedules">
                        <div class="mt-1">
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
                        </x-panel-group>
                    @endif

                    @if($editingId && $kind === 'trip')
                        <x-panel-group label="Departures" hint="routes, dates, seats" :open="true">
                        <div class="mt-1">
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
                        </x-panel-group>
                    @endif

                    {{-- Seasonal / date-range pricing rules --}}
                    @if($editingId)
                        <x-panel-group label="Seasonal pricing" hint="date-range price overrides">
                        <div class="mt-1">
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
                        </x-panel-group>
                    @endif
    </x-lightbox>
    @endif

    @if($panel === 'resource')
    <x-lightbox close="closePanel" :drawer="true" max-width="max-w-md" icon="👥"
                :title="$srEditingId ? 'Edit resource' : 'Add a resource'"
                subtitle="Staff, rooms and vehicles shared across services" wire:key="panel-resource">
        <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <x-field.text model="srName" placeholder="Name (e.g. Bella)" />
                            <x-field.text model="srCapacity" type="number" min="1" placeholder="Capacity" hint="Parallel bookings it can hold." />
                            <x-field.text model="srPrice" type="number" step="0.01" min="0" placeholder="Price override" hint="Blank = service price." />
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button type="button" wire:click="saveSiteResource"
                                    class="px-3 py-1.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold">{{ $srEditingId ? 'Update' : '＋ Add resource' }}</button>
                            @if($srEditingId)
                                <button type="button" wire:click="closePanel" class="px-3 py-1.5 rounded-xl text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Cancel</button>
                            @endif
                        </div>
                        @error('srName')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </x-lightbox>
    @endif

    @if($panel === 'schedule')
    <x-lightbox close="closePanel" :drawer="true" max-width="max-w-xl" icon="🕑"
                :title="$availServiceId !== '' ? ($this->availService()?->name.' — schedule') : 'Site schedule'"
                :subtitle="$availServiceId !== '' ? 'Overrides for this service only' : 'Open days, hours and bookable slots for the whole site'"
                wire:key="panel-schedule">
        <p class="bkf-label">Scope</p>
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
        <div class="space-y-4">
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
                    <div class="mb-4"><x-field.check model="asAutoConfirm" text="Auto-confirm bookings"
                                   hint="On: successful bookings confirm instantly. Off: you confirm each one manually." /></div>
                    <button wire:click="saveServiceAvailability" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save service availability</button>

                    <div class="olx-adv-lead">More options</div>

                    <x-panel-group label="Weekday hours" hint="different hours on certain days">
                    {{-- Per-weekday hour overrides (scope-aware) --}}
                    <div class="mb-1">
                        <label class="bkf-label sr-only">Weekday hours</label>
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
                    </x-panel-group>
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
                    @error('availOpen')<p class="text-xs text-rose-500 mb-2">{{ $message }}</p>@enderror
                    @error('availSlot')<p class="text-xs text-rose-500 mb-2">{{ $message }}</p>@enderror

                    <button wire:click="saveAvailability" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save availability</button>

                    @php $preview = app(\App\Services\BookingService::class)->settings($site); @endphp
                    <p class="text-[11px] text-gray-400 mt-4">
                        Currently live: {{ strtoupper(implode(' · ', $preview['days'])) }} — {{ $preview['open'] }}–{{ $preview['close'] }},
                        every {{ $preview['slot'] }} min, {{ $preview['lead'] }}h lead, {{ $preview['horizon'] }} days ahead.
                    </p>

                    <div class="olx-adv-lead">More options</div>

                    <x-panel-group label="Weekday hours" hint="different hours on certain days">
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
                    </x-panel-group>

                    <x-panel-group label="Slots & booking window" hint="slot length, lead time, horizon">
                        <div class="grid grid-cols-3 gap-3">
                            <x-field.text label="Slot length (min)" model="availSlot" type="number" min="5" step="5" hint="Times offered every N minutes." />
                            <x-field.text label="Lead time (hours)" model="availLead" type="number" min="0" hint="Earliest a customer can book." />
                            <x-field.text label="Horizon (days)" model="availHorizon" type="number" min="1" hint="How far ahead bookings open." />
                        </div>
                        <p class="text-[10px] text-gray-400">Remember to press “Save availability” above after changing these.</p>
                    </x-panel-group>
                </div>
                @endif
        </div>
    </x-lightbox>
    @endif

    @if($panel === 'exceptions')
    <x-lightbox close="closePanel" :drawer="true" max-width="max-w-2xl" icon="📅"
                title="Day & slot exceptions"
                :subtitle="$availServiceId !== '' ? ($this->availService()?->name.' only — site-wide blocks still apply') : 'Close whole days or single slots on specific dates'"
                wire:key="panel-exceptions">
        <p class="bkf-label">Scope</p>
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
    </x-lightbox>
    @endif

    {{-- ══════════ BOOKING DETAIL — dashboard-card lightbox ══════════ --}}
    @if($this->viewedBooking)
        @php
            $vb = $this->viewedBooking;
            $vAccent = $site->theme["accent"] ?? "#6366f1";
        @endphp
        {{-- right-side drawer: grey overlay over the page, card slides in from the right --}}
        <div class="fixed inset-0 z-50 flex justify-end" wire:key="booking-detail"
             x-data @keydown.escape.window="$wire.closeBooking()">
            <div class="lightbox-backdrop absolute inset-0 bg-gray-900/40" wire:click="closeBooking"></div>

            <div class="lightbox-drawer relative h-full w-full max-w-lg bg-gray-50 dark:bg-[#16171f] border-l border-gray-100 dark:border-white/[0.06] shadow-2xl flex flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-5 py-3.5 bg-white dark:bg-[#1d1e2a] border-b border-gray-100 dark:border-white/[0.06] shrink-0">
                    <div class="min-w-0">
                        <p class="text-[15px] font-extrabold text-gray-900 dark:text-white truncate">Booking details</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">Booked {{ $vb->created_at->format('j M Y · g:i A') }}</p>
                    </div>
                    <button type="button" wire:click="closeBooking" title="Close (Esc)" aria-label="Close"
                            class="w-9 h-9 rounded-full grid place-items-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1] transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5 overflow-y-auto grow">
                    <x-booking-card :booking="$vb" :accent="$vAccent" />
                </div>

                {{-- Pinned actions: the next step first, destructive ones last --}}
                @php $btn = 'inline-flex items-center justify-center gap-2 min-h-[44px] px-4 rounded-xl text-[13.5px] font-bold transition-colors'; @endphp
                <div class="shrink-0 px-5 py-4 bg-white dark:bg-[#1d1e2a] border-t border-gray-100 dark:border-white/[0.06] space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        @if(! in_array($vb->status, ["confirmed", "awaiting_payment", "cancelled", "no_show"], true))
                            <button type="button" wire:click="setStatus('{{ $vb->id }}', 'confirmed')"
                                    class="{{ $btn }} text-white shadow-sm hover:opacity-90" style="background:{{ $vAccent }}">✓ Confirm booking</button>
                        @endif
                        @if($vb->balanceCents() > 0 && $vb->status !== "cancelled")
                            <button type="button" wire:click="markFullyPaid" data-confirm="Record the {{ $vb->formattedBalance() }} balance as collected?"
                                    class="{{ $btn }} {{ in_array($vb->status, ['confirmed', 'awaiting_payment'], true) ? 'text-white shadow-sm hover:opacity-90' : 'bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}"
                                    @if(in_array($vb->status, ['confirmed', 'awaiting_payment'], true)) style="background:{{ $vAccent }}" @endif>Record {{ $vb->formattedBalance() }} paid</button>
                        @endif
                        <a href="mailto:{{ $vb->customer_email }}?subject={{ rawurlencode('Your booking '.$vb->reference) }}"
                           class="{{ $btn }} bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-white/[0.06]">✉ Email customer</a>
                        @if($vb->customer_phone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $vb->customer_phone) }}"
                               class="{{ $btn }} bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-white/[0.06]">☎ Call</a>
                        @endif
                    </div>
                    @if($vb->status !== "cancelled" || ($vb->starts_at?->isPast() && in_array($vb->status, ["confirmed", "pending"], true)))
                        <div class="flex items-center justify-center gap-4 pt-1 text-[12.5px] font-semibold">
                            @if($vb->starts_at?->isPast() && in_array($vb->status, ["confirmed", "pending"], true))
                                <button type="button" wire:click="setStatus('{{ $vb->id }}', 'no_show')" data-confirm="Mark this booking as a no-show? The customer is NOT emailed."
                                        class="text-orange-600 dark:text-orange-400 hover:underline">Mark no-show</button>
                            @endif
                            @if($vb->status !== "cancelled")
                                <button type="button" wire:click="setStatus('{{ $vb->id }}', 'cancelled')" data-confirm="Cancel this booking? The customer is emailed about the cancellation."
                                        class="text-rose-600 dark:text-rose-400 hover:underline">Cancel booking</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>{{-- /drawer --}}
        </div>
    @endif

    {{-- ══ RIGHT rail: summary · needs attention · calendar · setup · related ══ --}}
    <x-slot:quick>
        {{-- Summary: status breakdown + this week's money --}}
        <div class="{{ $panelCls }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Bookings summary</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $s['total'] }}</b> {{ Str::plural('booking', $s['total']) }} ·
                <b class="text-gray-900 dark:text-white">{{ $s['upcoming'] }}</b> upcoming
            </p>
            @if ($s['total'])
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach ($statusMeta as $st => [$label, , , $color])
                        @if ($s[$st] ?? 0)
                            <span style="width:{{ round($s[$st] / $s['total'] * 100, 2) }}%;background:{{ $color }}" title="{{ $label }} · {{ $s[$st] }}"></span>
                        @endif
                    @endforeach
                </div>
                <div class="mt-3 space-y-1">
                    @foreach ($statusMeta as $st => [$label, , , $color])
                        @if ($s[$st] ?? 0)
                            @php $fk = $st === 'awaiting_payment' ? null : $st; @endphp
                            <button type="button" @if($fk) wire:click="openTile('{{ $fk }}')" @endif
                                    class="w-full flex items-center gap-2 text-[12.5px] rounded-lg px-1 py-0.5 {{ $fk ? 'hover:bg-gray-50 dark:hover:bg-white/[0.04]' : 'cursor-default' }}">
                                <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $color }}"></span>
                                <span class="text-gray-600 dark:text-gray-300">{{ $label }}</span>
                                <span class="ml-auto font-bold text-gray-900 dark:text-white tabular-nums">{{ $s[$st] }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-white/[0.06]">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">This week</p>
                <div class="mt-1.5 grid grid-cols-2 gap-2">
                    <div>
                        <p class="text-[17px] font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $money($s['week_value']) }}</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">booked · {{ $s['week_count'] }} {{ Str::plural('booking', $s['week_count']) }}</p>
                    </div>
                    <div>
                        <p class="text-[17px] font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $money($s['week_paid']) }}</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">collected</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Needs attention --}}
        <div class="{{ $panelCls }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            @if ($att['pending']->isEmpty() && ! $s['unpaid'] && ! $att['next'])
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400">All caught up — nothing pending, no unpaid balances, nothing else today.</p>
            @endif
            <div class="space-y-2.5">
                @if ($att['next'])
                    @php $nx = $att['next']; @endphp
                    <button type="button" wire:click="viewBooking('{{ $nx->id }}')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-indigo-50 dark:bg-indigo-500/10 hover:ring-2 hover:ring-indigo-200 dark:hover:ring-indigo-500/30">
                        <p class="text-[13px] font-bold text-indigo-900 dark:text-indigo-200">Next today · {{ $nx->starts_at->format('g:i A') }}</p>
                        <p class="text-[12px] text-indigo-800/80 dark:text-indigo-200/70 truncate">{{ $nx->customer_name }} — {{ $nx->service?->name }}{{ $nx->resource ? ' with '.$nx->resource->name : '' }} →</p>
                    </button>
                @endif
                @if ($att['pending']->isNotEmpty())
                    <div class="rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $s['pending'] }} to confirm</p>
                        <div class="mt-1.5 space-y-1">
                            @foreach ($att['pending'] as $pb)
                                <button type="button" wire:click="viewBooking('{{ $pb->id }}')" class="w-full text-left flex items-center gap-2 text-[12px] text-rose-800/90 dark:text-rose-200/80 hover:underline">
                                    <span class="font-semibold tabular-nums shrink-0">{{ $pb->starts_at?->format('M j') }}</span>
                                    <span class="truncate">{{ $pb->customer_name }} · {{ $pb->service?->name }}</span>
                                </button>
                            @endforeach
                        </div>
                        @if ($s['pending'] > 3)
                            <button type="button" wire:click="openTile('pending')" class="mt-1.5 text-[12px] font-bold text-rose-800 dark:text-rose-200 hover:underline">Show all {{ $s['pending'] }} →</button>
                        @endif
                    </div>
                @endif
                @if ($s['unpaid'])
                    <button type="button" wire:click="openTile('unpaid')"
                            class="w-full text-left rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 hover:ring-2 hover:ring-amber-200 dark:hover:ring-amber-500/30">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $money($s['unpaid_cents']) }} unpaid</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">on {{ $s['unpaid'] }} past {{ Str::plural('booking', $s['unpaid']) }} — record what was collected →</p>
                    </button>
                @endif
            </div>
        </div>

        {{-- Mini calendar (the Calendar tab shows the full month) --}}
        @if ($tab !== 'calendar')
            @php
                $accent = $site->theme['accent'] ?? '#4f46e5';
                $shade = function (string $hex, float $dSat, float $dLig): string {
                    [$r, $g, $b] = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
                    $max = max($r, $g, $b); $min = min($r, $g, $b); $l = ($max + $min) / 2; $d = $max - $min;
                    $sat = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));
                    $h = $d == 0 ? 0 : ($max === $r ? fmod(($g - $b) / $d, 6) : ($max === $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4)) * 60;
                    if ($h < 0) $h += 360;
                    $sat = max(0, min(1, $sat + $dSat)); $l = max(0, min(1, $l + $dLig));
                    $c = (1 - abs(2 * $l - 1)) * $sat; $x = $c * (1 - abs(fmod($h / 60, 2) - 1)); $m = $l - $c / 2;
                    [$r, $g, $b] = match (true) {
                        $h < 60 => [$c, $x, 0], $h < 120 => [$x, $c, 0], $h < 180 => [0, $c, $x],
                        $h < 240 => [0, $x, $c], $h < 300 => [$x, 0, $c], default => [$c, 0, $x],
                    };
                    return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
                };
                $ok = strlen($accent) === 7;
                $deep = $ok ? $shade($accent, +0.10, -0.10) : $accent;
                $vivid = $ok ? $shade($accent, +0.18, +0.03) : $accent;
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
                    <button type="button" wire:click="calShift(-1)" class="w-7 h-7 rounded-lg bg-white/15 hover:bg-white/25 text-white text-sm transition-colors" aria-label="Previous month">‹</button>
                    <h2 class="text-sm font-bold">{{ \Carbon\Carbon::parse($calMonth.'-01')->format('F Y') }}</h2>
                    <button type="button" wire:click="calShift(1)" class="w-7 h-7 rounded-lg bg-white/15 hover:bg-white/25 text-white text-sm transition-colors" aria-label="Next month">›</button>
                </div>
                <div class="grid grid-cols-7 gap-1 mb-1">
                    @foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $dow)
                        <span class="text-center text-[10px] font-bold text-white/50">{{ $dow }}</span>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($this->calendarDays as $cell)
                        <button type="button" wire:click="openDay('{{ $cell['date'] }}')" title="Open {{ $cell['date'] }} in the calendar"
                                class="relative aspect-square rounded-lg text-xs transition-colors
                                    {{ ! $cell['inMonth'] ? 'opacity-40' : '' }}
                                    {{ $calDate === $cell['date'] ? 'bg-white bkcal-sel font-bold shadow-md' : ($cell['count'] > 0 ? 'bkcal-booked text-white font-bold ring-1 ring-white/60 shadow-md' : ($cell['isToday'] ? 'ring-1 ring-white/70 text-white font-bold hover:bg-white/15' : 'text-white/85 hover:bg-white/15')) }}">
                            {{ $cell['day'] }}
                            @if ($cell['count'] > 0)
                                <span class="absolute -top-1 -right-1 min-w-[15px] h-[15px] px-0.5 rounded-full text-[8px] font-bold leading-[15px] bg-white shadow bkcal-badge">{{ $cell['count'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Setup at a glance: services, staff, hours --}}
        @php $liveHours = app(\App\Services\BookingService::class)->settings($site); @endphp
        <div class="{{ $panelCls }} p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Services &amp; hours</p>
                <button type="button" wire:click="setTab('services')" class="text-[12px] font-bold hover:underline" style="color:var(--primary)">Manage →</button>
            </div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $this->services->where('is_active', true)->count() }} active {{ Str::plural('service', $this->services->where('is_active', true)->count()) }}</p>
            <div class="flex flex-wrap gap-1 mb-3">
                @forelse ($this->services->take(8) as $svc)
                    <button type="button" wire:click="editService('{{ $svc->id }}')"
                            class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $svc->is_active ? 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-200' : 'bg-gray-50 text-gray-400 line-through dark:bg-white/[0.03]' }} hover:ring-1 hover:ring-gray-300">{{ $svc->name }}</button>
                @empty
                    <span class="text-[12px] text-gray-400">No services yet.</span>
                @endforelse
                @if ($this->services->count() > 8)<span class="text-[11.5px] text-gray-400 px-1">+{{ $this->services->count() - 8 }} more</span>@endif
            </div>
            @if ($this->siteResources->isNotEmpty())
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Staff &amp; resources</p>
                <div class="flex flex-wrap gap-1 mb-3">
                    @foreach ($this->siteResources->take(8) as $r)
                        <button type="button" wire:click="editSiteResource('{{ $r->id }}')"
                                class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $r->is_active ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300' : 'bg-gray-50 text-gray-400 line-through dark:bg-white/[0.03]' }}">{{ $r->name }}</button>
                    @endforeach
                </div>
            @endif
            <p class="text-[12px] text-gray-500 dark:text-gray-400 mb-3">
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ strtoupper(implode(' · ', $liveHours['days'])) ?: 'No open days' }}</span><br>
                {{ $liveHours['open'] }}–{{ $liveHours['close'] }} · every {{ $liveHours['slot'] }} min
            </p>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="newService" class="{{ $btnSolid }} text-[12px] px-2.5 py-2">＋ Add service</button>
                <button type="button" wire:click="openPanel('schedule')" class="{{ $btnSolid }} text-[12px] px-2.5 py-2">✎ Edit schedule</button>
            </div>
        </div>

        {{-- Related --}}
        <div class="{{ $panelCls }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach (array_filter([
                    ['Contacts', 'Your customers', route('site.contacts', $site->name), false],
                    ['Payments', 'Deposits & payouts', route('site.payments', $site->name), false],
                    $site->hasFeature('invoices') ? ['Invoices', 'Bill a customer', route('site.invoices', $site->name), false] : null,
                    ['Booking page', 'What customers see ↗', $bookPage, true],
                ]) as [$label, $hint, $href, $external])
                    <a href="{{ $href }}" @if($external) target="_blank" rel="noopener" @else wire:navigate @endif
                       class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} {{ $external ? '' : '→' }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
