@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
@endphp

<x-tri-layout title="Add-ons" subtitle="The add-ons clients can switch on for their sites: wording, tier, availability and default settings."
    :labels="['📊 Numbers', '🧩 Add-ons', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$stats['activations']" label="Active add-ons across sites"
                :sub="$stats['top'] && $stats['top']['sites'] ? 'most used: '.$stats['top']['f']['name'] : 'none switched on yet'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
        <x-tile accent="lime" :value="$stats['available'].' / '.$stats['addons']" label="Available" sub="clients can switch on"
                icon="M5 13l4 4L19 7" />
        <x-tile accent="lavender" :value="$stats['premium']" label="Premium" sub="need a premium plan"
                icon="M11.48 3.5a.562.562 0 011.04 0l2.125 5.11a.563.563 0 00.475.345l5.518.442c.5.04.7.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557L3.04 10.385a.562.562 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
    </div>
    </x-slot:rail>

    <div class="@container max-w-[52rem] mx-auto grid @xl:grid-cols-2 gap-4">
        @foreach ($rows as $r)
            @php $f = $r['f']; $hidden = ! empty($f['hidden']); @endphp
            <div class="{{ $panel }} p-5 flex flex-col {{ $hidden ? 'opacity-70' : '' }}">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center shrink-0" style="background:color-mix(in srgb, var(--primary) 14%, transparent);color:var(--primary)">
                        <x-dynamic-component :component="'icons.'.($f['icon'] ?? 'puzzle')" class="w-5 h-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[15px] font-bold text-gray-900 dark:text-white">{{ $f['name'] ?? $r['key'] }}</span>
                            <span class="text-[9.5px] font-extrabold tracking-wider px-1.5 py-0.5 rounded {{ ($f['tier'] ?? 'basic') === 'premium' ? '' : 'bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300' }}"
                                  @if (($f['tier'] ?? 'basic') === 'premium') style="background:color-mix(in srgb, var(--primary) 18%, transparent);color:var(--primary)" @endif>{{ strtoupper($f['tier'] ?? 'basic') }}</span>
                            @if ($hidden)<span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-gray-200 text-gray-700 dark:bg-white/[0.1] dark:text-gray-200">Hidden</span>@endif
                            @if ($r['edited'])<span class="text-[10px] font-bold text-gray-500">edited</span>@endif
                        </p>
                        <p class="text-[12.5px] text-gray-600 dark:text-gray-300 mt-1 line-clamp-3">{{ $f['description'] ?? '' }}</p>
                    </div>
                </div>
                <div class="mt-auto pt-4 flex flex-wrap items-center gap-2 text-[12px] text-gray-600 dark:text-gray-300">
                    <span><b class="tabular-nums">{{ $r['sites'] }}</b> {{ Str::plural('site', $r['sites']) }}</span>
                    @if (! empty($f['needs_payments']))<span class="text-gray-500">· takes payments</span>@endif
                    <span class="ml-auto flex items-center gap-2">
                        <button wire:click="toggleAvailable('{{ $r['key'] }}')" class="{{ $btnOutline }}"
                                @unless ($hidden) data-confirm="Hide {{ $f['name'] }}? Sites already using it keep it; nobody else can switch it on." @endunless>
                            {{ $hidden ? 'Make available' : 'Hide' }}
                        </button>
                        <button wire:click="edit('{{ $r['key'] }}')" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Edit</button>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Sites using each</h3>
            @php $maxSites = max(1, $rows->max('sites')); @endphp
            @foreach ($rows as $r)
                <div class="flex items-center gap-3 py-1.5">
                    <span class="w-24 text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $r['f']['name'] ?? $r['key'] }}</span>
                    <span class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                        <span class="block h-full rounded-full" style="width: {{ round($r['sites'] / $maxSites * 100) }}%; background: var(--primary)"></span>
                    </span>
                    <span class="w-7 text-right text-[12.5px] font-bold tabular-nums text-gray-700 dark:text-gray-200">{{ $r['sites'] }}</span>
                </div>
            @endforeach
        </div>
        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">Good to know</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 list-disc ml-4">
                <li>Premium add-ons need a plan with premium modules (set on Plans).</li>
                <li>Hiding keeps the add-on working on sites that already use it.</li>
                <li>New default settings apply to sites that switch the add-on on from now.</li>
            </ul>
        </div>
    </x-slot:quick>

    @if ($editing && $editingDef)
        <x-side-drawer close="close" width="max-w-xl">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Edit add-on</p>
                <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white truncate">{{ $form['name'] ?: $editing }}</h2>
            </x-slot:header>
            <form id="addon-form" wire:submit="save" class="p-6 space-y-5">
                <label class="block">
                    <span class="bkf-label">Name</span>
                    <input type="text" wire:model.live.debounce.300ms="form.name" maxlength="60" class="bkf-input w-full">
                    @error('form.name')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
                <label class="block">
                    <span class="bkf-label">Description <span class="font-normal text-gray-500">(shown on the site Add-ons page)</span></span>
                    <textarea wire:model="form.description" rows="4" maxlength="600" class="bkf-input w-full"></textarea>
                    @error('form.description')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
                <div class="grid sm:grid-cols-2 gap-4 items-end">
                    <label class="block">
                        <span class="bkf-label">Tier</span>
                        <select wire:model="form.tier" class="bkf-input w-full">
                            <option value="basic">Basic (every plan)</option>
                            <option value="premium">Premium (premium plans)</option>
                        </select>
                    </label>
                    <x-field.toggle model="form.available" text="Available to clients" />
                </div>

                @if (! empty($editingDef['settings']))
                    <fieldset class="rounded-2xl border border-gray-200 dark:border-white/[0.1] p-4 space-y-4">
                        <legend class="px-1 text-[12px] font-bold text-gray-700 dark:text-gray-200">Default settings for new sites</legend>
                        @foreach ($editingDef['settings'] as $name => $field)
                            @php $type = $field['type'] ?? 'text'; @endphp
                            @if ($type === 'toggle')
                                <x-field.toggle :model="'form.defaults.'.$name" :text="$field['label'] ?? $name" />
                            @else
                                <label class="block">
                                    <span class="bkf-label">{{ $field['label'] ?? $name }}</span>
                                    @if ($type === 'select')
                                        <select wire:model="form.defaults.{{ $name }}" class="bkf-input w-full">
                                            @foreach ((array) ($field['options'] ?? []) as $opt)<option value="{{ $opt }}">{{ strtoupper($opt) }}</option>@endforeach
                                        </select>
                                    @elseif ($type === 'textarea')
                                        <textarea wire:model="form.defaults.{{ $name }}" rows="3" class="bkf-input w-full"></textarea>
                                    @else
                                        <input type="{{ $type === 'number' ? 'number' : 'text' }}" wire:model="form.defaults.{{ $name }}" class="bkf-input w-full">
                                    @endif
                                    @error('form.defaults.'.$name)<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                                </label>
                            @endif
                        @endforeach
                    </fieldset>
                @endif

                <button type="button" wire:click="resetAddon('{{ $editing }}')" data-confirm="Reset {{ $form['name'] }} to its built-in wording and settings?" class="{{ $btnOutline }}">Reset to built-in</button>
            </form>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="close" class="{{ $btnOutline }}">Cancel</button>
                    <button type="submit" form="addon-form" class="{{ $btn }} min-w-[7rem]" style="background:var(--primary);color:var(--on-primary)">Save add-on</button>
                </div>
            </x-slot:footer>
        </x-side-drawer>
    @endif
</x-tri-layout>
