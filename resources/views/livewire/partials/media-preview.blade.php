{{-- Asset preview for a field value. Vars: $media (MediaValue::detect result), $size ('sm'|'lg'). --}}
@php $size = $size ?? 'sm'; @endphp
@if ($media)
    @switch($media['kind'])
        @case('image')
            <a href="{{ $media['url'] }}" target="_blank" rel="noopener" title="Open {{ $media['name'] }}">
                <img src="{{ $media['url'] }}" alt="" class="{{ $size === 'lg' ? 'max-h-56 w-auto' : 'h-20 w-auto' }} max-w-full rounded-xl border border-gray-100 dark:border-white/[0.08] bg-[repeating-conic-gradient(#f3f4f6_0_25%,#fff_0_50%)] bg-[length:16px_16px] object-contain"
                     loading="lazy" onerror="this.closest('a').style.display='none'">
            </a>
            @break
        @case('video')
            <video src="{{ $media['url'] }}" controls preload="metadata" class="{{ $size === 'lg' ? 'max-h-60' : 'max-h-32' }} max-w-full rounded-xl bg-black"></video>
            @break
        @case('audio')
            <audio src="{{ $media['url'] }}" controls preload="none" class="w-full max-w-sm"></audio>
            @break
        @default
            <a href="{{ $media['url'] }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] px-3 py-2 text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06]">
                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z"/></svg>
                {{ $media['name'] }} ↗
            </a>
    @endswitch
@endif
