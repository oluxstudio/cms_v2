{{--
  THE preview button — "Get started" pill: solid primary, bold label, the
  eye icon trailing on the right inside a solid white circle.

    <x-preview-button :href="$site->templatePreviewUrl()" />
    <x-preview-button :href="$url" label="Preview" small />
--}}
@props(['href' => '#', 'label' => 'Live preview', 'target' => '_blank', 'small' => false])

<a href="{{ $href }}" target="{{ $target }}" @if($target === '_blank') rel="noopener" @endif
   {{ $attributes->merge(['class' => 'fx inline-flex items-center rounded-full font-bold shadow-md hover:shadow-lg '.($small ? 'gap-2 pl-4 pr-1.5 py-1.5 text-[13px]' : 'gap-2.5 pl-5 pr-2 py-2 text-[15px]')]) }}
   style="background:var(--primary);color:var(--on-primary)">
    <span class="min-w-0 truncate">{{ $label }}</span>
    <span class="ml-auto shrink-0 grid place-items-center rounded-full bg-white shadow-sm {{ $small ? 'w-6 h-6' : 'w-8 h-8' }}">
        <svg class="{{ $small ? 'w-3.5 h-3.5' : 'w-4 h-4' }}" style="color:var(--primary)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
    </span>
</a>
