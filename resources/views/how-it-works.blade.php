<x-layouts.page>
    <x-slot:title>Getting started — Olux</x-slot>

    @php
        $gbp = fn (int $c) => \App\Support\Money::format($c, 'gbp');
        $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
        $to = fn (string $seg) => $site ? url($site->name.'/'.$seg) : null;
        $tier = $sub->tier();
        $limit = fn ($v, string $unit = '') => $v === null ? 'Unlimited' : number_format($v).$unit;
        $storage = fn ($mb) => $mb === null ? 'Unlimited' : ($mb >= 1024 ? round($mb / 1024, 1).' GB' : $mb.' MB');

        $concepts = [
            ['🏠', 'Account', 'You. Your plan, billing and every site you own live under one account.'],
            ['🌐', 'Site', 'One website, with its own pages, content, contacts, team and settings. Your plan sets how many you can have.'],
            ['🎨', 'Template', 'The design your site uses. Change it any time from the site\'s Design page; your pages and content stay.'],
            ['✏️', 'Edit mode', 'Your live site with editing switched on: click any section to change its words and pictures. Checkpoints let you undo.'],
            ['📄', 'Pages & posts', 'The pages of your site, plus blog-style posts and collections (lists like services, team members or events).'],
            ['📥', 'Forms & contacts', 'Every form a visitor fills in becomes a contact in your built-in CRM, and you get an alert.'],
            ['🧩', 'Add-ons', 'Extra features you switch on per site: bookings, an online store, invoices, donations, a cost estimator, polls.'],
            ['👥', 'Team', 'Invite people to help, each with a role that decides what they can see and change.'],
            ['🚀', 'Going live', 'Every site gets a free web address. Buy your own domain or connect one you already have when you\'re ready.'],
        ];

        // The five checklist steps (App\Support\Onboarding), expanded.
        $journey = [
            'create_site' => ['What it is', [
                'A site is one website: its pages, content, contacts, team and settings all live inside it.',
                'Click "+ New Site" on your sites page and give it a name (your business name works well). The name becomes part of its free web address.',
                'Your plan decides how many sites you can have; you can add more later.',
            ], route('home'), 'Go to your sites'],
            'choose_template' => ['The look of your site', [
                'A template is a finished design with pages already laid out. Browse them in the Templates store and add one to your library.',
                'Apply it from the site\'s Design page. A restore point is saved first, so you can switch back in one click.',
                'Changing template later keeps your pages, text, contacts and bookings.',
            ], $to('design'), 'Open Design'],
            'update_content' => ['Make it about you', [
                'Open edit mode: it shows your real site with editing switched on.',
                'Click any section to change its words, pictures and links; every save is kept as a checkpoint you can go back to.',
                'Add or remove pages from the page list, and switch on extras like a contact form or bookings when you need them.',
            ], $to('connect'), 'Open edit mode'],
            'get_domain' => ['Your own web address', [
                'Every site already works on a free Olux address, so this step is optional but recommended.',
                'On the Go live page, search for a name and buy it — it\'s connected for you automatically, with the padlock (https) included.',
                'Already own one? Keep it where it is and add the two records the Go live page shows you; we check them for you.',
            ], $to('publish'), 'Get a domain'],
            'go_live' => ['Open for visitors', [
                'When your pages are ready, switch the site to live on the Go live page.',
                'Visitors can then find it on your address; forms, bookings and contacts start flowing into your dashboard.',
                'You can keep editing after going live — changes appear straight away.',
            ], $to('publish'), 'Go live'],
        ];

        $faqs = [
            ['Can I change my template later?', 'Yes. Apply any template in your library from the site\'s Design page. We save a restore point first, and your pages, text, contacts and bookings stay.'],
            ['Do I need to buy a domain?', 'No. Every site works on a free Olux address. A domain of your own (like yoursalon.co.uk) is optional and can be bought or connected at any time.'],
            ['What happens when my free trial ends?', 'Pick a plan to keep going. Nothing you built is deleted; your sites unlock again as soon as you choose a plan.'],
            ['Can I change or cancel my plan?', 'Yes, any time from Account › Subscription. Changes apply straight away.'],
            ['Who can edit my site?', 'You, and anyone you invite to the site\'s team. Each teammate\'s role decides which pages and tools they can use.'],
            ['Where do form submissions and bookings go?', 'Into the site\'s Contacts (your CRM), with an alert to you. Bookings also appear in the Bookings diary.'],
        ];
    @endphp

    <x-tri-layout title="Getting started" subtitle="Everything you need to know to build, run and grow your sites on Olux."
        :labels="['📊 You', '📘 Guide', '✅ Checklist']" quick-width="lg:!w-[320px] xl:!w-[340px]">

        <x-slot:header>
            <a href="{{ route('home') }}" class="fx inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-full text-sm font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200">
                ← Back to your sites
            </a>
        </x-slot:header>

        {{-- ══ LEFT rail: where you are ══ --}}
        <x-slot:rail>
        <div class="grid grid-cols-2 gap-3">
            <x-tile accent="ink" wide :value="$tier['name'] ?? 'Free trial'" label="Your plan"
                    :sub="$sub->onTrial() ? $sub->trialDaysLeft().' days left on trial' : ($sub->trialExpired() ? 'trial ended' : 'active')"
                    style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                    :href="route('account.subscription')"
                    icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            <x-tile accent="lime" :value="$progress['done'].' / '.$progress['total']" label="Setup steps" :sub="$progress['complete'] ? 'all done' : 'keep going'"
                    icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            <x-tile accent="sky" :value="$siteCount.' / '.$limit($tier['limits']['sites'] ?? null)" label="Sites" sub="used / allowed"
                    icon="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9" />
            <x-tile accent="lavender" :value="$storage($tier['limits']['storage_mb'] ?? null)" label="Storage" sub="for images & files"
                    icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z" />
            <x-tile accent="cocoa" :value="! empty($tier['limits']['premium']) ? 'Included' : 'Not included'" label="Premium add-ons" :sub="! empty($tier['limits']['premium']) ? 'on your plan' : 'upgrade to unlock'"
                    icon="M11.48 3.5a.562.562 0 011.04 0l2.125 5.11a.563.563 0 00.475.345l5.518.442c.5.04.7.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557L3.04 10.385a.562.562 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
        </div>
        </x-slot:rail>

        {{-- ══ CENTER: the guide ══ --}}
        @php $tabs = ['overview' => 'Overview', 'steps' => 'Step by step', 'plans' => 'Plans', 'addons' => 'Add-ons', 'live' => 'Going live', 'faq' => 'FAQ']; @endphp
        <div class="@container max-w-[52rem] mx-auto"
             x-data="{ tab: (location.hash || '#overview').slice(1) }"
             x-init="if (! @js(array_keys($tabs)).includes(tab)) tab = 'overview'; $watch('tab', t => history.replaceState(null, '', '#' + t))">

            <x-pill-tabs :tabs="$tabs" />

            {{-- Overview --}}
            <section x-show="tab === 'overview'" class="space-y-4">
                <div class="{{ $panel }} p-6">
                    <p class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">Olux in one minute</p>
                    <h2 class="font-display text-2xl font-extrabold text-gray-900 dark:text-white mt-1">Your website and your customers, in one place</h2>
                    <p class="mt-2 text-[14.5px] text-gray-700 dark:text-gray-200 leading-relaxed">
                        Olux builds your website from a professionally designed template, lets you edit it by clicking on it,
                        and keeps everyone who contacts you, books or buys in a built-in CRM. Add bookings, a shop or invoices
                        when you need them, invite your team, and go live on your own domain.
                    </p>
                    <div class="mt-5 grid @xl:grid-cols-4 grid-cols-2 gap-3">
                        @foreach ([['🎨', 'Build', 'Pick a template, click to edit'], ['📥', 'Capture', 'Forms become contacts'], ['💳', 'Sell & book', 'Bookings, store, invoices'], ['🚀', 'Go live', 'Free address or your domain']] as [$i, $t, $d])
                            <div class="rounded-2xl p-4" style="background:var(--primary-soft)">
                                <span class="text-2xl" aria-hidden="true">{{ $i }}</span>
                                <p class="mt-1 text-[14px] font-bold text-gray-900 dark:text-white">{{ $t }}</p>
                                <p class="text-[12px] text-gray-600 dark:text-gray-300">{{ $d }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="{{ $panel }} p-6">
                    <h2 class="text-[16px] font-bold text-gray-900 dark:text-white mb-3">The words you'll see</h2>
                    <dl class="grid @xl:grid-cols-2 gap-x-6 gap-y-4">
                        @foreach ($concepts as [$icon, $term, $def])
                            <div class="flex gap-3">
                                <span class="w-9 h-9 rounded-xl grid place-items-center shrink-0 text-base" style="background:var(--primary-soft)" aria-hidden="true">{{ $icon }}</span>
                                <div>
                                    <dt class="text-[14px] font-bold text-gray-900 dark:text-white">{{ $term }}</dt>
                                    <dd class="text-[13px] text-gray-600 dark:text-gray-300 leading-relaxed">{{ $def }}</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            {{-- Step by step: the checklist, expanded --}}
            <section x-show="tab === 'steps'" x-cloak class="space-y-3">
                <p class="text-[14px] text-gray-700 dark:text-gray-200">The five steps from sign-up to a live website. Ticks show what you've already done.</p>
                @foreach ($steps as $i => $step)
                    @php [$kicker, $points, $url, $label] = $journey[$step['key']] ?? ['', [], null, '']; @endphp
                    <div class="{{ $panel }} p-5 flex items-start gap-4">
                        <span class="w-10 h-10 rounded-2xl grid place-items-center shrink-0 font-display text-lg font-extrabold"
                              style="{{ $step['done'] ? 'background:#16a34a;color:#fff' : 'background:var(--primary);color:var(--on-primary)' }}">{!! $step['done'] ? '&#10003;' : $i + 1 !!}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Step {{ $i + 1 }} · {{ $kicker }}</p>
                            <h3 class="text-[16px] font-bold text-gray-900 dark:text-white">
                                {{ $step['label'] }}
                                @if ($step['done'])<span class="ml-1 text-[11px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 align-middle">Done</span>@endif
                            </h3>
                            <ul class="mt-2 space-y-1.5 text-[13.5px] text-gray-700 dark:text-gray-200 leading-relaxed">
                                @foreach ($points as $point)
                                    <li class="flex gap-2"><span class="mt-[7px] w-1.5 h-1.5 rounded-full shrink-0" style="background:var(--primary)"></span>{{ $point }}</li>
                                @endforeach
                            </ul>
                            @if ($url)
                                <a href="{{ $url }}" class="inline-block mt-3 text-[13px] font-bold" style="color:var(--primary)">{{ $label }} →</a>
                            @else
                                <span class="inline-block mt-3 text-[12px] text-gray-500">Create a site first to unlock this.</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>

            {{-- Plans --}}
            <section x-show="tab === 'plans'" x-cloak>
                <p class="text-[14px] text-gray-700 dark:text-gray-200 mb-4">
                    Every account starts with a free trial with everything switched on. Pick a plan before it ends to keep going;
                    you can change plan any time.
                </p>
                <div class="grid @xl:grid-cols-2 gap-4">
                    @foreach ($tiers as $key => $t)
                        @php $current = $key === $sub->plan; @endphp
                        <div class="{{ $panel }} p-5 flex flex-col {{ $current ? 'ring-2' : '' }}" @if ($current) style="--tw-ring-color:var(--primary)" @endif>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-display text-[18px] font-extrabold text-gray-900 dark:text-white">{{ $t['name'] ?? $key }}</p>
                                    <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $t['tagline'] ?? '' }}</p>
                                </div>
                                @if ($current)
                                    <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:var(--primary);color:var(--on-primary)">Your plan</span>
                                @elseif (! empty($t['highlight']))
                                    <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full bg-gray-900 text-white dark:bg-white dark:text-gray-900">Most popular</span>
                                @endif
                            </div>
                            <p class="mt-3">
                                @if (($t['price_cents'] ?? 0) && ! empty($t['price_prefix']))<span class="text-[12px] font-bold text-gray-500">{{ $t['price_prefix'] }} </span>@endif
                                <span class="font-display text-2xl font-extrabold text-gray-900 dark:text-white">{{ ($t['price_cents'] ?? 0) ? $gbp((int) $t['price_cents']) : 'Free' }}</span>
                                @if ($t['price_cents'] ?? 0)<span class="text-[12px] text-gray-500"> / month</span>@endif
                            </p>
                            <ul class="mt-3 space-y-1.5 text-[13px] text-gray-700 dark:text-gray-200 flex-1">
                                @foreach ((array) ($t['features'] ?? []) as $feature)
                                    <li class="flex items-start gap-2"><span class="mt-0.5 font-bold" style="color:var(--primary)">✓</span>{{ $feature }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-3 text-[11.5px] text-gray-500 dark:text-gray-400">
                                {{ $limit($t['limits']['sites'] ?? null) }} {{ ($t['limits']['sites'] ?? 2) === 1 ? 'site' : 'sites' }}
                                · {{ $storage($t['limits']['storage_mb'] ?? null) }} storage
                                · {{ array_key_exists('mailboxes', $t['limits'] ?? []) ? (($t['limits']['mailboxes'] ?? null) === null ? 'custom mailboxes' : (($t['limits']['mailboxes'] ?: 'no').' '.Str::plural('mailbox', (int) ($t['limits']['mailboxes'] ?: 2)))) : '' }}
                            </p>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('account.subscription') }}" class="fx mt-5 inline-flex items-center min-h-[44px] px-5 rounded-xl text-[14px] font-bold" style="background:var(--primary);color:var(--on-primary)">
                    {{ $sub->onTrial() || $sub->trialExpired() ? 'Choose a plan' : 'Manage your plan' }}
                </a>
            </section>

            {{-- Add-ons --}}
            <section x-show="tab === 'addons'" x-cloak>
                <p class="text-[14px] text-gray-700 dark:text-gray-200 mb-4">Switch these on per site from its Add-ons page. Anything that takes money needs Stripe connected on the site's Payments page.</p>
                <div class="grid @xl:grid-cols-2 gap-3">
                    @foreach ($addons as $key => $f)
                        @php $premium = ($f['tier'] ?? 'basic') === 'premium'; @endphp
                        <div class="{{ $panel }} p-4 flex gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center shrink-0" style="background:var(--primary-soft);color:var(--primary)">
                                <x-dynamic-component :component="'icons.'.($f['icon'] ?? 'puzzle')" class="w-5 h-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[14px] font-bold text-gray-900 dark:text-white">{{ $f['name'] ?? $key }}</span>
                                    <span class="text-[9.5px] font-extrabold tracking-wider px-1.5 py-0.5 rounded {{ $premium ? '' : 'bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300' }}"
                                          @if ($premium) style="background:color-mix(in srgb, var(--primary) 18%, transparent);color:var(--primary)" @endif>{{ $premium ? 'PREMIUM' : 'BASIC' }}</span>
                                    @if (! empty($f['needs_payments']))<span class="text-[11px] text-gray-500">takes payments</span>@endif
                                </p>
                                <p class="text-[12.5px] text-gray-600 dark:text-gray-300 mt-0.5 leading-relaxed">{{ $f['description'] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($site)
                    <a href="{{ $to('addons') }}" class="inline-block mt-4 text-[13px] font-bold" style="color:var(--primary)">Open Add-ons for {{ $site->name }} →</a>
                @endif
            </section>

            {{-- Going live --}}
            <section x-show="tab === 'live'" x-cloak class="space-y-3">
                @foreach ([
                    ['Free Olux address', 'Every site has its own web address on Olux straight away — share it, test it, show it to customers.', 'Nothing to set up.'],
                    ['Buy a new domain', 'Search for a name on the Go live page and buy it in a few clicks. It\'s connected to your site automatically, with a secure padlock (https).', 'Easiest.'],
                    ['Use a domain you already own', 'Keep it where it is and add two records at your domain company. The Go live page shows exactly what to add and checks it for you.', 'About 10 minutes, plus a wait for the records to update.'],
                ] as $i => [$t, $d, $note])
                    <div class="{{ $panel }} p-5">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Option {{ $i + 1 }}</p>
                        <h3 class="text-[15px] font-bold text-gray-900 dark:text-white">{{ $t }}</h3>
                        <p class="text-[13.5px] text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">{{ $d }}</p>
                        <p class="text-[12px] font-semibold mt-1.5" style="color:var(--primary)">{{ $note }}</p>
                    </div>
                @endforeach
                @if ($site)
                    <a href="{{ $to('publish') }}" class="fx inline-flex items-center min-h-[44px] px-5 rounded-xl text-[14px] font-bold" style="background:var(--primary);color:var(--on-primary)">Open Go live for {{ $site->name }}</a>
                @endif
            </section>

            {{-- FAQ --}}
            <section x-show="tab === 'faq'" x-cloak class="{{ $panel }} divide-y divide-gray-100 dark:divide-white/[0.06]">
                @foreach ($faqs as [$q, $a])
                    <details class="group px-5 py-4">
                        <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-[14.5px] font-bold text-gray-900 dark:text-white">
                            {{ $q }}
                            <span class="text-gray-400 transition-transform group-open:rotate-45 text-lg leading-none" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-2 text-[13.5px] text-gray-600 dark:text-gray-300 leading-relaxed">{{ $a }}</p>
                    </details>
                @endforeach
            </section>
        </div>

        {{-- ══ RIGHT rail: your checklist + help ══ --}}
        <x-slot:quick>
            <div class="{{ $panel }} p-5">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-[15px] font-bold text-gray-900 dark:text-white">Your checklist</h3>
                    <span class="text-[12px] font-semibold text-gray-500">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden mb-3">
                    <div class="h-full rounded-full" style="width: {{ $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 0 }}%; background: var(--primary)"></div>
                </div>
                @foreach ($steps as $step)
                    <div class="flex items-start gap-2.5 py-1.5">
                        <span class="mt-0.5 w-5 h-5 rounded-full grid place-items-center shrink-0 text-[11px] font-bold {{ $step['done'] ? '' : 'bg-gray-100 dark:bg-white/[0.08] text-transparent' }}"
                              @if ($step['done']) style="background:var(--primary);color:var(--on-primary)" @endif>✓</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold {{ $step['done'] ? 'text-gray-500 line-through' : 'text-gray-800 dark:text-gray-100' }}">{{ $step['label'] }}</span>
                            @if (! $step['done'] && $step['cta_url'])
                                <a href="{{ $step['cta_url'] }}" class="text-[12px] font-bold" style="color:var(--primary)">{{ $step['cta_label'] }} →</a>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
                <h3 class="font-display text-[16px] font-bold">Need a hand?</h3>
                <p class="mt-1.5 text-[12.5px] opacity-85 leading-relaxed">Reply to any email from us, or write to <b>{{ config('mail.from.address') }}</b> — a real person will help you get set up.</p>
            </div>

            <div class="{{ $panel }} p-5">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Quick links</h3>
                @foreach (array_filter([
                    ['Your sites', 'create or open a site', route('home')],
                    $site ? ['Templates store', 'find a new design', route('marketplace', $site->name)] : null,
                    ['Plans & billing', 'compare and change plans', route('account.subscription')],
                    ['Account settings', 'profile, password, security', route('settings')],
                ]) as [$rl, $rd, $ru])
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
</x-layouts.page>
