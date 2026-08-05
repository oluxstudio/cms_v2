@props([
    'src'      => null,
    'initials' => '?',
    'size'     => 'w-9 h-9',
    'textSize' => 'text-sm font-bold',
    'ring'     => false,
    'shadow'   => '',
])
@php
    $ringClass   = $ring   ? 'ring-2 ring-white' : '';
    $shadowClass = $shadow ?: '';
    $gradient    = 'background:linear-gradient(135deg,var(--primary),var(--primary-2))';
@endphp
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
