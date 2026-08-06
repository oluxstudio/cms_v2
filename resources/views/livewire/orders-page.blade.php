@php
    $statusStyles = [
        'pending'   => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
        'paid'      => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        'fulfilled' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400',
        'cancelled' => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
    ];
    $statuses = \App\Models\Order::STATUSES;
    $counts   = $this->statusCounts;
    $ins      = $this->insights;
    $accent   = 'var(--primary)'; // APP theme token (orange light / blue dark)
    $delta = fn (?int $d) => $d === null ? null : (($d >= 0 ? '▲ ' : '▼ ').abs($d).'% vs last month');
    $donutColors = [$accent, '#f59e0b', '#10b981', '#ec4899', '#38bdf8'];
@endphp

<div class="main-body p-5 sm:p-6"
     x-data="{ toast:'' }"
     x-init="$watch('$wire.successMessage', v => { if(v){ toast=v; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } })">

    {{-- ══ Greeting ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                Hello, {{ ucfirst(explode(' ', trim($site->user?->name ?? 'there'))[0]) }}! 👋</h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">This is what's happening in your store this month.</p>
        </div>
        <span class="text-xs font-semibold px-3 py-2 rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300">
            {{ now()->format('F Y') }} ▾</span>
    </div>

    {{-- ══ Row 2: stat tiles + revenue bars ══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 mb-4">
        <div class="xl:col-span-2 grid grid-cols-2 gap-4">
            <x-tile accent="ink" :value="$ins['monthRevenue']" label="Total revenue this month"
                    :sub="$delta($ins['revDelta']) ?? 'this month'" />
            <x-tile accent="lime" :value="$ins['monthOrders']" label="Orders this month"
                    :sub="$delta($ins['ordDelta']) ?? 'this month'" />
            <x-tile accent="lavender" :value="$ins['customers']" label="Customers"
                    :sub="$delta($ins['custDelta']) ?? 'this month'" />
            <x-tile accent="cocoa" :value="$ins['aov']" label="Avg order value" sub="per paid order · all time" />
        </div>

        {{-- Revenue bars (last 8 days) --}}
        <div class="xl:col-span-3 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5 shadow-sm">
            <div class="flex items-baseline justify-between mb-3">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Revenue</h2>
                <span class="text-[11px] text-gray-400">last 8 days</span>
            </div>
            @php
                $dMax = max(1, collect($ins['daily'])->max('cents'));
                $maxIdx = collect($ins['daily'])->search(fn ($d) => $d['cents'] === $dMax);
            @endphp
            <div class="flex items-end gap-2.5 h-40">
                @foreach($ins['daily'] as $i => $bar)
                    @php $h = $bar['cents'] > 0 ? max(8, (int) round($bar['cents'] / $dMax * 100)) : 4; @endphp
                    <div class="flex-1 flex flex-col items-center gap-1.5 group relative h-full justify-end">
                        <span class="absolute -top-1 left-1/2 -translate-x-1/2 text-[9px] font-bold whitespace-nowrap px-1.5 py-0.5 rounded-md
                                {{ $i === $maxIdx && $bar['cents'] > 0 ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'opacity-0 group-hover:opacity-100 text-gray-500 transition-opacity' }}">
                            {{ \App\Support\Money::format($bar['cents'], $ins['currency']) }}</span>
                        <div class="w-full rounded-lg transition-all group-hover:opacity-80"
                             style="height:{{ $h }}%; background:{{ $bar['cents'] > 0 ? 'var(--primary)' : 'rgba(148,163,184,.25)' }}"></div>
                        <span class="text-[9px] text-gray-400 whitespace-nowrap">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══ Row 3: big numbers + category donut ══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
        <x-tile accent="sky" :value="$counts['all'] ?? 0" label="orders all time"
                :sub="$ins['awaiting'] > 0 ? $ins['awaiting'].' awaiting confirmation' : 'all orders handled — nice'">
            <span class="w-8 h-8 rounded-full grid place-items-center text-sm" style="background:#bfdcf7">✓</span>
        </x-tile>
        <x-tile accent="lavender" :value="$ins['customers']" label="customers all time"
                :sub="$ins['waitingPeople'] > 0 ? $ins['waitingPeople'].' waiting for a response' : 'nobody is waiting on you'">
            <span class="w-8 h-8 rounded-full grid place-items-center text-sm" style="background:#d7c3f5">👤</span>
        </x-tile>
        {{-- Sales by Category donut --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5 shadow-sm">
            <div class="flex items-baseline justify-between mb-2">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Sales by Category</h2>
                <span class="text-[11px] text-gray-400">by revenue</span>
            </div>
            @if(count($ins['categories']))
                <div class="flex items-center gap-4">
                    @php $R = 34; $C = 2 * M_PI * $R; $off = 0; @endphp
                    <svg viewBox="0 0 90 90" class="w-24 h-24 shrink-0 -rotate-90">
                        @foreach($ins['categories'] as $i => $c)
                            @php $len = $C * $c['share'] / 100; @endphp
                            <circle cx="45" cy="45" r="{{ $R }}" fill="none" style="stroke:{{ $donutColors[$i % 5] }}"
                                    stroke-width="13" stroke-dasharray="{{ max(0.1, $len - 1.5) }} {{ $C }}"
                                    stroke-dashoffset="{{ -$off }}" stroke-linecap="butt"/>
                            @php $off += $len; @endphp
                        @endforeach
                    </svg>
                    <div class="min-w-0 flex-1 space-y-1.5">
                        @foreach($ins['categories'] as $i => $c)
                            <div class="flex items-center gap-2 text-[11px]">
                                <span class="shrink-0 w-2.5 h-2.5 rounded-full" style="background:{{ $donutColors[$i % 5] }}"></span>
                                <span class="truncate text-gray-600 dark:text-gray-300 font-medium">{{ $c['name'] }}</span>
                                <span class="ml-auto shrink-0 font-bold text-gray-800 dark:text-gray-100 tabular-nums">{{ $c['share'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="py-8 text-center text-xs text-gray-400">No paid sales yet.</p>
            @endif
        </div>
    </div>

    {{-- ══ Row 4: Order list ══ --}}
    <h2 class="text-lg font-extrabold tracking-tight text-gray-900 dark:text-white mb-3">Order list</h2>

    {{-- gradient status summary tiles --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        @foreach([
            ['pending',   'New / pending orders', 'linear-gradient(120deg,#dbeafe,#eef2ff)', 'linear-gradient(120deg,#1e3a5f33,#3730a333)'],
            ['paid',      'Paid orders',          'linear-gradient(120deg,#d1fae5,#ecfdf5)', 'linear-gradient(120deg,#064e3b33,#065f4633)'],
            ['fulfilled', 'Fulfilled orders',     'linear-gradient(120deg,#fef3c7,#fffbeb)', 'linear-gradient(120deg,#78350f33,#92400e33)'],
            ['cancelled', 'Cancelled orders',     'linear-gradient(120deg,#f3f4f6,#fafafa)', 'linear-gradient(120deg,#1f293733,#37415133)'],
        ] as [$st, $label, $grad, $gradDark])
            @php $wk = $ins['weekly'][$st]; @endphp
            <button type="button" wire:click="setStatusFilter('{{ $statusFilter === $st ? 'all' : $st }}')"
                    class="text-left rounded-2xl p-4 border transition-shadow hover:shadow-md
                        {{ $statusFilter === $st ? 'ring-2 ring-offset-1' : '' }} border-gray-100 dark:border-white/[0.05]"
                    style="background:{{ $grad }}; {{ $statusFilter === $st ? '--tw-ring-color:'.$accent : '' }}">
                <p class="text-[11px] font-bold text-gray-600 mb-2">{{ $label }}</p>
                <p class="text-2xl font-extrabold tabular-nums text-gray-900 flex items-center gap-2">
                    {{ $counts[$st] ?? 0 }}
                    @if($wk['delta'] !== null)
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $wk['delta'] >= 0 ? 'bg-emerald-200/70 text-emerald-800' : 'bg-rose-200/70 text-rose-700' }}">
                            {{ $wk['delta'] >= 0 ? '▲' : '▼' }} {{ abs($wk['delta']) }}%</span>
                    @endif
                </p>
                <p class="text-[10px] text-gray-500 mt-1">than last week</p>
            </button>
        @endforeach
    </div>

    {{-- toolbar --}}
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <div class="relative w-full sm:w-auto">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm">⌕</span>
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search orders"
                   class="w-full sm:w-48 text-xs font-medium rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] pl-8 pr-3 py-2 text-gray-700 dark:text-gray-200">
        </div>
        <span class="text-[11px] text-gray-400 font-medium">{{ $this->orders->count() }} orders</span>
        <div class="ml-auto flex items-center gap-2">
            <select wire:model.live="sort" class="text-[11px] font-semibold rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] px-2.5 py-2 text-gray-600 dark:text-gray-300">
                <option value="newest">Sort: newest</option>
                <option value="oldest">Sort: oldest</option>
                <option value="amount">Sort: amount</option>
            </select>
            <button type="button" wire:click="openQuickInvoice"
                    class="text-xs font-bold px-3.5 py-2 rounded-xl bg-gray-900 text-white dark:bg-white dark:text-gray-900 hover:opacity-90">＋ Create invoice</button>
        </div>
    </div>

    {{-- table --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left" style="min-width:640px">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                        @foreach(['Order', 'Customer', 'Items', 'Price', 'Date', 'Payment', 'Status'] as $th)
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->orders as $order)
                        <tr class="border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors">
                            <td class="px-4 py-3 text-xs font-bold text-gray-900 dark:text-white cursor-pointer" wire:click="open({{ $order->id }})">#{{ $order->id }}</td>
                            <td class="px-4 py-3 cursor-pointer" wire:click="open({{ $order->id }})">
                                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate max-w-[160px]">{{ $order->customer_name ?: 'Guest' }}</p>
                                <p class="text-[10px] text-gray-400 truncate max-w-[160px]">{{ $order->customer_email }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-300">{{ $order->items_count }}</td>
                            <td class="px-4 py-3 text-xs font-extrabold tabular-nums text-gray-900 dark:text-white">{{ $order->formattedTotal() }}</td>
                            <td class="px-4 py-3 text-[11px] text-gray-400 whitespace-nowrap">{{ $order->created_at->format('d.m.Y') }}</td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 dark:text-gray-300">{{ $order->stripe_session_id ? 'Card · Stripe' : 'Manual' }}</td>
                            <td class="px-4 py-3">
                                <select wire:change="setStatus({{ $order->id }}, $event.target.value)"
                                        class="text-[10px] font-bold capitalize rounded-lg border-0 px-2 py-1 cursor-pointer {{ $statusStyles[$order->status] ?? '' }}">
                                    @foreach($statuses as $st)
                                        <option value="{{ $st }}" @selected($order->status === $st)>{{ $st }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-16 text-center">
                            <span class="text-4xl">🧾</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mt-3">No orders found.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ════════ Order detail drawer ════════ --}}
    @if($this->selected)
    @php $o = $this->selected; @endphp
    <div class="fixed inset-0 z-50 flex justify-end" wire:key="order-{{ $o->id }}">
        <div class="absolute inset-0 bg-black/40" wire:click="closeDetail"></div>
        <div class="relative w-full max-w-md h-full bg-white dark:bg-[#1d1e2a] border-l border-gray-100 dark:border-white/[0.05] shadow-2xl overflow-y-auto">
            <div class="sticky top-0 bg-white dark:bg-[#1d1e2a] border-b border-gray-100 dark:border-white/[0.05] px-6 py-4 flex items-center justify-between z-10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Order #{{ $o->id }}</h2>
                <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full capitalize {{ $statusStyles[$o->status] ?? '' }}">{{ $o->status }}</span>
                    <span class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $o->formattedTotal() }}</span>
                </div>
                <div class="mb-5 text-sm">
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $o->customer_name ?: 'Guest' }}</p>
                    <p class="text-gray-400 dark:text-gray-500">{{ $o->customer_email }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Placed {{ $o->created_at->format('M j, Y · g:i A') }}@if($o->paid_at) · paid {{ $o->paid_at->diffForHumans() }}@endif</p>
                </div>

                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Items</p>
                <div class="space-y-2 mb-6">
                    @foreach($o->items as $it)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-700 dark:text-gray-200">{{ $it->qty }} × {{ $it->name }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ \App\Support\Money::format($it->lineTotalCents(), $o->currency) }}</span>
                    </div>
                    @endforeach
                </div>

                @if($o->status === 'paid')
                <button wire:click="markFulfilled({{ $o->id }})" data-confirm="Mark this order as fulfilled?" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors mb-2">Mark as fulfilled</button>
                @endif
                <button wire:click="invoiceOrder({{ $o->id }})" data-confirm="Create a draft invoice from this order? You'll be taken to the Invoices page."
                        class="w-full py-2.5 border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-700 dark:text-gray-200 rounded-xl hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">🧾 Invoice this order</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ════════ Quick Create-Invoice modal ════════ --}}
    @if($qiOpen)
    <div class="fixed inset-0 z-50 grid place-items-center p-6" style="background:rgba(10,10,12,.6); backdrop-filter:blur(4px)" wire:click.self="$set('qiOpen', false)">
        <div class="w-full max-w-md bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">New invoice</h2>
                <button wire:click="$set('qiOpen', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
            </div>
            <form wire:submit="createInvoice" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-field.text label="Customer name" model="qiName" />
                        @error('qiName')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-field.text label="Customer email" model="qiEmail" type="email" />
                        @error('qiEmail')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="bkf-label">Items</label>
                    <div class="space-y-2">
                        @foreach($qiItems as $i => $row)
                            <div class="flex gap-2 items-start" wire:key="qi-{{ $i }}">
                                <div class="flex-1 min-w-0"><x-field.text model="qiItems.{{ $i }}.description" placeholder="Description" /></div>
                                <div class="w-14"><x-field.text model="qiItems.{{ $i }}.qty" type="number" min="1" placeholder="Qty" /></div>
                                <div class="w-24"><x-field.text model="qiItems.{{ $i }}.price" type="number" step="0.01" min="0" placeholder="Price" /></div>
                                @if(count($qiItems) > 1)
                                    <button type="button" wire:click="qiRemoveItem({{ $i }})" class="mt-2 text-gray-300 hover:text-rose-500">✕</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @error('qiItems.*.description')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    <button type="button" wire:click="qiAddItem" class="mt-2 text-[11px] font-bold text-indigo-600 dark:text-indigo-300">＋ Add item</button>
                </div>
                <div class="w-40"><x-field.text label="Due date" model="qiDue" type="date" /></div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold hover:opacity-90">Create draft invoice</button>
                    <button type="button" wire:click="$set('qiOpen', false)" class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-600 dark:text-gray-300">Cancel</button>
                </div>
                <p class="text-[10px] text-gray-400">Created as a draft on the Invoices page — review, then send with its pay link.</p>
            </form>
        </div>
    </div>
    @endif

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition class="fixed bottom-6 right-6 z-[60] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium bg-gray-900 text-white">
        <span x-text="toast"></span>
    </div>
</div>
