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
                            <span>{{ array_key_exists('mailboxes', $t['limits'] ?? []) && $t['limits']['mailboxes'] === null ? 'Mailboxes per account' : ((int) ($t['limits']['mailboxes'] ?? 0)).' mailboxes' }}</span>
                            @if (array_key_exists('staff_calendars', $t['limits'] ?? []))<span>{{ ($t['limits']['staff_calendars'] ?? null) === null ? 'Unlimited' : $t['limits']['staff_calendars'] }} {{ Str::plural('calendar', (int) ($t['limits']['staff_calendars'] ?? 2)) }}</span>@endif
                            @if (($t['limits']['bookings_month'] ?? null) !== null)<span>{{ $t['limits']['bookings_month'] }} bookings/mo</span>@endif
                            @if (($t['limits']['payment_fee_pct'] ?? null) !== null)<span>{{ (float) $t['limits']['payment_fee_pct'] }}% payment fee</span>@endif
                            @if (! empty($t['limits']['badge']))<span>Olux badge</span>@endif
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="font-display text-2xl font-extrabold text-gray-900 dark:text-white tabular-nums">{{ ($t['price_cents'] ?? 0) ? $gbp((int) $t['price_cents']) : 'Free' }}</p>
                        <p class="text-[11.5px] text-gray-500 dark:text-gray-400">{{ ($t['price_cents'] ?? 0) ? (! empty($t['price_prefix']) ? strtolower($t['price_prefix']).', ' : '').'per month' : '' }}</p>
                        @if (! empty($t['annual_price_cents']))<p class="text-[11.5px] text-gray-500 dark:text-gray-400">{{ $gbp((int) $t['annual_price_cents']) }}/year</p>@endif
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
                        @if ($r['key'] !== 'trial')
                            <button wire:click="startDelete('{{ $r['key'] }}')" class="{{ $btnOutline }} !text-rose-600" aria-label="Delete {{ $t['name'] }}">Delete</button>
                        @endif
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ═══ Deleted default plans (restorable) ═══ --}}
    @if ($deletedPlans)
        <div class="max-w-[52rem] mx-auto mt-6 rounded-2xl border border-dashed border-gray-300 dark:border-white/15 p-4">
            <p class="text-sm font-extrabold text-gray-900 dark:text-white">Deleted plans</p>
            <p class="text-[12px] text-gray-500 dark:text-gray-400 mb-2">Default plans you deleted. Restoring brings one back hidden, with its last settings.</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($deletedPlans as $dk => $dn)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-[13px] font-semibold text-gray-700 dark:text-gray-200">
                        {{ $dn }}
                        <button wire:click="restorePlan('{{ $dk }}')" class="text-[12px] font-bold underline" style="color:var(--primary)">Restore</button>
                    </span>
                @endforeach
            </div>
        </div>
    @endif


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
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="bkf-label">Annual price (£) <span class="font-normal text-gray-500">(shown only; empty = none)</span></span>
                        <input type="number" step="0.01" min="0" wire:model="form.annual_price" @disabled($isTrial) placeholder="e.g. 390" class="bkf-input w-full disabled:opacity-60">
                        @error('form.annual_price')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="bkf-label">Price prefix <span class="font-normal text-gray-500">(e.g. From)</span></span>
                        <input type="text" wire:model="form.price_prefix" maxlength="12" class="bkf-input w-full">
                    </label>
                </div>

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
                    <label class="block mt-4">
                        <span class="bkf-label">Business email mailboxes (empty = set per account)</span>
                        <input type="number" min="0" step="1" wire:model="form.mailboxes" placeholder="e.g. 10" class="bkf-input w-full">
                        @error('form.mailboxes')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        <span class="block mt-1 text-[11.5px] text-gray-500 dark:text-gray-400">Empty = Enterprise-style: set per account in Admin › Accounts. Trial accounts never get mailboxes.</span>
                    </label>
                    <div class="mt-4 grid sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="bkf-label">Online bookings / month (empty = unlimited)</span>
                            <input type="number" min="0" wire:model="form.bookings_month" class="bkf-input w-full">
                            @error('form.bookings_month')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="bkf-label">Staff calendars (empty = unlimited)</span>
                            <input type="number" min="0" wire:model="form.staff_calendars" class="bkf-input w-full">
                            @error('form.staff_calendars')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="bkf-label">Invoices / month (empty = unlimited, 0 = none)</span>
                            <input type="number" min="0" wire:model="form.invoices_month" class="bkf-input w-full">
                            @error('form.invoices_month')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="bkf-label">Online payment fee % (on top of Stripe)</span>
                            <input type="number" min="0" max="20" step="0.1" wire:model="form.payment_fee_pct" placeholder="e.g. 0.5" class="bkf-input w-full">
                            @error('form.payment_fee_pct')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="bkf-label">Free domain for year 1</span>
                            <select wire:model="form.free_domain" class="bkf-input w-full">
                                <option value="">None</option>
                                <option value="co.uk">.co.uk only</option>
                                <option value="uk">.uk only</option>
                                <option value="com">.com only</option>
                                <option value="any">Any domain</option>
                            </select>
                        </label>
                    </div>
                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        <x-field.toggle model="form.deposits" text="Booking deposits" />
                        <x-field.toggle model="form.recurring_invoices" text="Recurring invoices" />
                        <x-field.toggle model="form.badge" text="Shows “Made with Olux” badge" />
                        <x-field.toggle model="form.custom_domain" text="Own domain allowed" />
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

                @if (! $isNew && ! $isTrial)
                    <div class="rounded-2xl border border-rose-200 dark:border-rose-500/30 p-4">
                        <p class="text-[12.5px] text-gray-700 dark:text-gray-200">Delete this plan. Accounts on it move to a plan you choose.{{ \App\Support\PlanCatalog::isBuiltIn($editing) ? ' A default plan can be restored later.' : '' }}</p>
                        <button type="button" wire:click="startDelete('{{ $editing }}')" class="{{ $btn }} mt-2 bg-rose-600 text-white">Delete plan…</button>
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
    {{-- ═══ Delete a plan: move its accounts first ═══ --}}
    @if ($deleting && ($dt = config("plans.tiers.{$deleting}")))
        @php
            $dAccounts = \App\Models\AccountSubscription::where('plan', $deleting)->count();
            $dTargets = collect(config('plans.tiers'))->except($deleting)->sortBy('order');
        @endphp
        <x-lightbox close="cancelDelete" max-width="max-w-md" :title="'Delete '.$dt['name'].'?'">
            <div class="space-y-3">
                <p class="text-sm text-gray-700 dark:text-gray-200">
                    {{ $dt['name'] }} disappears from every pricing page and plan picker.
                    @if (\App\Support\PlanCatalog::isBuiltIn($deleting)) It's a default plan, so you can restore it later. @else This can't be undone. @endif
                </p>
                @if ($dAccounts > 0)
                    <label class="block">
                        <span class="bkf-label">Move its {{ $dAccounts }} {{ Str::plural('account', $dAccounts) }} to</span>
                        <select wire:model.live="moveTo" class="bkf-input w-full">
                            <option value="">Choose a plan…</option>
                            @foreach ($dTargets as $tk => $tt)
                                <option value="{{ $tk }}">{{ $tt['name'] }}{{ ! empty($tt['hidden']) ? ' (hidden)' : '' }}{{ ($tt['price_cents'] ?? 0) > 0 ? ' · £'.number_format($tt['price_cents'] / 100, 0).'/mo' : '' }}</option>
                            @endforeach
                        </select>
                        @error('moveTo')<span class="text-[12px] font-semibold text-rose-600" role="alert">{{ $message }}</span>@enderror
                    </label>
                    <p class="text-[11.5px] text-gray-500 dark:text-gray-400">Accounts paying by card keep their current Stripe subscription and price until they change plan themselves.</p>
                @else
                    <p class="text-[12.5px] text-gray-500 dark:text-gray-400">No accounts are on this plan.</p>
                @endif
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete" class="{{ $btnOutline }}">Cancel</button>
                    <button type="button" wire:click="deletePlan" wire:loading.attr="disabled" @disabled($dAccounts > 0 && $moveTo === '')
                            class="{{ $btn }} bg-rose-600 text-white disabled:opacity-50">Delete {{ $dt['name'] }}</button>
                </div>
            </x-slot:footer>
        </x-lightbox>
    @endif

</x-tri-layout>
