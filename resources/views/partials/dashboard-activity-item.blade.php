{{-- One activity entry inside a dashboard group tile. Expects: $act, $site, $cardColors. --}}
<div class="flex items-start gap-4 px-5 py-4">
    {{-- Entity icon bubble — doubles as the timeline node when expanded --}}
    <div class="relative z-[1] w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 mt-0.5 ring-2 ring-white dark:ring-[#1d1e2a]"
         style="background:{{ $act['icon_bg'] }};color:{{ $act['icon_fg'] }}">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $act['icon_path'] }}"/>
        </svg>
    </div>
    {{-- Content --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <p class="text-sm font-bold text-gray-900 dark:text-white leading-snug">{{ $act['title'] }}</p>
            <span class="shrink-0 text-[10px] font-bold px-2.5 py-1 rounded-xl leading-none"
                  style="background:{{ $act['badge_bg'] }};color:{{ $act['badge_fg'] }}">
                {{ $act['badge_label'] }}
            </span>
        </div>

        @if ($act['description'])
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-1">{{ $act['description'] }}</p>
        @endif

        @if (!empty($act['meta']))
        <div class="flex items-center gap-4 mt-2 flex-wrap">
            @if (!empty($act['meta']['form_name']))
            <span class="text-[11px] text-gray-400 flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                {{ $act['meta']['form_name'] }}
            </span>
            @endif
            @if (!empty($act['meta']['email']))
            <span class="text-[11px] text-gray-400">{{ $act['meta']['email'] }}</span>
            @endif
            @if (!empty($act['meta']['priority']))
            @php $pFg = ['high'=>'#dc2626','normal'=>'#2563eb','low'=>'#6b7280'][$act['meta']['priority']] ?? '#6b7280'; @endphp
            <span class="text-[11px] font-semibold capitalize" style="color:{{ $pFg }}">
                🔥 {{ $act['meta']['priority'] }} priority
            </span>
            @endif
            @if (!empty($act['meta']['page_url']))
            <span class="text-[11px] text-gray-400 font-mono">{{ $act['meta']['page_url'] }}</span>
            @endif
        </div>
        @endif

        <div class="flex items-center justify-between mt-3 flex-wrap gap-2">
            <div class="flex items-center gap-2">
                @php $uColor = $cardColors[abs(crc32($act['user_name'])) % count($cardColors)]; @endphp
                <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0"
                     style="background:{{ $uColor }}">{{ $act['user_init'] }}</div>
                <span class="text-[11px] text-gray-400">
                    <span class="font-medium text-gray-600 dark:text-gray-300">{{ $act['user_name'] }}</span>
                    · {{ $act['created_at'] }}
                </span>
            </div>

            @if ($act['url'])
            <a href="{{ url($site->name . $act['url']) }}"
               class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-3 py-1.5 rounded-xl transition-colors shrink-0
                      bg-gray-50 dark:bg-white/[0.06] text-gray-700 dark:text-gray-200
                      hover:bg-gray-100 dark:hover:bg-white/[0.1] border border-gray-200 dark:border-white/[0.06]">
                @if ($act['entity_type'] === 'form_response')
                    View response
                @elseif ($act['entity_type'] === 'todo')
                    Open task
                @elseif ($act['entity_type'] === 'page')
                    Go to pages
                @elseif ($act['entity_type'] === 'form')
                    View form
                @elseif ($act['entity_type'] === 'media')
                    Asset library
                @elseif ($act['entity_type'] === 'member')
                    View team
                @elseif ($act['entity_type'] === 'booking')
                    View bookings
                @elseif ($act['entity_type'] === 'invoice')
                    View invoices
                @elseif ($act['entity_type'] === 'estimate')
                    View estimates
                @elseif ($act['entity_type'] === 'interest' || $act['entity_type'] === 'contact')
                    View contacts
                @else
                    View →
                @endif
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endif
        </div>
    </div>
</div>
