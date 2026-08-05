{{--
    Colour field (FOUNDATION): three ways to set any colour property —
      1. the native colour PICKER (swatch on the left),
      2. the theme-variable dropdown ($vars — inserts the CSS-native `--name`),
      3. free text (#hex, rgb(), color names, or a variable typed by hand).
    `vars` is the site's colour variables [['name','value'],…]; `value` is the
    current property value (used to seed the swatch when it's a hex colour).
--}}
@props(['label' => null, 'model' => null, 'vars' => [], 'value' => '', 'hint' => null, 'placeholder' => '#hex · rgb() · --variable'])
@php
    $isHex = is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', trim($value));
    // Seed the swatch: exact hex → itself; a variable → its defined value when that is hex.
    $seed = $isHex ? trim($value) : '#888888';
    if (! $isHex && is_string($value)) {
        $name = ltrim(trim($value), '$-');
        foreach ($vars as $var) {
            if (($var['name'] ?? '') === $name && preg_match('/^#[0-9a-fA-F]{6}$/', trim($var['value'] ?? ''))) {
                $seed = trim($var['value']);
                break;
            }
        }
    }
@endphp
@php $listId = $vars ? 'bkf-cl-'.substr(md5(($model ?? '').json_encode($vars)), 0, 10) : null; @endphp
<x-field.wrapper :label="$label" :hint="$hint">
    <div class="bkf-color" x-data>
        <input type="color" class="bkf-color-swatch" value="{{ $seed }}" title="Pick a colour"
               x-on:input="$refs.txt.value = $event.target.value; $refs.txt.dispatchEvent(new Event('input', { bubbles: true }))">
        {{-- ONE combined field: type any colour value OR pick a --variable
             from the suggestions that open on focus/typing (filterable). --}}
        <input type="text" x-ref="txt" @if($model) wire:model="{{ $model }}" @endif
               @if($listId) list="{{ $listId }}" @endif
               placeholder="{{ $placeholder }}" class="bkf-input bkf-mono" style="flex:1;min-width:0">
        @if($listId)
            <datalist id="{{ $listId }}">
                @foreach($vars as $var)
                    <option value="--{{ $var['name'] }}">{{ $var['value'] }}</option>
                @endforeach
            </datalist>
        @endif
    </div>
</x-field.wrapper>
