{{-- One pane of <x-carousel>: exactly device-width on mobile, your own
     lg: classes on desktop. Wide inner content clips/scrolls inside. --}}
<div {{ $attributes->merge(['class' => 'basis-full min-w-full max-w-full w-full shrink-0 snap-start overflow-x-hidden lg:basis-auto lg:min-w-0 lg:max-w-none lg:w-auto lg:shrink']) }}>
    {{ $slot }}
</div>
