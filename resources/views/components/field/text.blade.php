{{-- Single-line text (or number/url/…) field. `model` is the Livewire property path. --}}
@props(['label' => null, 'model' => null, 'type' => 'text', 'placeholder' => '', 'hint' => null, 'list' => null, 'mono' => false, 'live' => false])
<x-field.wrapper :label="$label" :hint="$hint">
    <input type="{{ $type }}"
           @if($model) wire:model{{ $live ? '.live.debounce.500ms' : '' }}="{{ $model }}" @endif
           @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif
           @if($list) list="{{ $list }}" @endif
           {{ $attributes->merge(['class' => 'bkf-input'.($mono ? ' bkf-mono' : '')]) }}>
</x-field.wrapper>
