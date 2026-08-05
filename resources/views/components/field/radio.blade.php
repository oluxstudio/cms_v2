{{--
    Radio group rendered as a segmented control — used for short enums
    (alignment, level, …). `options`: list or assoc value => label.
--}}
@props(['label' => null, 'model' => null, 'options' => [], 'hint' => null, 'name' => null, 'live' => false])
@php $name = $name ?? 'rg-'.md5($model ?? json_encode($options)); @endphp
<x-field.wrapper :label="$label" :hint="$hint">
    <div class="bkf-seg" {{ $attributes }}>
        @foreach($options as $value => $optLabel)
            @php $v = is_int($value) ? $optLabel : $value; @endphp
            <label>
                <input type="radio" name="{{ $name }}" value="{{ $v }}" @if($model) @if($live) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif @endif>
                <span title="{{ $optLabel }}">{{ $optLabel }}</span>
            </label>
        @endforeach
    </div>
</x-field.wrapper>
