{{--
    Range slider field (opacity & friends): drag to set, live value readout
    on the right. `value` seeds the readout; Livewire keeps it saved.
--}}
@props(['label' => null, 'model' => null, 'min' => 0, 'max' => 100, 'step' => 1, 'unit' => '%', 'value' => '', 'hint' => null])
<x-field.wrapper :label="$label" :hint="$hint">
    <div class="bkf-range" x-data>
        <input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
               @if($model) wire:model="{{ $model }}" @endif
               x-on:input="$refs.out.textContent = $event.target.value + '{{ $unit }}'"
               {{ $attributes }}>
        <span x-ref="out" class="bkf-range-val">{{ $value === '' || $value === null ? '—' : $value.$unit }}</span>
    </div>
</x-field.wrapper>
