{{-- One announcement banner. `preview` renders it without the dismiss call. --}}
@props(['a', 'preview' => false])
@php
    [$bg, $fg, $border] = match ($a->level) {
        'critical' => ['#be123c', '#ffffff', '#9f1239'],
        'warning' => ['#fef3c7', '#78350f', '#fcd34d'],
        default => ['var(--primary-soft)', 'var(--foreground)', 'color-mix(in srgb, var(--primary) 35%, transparent)'],
    };
@endphp
<div {{ $attributes->merge(['class' => 'flex items-start gap-3 px-4 py-2.5 rounded-xl border text-[13.5px]']) }}
     style="background:{{ $bg }};color:{{ $fg }};border-color:{{ $border }}" role="status" x-data="{ gone: false }" x-show="!gone">
    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
    <span class="min-w-0 flex-1">
        <b>{{ $a->title }}</b>@if ($a->body) <span class="opacity-90">— {{ $a->body }}</span>@endif
        @if ($a->link_url)
            <a href="{{ $a->link_url }}" class="ml-1 font-bold underline" @if ($preview) target="_blank" rel="noopener" @endif>{{ $a->link_label ?: 'Learn more' }}</a>
        @endif
    </span>
    @if ($a->dismissible)
        <button type="button" aria-label="Dismiss announcement" class="shrink-0 font-bold opacity-70 hover:opacity-100"
                @if ($preview) disabled @else @click="gone = true; fetch('{{ route('announcements.dismiss', $a) }}', {method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}})" @endif>✕</button>
    @endif
</div>
