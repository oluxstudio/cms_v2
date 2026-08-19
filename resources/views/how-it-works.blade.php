<x-layouts.home>
    <x-slot:title>How it works — Olux</x-slot>

    @php
        // Deep-link into the user's latest site when they have one; otherwise the
        // stage nudges them to create a site first.
        $to = fn (string $name) => $site ? route($name, $site->name) : null;

        $stages = [
            [
                'icon' => '🌐',
                'title' => 'Create your site',
                'body' => 'A site is the home for your pages, content, leads and bookings. Give it a name and (optionally) start with sample content so it looks great from day one.',
                'url' => route('home'),
                'label' => $site ? 'Go to your sites' : 'Create your first site',
            ],
            [
                'icon' => '🧩',
                'title' => 'Add pages & content',
                'body' => 'Build reusable components and collections (a Testimonials collection groups testimonial components, for example), then place them on your pages in the builder.',
                'url' => $to('site.components'),
                'label' => 'Open components',
            ],
            [
                'icon' => '📥',
                'title' => 'Capture leads',
                'body' => 'Add a form — every submission lands in your built-in CRM as a contact, and a branded receipt goes to the visitor while you get an alert.',
                'url' => $to('site.forms'),
                'label' => 'Build a form',
            ],
            [
                'icon' => '🎨',
                'title' => 'Make it yours',
                'body' => 'Add your logo and brand colours, and tailor the emails your visitors receive so everything feels like you.',
                'url' => $to('site.emails'),
                'label' => 'Add branding',
            ],
            [
                'icon' => '🚀',
                'title' => 'Publish & go live',
                'body' => 'Preview your site, publish, and point your own domain at it. Your visitors get a fast site backed by your live content.',
                'url' => $site ? url('/'.$site->name.'/publish') : null,
                'label' => 'Publish',
            ],
        ];
    @endphp

    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">

        {{-- Hero --}}
        <div class="mb-8">
            <p class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">Getting started</p>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1.5">How Olux works</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2 max-w-2xl leading-relaxed">
                From a blank account to a live site that <strong class="text-gray-700 dark:text-gray-200">takes bookings and captures leads</strong> — here's the whole journey, and exactly where to do each step.
            </p>
            @unless ($site)
                <a href="{{ route('home') }}"
                   class="mt-5 inline-flex items-center gap-2 text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow-sm transition-transform hover:-translate-y-0.5"
                   style="background:linear-gradient(120deg,var(--primary),var(--primary-2))">
                    Create your first site
                </a>
            @endunless
        </div>

        {{-- Stages --}}
        <div class="space-y-3">
            @foreach ($stages as $i => $stage)
                <div class="flex items-start gap-4 bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] rounded-2xl p-5">
                    <div class="shrink-0 w-11 h-11 rounded-xl grid place-items-center text-lg"
                         style="background:var(--primary-soft)">{{ $stage['icon'] }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold text-gray-300 dark:text-gray-600">STEP {{ $i + 1 }}</span>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ $stage['title'] }}</h3>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">{{ $stage['body'] }}</p>
                        <div class="mt-3">
                            @if ($stage['url'])
                                <a href="{{ $stage['url'] }}" class="text-sm font-semibold" style="color:var(--primary)">{{ $stage['label'] }} →</a>
                            @else
                                <span class="text-xs text-gray-400">Create a site first to unlock this.</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Bookings spotlight (the flagship flow) --}}
        <div class="mt-8 rounded-2xl p-6 text-white" style="background:#332433;">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:#d9f068">Flagship feature</span>
            </div>
            <h2 class="text-xl font-extrabold">Take bookings while you sleep</h2>
            <p class="text-sm text-white/70 mt-1.5 max-w-2xl leading-relaxed">
                Bookings is a premium feature — one engine for appointment <strong>slots</strong>, per-night <strong>stays</strong>, and <strong>trips</strong> with seats. Here's how it goes live:
            </p>

            <div class="mt-5 grid sm:grid-cols-2 gap-3">
                @php
                    $bookingSteps = [
                        ['Enable Bookings', 'Turn it on from the Marketplace (App Store) on a plan that includes premium features.', $to('site.marketplace'), 'Open Marketplace'],
                        ['Set your availability', 'Choose your open days, hours, slot length, notice period and how far ahead people can book.', $to('site.bookings'), 'Set availability'],
                        ['Create a service', 'Add a bookable service — a slot (e.g. a haircut), a stay, or a trip — with an optional Stripe deposit.', $to('site.bookings'), 'Add a service'],
                        ['Add it to a page', 'Drop the booking block onto a page in the builder (or share your booking page) so visitors can book.', $to('site.components'), 'Open the builder'],
                    ];
                @endphp
                @foreach ($bookingSteps as $i => [$title, $body, $url, $label])
                    <div class="rounded-xl p-4" style="background:rgba(255,255,255,.06)">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[11px] font-bold" style="background:#d9f068;color:#2b3110">{{ $i + 1 }}</span>
                            <p class="text-sm font-bold">{{ $title }}</p>
                        </div>
                        <p class="text-xs text-white/60 leading-relaxed">{{ $body }}</p>
                        @if ($url)
                            <a href="{{ $url }}" class="inline-block mt-2 text-xs font-semibold" style="color:#d9f068">{{ $label }} →</a>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="text-xs text-white/50 mt-4 leading-relaxed">
                When a visitor picks a service, date and time and confirms, Olux creates the booking, saves them as a CRM contact, emails both of you a confirmation (and takes the deposit via Stripe if you set one) — and it all shows up in your bookings inbox.
            </p>
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('home') }}" class="text-sm font-semibold" style="color:var(--primary)">← Back to your dashboard</a>
        </div>
    </div>
</x-layouts.home>
