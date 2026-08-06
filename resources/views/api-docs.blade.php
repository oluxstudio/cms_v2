<x-layouts.selected :siteName="$site->name">
    <x-slot:title>API docs — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>

@php
    $base = url('/api/sites/'.$site->name);

    // The manual: group → endpoints. Every endpoint shows the CALL first,
    // then how it works and the parameters it accepts.
    // param: [name, in (query|body|path), type, required?, description]
    $docs = [
        'Site content' => [
            [
                'method' => 'GET', 'path' => '/content',
                'summary' => 'The whole site in one payload — every page with its components and content nodes, menus and theme. Template-folder sites call this at render time (SSR) so edits in the CMS appear immediately.',
                'params' => [],
                'example' => "curl {$base}/content",
            ],
            [
                'method' => 'GET', 'path' => '/page?url=/about',
                'summary' => 'A single page\'s content tree — lighter than /content when a template only needs one page.',
                'params' => [
                    ['url', 'query', 'string', true, 'The page URL as stored in the CMS, e.g. / or /about.'],
                ],
                'example' => "curl \"{$base}/page?url=/\"",
            ],
        ],

        'Posts (blog)' => [
            [
                'method' => 'GET', 'path' => '/posts',
                'summary' => 'Published posts, newest first, paginated. Body HTML is excluded here — fetch a single post for it.',
                'params' => [
                    ['per_page', 'query', 'integer', false, 'Posts per page, 1–50. Default 10.'],
                    ['page', 'query', 'integer', false, 'Page number.'],
                ],
                'example' => "curl \"{$base}/posts?per_page=6\"",
            ],
            [
                'method' => 'GET', 'path' => '/posts/{slug}',
                'summary' => 'One published post including its full body HTML.',
                'params' => [
                    ['slug', 'path', 'string', true, 'The post slug from the /posts listing.'],
                ],
                'example' => "curl {$base}/posts/my-first-post",
            ],
            [
                'method' => 'POST', 'path' => '/posts/{slug}/view',
                'summary' => 'Count a view — call once when a visitor opens the post. Feeds the “Top posts by visits” tiles in the admin.',
                'params' => [['slug', 'path', 'string', true, 'The post slug.']],
                'example' => "curl -X POST {$base}/posts/my-first-post/view",
            ],
            [
                'method' => 'POST', 'path' => '/posts/{slug}/like',
                'summary' => 'Count a like — feeds the engagement tiles.',
                'params' => [['slug', 'path', 'string', true, 'The post slug.']],
                'example' => "curl -X POST {$base}/posts/my-first-post/like",
            ],
        ],

        'Forms & leads' => [
            [
                'method' => 'POST', 'path' => '/contact',
                'summary' => 'The contact form. Creates a submission, captures/updates the CRM Contact, emails you and the visitor, and posts a dashboard notification.',
                'params' => [
                    ['name', 'body', 'string', true, 'Visitor name (max 255).'],
                    ['email', 'body', 'string', true, 'Visitor email.'],
                    ['subject', 'body', 'string', false, 'Message subject (max 255).'],
                    ['message', 'body', 'string', true, 'The message (max 5000).'],
                ],
                'example' => "curl -X POST {$base}/contact \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"name\":\"Ada\",\"email\":\"ada@example.com\",\"message\":\"Hello!\"}'",
            ],
            [
                'method' => 'GET', 'path' => '/form/{formName}',
                'summary' => 'Fetch a custom form\'s field schema and validation rules — render the form from this before submitting.',
                'params' => [['formName', 'path', 'string', true, 'The form\'s name as defined on the Forms page.']],
                'example' => "curl {$base}/form/newsletter-signup",
            ],
            [
                'method' => 'POST', 'path' => '/form/{formName}',
                'summary' => 'Submit a custom form. The body keys are the form\'s own field keys (from the schema above); everything is validated server-side against the same rules. Lands in Form Responses + the dashboard feed.',
                'params' => [
                    ['formName', 'path', 'string', true, 'The form\'s name.'],
                    ['<field keys>', 'body', 'mixed', true, 'One body key per form field, e.g. {"email": "...", "topic": "..."}.'],
                ],
                'example' => "curl -X POST {$base}/form/newsletter-signup \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"email\":\"ada@example.com\"}'",
            ],
            [
                'method' => 'POST', 'path' => '/interest',
                'summary' => '“I\'m interested” — the lightest lead. Captures/updates the CRM Contact, notifies your dashboard (alert + activity) and emails you. No feature flag required.',
                'params' => [
                    ['name', 'body', 'string', true, 'Visitor name (max 120).'],
                    ['email', 'body', 'string', true, 'Visitor email.'],
                    ['phone', 'body', 'string', false, 'Phone (max 40).'],
                    ['subject', 'body', 'string', false, 'What they are interested in (max 150).'],
                    ['message', 'body', 'string', false, 'Free text (max 2000).'],
                    ['source', 'body', 'string', false, 'Where it came from — page/campaign tag (max 80).'],
                ],
                'example' => "curl -X POST {$base}/interest \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"name\":\"Ada\",\"email\":\"ada@example.com\",\"subject\":\"Kitchen refit\"}'",
            ],
        ],

        'Quotes (estimator)' => [
            [
                'method' => 'GET', 'path' => '/estimator/config',
                'summary' => 'Everything needed to render the estimator UI: your named estimators with their public fields (admin-only “set data” fields stay server-side), plus the built-in trade catalog. Requires the Estimator add-on.',
                'params' => [],
                'example' => "curl {$base}/estimator/config",
            ],
            [
                'method' => 'POST', 'path' => '/quote',
                'summary' => 'Get a quote instantly — runs the estimator\'s calculations server-side and returns the results. Nothing is stored, so call it live as the visitor changes inputs. (Also available at POST /estimator.)',
                'params' => [
                    ['estimator', 'body', 'string', false, 'Estimator slug from /estimator/config (e.g. cleaner). Optional when the site has exactly one.'],
                    ['fields', 'body', 'object', false, 'Field values keyed by field key, e.g. {"area_to_clean": 12}.'],
                    ['trade', 'body', 'string', false, 'Alternative: a built-in trade key (cleaner, plumber, …) with its inputs.'],
                    ['inputs', 'body', 'object', false, 'Inputs for the built-in trade engine.'],
                ],
                'example' => "curl -X POST {$base}/quote \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"estimator\":\"cleaner\",\"fields\":{\"area_to_clean\":12}}'",
            ],
            [
                'method' => 'POST', 'path' => '/quote/request',
                'summary' => 'Submit the quote as a lead. Saves the estimate with its calculated results, emails the visitor your drafted estimator email, emails you the details, adds the person to Contacts, and posts a dashboard alert + activity entry. (Also available at POST /estimator/request.)',
                'params' => [
                    ['name', 'body', 'string', true, 'Visitor name (max 120).'],
                    ['email', 'body', 'string', true, 'Visitor email — the quote email goes here.'],
                    ['phone', 'body', 'string', false, 'Phone (max 40).'],
                    ['notes', 'body', 'string', false, 'Free text (max 1000).'],
                    ['estimator', 'body', 'string', false, 'Estimator slug. Optional when the site has exactly one.'],
                    ['fields', 'body', 'object', false, 'Field values keyed by field key. Required fields are enforced.'],
                    ['trade', 'body', 'string', false, 'Alternative: built-in trade key + inputs.'],
                    ['inputs', 'body', 'object', false, 'Inputs for the built-in trade engine.'],
                ],
                'example' => "curl -X POST {$base}/quote/request \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"estimator\":\"cleaner\",\"fields\":{\"area_to_clean\":12},\"name\":\"Ada\",\"email\":\"ada@example.com\"}'",
            ],
        ],

        'Bookings' => [
            [
                'method' => 'GET', 'path' => '/booking/config',
                'summary' => 'The bookable services (kind: slot | stay | trip), their prices, custom fields and settings — render the booking widget from this. Requires the Bookings add-on.',
                'params' => [],
                'example' => "curl {$base}/booking/config",
            ],
            [
                'method' => 'GET', 'path' => '/booking/availability',
                'summary' => 'What can be booked. For slot services pass a date to get free time slots; for stays pass a date range to check units; for trips it returns departures.',
                'params' => [
                    ['service', 'query', 'string', true, 'The service slug (from /booking/config).'],
                    ['date', 'query', 'date', false, 'Slot/trip services: the day to check, e.g. 2026-08-20.'],
                    ['check_in', 'query', 'date', false, 'Stay services: arrival date (required for stays).'],
                    ['check_out', 'query', 'date', false, 'Stay services: departure date (required for stays).'],
                    ['guests', 'query', 'integer', false, 'Stay services: number of guests. Default 1.'],
                    ['units', 'query', 'integer', false, 'Stay services: units wanted. Default 1.'],
                    ['resource', 'query', 'string', false, 'A specific named staff member / room to check.'],
                    ['origin', 'query', 'string', false, 'Trip services: filter departures by origin.'],
                    ['destination', 'query', 'string', false, 'Trip services: filter by destination.'],
                ],
                'example' => "curl \"{$base}/booking/availability?service=consultation&date=2026-08-20\"",
            ],
            [
                'method' => 'POST', 'path' => '/booking',
                'summary' => 'Create a booking. Double-booking is prevented server-side. If the service requires payment (and Stripe is connected) the response includes a checkout URL and the booking confirms via webhook; otherwise it is created pending (or auto-confirmed if you enabled that). Emails + dashboard notifications fire automatically.',
                'params' => [
                    ['service', 'body', 'string', true, 'The service slug.'],
                    ['name', 'body', 'string', true, 'Customer name (max 120).'],
                    ['email', 'body', 'string', true, 'Customer email.'],
                    ['phone', 'body', 'string', false, 'Phone (max 40).'],
                    ['notes', 'body', 'string', false, 'Free text (max 1000).'],
                    ['start', 'body', 'datetime', false, 'Slot services: the chosen slot start, e.g. 2026-08-20 14:00 (required for slots).'],
                    ['check_in / check_out', 'body', 'date', false, 'Stay services: the date range (required for stays). Optional guests, units.'],
                    ['departure_id / qty', 'body', 'integer', false, 'Trip services: the departure and seat count (required for trips).'],
                    ['resource_id', 'body', 'integer', false, 'A specific staff member / room; omit for “any”.'],
                    ['fields', 'body', 'object', false, 'Owner-defined custom booking fields, keyed by field key.'],
                    ['return_url', 'body', 'url', false, 'Where to send the customer back after Stripe checkout (your booking page).'],
                ],
                'example' => "curl -X POST {$base}/booking \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"service\":\"consultation\",\"start\":\"2026-08-20 14:00\",\"name\":\"Ada\",\"email\":\"ada@example.com\"}'",
            ],
            [
                'method' => 'GET', 'path' => '/booking/{reference}',
                'summary' => 'Look up one booking by its reference — used by confirmation pages after checkout.',
                'params' => [['reference', 'path', 'string', true, 'The booking reference returned when it was created.']],
                'example' => "curl {$base}/booking/BK3F9A2C",
            ],
            [
                'method' => 'GET', 'path' => '/bookings 🔒', 'auth' => true,
                'summary' => 'Admin: list bookings. Requires an API token — send it as Authorization: Bearer <token>.',
                'params' => [],
                'example' => "curl {$base}/bookings \\\n  -H \"Authorization: Bearer YOUR_API_TOKEN\"",
            ],
            [
                'method' => 'PATCH', 'path' => '/bookings/{id} 🔒', 'auth' => true,
                'summary' => 'Admin: confirm or cancel a booking. Customer emails + dashboard activity fire automatically.',
                'params' => [
                    ['id', 'path', 'integer', true, 'The booking id.'],
                    ['status', 'body', 'string', true, 'confirmed or cancelled.'],
                ],
                'example' => "curl -X PATCH {$base}/bookings/42 \\\n  -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"status\":\"confirmed\"}'",
            ],
        ],

        'Newsletter' => [
            [
                'method' => 'POST', 'path' => '/subscribe',
                'summary' => 'Add an email to the site\'s newsletter list.',
                'params' => [
                    ['email', 'body', 'string', true, 'Subscriber email (max 255).'],
                    ['name', 'body', 'string', false, 'Subscriber name.'],
                    ['source', 'body', 'string', false, 'Where they signed up (footer, popup, …).'],
                ],
                'example' => "curl -X POST {$base}/subscribe \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"email\":\"ada@example.com\"}'",
            ],
            [
                'method' => 'POST', 'path' => '/unsubscribe',
                'summary' => 'Remove an email from the list.',
                'params' => [['email', 'body', 'string', true, 'The email to remove.']],
                'example' => "curl -X POST {$base}/unsubscribe \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"email\":\"ada@example.com\"}'",
            ],
        ],

        'Media' => [
            [
                'method' => 'GET', 'path' => '/media',
                'summary' => 'This site\'s asset library (images, video, documents) — for galleries and pickers in the client site.',
                'params' => [
                    ['type', 'query', 'string', false, 'Filter: image | video | document.'],
                    ['search', 'query', 'string', false, 'Search by file name.'],
                    ['per_page', 'query', 'integer', false, 'Results per page.'],
                ],
                'example' => "curl \"{$base}/media?type=image\"",
            ],
        ],
    ];

    $methodChip = [
        'GET' => 'background:#d9f068;color:#2b3110',
        'POST' => 'background:#d7c3f5;color:#33245c',
        'PATCH' => 'background:#f2c94c;color:#4a3608',
    ];
@endphp

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8"
     x-data="{
        q: '',
        hits: 0,
        scrolled: false,
        scroller: null,
        match(text) { return this.q.trim() === '' || (text || '').includes(this.q.toLowerCase().trim()) },
        jump(id) { document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }) },
        toTop() { (this.scroller || window).scrollTo({ top: 0, behavior: 'smooth' }) },
     }"
     x-init="
        scroller = document.getElementById('MainBody');
        (scroller || window).addEventListener('scroll', () => scrolled = (scroller ? scroller.scrollTop : window.scrollY) > 400, { passive: true });
     "
     x-effect="q; hits = Array.from($root.querySelectorAll('[data-ep]')).filter(el => match(el.dataset.search)).length">

    {{-- Header --}}
    <div class="mb-4">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">API documentation</h1>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">
            Everything your website can call on this CMS. All endpoints are stateless JSON over
            <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/[0.06]">{{ $base }}</span>
            — no session or CSRF token needed; standard rate limiting applies. 🔒 marks endpoints needing an API token.
        </p>
    </div>

    {{-- ── Sticky finder: search + group jump pills ── --}}
    @php $totalEndpoints = collect($docs)->sum(fn ($eps) => count($eps)); @endphp
    <div class="sticky top-0 z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 mb-5
                bg-[#fdf6ea]/85 dark:bg-[#0b1b3a]/85 backdrop-blur border-b border-gray-100/80 dark:border-white/[0.06]"
         style="background:color-mix(in srgb, var(--background) 88%, transparent)">
        <div class="flex items-center gap-2.5">
            <div class="relative flex-1 min-w-0">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input x-model="q" type="search" placeholder="Find an API — try “quote”, “booking”, “email”, a parameter name…"
                       class="w-full pl-10 pr-9 py-2.5 text-sm rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                       @keydown.escape="q = ''">
                <button type="button" x-show="q" x-cloak @click="q = ''" title="Clear"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full text-gray-400 hover:text-rose-500 hover:bg-gray-100 dark:hover:bg-white/[0.06] text-sm">✕</button>
            </div>
            <span class="shrink-0 whitespace-nowrap text-[11px] font-bold tabular-nums px-2.5 py-1.5 rounded-full"
                  style="background:#d9f068;color:#2b3110" x-text="hits + ' / {{ $totalEndpoints }}'"></span>
        </div>
        {{-- Group jump pills --}}
        <div class="flex flex-wrap gap-1.5 mt-2.5">
            @foreach (array_keys($docs) as $group)
                <button type="button" @click="jump('api-group-{{ Str::slug($group) }}')"
                        class="px-3 py-1 rounded-full text-[11px] font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
                    {{ $group }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- No matches --}}
    <div x-show="q && hits === 0" x-cloak class="text-center py-16">
        <p class="text-3xl mb-2">🔍</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Nothing matches “<span x-text="q"></span>”.</p>
        <button type="button" @click="q = ''" class="mt-2 text-xs font-semibold text-indigo-500 hover:underline">Clear the search</button>
    </div>

    @foreach ($docs as $group => $endpoints)
        @php
            $groupSearch = strtolower($group.' '.collect($endpoints)->map(fn ($e) => $e['method'].' '.$e['path'].' '.$e['summary'].' '.collect($e['params'])->pluck(0)->implode(' '))->implode(' '));
        @endphp
        {{-- Group (hidden entirely when no endpoint inside matches) --}}
        <div id="api-group-{{ Str::slug($group) }}" class="scroll-mt-32" data-search="{{ $groupSearch }}" x-show="match($el.dataset.search)">
        <div class="flex items-center gap-2 mt-8 mb-3 first:mt-0">
            <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">{{ $group }}</p>
            <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ count($endpoints) }}</span>
            <div class="flex-1 border-t border-gray-100 dark:border-white/[0.06]"></div>
        </div>

        @foreach ($endpoints as $ep)
        @php
            $epSearch = strtolower($ep['method'].' '.$ep['path'].' '.$ep['summary'].' '.$group.' '.collect($ep['params'])->map(fn ($p) => $p[0].' '.$p[4])->implode(' '));
        @endphp
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm mb-3 overflow-hidden"
             data-ep data-search="{{ $epSearch }}" x-show="match($el.dataset.search)">
            {{-- 1 · THE CALL --}}
            <div class="flex items-center gap-3 px-5 pt-4">
                <span class="shrink-0 text-[11px] font-extrabold px-2.5 py-1 rounded-lg" style="{{ $methodChip[explode(' ', $ep['method'])[0]] ?? '' }}">{{ explode(' ', $ep['method'])[0] }}</span>
                <code class="flex-1 min-w-0 text-[13px] font-mono text-gray-800 dark:text-gray-100 truncate">{{ $base }}{{ str_replace(' 🔒', '', $ep['path']) }}</code>
                @if (str_contains($ep['path'], '🔒'))<span title="Requires Authorization: Bearer token">🔒</span>@endif
                <button type="button" title="Copy URL"
                        @click="navigator.clipboard.writeText('{{ $base }}{{ str_replace(' 🔒', '', explode('?', $ep['path'])[0]) }}'); $el.textContent='✓'; setTimeout(() => $el.textContent='⧉', 1200)"
                        class="shrink-0 w-7 h-7 rounded-lg text-gray-400 hover:text-indigo-500 hover:bg-gray-50 dark:hover:bg-white/[0.06] text-sm">⧉</button>
            </div>

            {{-- Example call --}}
            <pre class="mx-5 mt-3 px-4 py-3 rounded-xl bg-gray-900 dark:bg-black/40 text-lime-300 text-[11px] leading-relaxed overflow-x-auto"><code>{{ $ep['example'] }}</code></pre>

            {{-- 2 · HOW IT WORKS --}}
            <p class="px-5 pt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $ep['summary'] }}</p>

            {{-- 3 · PARAMETERS --}}
            @if (count($ep['params']))
            <div class="px-5 pt-3 pb-4 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-gray-400">
                            <th class="py-1.5 pr-4 font-bold">Parameter</th>
                            <th class="py-1.5 pr-4 font-bold">In</th>
                            <th class="py-1.5 pr-4 font-bold">Type</th>
                            <th class="py-1.5 pr-4 font-bold">Required</th>
                            <th class="py-1.5 font-bold">Description</th>
                        </tr>
                    </thead>
                    <tbody class="align-top">
                        @foreach ($ep['params'] as [$name, $in, $type, $required, $desc])
                        <tr class="border-t border-gray-50 dark:border-white/[0.04]">
                            <td class="py-2 pr-4 font-mono text-xs font-semibold text-gray-800 dark:text-gray-100 whitespace-nowrap">{{ $name }}</td>
                            <td class="py-2 pr-4 text-xs text-gray-400">{{ $in }}</td>
                            <td class="py-2 pr-4 text-xs text-gray-400">{{ $type }}</td>
                            <td class="py-2 pr-4">
                                @if ($required)
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400">required</span>
                                @else
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-400 dark:bg-white/[0.06]">optional</span>
                                @endif
                            </td>
                            <td class="py-2 text-xs text-gray-500 dark:text-gray-400">{{ $desc }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="pb-4"></div>
            @endif
        </div>
        @endforeach
        </div> {{-- /group wrapper --}}
    @endforeach

    <p class="mt-8 text-xs text-gray-400">
        Responses are JSON. Validation failures return <span class="font-mono">422</span> with a message; unknown sites or disabled add-ons return <span class="font-mono">404</span>.
        Feature-gated groups (Quotes, Bookings) need their add-on enabled on this site.
    </p>

    {{-- ── Floating scroll-to-top ── --}}
    <button type="button" x-show="scrolled" x-cloak x-transition.opacity.duration.200ms @click="toTop()"
            title="Back to top"
            class="fixed bottom-6 right-6 z-30 w-11 h-11 rounded-full bg-[#332433] text-white shadow-lg shadow-black/20
                   flex items-center justify-center hover:-translate-y-0.5 transition-transform">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
    </button>
</div>
</x-layouts.selected>
