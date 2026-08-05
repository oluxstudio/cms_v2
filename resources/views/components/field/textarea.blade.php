{{-- Multi-line text field. --}}
@props(['label' => null, 'model' => null, 'rows' => 3, 'placeholder' => '', 'hint' => null, 'title' => null])
<x-field.wrapper :label="$label" :hint="$hint">
    <textarea rows="{{ $rows }}"
              @if($model) wire:model="{{ $model }}" @endif
              @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif
              @if($title) title="{{ $title }}" @endif
              {{ $attributes->merge(['class' => 'bkf-input']) }}></textarea>
</x-field.wrapper>
