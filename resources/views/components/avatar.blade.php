@props([
    'src'      => null,
    'initials' => '?',
    'size'     => 'w-9 h-9',
    'textSize' => 'text-sm font-bold',
    'ring'     => false,
    'shadow'   => '',
    // `live`: the signed-in user's own avatar — swaps to a newly uploaded
    // photo straight away (listens for the `avatar-updated` browser event).
    'live'     => false,
])
@php
    $ringClass   = $ring   ? 'ring-2 ring-white' : '';
    $shadowClass = $shadow ?: '';
    $gradient    = 'background:linear-gradient(135deg,var(--primary),var(--primary-2))';
@endphp
@if ($live)
<span class="relative inline-flex shrink-0" x-data="{ src: @js($src), broken: false }"
      x-on:avatar-updated.window="src = $event.detail.url; broken = false">
    <template x-if="src && ! broken">
        <img :src="src" class="{{ $size }} shrink-0 aspect-square rounded-full object-cover {{ $ringClass }} {{ $shadowClass }}" x-on:error="broken = true" alt="">
    </template>
    <span x-show="! src || broken" class="{{ $size }} {{ $textSize }} rounded-full flex items-center justify-center text-white {{ $ringClass }} {{ $shadowClass }}"
          style="{{ $gradient }}" @if($src) x-cloak @endif>
        {{ $initials }}
    </span>
</span>
@else
<span class="relative inline-flex shrink-0">
    @if($src)
        <img src="{{ $src }}"
             class="{{ $size }} rounded-full object-cover {{ $ringClass }} {{ $shadowClass }}"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    @endif
    <span class="{{ $size }} {{ $textSize }} rounded-full flex items-center justify-center text-white {{ $ringClass }} {{ $shadowClass }}"
          style="{{ $gradient }}{{ $src ? ';display:none' : '' }}">
        {{ $initials }}
    </span>
</span>
@endif
