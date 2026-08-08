<div class="max-w-5xl mx-auto px-5 sm:px-6 py-6"
     x-data="{ toast:'', t:'success' }"
     x-init="
        $watch('$wire.ok',  v => { if(v){ toast=v; t='success'; setTimeout(()=>{ toast=''; $wire.ok=''; },4000) } });
        $watch('$wire.err', v => { if(v){ toast=v; t='error';   setTimeout(()=>{ toast=''; $wire.err=''; },6000) } });
     ">

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition class="fixed top-5 right-5 z-50 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-lg"
         :class="t==='success' ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'" x-text="toast"></div>

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">My Templates</h1>
        <p class="mt-1 text-sm text-gray-400">Publish your own templates to the marketplace. Free or paid — submit for review, then they appear for everyone.</p>
    </div>

    @php
        $badge = fn ($s) => match ($s) {
            'published' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
            'in_review' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
            'rejected'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
            default     => 'bg-gray-100 text-gray-500 dark:bg-white/[0.08] dark:text-gray-400',
        };
    @endphp

    {{-- Payouts / Stripe Connect status --}}
    @if(! $this->paymentsConfigured)
        <div class="mb-5 px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.06] text-sm text-gray-500">
            Marketplace payments aren't set up on this instance — you can publish <strong>free</strong> templates.
        </div>
    @elseif($this->canSell)
        <div class="mb-5 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-sm text-emerald-700 dark:text-emerald-400 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Payouts connected — you can sell paid templates.
        </div>
    @else
        <div class="mb-5 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-sm text-amber-700 dark:text-amber-400 flex items-center justify-between gap-3">
            <span>Connect a Stripe account to receive payouts for paid templates.</span>
            <a href="{{ route('creator.connect') }}" class="shrink-0 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Connect payouts</a>
        </div>
    @endif

    {{-- Publish form --}}
    <div class="rounded-2xl border border-dashed border-gray-300 dark:border-white/[0.12] bg-gray-50/60 dark:bg-white/[0.02] p-5 mb-8">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Publish a new template (.zip)</h2>
        <form wire:submit="publish" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Name</label>
                <input wire:model="tName" type="text" placeholder="(defaults to template.json name)"
                       class="w-full text-sm px-3 py-2 rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Category</label>
                <input wire:model="tCategory" type="text" class="w-full text-sm px-3 py-2 rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Price (USD, 0 = free)</label>
                <input wire:model="tPrice" type="number" min="0" step="0.01" class="w-full text-sm px-3 py-2 rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
            </div>
            <div class="sm:col-span-3">
                <input type="file" wire:model="zip" accept=".zip"
                       class="text-xs text-gray-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 file:cursor-pointer">
                <span wire:loading wire:target="zip" class="text-[11px] text-gray-400 ml-2">Uploading…</span>
                @error('zip') <span class="text-[11px] text-rose-500 ml-2">{{ $message }}</span> @enderror
            </div>
            <div>
                <button type="submit" wire:loading.attr="disabled" wire:target="publish,zip"
                        class="w-full px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-semibold">
                    <span wire:loading.remove wire:target="publish">Publish draft</span>
                    <span wire:loading wire:target="publish">Publishing…</span>
                </button>
            </div>
        </form>
        <p class="text-[11px] text-gray-400 mt-2">Package format: <code>template.json</code> + <code>tokens/</code> + <code>css/</code> + <code>assets/</code> + <code>pages/</code> (see resources/templates/README.md).</p>
    </div>

    {{-- My templates --}}
    {{-- Earnings summary --}}
    @php $e = $this->earnings; $money = fn ($c) => \App\Support\Money::format((int) $c, 'gbp'); @endphp
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-8">
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] p-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Net earnings</p>
            <p class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400">{{ $money($e['net_cents']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] p-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Gross</p>
            <p class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $money($e['gross_cents']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] p-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Platform fees</p>
            <p class="text-lg font-extrabold text-gray-500">{{ $money($e['fees_cents']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] p-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Sales</p>
            <p class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $e['sales'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] p-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Installs</p>
            <p class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $e['installs'] }}</p>
        </div>
    </div>

    <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Your templates</h2>
    @php $stats = $this->analytics->keyBy(fn ($r) => $r['template']->id); @endphp
    @if($this->myTemplates->isEmpty())
        <p class="text-sm text-gray-400 mb-8">You haven't published any templates yet.</p>
    @else
        <div class="space-y-2 mb-10">
            @foreach($this->myTemplates as $t)
                <div wire:key="mine-{{ $t->id }}" class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] px-4 py-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br {{ $t->gradient_class ?: 'from-slate-400 to-slate-600' }} overflow-hidden shrink-0">
                        @if($t->thumbnail_url)<img src="{{ $t->thumbnail_url }}" alt="" class="w-full h-full object-cover">@endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $t->name }}</span>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full {{ $badge($t->status) }}">{{ str_replace('_',' ',$t->status) }}</span>
                            <span class="text-[10px] font-semibold text-gray-400">{{ $t->priceLabel() }}</span>
                        </div>
                        @php $st = $stats[$t->id] ?? null; @endphp
                        <p class="text-[11px] text-gray-400">{{ $t->category }} · {{ $t->latestVersion?->version ? 'v'.$t->latestVersion->version : '—' }}
                            @if($st) · {{ $st['installs'] }} installs · {{ $st['sales'] }} sales · {{ $money($st['revenue_cents']) }}
                                @if($st['rating_count']) · <span class="text-amber-500">★ {{ number_format($st['rating_avg'], 1) }}</span> ({{ $st['rating_count'] }})@endif
                            @endif
                            @if($t->status === 'rejected' && $t->rejection_reason) · <span class="text-rose-500">{{ $t->rejection_reason }}</span>@endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if(in_array($t->status, ['draft','rejected'], true))
                            <button wire:click="submit('{{ $t->id }}')" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Submit for review</button>
                        @endif
                        <button wire:click="deleteTemplate('{{ $t->id }}')" data-confirm="Delete this template?" class="text-xs font-semibold text-gray-400 hover:text-rose-500">Delete</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Moderation queue --}}
    @if($this->isModerator)
        <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Review queue
            <span class="ml-1 font-normal text-gray-400">({{ $this->reviewQueue->count() }})</span></h2>
        @if($this->reviewQueue->isEmpty())
            <p class="text-sm text-gray-400">Nothing awaiting review.</p>
        @else
            <div class="space-y-2">
                @foreach($this->reviewQueue as $t)
                    <div wire:key="rev-{{ $t->id }}" class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50/40 dark:bg-amber-500/[0.05] px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $t->name }}</span>
                                <span class="text-[11px] text-gray-400"> · by {{ $t->user?->name ?? 'Unknown' }} · {{ $t->priceLabel() }} · {{ $t->category }}</span>
                            </div>
                            <button wire:click="approve('{{ $t->id }}')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">Approve</button>
                            <button wire:click="$set('rejectingId', {{ $t->id }})" class="px-3 py-1.5 rounded-lg border border-rose-300 text-rose-600 dark:text-rose-400 text-xs font-semibold hover:bg-rose-50 dark:hover:bg-rose-500/10">Reject</button>
                        </div>
                        @if($rejectingId === $t->id)
                            <div class="flex items-center gap-2 mt-2">
                                <input wire:model="rejectReason" type="text" placeholder="Reason (shown to creator)"
                                       class="flex-1 text-xs px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-white/[0.04] text-gray-800 dark:text-gray-100">
                                <button wire:click="reject('{{ $t->id }}')" data-confirm="Reject this template submission?" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold">Confirm reject</button>
                                <button wire:click="$set('rejectingId', null)" class="text-xs text-gray-400">Cancel</button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
