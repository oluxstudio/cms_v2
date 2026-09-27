{{--
  Theme switch. Binds a boolean:  <x-field.toggle model="fbIsActive" text="Accept submissions" live />
  Without a model, pass wire:click / x-on handlers plus :checked yourself.
--}}
@props(['label' => null, 'model' => null, 'text' => null, 'hint' => null, 'live' => false, 'disabled' => false, 'checked' => false])

<x-field.wrapper :label="$label" :hint="$hint">
    <label class="inline-flex items-center gap-2.5 {{ $disabled ? 'cursor-not-allowed' : 'cursor-pointer' }}">
        <input type="checkbox" class="sr-only"
               @if($model) wire:model{{ $live ? '.live' : '' }}="{{ $model }}" @elseif($checked) checked @endif
               @disabled($disabled)
               {{ $attributes->whereStartsWith('wire:') }} {{ $attributes->whereStartsWith('x-') }} {{ $attributes->whereStartsWith('@') }}>
        <span class="bkf-switch"></span>
        @if ($text)
            <span class="text-sm font-semibold" style="color:var(--foreground)">{{ $text }}</span>
        @endif
    </label>
</x-field.wrapper>
