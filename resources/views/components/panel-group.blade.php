@props(['label', 'hint' => null, 'open' => false])
{{-- Progressive-disclosure group for editor panels: essentials stay outside,
     everything else lives in one of these. Plain <details> — collapsed
     content is still rendered, so wire:model bindings keep their state. --}}
<details {{ $open ? 'open' : '' }} {{ $attributes->merge(['class' => 'olx-adv']) }}>
    <summary>
        <span>{{ $label }}</span>
        @if ($hint)
            <em>{{ $hint }}</em>
        @endif
    </summary>
    <div class="olx-adv-body">
        {{ $slot }}
    </div>
</details>
