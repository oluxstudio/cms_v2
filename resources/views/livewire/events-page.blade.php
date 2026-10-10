@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $btnPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-bold shadow-sm';
    $label = 'block text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-1.5';
    $statusPill = fn ($s) => match ($s) {
        'published' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'cancelled' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300',
        default => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300',
    };
    $orderPill = fn ($s) => match ($s) {
        'paid' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'pending' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300',
        'refunded' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300',
        default => 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400',
    };
    $filters = [
        'upcoming' => ['Upcoming', $filterCounts['upcoming']],
        'past' => ['Past', $filterCounts['past']],
        'drafts' => ['Drafts', $filterCounts['drafts']],
        'cancelled' => ['Cancelled', $filterCounts['cancelled']],
    ];
    $soldLabel = fn ($e) => $e->sold.($e->capacity ? ' / '.$e->capacity : '').' '.($e->capacity ? 'sold' : Str::plural('ticket', $e->sold));
    $barColor = fn ($pct) => $pct === null ? 'var(--primary)' : ($pct >= 100 ? '#e11d48' : ($pct >= 80 ? '#f59e0b' : '#10b981'));
    $icon = [
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'ticket' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
        'money' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'check' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'fire' => 'M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z',
        'refund' => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
        'pin' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
    ];
@endphp
<x-tri-layout title="Events" subtitle="Events with free RSVPs or paid tickets — attendees, door check-in and ticket emails." :site-name="$site->name"
    :labels="['📊 Overview', '🎟 Events', '⚡ Summary']">

    {{-- ── LEFT rail: the numbers ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$stats['upcoming']" label="Upcoming events"
                :sub="$stats['nextEvent'] ? 'next '.$stats['nextEvent']->localStart()->format('j M') : 'none scheduled'" :icon="$icon['calendar']" />
        <x-tile accent="lime" :value="number_format($stats['soldMonth'])" label="Tickets this month" sub="sold & RSVPs" :icon="$icon['ticket']" />
        <x-tile accent="lavender" :value="$money($stats['revenueMonth'])" label="Revenue this month" :sub="strtoupper($currency)" :icon="$icon['money']" />
        <x-tile accent="sky" :value="$stats['checkinsToday']" label="Check-ins today" :sub="$stats['today']->count() ? $stats['today']->count().' on today' : 'no events today'" :icon="$icon['check']" />
        <x-tile accent="{{ $stats['nearCapacity']->count() ? 'rose' : 'cocoa' }}" :value="$stats['nearCapacity']->count()" label="Near capacity" sub="80%+ of places taken" :icon="$icon['fire']" />
        <x-tile accent="cocoa" :value="$stats['refundsMonth'].' / '.$stats['pending']" label="Refunds / pending" :sub="$stats['pending'] ? $stats['pending'].' awaiting payment' : 'refunded this month'" :icon="$icon['refund']" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    @if ($stats['paymentsMissing'])
        <div class="rounded-2xl px-4 py-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 flex flex-wrap items-center gap-3">
            <p class="flex-1 min-w-[14rem] text-[13px] text-amber-800 dark:text-amber-200"><b>Paid tickets can’t be sold yet.</b> An event has a paid ticket type but this site isn’t accepting payments — visitors get a clear “not available” message. Free RSVPs still work.</p>
            <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Connect payments →</a>
        </div>
    @endif

    {{-- ══ Event form (new / edit) ══ --}}
    @if ($editing)
        <x-page-panel close="cancelEdit" :back-label="$eventId ? 'Back to the event' : 'Back to events'"
            :title="$eventId ? 'Edit event' : 'New event'" :subtitle="$eventId ? ($form['title'] ?? '') : 'Free RSVP by default — add paid ticket types after saving'">
            <form wire:submit="saveEvent" class="space-y-4">
                <div>
                    <x-field.text label="Title" model="form.title" placeholder="e.g. Summer open evening" />
                    @error('form.title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <x-field.text label="Starts" type="datetime-local" model="form.starts_at" />
                        @error('form.starts_at') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-field.text label="Ends (optional)" type="datetime-local" model="form.ends_at" />
                        @error('form.ends_at') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $label }}">Timezone</label>
                        <select wire:model="form.timezone" class="bkf-input">
                            @foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach
                        </select>
                        @error('form.timezone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-field.select label="Status" model="form.status" :empty="null" :options="['draft' => 'Draft — hidden', 'published' => 'Published — on sale', 'cancelled' => 'Cancelled']" />
                    </div>
                </div>
                <div>
                    <x-field.text label="Summary" model="form.summary" placeholder="One line for cards and listings" />
                    @error('form.summary') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Image</label>
                    <x-asset-picker model="form.image" :site="$site" type="image" placeholder="Image URL, or pick from assets" />
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <x-field.text label="Venue name" model="form.venue_name" placeholder="e.g. The Old Hall" />
                    <x-field.text label="Venue address" model="form.venue_address" placeholder="Street, town, postcode" />
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <x-field.text label="Online link (optional)" type="url" model="form.online_url" placeholder="https://zoom.us/…" />
                        @error('form.online_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-field.text label="Capacity (blank = unlimited)" type="number" model="form.capacity" placeholder="e.g. 120" />
                        @error('form.capacity') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="olx-adv-lead">More options</div>
                <x-panel-group label="Description & web address" hint="long description, URL slug">
                    <div>
                        <x-field.textarea label="Description" model="form.description" rows="6" placeholder="What to expect, schedule, what to bring… (basic HTML allowed)" />
                        @error('form.description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-field.text label="Slug" model="form.slug" placeholder="made from the title" mono />
                    </div>
                </x-panel-group>
                <div class="flex gap-3 pt-1">
                    <button type="button" wire:click="cancelEdit" class="{{ $btnSolid }} flex-1 px-4 py-2.5 text-sm">Cancel</button>
                    <button type="submit" class="{{ $btnPrimary }} flex-1 px-4 py-2.5 text-sm" style="background:var(--primary);color:var(--on-primary)">{{ $eventId ? 'Save changes' : 'Create event' }}</button>
                </div>
            </form>
        </x-page-panel>

    {{-- ══ One event's panel ══ --}}
    @elseif ($open)
        @php
            $taken = $open->sold + $open->held;
            $paidTypes = $types->where('price_cents', '>', 0)->count();
        @endphp
        <x-page-panel close="closeEvent" back-label="Back to events">
            <div class="-mx-6 -my-5">
                <div class="flex flex-col sm:flex-row gap-4 p-5 border-b border-gray-100 dark:border-white/[0.05]">
                    @if ($open->imageUrl())
                        <img src="{{ $open->imageUrl() }}" alt="" class="w-full sm:w-40 h-32 sm:h-28 rounded-2xl object-cover shrink-0">
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $open->title }}</h2>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $statusPill($open->status) }}">{{ ucfirst($open->status) }}</span>
                            @if ($open->status === 'published' && ! $open->upcoming)<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-500">Past</span>@endif
                        </div>
                        <p class="text-[13px] text-gray-500 dark:text-gray-400 mt-1">{{ $open->whenLabel() }}</p>
                        @if ($open->venueLabel())<p class="text-[13px] text-gray-500 dark:text-gray-400">📍 {{ $open->venueLabel() }}</p>@endif
                        @if ($open->online_url)<p class="text-[13px] text-gray-500 dark:text-gray-400 truncate">🔗 {{ $open->online_url }}</p>@endif
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-[12px] text-gray-500 dark:text-gray-400">
                                <span><b class="text-gray-900 dark:text-white">{{ $open->sold }}</b>{{ $open->capacity ? ' / '.$open->capacity : '' }} {{ $open->capacity ? 'places taken' : Str::plural('ticket', $open->sold).' issued' }}@if ($open->held) · {{ $open->held }} held at checkout @endif</span>
                                <span>{{ $open->checked_in }} checked in · {{ $money($open->revenue_cents) }}</span>
                            </div>
                            @if ($open->capacity)
                                <span class="block mt-1.5 h-2 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                                    <span class="block h-full rounded-full" style="width:{{ $open->fill_pct }}%;background:{{ $barColor($open->fill_pct) }}"></span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($canManage)
                <div class="flex flex-wrap items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-white/[0.05]">
                    <button type="button" wire:click="editEvent('{{ $open->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">✎ Edit details</button>
                    @if ($open->status === 'draft')
                        <button type="button" wire:click="setStatus('{{ $open->id }}', 'published')" class="{{ $btnPrimary }} text-[12px] px-3 py-1.5" style="background:var(--primary);color:var(--on-primary)">Publish</button>
                    @elseif ($open->status === 'published')
                        <button type="button" wire:click="setStatus('{{ $open->id }}', 'draft')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Unpublish</button>
                    @endif
                    <button type="button" wire:click="$set('composing', true)" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">✉ Email attendees</button>
                    <button type="button" wire:click="exportCsv" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">⬇ Export CSV</button>
                    <span class="ml-auto flex gap-2">
                        @if ($open->status !== 'cancelled')
                            <button type="button" wire:click="cancelEvent('{{ $open->id }}')"
                                    data-confirm="Cancel “{{ $open->title }}”? Sales close and every ticket holder is emailed. Refund paid orders from the Orders tab."
                                    class="{{ $btnSolid }} text-[12px] px-3 py-1.5 !text-rose-600">Cancel event</button>
                        @endif
                        <button type="button" wire:click="deleteEvent('{{ $open->id }}')" data-confirm="Delete “{{ $open->title }}” with its tickets and orders? This can’t be undone."
                                class="{{ $btnSolid }} text-[12px] px-3 py-1.5 !text-rose-600" title="Delete">🗑</button>
                    </span>
                </div>
                @endif

                @if ($composing && $canManage)
                    <form wire:submit="sendMessage" class="p-5 space-y-3 border-b border-gray-100 dark:border-white/[0.05] bg-gray-50/60 dark:bg-white/[0.02]">
                        <p class="text-[13px] font-bold text-gray-900 dark:text-white">Email everyone with a ticket</p>
                        <x-field.text model="mailSubject" placeholder="Subject" />
                        @error('mailSubject') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        <x-field.textarea model="mailBody" rows="4" placeholder="Your message — replies come back to your site email." />
                        @error('mailBody') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        <div class="flex gap-2 justify-end">
                            <button type="button" wire:click="$set('composing', false)" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Cancel</button>
                            <button type="submit" class="{{ $btnPrimary }} text-[12px] px-3 py-1.5" style="background:var(--primary);color:var(--on-primary)">Send</button>
                        </div>
                    </form>
                @endif

                <div class="p-5" x-data="{ tab: $wire.entangle('tab') }">
                    <x-pill-tabs :tabs="['attendees' => 'Attendees · '.$open->sold, 'tickets' => 'Ticket types · '.$types->count(), 'orders' => 'Orders · '.$orders->count(), 'details' => 'Details']"
                                 :dots="$paidTypes && ! $paymentsReady ? ['tickets'] : []" />

                    {{-- Attendees + check-in --}}
                    <section x-show="tab === 'attendees'" class="space-y-3">
                        @if ($canManage)
                        <form wire:submit="checkInByCode" class="flex gap-2">
                            <input wire:model="checkinCode" type="text" autocomplete="off" placeholder="Door check-in: type or scan a ticket code…"
                                   class="bkf-input flex-1 font-mono uppercase tracking-wider">
                            <button type="submit" class="{{ $btnPrimary }} text-sm px-4" style="background:var(--primary);color:var(--on-primary)">Check in</button>
                        </form>
                        @endif
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <x-field.text wire:model.live.debounce.250ms="attendeeSearch" placeholder="Search attendees by name, email or code…" class="w-full" style="padding-left:2.25rem" />
                        </div>
                        @if ($attendees->isEmpty())
                            <p class="py-8 text-center text-sm text-gray-400">{{ $attendeeSearch !== '' ? 'No attendee matches “'.$attendeeSearch.'”.' : 'No tickets issued yet — share the event on your site.' }}</p>
                        @else
                            <div class="overflow-x-auto rounded-2xl border border-gray-100 dark:border-white/[0.06]">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            <th class="px-3 py-2">Attendee</th><th class="px-3 py-2">Ticket</th><th class="px-3 py-2">Code</th><th class="px-3 py-2 text-right">Check-in</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                                        @foreach ($attendees as $t)
                                            <tr wire:key="att-{{ $t->id }}" class="{{ $t->checked_in_at ? 'bg-emerald-50/50 dark:bg-emerald-500/[0.04]' : '' }}">
                                                <td class="px-3 py-2">
                                                    <span class="block font-semibold text-gray-900 dark:text-white">{{ $t->attendee_name }}</span>
                                                    <span class="block text-[11px] text-gray-400">{{ $t->order->buyer_email }} · {{ $t->order->reference }}</span>
                                                </td>
                                                <td class="px-3 py-2 text-[12px] text-gray-600 dark:text-gray-300">{{ $t->ticketType?->name }}</td>
                                                <td class="px-3 py-2 font-mono text-[12px] tracking-wider text-gray-700 dark:text-gray-200">{{ $t->code }}</td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                                    @if ($canManage)
                                                        <button type="button" wire:click="toggleCheckIn('{{ $t->id }}')"
                                                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold border {{ $t->checked_in_at ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.1]' }}">
                                                            {{ $t->checked_in_at ? '✓ '.$t->checked_in_at->setTimezone($open->safeTimezone())->format('g:i a') : 'Check in' }}
                                                        </button>
                                                    @else
                                                        <span class="text-[11px] {{ $t->checked_in_at ? 'text-emerald-600' : 'text-gray-400' }}">{{ $t->checked_in_at ? '✓ in' : '—' }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    {{-- Ticket types --}}
                    <section x-show="tab === 'tickets'" x-cloak class="space-y-3">
                        @if ($paidTypes && ! $paymentsReady)
                            <div class="rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 text-[12.5px] text-amber-800 dark:text-amber-200">
                                Paid ticket types won’t sell until payments are connected. <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="font-bold underline">Connect payments →</a>
                            </div>
                        @endif
                        @foreach ($types as $t)
                            @php $a = $avail['types'][$t->id] ?? null; @endphp
                            <div wire:key="type-{{ $t->id }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 dark:border-white/[0.06] px-4 py-3">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold text-gray-900 dark:text-white">{{ $t->name }} <span class="font-semibold text-gray-500">· {{ \App\Support\Money::format((int) $t->price_cents, $currency, free: true) }}</span></span>
                                    <span class="block text-[12px] text-gray-400">
                                        {{ $a['taken'] ?? 0 }}{{ $t->quantity ? ' / '.$t->quantity : '' }} taken · max {{ $t->max_per_order }} per order
                                        @if ($t->sales_start || $t->sales_end) · on sale {{ $t->sales_start?->setTimezone($open->safeTimezone())->format('j M') ?? 'now' }} – {{ $t->sales_end?->setTimezone($open->safeTimezone())->format('j M g:i a') ?? 'event' }} @endif
                                        @if ($a && ! $a['on_sale']) · <b class="text-amber-600">not on sale</b> @endif
                                        @if ($a && $a['remaining'] === 0) · <b class="text-rose-600">sold out</b> @endif
                                    </span>
                                    @if ($t->description)<span class="block text-[12px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $t->description }}</span>@endif
                                </span>
                                @if ($canManage)
                                    <button type="button" wire:click="editType('{{ $t->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Edit</button>
                                    <button type="button" wire:click="deleteType('{{ $t->id }}')" data-confirm="Delete the “{{ $t->name }}” ticket type?" class="{{ $btnSolid }} text-[12px] px-2.5 py-1.5 !text-rose-600" title="Delete">✕</button>
                                @endif
                            </div>
                        @endforeach
                        @if ($types->isEmpty())<p class="py-6 text-center text-sm text-gray-400">No ticket types — add one so people can RSVP or buy.</p>@endif

                        @if ($typeId !== null && $canManage)
                            <form wire:submit="saveType" class="rounded-2xl border border-gray-200 dark:border-white/[0.1] p-4 space-y-3 bg-gray-50/60 dark:bg-white/[0.02]">
                                <p class="text-[13px] font-bold text-gray-900 dark:text-white">{{ $typeId ? 'Edit ticket type' : 'New ticket type' }}</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <x-field.text label="Name" model="typeForm.name" placeholder="e.g. Early bird" />
                                        @error('typeForm.name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-field.text label="Price ({{ strtoupper($currency) }}, 0 = free RSVP)" type="number" step="0.01" model="typeForm.price" />
                                        @error('typeForm.price') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-field.text label="Quantity (blank = unlimited)" type="number" model="typeForm.quantity" />
                                        @error('typeForm.quantity') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-field.text label="Max per order" type="number" model="typeForm.max_per_order" />
                                        @error('typeForm.max_per_order') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <x-field.text label="Sales start (optional)" type="datetime-local" model="typeForm.sales_start" />
                                    <x-field.text label="Sales end (optional)" type="datetime-local" model="typeForm.sales_end" />
                                </div>
                                <x-field.text label="Description (optional)" model="typeForm.description" placeholder="What this ticket includes" />
                                <div class="flex gap-2 justify-end">
                                    <button type="button" wire:click="cancelType" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Cancel</button>
                                    <button type="submit" class="{{ $btnPrimary }} text-[12px] px-3 py-1.5" style="background:var(--primary);color:var(--on-primary)">Save ticket type</button>
                                </div>
                            </form>
                        @elseif ($canManage)
                            <button type="button" wire:click="newType" class="{{ $btnSolid }} text-[13px] px-4 py-2">＋ Add ticket type</button>
                        @endif
                    </section>

                    {{-- Orders + refunds --}}
                    <section x-show="tab === 'orders'" x-cloak>
                        @if ($orders->isEmpty())
                            <p class="py-8 text-center text-sm text-gray-400">No orders yet.</p>
                        @else
                            <div class="overflow-x-auto rounded-2xl border border-gray-100 dark:border-white/[0.06]">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            <th class="px-3 py-2">Order</th><th class="px-3 py-2">Buyer</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                                        @foreach ($orders as $o)
                                            <tr wire:key="ord-{{ $o->id }}">
                                                <td class="px-3 py-2">
                                                    <span class="block font-mono text-[12px] text-gray-900 dark:text-white">{{ $o->reference }}</span>
                                                    <span class="block text-[11px] text-gray-400">{{ $o->quantity }} {{ Str::plural('ticket', $o->quantity) }} · {{ $o->created_at->diffForHumans() }}</span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <span class="block text-gray-800 dark:text-gray-100">{{ $o->buyer_name }}</span>
                                                    <span class="block text-[11px] text-gray-400">{{ $o->buyer_email }}</span>
                                                </td>
                                                <td class="px-3 py-2 text-right font-bold tabular-nums text-gray-900 dark:text-white">{{ $o->formattedTotal() }}</td>
                                                <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $orderPill($o->status) }}">{{ ucfirst($o->status) }}</span></td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                                    @if ($canManage && $o->status === 'paid')
                                                        <button type="button" wire:click="resendTickets('{{ $o->id }}')" class="{{ $btnSolid }} text-[11px] px-2.5 py-1">Resend</button>
                                                        <button type="button" wire:click="refundOrder('{{ $o->id }}')"
                                                                data-confirm="{{ $o->isFree() ? 'Cancel RSVP '.$o->reference.'? Its places are released.' : 'Refund '.$o->formattedTotal().' for order '.$o->reference.' through Stripe? Its tickets stop working.' }}"
                                                                class="{{ $btnSolid }} text-[11px] px-2.5 py-1 !text-rose-600">{{ $o->isFree() ? 'Cancel' : 'Refund' }}</button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    {{-- Details --}}
                    <section x-show="tab === 'details'" x-cloak class="space-y-3 text-[13px] text-gray-600 dark:text-gray-300">
                        @if ($open->summary)<p class="font-semibold text-gray-800 dark:text-gray-100">{{ $open->summary }}</p>@endif
                        @if ($open->description)
                            <div class="prose prose-sm dark:prose-invert max-w-none">{!! $open->description !!}</div>
                        @else
                            <p class="text-gray-400">No description yet.</p>
                        @endif
                        <dl class="grid grid-cols-2 gap-3 pt-2">
                            <div><dt class="{{ $label }}">Web address</dt><dd class="font-mono text-[12px]">{{ $open->slug }}</dd></div>
                            <div><dt class="{{ $label }}">Capacity</dt><dd>{{ $open->capacity ?? 'Unlimited' }}</dd></div>
                            <div class="col-span-2"><dt class="{{ $label }}">API</dt><dd class="font-mono text-[11px] break-all">{{ url('api/sites/'.$site->name.'/events/'.$open->slug) }}</dd></div>
                        </dl>
                        @if ($canManage)<button type="button" wire:click="editEvent('{{ $open->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">✎ Edit details</button>@endif
                    </section>
                </div>
            </div>
        </x-page-panel>

    {{-- ══ The list ══ --}}
    @else
        <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[12rem]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <x-field.text wire:model.live.debounce.250ms="search" placeholder="Search events by title or venue…" class="w-full" style="padding-left:2.25rem" />
                </div>
                <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                    <option value="date">Date</option>
                    <option value="sales">Most tickets</option>
                    <option value="title">Title A–Z</option>
                </select>
                <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
                @if ($canManage)
                    <button type="button" wire:click="newEvent" class="{{ $btnPrimary }} text-sm px-4 py-2.5" style="background:var(--primary);color:var(--on-primary)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        New event
                    </button>
                @endif
            </div>
            <div class="flex gap-2 overflow-x-auto no-scrollbar">
                @foreach ($filters as $key => [$fLabel, $n])
                    <button type="button" wire:click="setFilter('{{ $key }}')"
                        class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                            {{ $filter === $key
                                ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                                : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                        {{ $fLabel }} <span class="opacity-60">{{ $n }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($events->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                    <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon['calendar'] }}"/></svg>
                </span>
                @if ($all->isEmpty())
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">No events yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Create an event, take free RSVPs or sell tickets, and check people in at the door.</p>
                    @if ($canManage)<button type="button" wire:click="newEvent" class="{{ $btnPrimary }} mt-4 text-sm px-4 py-2.5" style="background:var(--primary);color:var(--on-primary)">Create the first event</button>@endif
                @else
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing here</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">No {{ strtolower($filters[$filter][0]) }} events{{ $search !== '' ? ' match “'.$search.'”' : '' }}.</p>
                @endif
            </div>
        @elseif ($viewMode === 'grid')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($events as $e)
                    @php $start = $e->localStart(); @endphp
                    <div wire:key="ev-{{ $e->id }}" class="group flex flex-col {{ $panel }} !rounded-2xl hover:shadow-md transition-shadow overflow-hidden">
                        <button type="button" wire:click="openEvent('{{ $e->id }}')" class="text-left flex-1 flex flex-col">
                            <span class="relative block h-32 bg-gray-100 dark:bg-white/[0.04]">
                                @if ($e->imageUrl())
                                    <img src="{{ $e->imageUrl() }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                                @else
                                    <span class="absolute inset-0 grid place-items-center text-4xl opacity-40">🎟</span>
                                @endif
                                <span class="absolute top-3 left-3 w-12 rounded-xl bg-white dark:bg-[#1d1e2a] shadow text-center py-1">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-rose-600">{{ $start->format('M') }}</span>
                                    <span class="block text-lg font-extrabold leading-none text-gray-900 dark:text-white">{{ $start->format('j') }}</span>
                                </span>
                                <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[11px] font-bold {{ $statusPill($e->status) }}">{{ ucfirst($e->status) }}</span>
                            </span>
                            <span class="p-4 block">
                                <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate group-hover:underline">{{ $e->title }}</span>
                                <span class="block text-[12px] text-gray-400 mt-0.5">{{ $start->format('D j M, g:i a') }}</span>
                                <span class="block text-[12px] text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ $e->venueLabel() ?: ($e->online_url ? 'Online' : 'No venue set') }}</span>
                                <span class="mt-3 flex items-center justify-between text-[12px]">
                                    <span class="text-gray-600 dark:text-gray-300 font-semibold">{{ $soldLabel($e) }}</span>
                                    <span class="text-gray-400">{{ $e->revenue_cents ? $money($e->revenue_cents) : ($e->has_paid_types ? '' : 'Free') }}</span>
                                </span>
                                <span class="block mt-1.5 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                                    <span class="block h-full rounded-full" style="width:{{ $e->fill_pct ?? ($e->sold ? 100 : 0) }}%;background:{{ $barColor($e->fill_pct) }}"></span>
                                </span>
                                @if ($e->has_paid_types && ! $paymentsReady)
                                    <span class="mt-2 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300">Payments not connected</span>
                                @endif
                            </span>
                        </button>
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
                                <th class="px-4 py-3">Event</th>
                                <th class="px-4 py-3">Date</th>
                                @unless ($compact)<th class="px-4 py-3">Venue</th>@endunless
                                <th class="px-4 py-3 text-right">Sold</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach ($events as $e)
                                <tr wire:key="row-{{ $e->id }}" wire:click="openEvent('{{ $e->id }}')" class="cursor-pointer hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    <td class="{{ $pad }} font-semibold text-gray-900 dark:text-white">{{ $e->title }}</td>
                                    <td class="{{ $pad }} text-[12px] text-gray-500 whitespace-nowrap">{{ $e->localStart()->format('D j M Y, g:i a') }}</td>
                                    @unless ($compact)<td class="{{ $pad }} text-[12px] text-gray-500 truncate max-w-[12rem]">{{ $e->venueLabel() ?: ($e->online_url ? 'Online' : '—') }}</td>@endunless
                                    <td class="{{ $pad }} text-right tabular-nums font-bold text-gray-900 dark:text-white">{{ $e->sold }}{{ $e->capacity ? ' / '.$e->capacity : '' }}</td>
                                    <td class="{{ $pad }}"><span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $statusPill($e->status) }}">{{ ucfirst($e->status) }}</span></td>
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
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Sales by event</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $all->sum('sold') }}</b> {{ Str::plural('ticket', $all->sum('sold')) }} ·
                <b class="text-gray-900 dark:text-white">{{ $money($all->sum('revenue_cents')) }}</b> all time
            </p>
            @if ($stats['salesByEvent']->isEmpty())
                <p class="text-[12px] text-gray-400">Sales show here once people book.</p>
            @else
                @php $maxSold = max(1, $stats['salesByEvent']->max('sold')); @endphp
                <div class="space-y-2.5">
                    @foreach ($stats['salesByEvent'] as $e)
                        <button type="button" wire:click="openEvent('{{ $e->id }}')" class="block w-full text-left group">
                            <span class="flex items-center justify-between text-[12.5px] gap-2">
                                <span class="truncate text-gray-700 dark:text-gray-200 group-hover:underline">{{ $e->title }}</span>
                                <span class="font-bold text-gray-900 dark:text-white tabular-nums shrink-0">{{ $e->sold }}{{ $e->capacity ? '/'.$e->capacity : '' }}</span>
                            </span>
                            <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                                <span class="block h-full rounded-full" style="width:{{ $e->capacity ? $e->fill_pct : round($e->sold / $maxSold * 100) }}%;background:{{ $barColor($e->fill_pct) }}"></span>
                            </span>
                            @if ($e->revenue_cents)<span class="block text-[11px] text-gray-400 mt-0.5">{{ $money($e->revenue_cents) }}</span>@endif
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($stats['paymentsMissing'] || $stats['drafts']->isNotEmpty() || $stats['today']->isNotEmpty() || $stats['nearCapacity']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if ($stats['paymentsMissing'])
                    <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="block rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">Payments not connected</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70">Paid tickets can’t sell until you connect Stripe →</p>
                    </a>
                @endif
                @foreach ($stats['today']->take(3) as $e)
                    <button type="button" wire:click="openEvent('{{ $e->id }}')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-sky-50 dark:bg-sky-500/10 hover:ring-2 hover:ring-sky-200 dark:hover:ring-sky-500/30">
                        <p class="text-[13px] font-bold text-sky-800 dark:text-sky-200">Today: {{ $e->title }}</p>
                        <p class="text-[12px] text-sky-700/80 dark:text-sky-200/70">{{ $e->localStart()->format('g:i a') }} · {{ $e->checked_in }}/{{ $e->sold }} checked in — open door check-in →</p>
                    </button>
                @endforeach
                @foreach ($stats['nearCapacity']->take(3) as $e)
                    <button type="button" wire:click="openEvent('{{ $e->id }}')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $e->fill_pct >= 100 ? 'Sold out' : $e->fill_pct.'% full' }}: {{ $e->title }}</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">{{ $e->sold + $e->held }} of {{ $e->capacity }} places taken</p>
                    </button>
                @endforeach
                @if ($stats['drafts']->isNotEmpty())
                    <button type="button" wire:click="setFilter('drafts')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-gray-200 dark:hover:ring-white/10">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $stats['drafts']->count() }} draft {{ Str::plural('event', $stats['drafts']->count()) }}</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Not visible on your site until published. Show them →</p>
                    </button>
                @endif
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Payments', $paymentsReady ? 'Connected' : 'Connect Stripe', route('site.payments', $site->name)],
                    ['Contacts', 'Your audience', route('site.contacts', $site->name)],
                    ['Assets', 'Event images', route('media', $site->name)],
                    ['Add-ons', 'Events settings', route('site.marketplace', $site->name)],
                ] as [$rLabel, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $rLabel }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
