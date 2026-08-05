{{--
    Select field. `options`: list (value used as label) or assoc value => label.
    `empty`: label of the empty "—" choice (null hides it).
--}}
@props(['label' => null, 'model' => null, 'options' => [], 'empty' => '—', 'hint' => null, 'live' => false])
<x-field.wrapper :label="$label" :hint="$hint">
    <select @if($model) wire:model{{ $live ? '.live' : '' }}="{{ $model }}" @endif {{ $attributes->merge(['class' => 'bkf-input']) }}>
        @if($empty !== null)<option value="">{{ $empty }}</option>@endif
        @foreach($options as $value => $optLabel)
            <option value="{{ is_int($value) ? $optLabel : $value }}">{{ $optLabel }}</option>
        @endforeach
    </select>
</x-field.wrapper>
