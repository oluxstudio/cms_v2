@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $input = 'w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/[0.04] px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[color:var(--primary)]/40 focus:border-[color:var(--primary)]';
    $label = 'block text-[12px] font-bold text-gray-600 dark:text-gray-300 mb-1';
    $ghost = 'fx inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400';
    $iconBtn = 'w-8 h-8 grid place-items-center rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-500 hover:text-gray-900 dark:hover:text-white disabled:opacity-30';
    $v = $values;
    $filled = fn ($k) => trim((string) ($v[$k] ?? '')) !== '';
    $on = fn ($k) => in_array($v[$k] ?? '', ['1', 'true', 'on'], true);
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $phoneCount = collect($rows['phones'] ?? [])->filter(fn ($r) => trim($r['value'] ?? '') !== '')->count();

    // ── Overview numbers for the rail ──
    $checks = [
        'site_name' => $filled('site_name'), 'tagline' => $filled('tagline'), 'logo' => $filled('logo'),
        'square_icon' => $filled('square_icon'), 'share_image' => $filled('share_image'), 'email' => $filled('email'),
        'phone' => $phoneCount > 0, 'business_type' => $filled('business_type'), 'address' => $filled('address_street') && $filled('address_postcode'),
        'hours' => collect($days)->contains(fn ($d) => $filled("hours_{$d}")), 'meta_description' => $filled('meta_description'),
        'social' => collect(['facebook', 'instagram', 'tiktok', 'linkedin', 'x', 'youtube'])->contains(fn ($s) => $filled($s)),
    ];
    $complete = (int) round(count(array_filter($checks)) / count($checks) * 100);
    $napMissing = array_keys(array_filter(['name' => ! $filled('site_name'), 'address' => ! $checks['address'], 'phone' => ! $checks['phone']]));
    $openDays = collect($days)->filter(fn ($d) => \App\Support\SiteProperties::parseHours($v["hours_{$d}"] ?? '') !== [])->count();
    $tz = $v['timezone'] ?: 'Europe/London';
    $now = now($tz);
    $openNow = collect(\App\Support\SiteProperties::parseHours($v['hours_'.strtolower($now->englishDayOfWeek)] ?? ''))
        ->contains(fn ($r) => $now->format('H:i') >= $r[0] && $now->format('H:i') < $r[1]);
    $socialCount = collect(['facebook', 'instagram', 'tiktok', 'linkedin', 'x', 'youtube'])->filter(fn ($s) => $filled($s))->count();
    $legalCount = collect(['company_number', 'vat_number', 'ico_number'])->filter(fn ($k) => $filled($k))->count() + count($rows['accreditations'] ?? []);
    $hidden = $on('noindex') || ($on('noindex_while_draft') && ! $site->live);
    $tags = collect(['ga4_id', 'plausible_domain', 'meta_pixel_id'])->filter(fn ($k) => $filled($k))->count();
    $status = $on('maintenance') ? 'Maintenance' : ($site->live ? 'Live' : 'Offline');

    $tabErrors = collect($errors->keys())->map(function ($k) use ($fields, $repeaters) {
        $p = explode('.', $k);

        return match ($p[0]) {
            'values' => $fields[$p[1] ?? '']['tab'] ?? null,
            'rows' => $repeaters[$p[1] ?? '']['tab'] ?? null,
            'variables' => 'variables', 'colors' => 'colours', 'scripts' => 'seo', 'currency' => 'locale',
            default => null,
        };
    })->filter()->unique()->flip();

    // Fields per tab, split into titled groups (ungrouped first).
    $groupsFor = fn (string $tab) => collect($fields)->filter(fn ($f) => $f['tab'] === $tab && $f['input'] !== 'hours')
        ->groupBy(fn ($f) => $f['group'] ?? '', true);
    $tabIntro = [
        'brand' => ['Brand', 'Your name and look — used on the site, in emails, browser tabs and when your site is shared.'],
        'business' => ['Business details', 'What you do and where — powers your contact pages, maps and Google\'s business listing.'],
        'contact' => ['Contact', 'How customers reach you.'],
        'legal' => ['Company details', 'UK registration details shown in your footer and used for trust signals.'],
        'seo' => ['Search engines', 'Defaults for Google and other search engines.'],
        'locale' => ['Language & region', 'How dates, times and prices are shown.'],
        'assistant' => ['AI assistant', 'Personality and limits for the assistant that answers questions about your business.'],
    ];
    // Same address the site is really served at: verified live custom domain → {name}.subdomain.
    $siteHost = $site->live && $site->domain && $site->domain_verified_at
        ? $site->domain
        : ($site->subdomainHost() ?: $site->name.'.oluxstudio.com');
    $snippetTitle = str_replace(['{page}', '{site}'], ['Home', $v['site_name'] ?: $site->name], $v['title_pattern'] ?: '{page} | {site}');
    $sub = $site->user?->currentSubscription();
@endphp
<div>
<x-tri-layout title="Properties" subtitle="Your business profile — used across the site, its templates, emails and search engines. Also editable on the Edit page."
    :site-name="$site->name" :labels="['📊 Overview', '⚙️ Properties', '👁 Preview']" quick-width="lg:!w-[330px] xl:!w-[350px]">

    <x-slot:header>
        <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="fx inline-flex items-center gap-2 min-h-[40px] px-5 rounded-full text-sm font-bold shadow-sm"
                style="background:var(--primary);color:var(--on-primary)">
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </x-slot:header>

    {{-- ══ LEFT rail: how complete the profile is ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$complete.'%'" label="Profile complete" :sub="$v['site_name'] ?: $site->name"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lime" :value="$napMissing ? 'Missing' : 'Complete'" label="Business listing" :sub="$napMissing ? 'add '.$napMissing[0] : 'all set'"
                icon="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        <x-tile accent="sky" :value="$openDays ? $openDays.' '.\Illuminate\Support\Str::plural('day', $openDays) : 'Not set'" label="Opening hours" :sub="$openDays ? ($openNow ? 'open now' : 'closed now') : 'add hours'"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$socialCount" label="Social profiles" sub="linked"
                icon="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
        <x-tile accent="cocoa" :value="$legalCount" label="Legal details" sub="on record"
                icon="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
        <x-tile accent="rose" wide :value="$hidden ? 'Hidden' : 'Visible'" label="Search engines" :sub="$hidden ? ($on('noindex') ? 'noindex on' : 'until the site is live') : ($tags ? $tags.' tracking tag'.($tags > 1 ? 's' : '') : 'no tracking tags')"
                icon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        <x-tile accent="sky" :value="$status" label="Site status" :sub="$filled('launch_date') ? \Illuminate\Support\Carbon::parse($v['launch_date'])->format('j M') : ($site->live ? 'online' : 'draft')"
                icon="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9" />
        <x-tile accent="lime" :value="count($variables)" label="Custom variables" :sub="collect($variables)->where('type', 'image')->count().' img'"
                icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm5 3l-2 2 2 2m6-4l2 2-2 2" />
    </div>
    </x-slot:rail>

    {{-- ══ CENTER: pill tabs ══ --}}
    <div class="@container max-w-[52rem] mx-auto"
         x-data="{ tab: (location.hash || '#brand').slice(1) }"
         x-init="if (! @js(array_keys($tabs)).includes(tab)) tab = 'brand'; $watch('tab', t => history.replaceState(null, '', '#' + t))"
         x-on:properties-error.window="tab = $event.detail.tab">

        <x-pill-tabs :tabs="$tabs" :dots="$tabErrors->keys()->all()" />

        @if ($errors->any())
            <div class="mb-4 rounded-2xl px-5 py-3 text-sm font-semibold bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-100 dark:border-rose-500/20">
                Some details need fixing before they can be saved — look for the red dot on the tabs.
            </div>
        @endif

        {{-- Standard tabs: grouped schema fields + that tab's repeaters --}}
        @foreach (['brand', 'business', 'contact', 'legal', 'seo', 'locale', 'assistant'] as $tk)
            <section x-show="tab === '{{ $tk }}'" @if ($tk !== 'brand') x-cloak @endif class="space-y-4">
                @if ($tk === 'locale')
                    <div class="{{ $panel }} p-6">
                        <label class="{{ $label }}" for="p-currency">Currency</label>
                        <select id="p-currency" wire:model="currency" class="{{ $input }} max-w-xs">
                            @foreach (['gbp' => 'GBP — Pound sterling', 'eur' => 'EUR — Euro', 'usd' => 'USD — US dollar', 'cad' => 'CAD — Canadian dollar', 'aud' => 'AUD — Australian dollar', 'ngn' => 'NGN — Naira', 'zar' => 'ZAR — Rand', 'ghs' => 'GHS — Cedi', 'kes' => 'KES — Kenyan shilling', 'inr' => 'INR — Rupee'] as $cv => $cl)
                                <option value="{{ $cv }}">{{ $cl }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Prices, checkout and invoices use this currency. Same setting as on the Payments page.</p>
                    </div>
                @endif

                @foreach ($groupsFor($tk) as $group => $groupFields)
                    <div class="{{ $panel }} p-6">
                        @if ($group === '' && isset($tabIntro[$tk]))
                            <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">{{ $tabIntro[$tk][0] }}</h2>
                            <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mb-4">{{ $tabIntro[$tk][1] }}</p>
                        @elseif ($group !== '')
                            <h2 class="text-[16px] font-bold text-gray-900 dark:text-white mb-4">{{ $group }}</h2>
                        @endif
                        <div class="grid @xl:grid-cols-2 gap-4">
                            @foreach ($groupFields as $key => $f)
                                <div class="{{ in_array($f['input'], ['textarea', 'image'], true) || in_array($key, ['service_area', 'title_pattern'], true) ? '@xl:col-span-2' : '' }}" wire:key="f-{{ $key }}">
                                    @include('partials.properties.field', ['f' => $f, 'model' => "values.$key", 'value' => $v[$key] ?? ''])
                                    @include('partials.properties.linked-hint', ['prop' => $key])
                                    @if ($key === 'registered_office')
                                        <button type="button" wire:click="copyAddressToOffice" class="text-[11px] font-bold mt-1" style="color:var(--primary)">Same as trading address</button>
                                    @endif
                                    @if ($key === 'square_icon' && $icons)
                                        <div class="flex items-end gap-2 mt-2">
                                            @foreach ($icons as $size => $url)
                                                <img src="{{ $url }}" alt="{{ $size }}px icon" title="{{ $size }}×{{ $size }}" class="rounded border border-gray-100 dark:border-white/10" style="width:{{ min(40, max(16, (int) $size / 8)) }}px;height:auto">
                                            @endforeach
                                            <span class="text-[11px] text-gray-400">favicon & app icons made from this</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @foreach ($repeaters as $rk => $r)
                    @if ($r['tab'] === $tk)
                        @include('partials.properties.repeater', ['key' => $rk, 'r' => $r, 'rows' => $rows, 'site' => $site, 'panel' => $panel])
                        @if ($rk === 'phones')
                            <div class="-mt-2 px-1">@include('partials.properties.linked-hint', ['prop' => 'phone', 'note' => 'The first number'])</div>
                        @endif
                    @endif
                @endforeach

                @if ($tk === 'seo')
                    <div class="{{ $panel }} p-6">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Custom scripts</h2>
                                <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Code added to every page — chat widgets, extra analytics. Only paste code from services you trust.</p>
                            </div>
                            <span class="text-[10px] font-extrabold tracking-wider px-2 py-0.5 rounded shrink-0" style="background:color-mix(in srgb, var(--primary) 18%, transparent);color:var(--primary)">PRO</span>
                        </div>
                        @if ($this->canEditScripts)
                            <div class="grid gap-4">
                                <div>
                                    <label class="{{ $label }}" for="p-head">In the page head</label>
                                    <textarea id="p-head" wire:model.blur="scripts.head" rows="4" class="{{ $input }} font-mono text-xs" placeholder="<script src=&quot;https://…&quot;></script>"></textarea>
                                </div>
                                <div>
                                    <label class="{{ $label }}" for="p-body">Before the closing body tag</label>
                                    <textarea id="p-body" wire:model.blur="scripts.body" rows="4" class="{{ $input }} font-mono text-xs"></textarea>
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $sub?->allowsPremium() ? 'Only the account owner or a team admin can edit custom scripts.' : 'Available on plans with premium features.' }}
                                @unless ($sub?->allowsPremium())<a href="{{ route('account.subscription') }}" class="font-bold" style="color:var(--primary)">See plans →</a>@endunless
                            </p>
                        @endif
                    </div>
                @endif
            </section>
        @endforeach

        {{-- Hours: the week + special closures --}}
        <section x-show="tab === 'hours'" x-cloak class="space-y-4">
            <div class="{{ $panel }} p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Opening hours</h2>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Type times like <b>09:00-17:00</b>. Split days: <b>09:00-12:00, 13:00-17:00</b>. Leave empty if you'd rather not show hours.</p>
                    </div>
                    <button type="button" wire:click="copyHoursToWeekdays" class="{{ $ghost }}">Copy Monday to weekdays</button>
                </div>
                <div class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                    @foreach ($days as $d)
                        @php $dk = "hours_{$d}"; @endphp
                        <div class="flex flex-wrap @xl:flex-nowrap items-center gap-2 py-2" wire:key="h-{{ $d }}">
                            <span class="w-28 shrink-0 text-sm font-bold text-gray-800 dark:text-gray-100">{{ ucfirst($d) }}</span>
                            <div class="flex-1 min-w-[180px]">
                                @include('partials.properties.field', ['f' => $fields[$dk] + ['placeholder' => '09:00-17:00'], 'model' => "values.$dk", 'value' => $v[$dk] ?? '', 'hideLabel' => true])
                            </div>
                            <div class="flex gap-1.5 shrink-0">
                                <button type="button" wire:click="$set('values.{{ $dk }}', '09:00-17:00')" class="{{ $ghost }}">9–5</button>
                                <button type="button" wire:click="$set('values.{{ $dk }}', 'Closed')" class="{{ $ghost }}">Closed</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @include('partials.properties.repeater', ['key' => 'closures', 'r' => $repeaters['closures'], 'rows' => $rows, 'site' => $site, 'panel' => $panel])
        </section>

        {{-- Colours: the template's own CSS colour variables, applied live on the site --}}
        <section x-show="tab === 'colours'" x-cloak class="space-y-4">
            <div class="{{ $panel }} p-6">
                <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Theme colours</h2>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mb-5">The colours your template is built from. Pick a new one and save — the whole site updates, nothing to rebuild.</p>
                @php $colorDefaults = \App\Support\SiteColors::defaults($site); @endphp
                @forelse ($colors as $name => $value)
                    @php $isHex = (bool) preg_match('/^#[0-9a-fA-F]{6}$/', trim((string) $value)); @endphp
                    <div class="flex flex-wrap items-center gap-3 py-3 border-t border-gray-100 dark:border-white/5 first:border-t-0" wire:key="color-{{ $name }}">
                        <div class="w-40 shrink-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ \App\Support\SiteColors::label($name) }}</p>
                            <code class="text-[11px] font-mono text-gray-400">--{{ $name }}</code>
                        </div>
                        <div class="flex items-center gap-2 flex-1 min-w-[12rem]" x-data>
                            <input type="color" value="{{ $isHex ? $value : '#888888' }}" title="Pick a colour"
                                   class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-200 dark:border-white/10 bg-transparent p-1"
                                   x-on:input="$refs.txt.value = $event.target.value; $refs.txt.dispatchEvent(new Event('input', { bubbles: true }))">
                            <input type="text" x-ref="txt" wire:model="colors.{{ $name }}" class="{{ $input }} font-mono" placeholder="{{ $colorDefaults[$name] ?? '' }}" maxlength="60"
                                   x-on:input="if (/^#[0-9a-f]{6}$/i.test($event.target.value)) $el.previousElementSibling.value = $event.target.value">
                        </div>
                        @if (strcasecmp(trim((string) $value), $colorDefaults[$name] ?? '') !== 0)
                            <button type="button" class="text-[12px] font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-white"
                                    wire:click="$set('colors.{{ $name }}', @js($colorDefaults[$name] ?? ''))" title="Back to the template's {{ $colorDefaults[$name] ?? '' }}">Reset</button>
                        @endif
                        @if ($m = $errors->first("colors.$name"))<p class="w-full text-xs text-rose-500">{{ $m }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">This site's template doesn't define any colour variables yet. Templates list them as <code class="font-mono">--color-*</code> variables on <code class="font-mono">:root</code>.</p>
                @endforelse
            </div>
        </section>

        {{-- Variables: any other node on the Site Properties component --}}
        <section x-show="tab === 'variables'" x-cloak class="space-y-4">
            {{-- Reusable values: every property + custom variable as a {{token}} usable in any content --}}
            <div class="{{ $panel }} p-6" x-data="{ copied: '' }">
                <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Reusable values</h2>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mb-4">
                    Type a token into any text on the Edit page or in a collection entry — e.g. <code class="font-mono text-gray-700 dark:text-gray-200">Call us on @{{phone}}</code> —
                    and your live site shows the current value. Change it here once and every place updates.
                </p>
                <div class="divide-y divide-gray-100 dark:divide-white/[0.06] rounded-2xl border border-gray-100 dark:border-white/[0.08] overflow-hidden">
                    @foreach ($tokens as $t)
                        @php $code = str_repeat('{', 2).$t['token'].str_repeat('}', 2); @endphp
                        <div class="flex items-center gap-3 px-4 py-2.5" wire:key="tok-{{ $t['token'] }}">
                            <button type="button" @click="navigator.clipboard.writeText(@js($code)); copied = @js($t['token']); setTimeout(() => copied = '', 1500)"
                                    class="shrink-0 font-mono text-[12.5px] font-semibold px-2.5 py-1 rounded-lg border {{ $t['custom'] ? 'border-indigo-200 dark:border-indigo-500/30' : 'border-gray-200 dark:border-white/[0.1]' }} bg-white dark:bg-[#1d1e2a] text-gray-800 dark:text-gray-100 hover:border-[var(--primary)]"
                                    title="Copy {{ $code }}">
                                <span x-show="copied !== @js($t['token'])">{{ $code }}</span>
                                <span x-show="copied === @js($t['token'])" x-cloak style="color:var(--primary)">Copied!</span>
                            </button>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[12px] text-gray-500 dark:text-gray-400">{{ $t['label'] }}@if ($t['custom']) <span class="text-indigo-500">· your variable</span>@endif</span>
                                <span class="block text-[13px] text-gray-800 dark:text-gray-100 truncate" title="{{ $t['value'] }}">{{ $t['value'] !== '' ? $t['value'] : '—' }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-2">Values shown are the saved ones — save to update them. Tokens nobody recognises are left as typed.</p>
            </div>

            <div class="{{ $panel }} p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div class="max-w-xl">
                        <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Custom variables</h2>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Name anything else your site needs — a hero image, a registration line. Fields added to "Site Properties" on the Edit page appear here too.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="addVariable('text')" class="{{ $ghost }}">+ Text</button>
                        <button type="button" wire:click="addVariable('image')" class="{{ $ghost }}">+ Image</button>
                    </div>
                </div>
                <div class="space-y-3">
                    @forelse ($variables as $i => $var)
                        <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/60 dark:bg-white/[0.02] p-4" wire:key="var-{{ $i }}">
                            <div class="flex flex-wrap @xl:flex-nowrap items-start gap-2">
                                <div class="flex-1 min-w-[160px]">
                                    <label class="{{ $label }}">Name</label>
                                    <input type="text" wire:model.blur="variables.{{ $i }}.key" class="{{ $input }}" placeholder="Opening line" maxlength="60">
                                    @if ($m = $errors->first("variables.$i.key"))<p class="text-xs text-rose-500 mt-1">{{ $m }}</p>@endif
                                </div>
                                <div class="w-36 shrink-0">
                                    <label class="{{ $label }}">Type</label>
                                    <select wire:model.live="variables.{{ $i }}.type" class="{{ $input }}">
                                        @foreach ($types as $tv => $tl)<option value="{{ $tv }}">{{ $tl }}</option>@endforeach
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
                                @if (($var['type'] ?? 'text') === 'image')
                                    @include('partials.properties.field', ['f' => ['label' => 'Value', 'input' => 'image'], 'model' => "variables.$i.value", 'value' => $var['value'] ?? ''])
                                @else
                                    <label class="{{ $label }}">Value</label>
                                    <textarea wire:model.blur="variables.{{ $i }}.value" rows="2" class="{{ $input }}"></textarea>
                                @endif
                                @if (preg_match(\App\Support\SiteProperties::KEY_PATTERN, $var['key'] ?? ''))
                                    <p class="text-[11px] text-gray-400 mt-1.5">Use it in any content as <code class="font-mono text-gray-700 dark:text-gray-200">{{ str_repeat('{', 2).\App\Support\SiteTokens::key($var['key']).str_repeat('}', 2) }}</code> — templates can also read <code class="font-mono text-gray-600 dark:text-gray-300">properties.variables["{{ $var['key'] }}"]</code></p>
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

    {{-- ══ RIGHT rail: previews + where the rest lives ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-3" style="color:var(--primary)">Contact card preview</p>
            <div class="flex items-center gap-3">
                @php $logoSrc = \App\Support\SiteProperties::imageUrl($site, $v['logo']); @endphp
                <div class="w-12 h-12 rounded-xl grid place-items-center overflow-hidden shrink-0 {{ $logoSrc ? 'bg-white border border-gray-100 dark:border-white/10' : '' }}"
                     @unless ($logoSrc) style="background:var(--primary);color:var(--on-primary)" @endunless>
                    @if ($logoSrc)
                        <img src="{{ $logoSrc }}" alt="" class="max-w-full max-h-full object-contain p-1">
                    @else
                        <span class="text-lg font-extrabold">{{ mb_strtoupper(mb_substr($v['site_name'] ?: $site->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="font-display text-[16px] font-extrabold text-gray-900 dark:text-white truncate">{{ $v['site_name'] ?: 'Your site name' }}</p>
                    <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $v['tagline'] ?: ($v['email'] ?: 'no main email yet') }}</p>
                </div>
            </div>
            @php
                $cardRows = collect($rows['phones'] ?? [])->filter(fn ($p) => trim($p['value'] ?? '') !== '')->map(fn ($p) => ['📞', $p['label'] ?: 'Phone', $p['value']])
                    ->when($v['email'], fn ($c) => $c->push(['✉️', 'Email', $v['email']]))
                    ->when($checks['address'], fn ($c) => $c->push(['📍', 'Address', collect([$v['address_street'], $v['address_town'], $v['address_postcode']])->filter()->implode(', ')]));
            @endphp
            @if ($cardRows->isNotEmpty())
                <div class="mt-4 space-y-1.5">
                    @foreach ($cardRows->take(6) as [$ic, $cl, $cv])
                        <div class="flex items-center gap-2 text-[12.5px]">
                            <span aria-hidden="true">{{ $ic }}</span>
                            <span class="text-gray-500 dark:text-gray-400 shrink-0">{{ $cl }}</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-100 truncate ml-auto">{{ $cv }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-3" style="color:var(--primary)">In Google search</p>
            <p class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">{{ $siteHost }}</p>
            <p class="text-[15px] font-semibold text-[#1a0dab] dark:text-[#8ab4f8] leading-snug line-clamp-2">{{ $snippetTitle }}</p>
            <p class="text-[12.5px] text-gray-600 dark:text-gray-300 line-clamp-3 mt-0.5">{{ $v['meta_description'] ?: ($v['description'] ?: 'Add a meta description so search engines show your own summary here.') }}</p>
            @if ($hidden)<p class="text-[11px] font-bold text-rose-500 mt-2">Hidden from search engines right now.</p>@endif
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Managed elsewhere</h3>
            @foreach ([
                ['Domain', $site->domain ? $site->domain.($site->domain_verified_at ? ' · connected' : ' · not verified') : 'Free address only', url($site->name.'/publish')],
                ['Status', $site->live ? 'Live' : 'Offline', url($site->name.'/publish')],
                ['Plan', $sub?->tier()['name'] ?? '—', route('account.subscription')],
                ['Template', \Illuminate\Support\Str::headline($site->template ?: 'none'), url($site->name.'/design')],
                ['Colours & fonts', 'in edit mode', url($site->name.'/connect')],
                ['Team', $site->teamUsers()->count().' people', url($site->name.'/team')],
            ] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">Same data, two places</h3>
            <p class="mt-1.5 text-[12.5px] opacity-85 leading-relaxed">These details live in the <b>Site Properties</b> component. Change them here or in edit mode — both update the other.</p>
            <a href="{{ url($site->name.'/connect') }}?properties=1" class="inline-block mt-2 text-[12px] font-bold underline underline-offset-2">Open in edit mode →</a>
        </div>
    </x-slot:quick>
</x-tri-layout>
</div>
