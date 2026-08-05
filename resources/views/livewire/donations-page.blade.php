<div class="main-body p-6">
    @php $s = $this->stats; @endphp

    <div class="flex items-start justify-between mb-5">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Donations</h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                <a href="{{ url($site->name.'/donate') }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">View public donate page ↗</a>
            </p>
        </div>
    </div>

    @unless($site->stripeReady())
    <div class="mb-5 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-sm text-amber-700 dark:text-amber-400">
        Stripe isn't connected — donations can't be collected yet. Connect it in <a href="{{ url($site->name.'/marketplace') }}" class="font-semibold underline">Marketplace</a>.
    </div>
    @endunless

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-tile label="Total raised" :value="$s['raisedMajor']" sub="all time" accent="ink" />
        <x-tile label="Donations" :value="$s['count']" sub="gifts received" accent="lime" />
        <x-tile label="Average gift" :value="$s['avg']" sub="per donation" accent="lavender" />
    </div>

    {{-- List --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Recent donations</h2>
        </div>
        @forelse($this->donations as $d)
        <div class="flex items-center gap-4 px-5 py-3.5 border-b border-gray-50 dark:border-white/[0.04] last:border-0">
            @php $cb = ['#ec4899','#6366f1','#8b5cf6','#0ea5e9','#10b981'][abs(crc32($d->donor_name ?: $d->donor_email ?: 'anon')) % 5]; @endphp
            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0" style="background:{{ $cb }}">
                {{ strtoupper(\Illuminate\Support\Str::substr($d->donor_name ?: $d->donor_email ?: 'A', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $d->donor_name ?: ($d->donor_email ?: 'Anonymous') }}</p>
                @if($d->message)<p class="text-xs text-gray-400 dark:text-gray-500 truncate italic">“{{ $d->message }}”</p>@endif
                <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $d->created_at->diffForHumans() }}</p>
            </div>
            <span class="text-sm font-extrabold text-gray-900 dark:text-white shrink-0">{{ $d->formattedAmount() }}</span>
            <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full shrink-0 {{ $d->status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400' }}">{{ $d->status }}</span>
            <button wire:click="deleteDonation({{ $d->id }})"
                    data-confirm="Delete this donation record? The raised total will change. This cannot be undone."
                    class="shrink-0 p-1 rounded-lg text-gray-300 dark:text-gray-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete donation record">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </div>
        @empty
        <div class="px-5 py-16 text-center">
            <span class="text-4xl">💝</span>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mt-3">No donations yet.</p>
        </div>
        @endforelse
    </div>
</div>
