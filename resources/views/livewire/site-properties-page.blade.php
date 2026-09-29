@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $input = 'w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/[0.04] px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[color:var(--primary)]/40 focus:border-[color:var(--primary)]';
    $label = 'block text-[12px] font-bold text-gray-600 dark:text-gray-300 mb-1';
    $ghostBtn = 'fx inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400';
    $iconBtn = 'w-8 h-8 grid place-items-center rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-500 hover:text-gray-900 dark:hover:text-white disabled:opacity-30';
    $imageVars = collect($variables)->where('type', 'image')->count();
    $filledPhones = collect($phones)->filter(fn ($p) => trim($p['value'] ?? '') !== '')->count();
    $filledEmails = collect($emails)->filter(fn ($e) => trim($e['value'] ?? '') !== '')->count();
    $tabs = ['identity' => 'Identity', 'contacts' => 'Contact details', 'variables' => 'Variables'];
    $err = fn (string $key) => $errors->first($key);
@endphp
<div>
<x-tri-layout title="Properties" subtitle="Your site's name, logo, contact details and custom variables — used across the site and its templates."
    :site-name="$site->name" :labels="['📊 Overview', '⚙️ Properties', '👁 Preview']" quick-width="lg:!w-[330px] xl:!w-[350px]">

    <x-slot:header>
        <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="fx inline-flex items-center gap-2 min-h-[40px] px-5 rounded-full text-sm font-bold shadow-sm"
                style="background:var(--primary);color:var(--on-primary)">
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </x-slot:header>

    {{-- ══ LEFT rail: what's filled in ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$name ?: 'Unnamed'" label="Site name" :sub="$site->name"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        <x-tile accent="lime" :value="$logo ? 'Set' : 'Missing'" label="Logo" :sub="$logo ? 'site & emails' : 'add one'"
                icon="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        <x-tile accent="sky" :value="$email ? 'Set' : 'Missing'" label="Main email" :sub="$email ? 'primary' : 'add one'"
                icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        <x-tile accent="cocoa" :value="$filledPhones" label="Phone numbers" sub="listed"
                icon="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
        <x-tile accent="rose" :value="$filledEmails" label="Other emails" sub="listed"
                icon="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
        <x-tile accent="lavender" wide :value="count($variables)" label="Custom variables"
                :sub="$imageVars.' '.\Illuminate\Support\Str::plural('image', $imageVars).' · '.(count($variables) - $imageVars).' text'"
                icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm5 3l-2 2 2 2m6-4l2 2-2 2" />
    </div>
    </x-slot:rail>

    {{-- ══ CENTER: the editor ══ --}}
    <div class="@container max-w-[52rem] mx-auto"
         x-data="{ tab: (location.hash || '#identity').slice(1) }"
         x-init="if (! @js(array_keys($tabs)).includes(tab)) tab = 'identity'; $watch('tab', t => history.replaceState(null, '', '#' + t))"
         x-on:properties-error.window="tab = $event.detail.tab">

        <div class="flex gap-1 p-1 mb-5 rounded-full bg-white/70 dark:bg-white/[0.05] shadow-sm overflow-x-auto no-scrollbar" role="tablist">
            @foreach ($tabs as $tk => $tl)
                @php $tabHasError = collect($errors->keys())->contains(fn ($k) => match ($tk) {
                    'identity' => in_array($k, ['name', 'logo', 'email', 'logoUpload']),
                    'contacts' => str_starts_with($k, 'phones') || str_starts_with($k, 'emails'),
                    'variables' => str_starts_with($k, 'variable'),
                }); @endphp
                <button type="button" role="tab" @click="tab = '{{ $tk }}'" :aria-selected="tab === '{{ $tk }}'"
                        class="shrink-0 inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-semibold transition-colors"
                        :class="tab === '{{ $tk }}' ? 'shadow-sm' : 'text-gray-600 dark:text-gray-300'"
                        :style="tab === '{{ $tk }}' ? 'background:var(--foreground);color:var(--background)' : ''">
                    {{ $tl }}
                    @if ($tabHasError)<span class="w-2 h-2 rounded-full bg-rose-500" aria-label="has errors"></span>@endif
                </button>
            @endforeach
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-2xl px-5 py-3 text-sm font-semibold bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-100 dark:border-rose-500/20">
                Some details need fixing before they can be saved — look for the red dot on the tabs.
            </div>
        @endif

        {{-- Identity --}}
        <section x-show="tab === 'identity'" class="space-y-4">
            <div class="{{ $panel }} p-6">
                <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Name & main email</h2>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mb-4">How your business appears on the site, in emails and to search engines.</p>
                <div class="grid @xl:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}" for="prop-name">Site name</label>
                        <input id="prop-name" type="text" wire:model.live.debounce.400ms="name" class="{{ $input }}" placeholder="e.g. Grace Way Church" maxlength="120">
                        @if ($m = $err('name'))<p class="text-xs text-rose-500 mt-1">{{ $m }}</p>@endif
                        <p class="text-[11px] text-gray-400 mt-1">Your web address stays <b>{{ $site->name }}</b>.</p>
                    </div>
                    <div>
                        <label class="{{ $label }}" for="prop-email">Main email address</label>
                        <input id="prop-email" type="email" wire:model.live.debounce.400ms="email" class="{{ $input }}" placeholder="hello@yourbusiness.com">
                        @if ($m = $err('email'))<p class="text-xs text-rose-500 mt-1">{{ $m }}</p>@endif
                    </div>
                </div>
            </div>

            <div class="{{ $panel }} p-6">
                <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Logo</h2>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mb-4">Shown on your site and on every email (unless the Emails page sets a different one).</p>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="w-28 h-28 rounded-2xl border border-dashed border-gray-200 dark:border-white/10 grid place-items-center overflow-hidden bg-gray-50 dark:bg-white/[0.03] shrink-0">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="Logo" class="max-w-full max-h-full object-contain p-2">
                        @else
                            <span class="text-xs text-gray-400">No logo</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-[220px] space-y-2">
                        <x-asset-picker model="logo" :site="$site" type="image" placeholder="Logo URL, or pick from assets" />
                        <div class="flex items-center gap-3">
                            <label class="{{ $ghostBtn }} cursor-pointer">
                                <span wire:loading.remove wire:target="logoUpload">⬆ Upload</span>
                                <span wire:loading wire:target="logoUpload">Uploading…</span>
                                <input type="file" wire:model="logoUpload" accept="image/*" class="hidden">
                            </label>
                            @if ($logo)
                                <button type="button" wire:click="$set('logo', '')" class="text-xs font-semibold text-rose-500 hover:text-rose-600">Remove</button>
                            @endif
                        </div>
                        @if ($m = $err('logoUpload'))<p class="text-xs text-rose-500">{{ $m }}</p>@endif
                    </div>
                </div>
            </div>
        </section>

        {{-- Contact details --}}
        <section x-show="tab === 'contacts'" x-cloak class="space-y-4">
            @foreach ([
                ['phones', 'Phone numbers', 'Add every number customers might need — give each a label such as Office, Mobile or WhatsApp.', 'Office', '+44 20 7946 0000', 'tel', 'addPhone', 'removePhone', '+ Add phone number'],
                ['emails', 'Email addresses', 'Other addresses beyond the main one — e.g. Bookings, Accounts, Support.', 'Bookings', 'bookings@yourbusiness.com', 'email', 'addEmail', 'removeEmail', '+ Add email address'],
            ] as [$prop, $title, $help, $labelPh, $valuePh, $inputType, $addAction, $removeAction, $addLabel])
                <div class="{{ $panel }} p-6">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div>
                            <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">{{ $title }}</h2>
                            <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $help }}</p>
                        </div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300 shrink-0">{{ count($$prop) }}</span>
                    </div>
                    <div class="space-y-2.5">
                        @forelse ($$prop as $i => $row)
                            <div class="flex flex-wrap @xl:flex-nowrap items-start gap-2" wire:key="{{ $prop }}-{{ $i }}">
                                <div class="w-full @xl:w-44 shrink-0">
                                    <input type="text" wire:model.blur="{{ $prop }}.{{ $i }}.label" class="{{ $input }}" placeholder="{{ $labelPh }}" aria-label="Label" maxlength="60">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <input type="{{ $inputType }}" wire:model.blur="{{ $prop }}.{{ $i }}.value" class="{{ $input }}" placeholder="{{ $valuePh }}" aria-label="{{ $inputType === 'tel' ? 'Number' : 'Email address' }}">
                                    @if ($m = $err("$prop.$i.value"))<p class="text-xs text-rose-500 mt-1">{{ $m }}</p>@endif
                                </div>
                                <button type="button" wire:click="{{ $removeAction }}({{ $i }})" class="{{ $iconBtn }} hover:!text-rose-500 mt-0.5" title="Remove">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 py-2">None yet.</p>
                        @endforelse
                    </div>
                    <button type="button" wire:click="{{ $addAction }}" class="{{ $ghostBtn }} mt-4">{{ $addLabel }}</button>
                </div>
            @endforeach
        </section>

        {{-- Custom variables --}}
        <section x-show="tab === 'variables'" x-cloak class="space-y-4">
            <div class="{{ $panel }} p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div class="max-w-xl">
                        <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Custom variables</h2>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Name any other detail your site needs — opening hours, a hero image, a registration number. Pick a type, and templates can read it by its name.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="addVariable('text')" class="{{ $ghostBtn }}">+ Text</button>
                        <button type="button" wire:click="addVariable('image')" class="{{ $ghostBtn }}">+ Image</button>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse ($variables as $i => $v)
                        <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/60 dark:bg-white/[0.02] p-4" wire:key="var-{{ $i }}">
                            <div class="flex flex-wrap @xl:flex-nowrap items-start gap-2">
                                <div class="flex-1 min-w-[160px]">
                                    <label class="{{ $label }}">Name</label>
                                    <input type="text" wire:model.blur="variables.{{ $i }}.key" class="{{ $input }} font-mono" placeholder="opening_hours" maxlength="40"
                                           x-on:input="$el.value = $el.value.toLowerCase().replace(/[^a-z0-9_]+/g, '_')">
                                    @if ($m = $err("variables.$i.key"))<p class="text-xs text-rose-500 mt-1">{{ $m }}</p>@endif
                                </div>
                                <div class="w-36 shrink-0">
                                    <label class="{{ $label }}">Type</label>
                                    <select wire:model.live="variables.{{ $i }}.type" class="{{ $input }}">
                                        @foreach ($types as $tv => $tl)
                                            <option value="{{ $tv }}">{{ $tl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-end gap-1.5 pt-5 shrink-0">
                                    <button type="button" wire:click="moveVariable({{ $i }}, -1)" class="{{ $iconBtn }}" title="Move up" @disabled($loop->first)>
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" wire:click="moveVariable({{ $i }}, 1)" class="{{ $iconBtn }}" title="Move down" @disabled($loop->last)>
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <button type="button" wire:click="removeVariable({{ $i }})" class="{{ $iconBtn }} hover:!text-rose-500" title="Remove">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="{{ $label }}">Value</label>
                                @if (($v['type'] ?? 'text') === 'image')
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="w-20 h-20 rounded-xl border border-dashed border-gray-200 dark:border-white/10 grid place-items-center overflow-hidden bg-white dark:bg-white/[0.03] shrink-0">
                                            @if ($v['value'])
                                                <img src="{{ $v['value'] }}" alt="" class="w-full h-full object-cover">
                                            @else
                                                <span class="text-[10px] text-gray-400">No image</span>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-[200px] space-y-2">
                                            <x-asset-picker :model="'variables.'.$i.'.value'" :site="$site" type="image" placeholder="Image URL, or pick from assets" />
                                            <label class="{{ $ghostBtn }} cursor-pointer">
                                                <span wire:loading.remove wire:target="variableUploads.{{ $i }}">⬆ Upload image</span>
                                                <span wire:loading wire:target="variableUploads.{{ $i }}">Uploading…</span>
                                                <input type="file" wire:model="variableUploads.{{ $i }}" accept="image/*" class="hidden">
                                            </label>
                                            @if ($m = $err("variableUploads.$i"))<p class="text-xs text-rose-500">{{ $m }}</p>@endif
                                        </div>
                                    </div>
                                @else
                                    <textarea wire:model.blur="variables.{{ $i }}.value" rows="2" class="{{ $input }}" placeholder="Mon–Fri 9am–5pm"></textarea>
                                @endif
                                @if ($m = $err("variables.$i.value"))<p class="text-xs text-rose-500 mt-1">{{ $m }}</p>@endif
                                @if (preg_match(\App\Support\SiteProperties::KEY_PATTERN, $v['key'] ?? ''))
                                    <p class="text-[11px] text-gray-400 mt-1.5">Templates read this as <code class="font-mono text-gray-600 dark:text-gray-300">properties.variables.{{ $v['key'] }}</code></p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-200 dark:border-white/10 p-8 text-center">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No custom variables yet</p>
                            <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">Add a text value or an image, and give it a name.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <div class="flex justify-end mt-5">
            <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="fx inline-flex items-center gap-2 min-h-[42px] px-6 rounded-full text-sm font-bold shadow-sm"
                    style="background:var(--primary);color:var(--on-primary)">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>

    {{-- ══ RIGHT rail: live preview + how it's used ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-3" style="color:var(--primary)">Contact card preview</p>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl grid place-items-center overflow-hidden shrink-0 {{ $logo ? 'bg-white border border-gray-100 dark:border-white/10' : '' }}"
                     @unless ($logo) style="background:var(--primary);color:var(--on-primary)" @endunless>
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="max-w-full max-h-full object-contain p-1">
                    @else
                        <span class="text-lg font-extrabold">{{ mb_strtoupper(mb_substr($name ?: $site->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="font-display text-[16px] font-extrabold text-gray-900 dark:text-white truncate">{{ $name ?: 'Your site name' }}</p>
                    <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $email ?: 'no main email yet' }}</p>
                </div>
            </div>
            @php $cardRows = collect($phones)->filter(fn ($p) => trim($p['value'] ?? '') !== '')->map(fn ($p) => ['📞', $p['label'] ?: 'Phone', $p['value']])
                ->merge(collect($emails)->filter(fn ($e) => trim($e['value'] ?? '') !== '')->map(fn ($e) => ['✉️', $e['label'] ?: 'Email', $e['value']])); @endphp
            @if ($cardRows->isNotEmpty())
                <div class="mt-4 space-y-1.5">
                    @foreach ($cardRows->take(8) as [$ic, $cl, $cv])
                        <div class="flex items-center gap-2 text-[12.5px]">
                            <span aria-hidden="true">{{ $ic }}</span>
                            <span class="text-gray-500 dark:text-gray-400 shrink-0">{{ $cl }}</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-100 truncate ml-auto">{{ $cv }}</span>
                        </div>
                    @endforeach
                    @if ($cardRows->count() > 8)<p class="text-[11px] text-gray-400">+{{ $cardRows->count() - 8 }} more</p>@endif
                </div>
            @endif
        </div>

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">Where these appear</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 leading-relaxed list-disc pl-4">
                <li>Your template's header, footer and contact sections.</li>
                <li>The logo on every email your site sends.</li>
                <li>Developers: the content API returns them as <code class="font-mono">site.properties</code>.</li>
            </ul>
            @if (collect($variables)->filter(fn ($v) => preg_match(\App\Support\SiteProperties::KEY_PATTERN, $v['key'] ?? ''))->isNotEmpty())
                <div class="mt-3 rounded-xl p-3 text-[11px] font-mono leading-relaxed overflow-x-auto" style="background:color-mix(in srgb, var(--background) 12%, transparent)">
                    @foreach (collect($variables)->filter(fn ($v) => preg_match(\App\Support\SiteProperties::KEY_PATTERN, $v['key'] ?? ''))->take(6) as $v)
                        <div class="whitespace-nowrap">variables.{{ $v['key'] }} <span class="opacity-60">· {{ $types[$v['type']] ?? 'Text' }}</span></div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([
                ['Edit site', 'place these on your pages', url($site->name.'/connect')],
                ['Emails', 'email logo & wording', url($site->name.'/emails')],
                ['Assets', 'logos & images', url($site->name.'/media')],
                ['Design', 'template & colours', url($site->name.'/design')],
            ] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
</div>
