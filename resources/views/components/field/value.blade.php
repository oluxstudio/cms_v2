{{--
    Filterable CSS-value field (width, max-width, margin sides, colours…):
    a text input pre-loaded with suggestions — `auto` (when sizing) plus the
    site's THEME VARIABLES — the user filters by typing, picks one, or types
    any custom value. `options`: list of value strings, or assoc
    value => description (shown next to the suggestion).
--}}
@props(['label' => null, 'model' => null, 'options' => [], 'placeholder' => 'auto · 24px · 50% · $variable', 'hint' => null])
@php $listId = 'bkf-dl-'.substr(md5(($model ?? '').json_encode($options)), 0, 10); @endphp
<x-field.wrapper :label="$label" :hint="$hint">
    <input type="text" @if($model) wire:model="{{ $model }}" @endif
           list="{{ $listId }}" placeholder="{{ $placeholder }}"
           {{ $attributes->merge(['class' => 'bkf-input bkf-mono']) }}>
    <datalist id="{{ $listId }}">
        @foreach($options as $value => $desc)
            <option value="{{ is_int($value) ? $desc : $value }}">{{ is_int($value) ? '' : $desc }}</option>
        @endforeach
    </datalist>
</x-field.wrapper>
