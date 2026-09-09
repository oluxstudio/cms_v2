{{--
    FOUNDATION site menu — ONE component renders the grouped navigation on
    every surface: desktop pills + labelled dropdowns (md+) and the hamburger
    slide-down panel (below md). Used by the site layout (selected) and any
    page that resolves a site (e.g. user settings). Edit the grouping HERE
    and it changes everywhere.

        Dashboard · Build │ Content ▾ · Audience ▾ · Commerce ▾ · Site ▾
--}}
@props(['siteName'])

@php
    $navSite = \App\Models\Site::where('name', $siteName)->first();
    // Module tiers (config/modules.php): premium modules wear a PRO badge.
    $tiers = config('modules.tiers', []);
    $link = fn ($seg, $label, $tierKey = null) => [
        'href' => url($siteName.'/'.$seg), 'label' => $label, 'seg' => $seg,
        'pro'  => ($tiers[$tierKey ?? $seg] ?? 'basic') === 'premium',
    ];

    // Only the USABLE day-to-day pages appear in the menu. Builder internals
    // and developer surfaces (Blocks, Components, Collections, My Designs,
    // API docs/keys) stay routable for direct access but are not offered here.
    $topLevel = [
        $link('dashboard', 'Dashboard'),
        $link('marketplace', 'Marketplace'),
    ];
    $menus = [
        'Content' => [
            $link('pages', 'Pages'),
            $link('posts', 'Posts'),
            $link('media', 'Assets'),
            $link('connect', 'Preview'),
        ],
        'Commerce' => [
            // Populated by enabled features below; empty group auto-hides.
        ],
        'Audience' => [
            $link('contacts', 'Contacts'),
            $link('forms', 'Forms'),
            $link('messages', 'Messages'),
            $link('tasks', 'Tasks'),
            $link('alerts', 'Alerts'),
        ],
        'Site' => [
            $link('analytics', 'Analytics'),
            $link('publish', 'Go live'),
        ],
    ];

    // Registry-driven: enabled-feature pages (Store, Orders, Donations…) join Commerce.
    if ($navSite) {
        $needsPayments = false;
        foreach (\App\Features\FeatureRegistry::all() as $feat) {
            if (! $navSite->hasFeature($feat['key'])) {
                continue;
            }
            $needsPayments = $needsPayments || ($feat['needs_payments'] ?? false);
            foreach ($feat['nav'] ?? [] as $item) {
                $menus['Commerce'][] = $link($item['seg'], $item['label'], $feat['key']);
            }
        }
        // Payments setup only matters once a payment-taking feature is on.
        if ($needsPayments) {
            $menus['Commerce'][] = $link('payments', 'Payments');
        }
    }

    // Owners/admins manage the team + outgoing email (super admins always can).
    if ($navSite && $navSite->canManageTeam(auth()->user())) {
        $menus['Site'][] = $link('team', 'Team');
        $menus['Site'][] = $link('emails', 'Emails');
    }

    // RBAC: hide any page the member's role doesn't grant (config/permissions.php
    // maps segment → permission; unmapped segments are open to every member).
    if ($navSite) {
        $allowed = fn ($item) => $navSite->allows(auth()->user(), \App\Access\Permissions::forSegment($item['seg']));
        $topLevel = array_values(array_filter($topLevel, $allowed));
        $menus = array_map(fn ($items) => array_values(array_filter($items, $allowed)), $menus);
    }

    $menus = array_filter($menus);
    $seg = last(request()->segments());
@endphp

{{-- ── Desktop: top-level pills + labelled dropdown groups ── --}}
<div class="hidden md:flex items-center gap-1 max-w-full px-1">
    @foreach ($topLevel as $item)
        @php $active = $seg === $item['seg']; @endphp
        <a href="{{ $item['href'] }}"
           class="whitespace-nowrap px-3.5 py-2 rounded-full text-sm font-medium transition-colors
                  {{ $active
                      ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                      : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' }}">
            {{ $item['label'] }} @if($item['pro'] ?? false)<span class="ml-1 align-middle text-[8px] font-extrabold tracking-wider px-1 py-0.5 rounded" style="background:color-mix(in srgb, var(--primary) 18%, transparent); color:var(--primary)">PRO</span>@endif
        </a>
    @endforeach

    <span class="shrink-0 w-px h-5 bg-gray-200 dark:bg-white/10 mx-1.5"></span>

    @foreach ($menus as $menuLabel => $items)
        @php $groupActive = in_array($seg, array_column($items, 'seg'), true); @endphp
        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
            <button type="button" @click="open = ! open"
                    class="flex items-center gap-1 whitespace-nowrap px-3.5 py-2 rounded-full text-sm font-medium transition-colors
                           {{ $groupActive
                               ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                               : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' }}">
                {{ $menuLabel }}
                <svg class="w-3.5 h-3.5 opacity-60 transition-transform" :class="open ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                 class="absolute left-1/2 -translate-x-1/2 top-full mt-2 min-w-[176px] p-1.5 rounded-2xl z-50
                        bg-white/95 dark:bg-[#1d1e2a]/95 backdrop-blur
                        border border-gray-100 dark:border-white/[0.08] shadow-xl shadow-gray-900/10">
                @foreach ($items as $item)
                    @php $active = $seg === $item['seg']; @endphp
                    <a href="{{ $item['href'] }}"
                       class="block px-3.5 py-2 rounded-xl text-sm font-medium transition-colors
                              {{ $active
                                  ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                  : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06]' }}">
                        {{ $item['label'] }} @if($item['pro'] ?? false)<span class="ml-1 align-middle text-[8px] font-extrabold tracking-wider px-1 py-0.5 rounded" style="background:color-mix(in srgb, var(--primary) 18%, transparent); color:var(--primary)">PRO</span>@endif
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

{{-- ── Mobile: hamburger → slide-down panel, same grouping ── --}}
<div class="md:hidden" x-data="{ mnav: false }" @keydown.escape.window="mnav = false">
    <button type="button" @click="mnav = ! mnav" aria-label="Menu"
            class="w-9 h-9 flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
        <svg x-show="! mnav" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <svg x-show="mnav" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div x-show="mnav" x-cloak @click.outside="mnav = false" x-transition.opacity.duration.100ms
         class="fixed left-4 right-4 top-24 z-50 max-h-[calc(100vh-7rem)] overflow-y-auto p-3 rounded-2xl
                bg-white/95 dark:bg-[#1d1e2a]/95 backdrop-blur
                border border-gray-100 dark:border-white/[0.08] shadow-2xl shadow-gray-900/20">
        <div class="grid grid-cols-2 gap-1.5 mb-2">
            @foreach ($topLevel as $item)
                @php $active = $seg === $item['seg']; @endphp
                <a href="{{ $item['href'] }}"
                   class="px-3.5 py-2.5 rounded-xl text-sm font-semibold text-center transition-colors
                          {{ $active
                              ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                              : 'bg-gray-50 dark:bg-white/[0.04] text-gray-700 dark:text-gray-200' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
        {{-- Collapsible groups — the group holding the current page starts open --}}
        @php
            $openGroup = '';
            foreach ($menus as $menuLabel => $items) {
                if (in_array($seg, array_column($items, 'seg'), true)) { $openGroup = $menuLabel; break; }
            }
        @endphp
        <div x-data="{ grp: @js($openGroup) }" class="space-y-0.5">
        @foreach ($menus as $menuLabel => $items)
            @php $groupActive = in_array($seg, array_column($items, 'seg'), true); @endphp
            <button type="button" @click="grp = grp === @js($menuLabel) ? '' : @js($menuLabel)"
                    class="w-full flex items-center justify-between px-2.5 py-2 rounded-xl text-left transition-colors
                           {{ $groupActive ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}
                           hover:bg-gray-100 dark:hover:bg-white/[0.06]">
                <span class="text-[10px] font-bold uppercase tracking-[.12em]">{{ $menuLabel }}
                    <span class="ml-1 font-semibold text-gray-300 dark:text-gray-500 normal-case tracking-normal">{{ count($items) }}</span>
                </span>
                <svg class="w-3.5 h-3.5 opacity-60 transition-transform" :class="grp === @js($menuLabel) ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="grp === @js($menuLabel)" x-collapse x-cloak class="pb-1">
            @foreach ($items as $item)
                @php $active = $seg === $item['seg']; @endphp
                <a href="{{ $item['href'] }}"
                   class="block px-3.5 py-2 rounded-xl text-sm font-medium transition-colors
                          {{ $active
                              ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                              : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06]' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
            </div>
        @endforeach
        </div>
    </div>
</div>
