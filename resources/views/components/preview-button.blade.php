{{--
  THE preview button — "Get started" pill: solid primary, bold label, the
  eye icon trailing on the right inside a solid white circle.
  With no href (nothing to preview) it becomes a non-clickable pill in the
  theme's foreground colour saying so.

    <x-preview-button :href="$site->visitorPreviewUrl()" />
    <x-preview-button :href="$url" label="Preview" small />
--}}
@props(['href' => null, 'label' => 'Live preview', 'emptyLabel' => 'Nothing to preview', 'emptyHint' => 'Choose a template to preview this site', 'target' => '_blank', 'small' => false])

@php
    $pill = 'inline-flex items-center rounded-full font-bold '.($small ? 'gap-2 pl-4 pr-1.5 py-1.5 text-[13px]' : 'gap-2.5 pl-5 pr-2 py-2 text-[15px]');
    $circle = 'ml-auto shrink-0 grid place-items-center rounded-full shadow-sm '.($small ? 'w-6 h-6' : 'w-8 h-8');
    $icon = $small ? 'w-3.5 h-3.5' : 'w-4 h-4';
@endphp

@if (blank($href) || $href === '#')
    <span role="status" aria-disabled="true" title="{{ $emptyHint }}"
          {{ $attributes->except(['title', 'wire:click'])->merge(['class' => $pill.' cursor-not-allowed select-none']) }}
          style="background:var(--foreground);color:var(--background)">
        <span class="min-w-0 truncate">{{ $emptyLabel }}</span>
        <span class="{{ $circle }}" style="background:var(--background)">
            <svg class="{{ $icon }}" style="color:var(--foreground)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
            </svg>
        </span>
    </span>
@else
    <a href="{{ $href }}" target="{{ $target }}" @if($target === '_blank') rel="noopener" @endif
       {{ $attributes->merge(['class' => 'fx shadow-md hover:shadow-lg '.$pill]) }}
       style="background:var(--primary);color:var(--on-primary)">
        <span class="min-w-0 truncate">{{ $label }}</span>
        <span class="{{ $circle }} bg-white">
            <svg class="{{ $icon }}" style="color:var(--primary)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </span>
    </a>
@endif
