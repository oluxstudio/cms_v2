{{-- Checkbox field. --}}
@props(['label' => null, 'model' => null, 'text' => 'Yes', 'hint' => null])
<x-field.wrapper :label="$label" :hint="$hint">
    <label class="bkf-check">
        <input type="checkbox" @if($model) wire:model="{{ $model }}" @endif {{ $attributes }}>
        {{ $text }}
    </label>
</x-field.wrapper>
