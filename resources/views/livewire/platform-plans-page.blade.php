@php
    $gbp = fn (int $c) => \App\Support\Money::format($c, 'gbp');
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
    $limit = fn ($v, $unit = '') => $v === null ? 'Unlimited' : number_format($v).$unit;
@endphp

<x-tri-layout title="Membership plans" subtitle="Prices, limits and what each plan includes. Changes apply across the app straight away."
    :labels="['📊 Numbers', '💳 Plans', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <button wire:click="create" class="fx inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-full text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New plan
        </button>
    </x-slot:header>

    {{-- ══ LEFT rail ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$gbp($stats['mrr'])" label="Monthly recurring revenue" :sub="$stats['paying'].' paying'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$stats['plans']" label="Plans" :sub="$stats['visible'].' on sale'"
                icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        <x-tile accent="cocoa" :value="$stats['trialing']" label="On trial" :sub="$trialDays.'-day trial'"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </div>

    {{-- Trial length --}}
    <form wire:submit="saveTrialDays" class="{{ $panel }} p-5 mt-3">
        <label class="block">
            <span class="bkf-label">Free trial length (days)</span>
            <span class="flex gap-2">
                <input type="number" min="0" max="90" wire:model="trialDays" class="bkf-input w-full">
                <button type="submit" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Save</button>
            </span>
        </label>
        @error('trialDays')<p class="mt-1 text-[12px] font-semibold text-rose-600">{{ $message }}</p>@enderror
        <p class="mt-2 text-[11.5px] text-gray-500 dark:text-gray-400">Applies to new signups. Current trials keep their end date.</p>
    </form>
    </x-slot:rail>

    {{-- ══ CENTER: the plans, in display order ══ --}}
    <div class="@container max-w-[52rem] mx-auto space-y-3">
        @foreach ($rows->values() as $i => $r)
            @php $t = $r['tier']; @endphp
            <div class="{{ $panel }} p-5 {{ ! empty($t['hidden']) ? 'opacity-70' : '' }}">
                <div class="flex flex-wrap items-start gap-4">
                    <span class="w-12 h-12 rounded-2xl shrink-0 grid place-items-center text-white font-display text-lg font-extrabold" style="background: {{ $t['color'] ?? '#6366f1' }}">
                        {{ mb_substr($t['name'] ?? $r['key'], 0, 1) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-display text-[17px] font-bold text-gray-900 dark:text-white">{{ $t['name'] ?? $r['key'] }}</span>
                            @if (! empty($t['highlight']))<span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:var(--primary);color:var(--on-primary)">Recommended</span>@endif
                            @if (! empty($t['hidden']))<span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full bg-gray-200 text-gray-700 dark:bg-white/[0.1] dark:text-gray-200">Hidden</span>@endif
                            @if (! $r['builtIn'])<span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full bg-sky-100 text-sky-800">Custom plan</span>@endif
                        </p>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $t['tagline'] ?? '' }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[12px] text-gray-700 dark:text-gray-300">
                            <span>{{ $limit($t['limits']['sites'] ?? null) }} {{ ($t['limits']['sites'] ?? 2) === 1 ? 'site' : 'sites' }}</span>
                            <span>{{ ($t['limits']['storage_mb'] ?? null) === null ? 'Unlimited' : (($t['limits']['storage_mb'] >= 1024) ? round($t['limits']['storage_mb'] / 1024, 1).' GB' : $t['limits']['storage_mb'].' MB') }} storage</span>
                            <span>{{ ! empty($t['limits']['premium']) ? 'Premium modules' : 'Basic modules' }}</span>
                            @if (! empty($t['limits']['marketplace']))<span>Sells templates</span>@endif
                            @if (! empty($t['domain_included']))<span>Domain included</span>@endif
                            <span>{{ ($t['limits']['ai_tokens_month'] ?? null) === null ? 'Unlimited AI' : number_format($t['limits']['ai_tokens_month']).' AI tokens/mo' }}</span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="font-display text-2xl font-extrabold text-gray-900 dark:text-white tabular-nums">{{ ($t['price_cents'] ?? 0) ? $gbp((int) $t['price_cents']) : 'Free' }}</p>
                        <p class="text-[11.5px] text-gray-500 dark:text-gray-400">{{ ($t['price_cents'] ?? 0) ? 'per month' : '' }}</p>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-white/[0.06] flex flex-wrap items-center gap-2">
                    <span class="text-[12px] text-gray-600 dark:text-gray-300">
                        <b class="tabular-nums">{{ $r['subscribers'] }}</b> {{ Str::plural('account', $r['subscribers']) }}
                        @if ($r['mrr']) · <b>{{ $gbp($r['mrr']) }}</b>/mo @endif
                    </span>
                    <span class="ml-auto flex flex-wrap items-center gap-2">
                        <button wire:click="move('{{ $r['key'] }}', -1)" @disabled($i === 0) title="Move up" aria-label="Move {{ $t['name'] }} up"
                                class="{{ $btnOutline }} !px-2.5 disabled:opacity-30">↑</button>
                        <button wire:click="move('{{ $r['key'] }}', 1)" @disabled($i === $rows->count() - 1) title="Move down" aria-label="Move {{ $t['name'] }} down"
                                class="{{ $btnOutline }} !px-2.5 disabled:opacity-30">↓</button>
                        @if ($r['key'] !== 'trial')
                            <button wire:click="toggleHidden('{{ $r['key'] }}')" class="{{ $btnOutline }}"
                                    @if (empty($t['hidden'])) data-confirm="Hide {{ $t['name'] }}? Nobody new can choose it; current subscribers keep it." @endif>
                                {{ empty($t['hidden']) ? 'Hide' : 'Show' }}
                            </button>
                        @endif
                        <button wire:click="edit('{{ $r['key'] }}')" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Edit</button>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ══ RIGHT rail ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-3">Accounts per plan</h3>
            @php $totalSubs = max(1, $rows->sum('subscribers')); @endphp
            @foreach ($rows as $r)
                <div class="flex items-center gap-3 py-1.5">
                    <span class="w-20 text-[12.5px] font-bold shrink-0 truncate" style="color: {{ $r['tier']['color'] ?? '#6366f1' }}">{{ $r['tier']['name'] ?? $r['key'] }}</span>
                    <span class="flex-1 h-2.5 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                        <span class="block h-full rounded-full" style="width: {{ round($r['subscribers'] / $totalSubs * 100) }}%; background: {{ $r['tier']['color'] ?? '#6366f1' }}"></span>
                    </span>
                    <span class="w-8 text-right text-[12.5px] font-bold tabular-nums text-gray-700 dark:text-gray-200">{{ $r['subscribers'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">Good to know</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 list-disc ml-4">
                <li>New prices apply to new checkouts. Current subscribers keep the price they signed up at.</li>
                <li>Hiding a plan removes it from the pricing pages; people already on it keep it.</li>
                <li>Per-client prices are set on the Accounts page.</li>
            </ul>
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([['Accounts', 'assign plans & custom prices', route('admin.accounts')], ['Dashboard', 'platform numbers', route('admin.dashboard')]] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" wire:navigate class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>

    {{-- ══ Plan editor (right drawer, fixed header) ══ --}}
    @if ($editing !== null)
        @php $isTrial = $editing === 'trial'; $isNew = $editing === ''; @endphp
        <x-side-drawer close="close" width="max-w-xl">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ $isNew ? 'New plan' : 'Edit plan' }}</p>
                <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white truncate">{{ $form['name'] ?: 'Untitled plan' }}</h2>
            </x-slot:header>

            <form id="plan-form" wire:submit="save" class="p-6 space-y-5">
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="bkf-label">Name</span>
                        <input type="text" wire:model.live.debounce.300ms="form.name" maxlength="40" class="bkf-input w-full">
                        @error('form.name')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    @if ($isNew)
                        <label class="block">
                            <span class="bkf-label">Key <span class="font-normal text-gray-500">(permanent, from the name if empty)</span></span>
                            <input type="text" wire:model="form.key" maxlength="40" placeholder="e.g. agency" class="bkf-input w-full font-mono">
                            @error('form.key')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @else
                        <label class="block">
                            <span class="bkf-label">Price per month (£)</span>
                            <input type="number" step="0.01" min="0" wire:model="form.price" @disabled($isTrial) class="bkf-input w-full disabled:opacity-60">
                            @error('form.price')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                </div>
                @if ($isNew)
                    <label class="block">
                        <span class="bkf-label">Price per month (£)</span>
                        <input type="number" step="0.01" min="0" wire:model="form.price" class="bkf-input w-full">
                        @error('form.price')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                @endif
                <label class="block">
                    <span class="bkf-label">Tagline</span>
                    <input type="text" wire:model="form.tagline" maxlength="80" placeholder="For growing businesses" class="bkf-input w-full">
                </label>

                <fieldset class="rounded-2xl border border-gray-200 dark:border-white/[0.1] p-4">
                    <legend class="px-1 text-[12px] font-bold text-gray-700 dark:text-gray-200">Limits</legend>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="bkf-label">Sites (empty = unlimited)</span>
                            <input type="number" min="1" wire:model="form.sites" class="bkf-input w-full">
                            @error('form.sites')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="bkf-label">Storage MB (empty = unlimited)</span>
                            <input type="number" min="1" wire:model="form.storage_mb" class="bkf-input w-full">
                            @error('form.storage_mb')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <label class="block mt-4">
                        <span class="bkf-label">AI tokens per month (empty = unlimited)</span>
                        <input type="number" min="0" step="1000" wire:model="form.ai_tokens" placeholder="e.g. 500000" class="bkf-input w-full">
                        @error('form.ai_tokens')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        <span class="block mt-1 text-[11.5px] text-gray-500 dark:text-gray-400">A typical assistant turn uses 2,000–8,000 tokens.</span>
                    </label>
                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        <x-field.toggle model="form.premium" text="Premium modules" />
                        <x-field.toggle model="form.marketplace" text="Can sell templates" />
                        <x-field.toggle model="form.domain_included" text="Domain included" />
                    </div>
                </fieldset>

                <label class="block">
                    <span class="bkf-label">What's included <span class="font-normal text-gray-500">(one per line, shown as ticks)</span></span>
                    <textarea wire:model="form.features" rows="6" class="bkf-input w-full" placeholder="5 sites&#10;All premium modules&#10;Email support"></textarea>
                    @error('form.features')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
                <label class="block">
                    <span class="bkf-label">Description <span class="font-normal text-gray-500">(plan detail view)</span></span>
                    <textarea wire:model="form.description" rows="5" maxlength="3000" class="bkf-input w-full"></textarea>
                </label>

                <fieldset class="rounded-2xl border border-gray-200 dark:border-white/[0.1] p-4">
                    <legend class="px-1 text-[12px] font-bold text-gray-700 dark:text-gray-200">Look</legend>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="bkf-label">Colour</span>
                            <span class="flex items-center gap-2">
                                <input type="color" wire:model.live="form.color" class="h-11 w-14 rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] p-1">
                                <input type="text" wire:model.live.debounce.300ms="form.color" maxlength="7" class="bkf-input w-full font-mono">
                            </span>
                            @error('form.color')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="bkf-label">Card accent</span>
                            <select wire:model="form.accent" class="bkf-input w-full">
                                @foreach ($accents as $a)<option value="{{ $a }}">{{ ucfirst($a) }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        <x-field.toggle model="form.highlight" text="Recommended plan" />
                        @unless ($isTrial)<x-field.toggle model="form.hidden" text="Hidden from pricing" />@endunless
                    </div>
                </fieldset>

                @if (! $isNew && ! \App\Support\PlanCatalog::isBuiltIn($editing))
                    <div class="rounded-2xl border border-rose-200 dark:border-rose-500/30 p-4">
                        <p class="text-[12.5px] text-gray-700 dark:text-gray-200">Delete this plan. Only possible while no account is on it.</p>
                        <button type="button" wire:click="deletePlan('{{ $editing }}')" data-confirm="Delete the {{ $form['name'] }} plan?"
                                class="{{ $btn }} mt-2 bg-rose-600 text-white">Delete plan</button>
                    </div>
                @endif
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="close" class="{{ $btnOutline }}">Cancel</button>
                    <button type="submit" form="plan-form" class="{{ $btn }} min-w-[7rem]" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="save">{{ $isNew ? 'Create plan' : 'Save plan' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </x-slot:footer>
        </x-side-drawer>
    @endif
</x-tri-layout>
