{{--
    FOUNDATION field wrapper — every form field in the CMS renders through
    the x-field.* components so the design is defined ONCE. The .bkf-* styles
    live in resources/css/app.css (global), so fields render correctly even
    when they first appear inside an inert Alpine <template> tab pane.
--}}
@props(['label' => null, 'hint' => null])

<div {{ $attributes }}>
    @if($label)<label class="bkf-label">{{ $label }}</label>@endif
    {{ $slot }}
    @if($hint)<p class="bkf-hint">{{ $hint }}</p>@endif
</div>
