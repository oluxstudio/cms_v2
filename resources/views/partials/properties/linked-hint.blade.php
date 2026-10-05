{{-- Where a property is kept in step with the template's own content (PropertyLinks).
     Vars: $prop, optional $note (prefix, e.g. "The first number"). --}}
@php
    $from = $filledFrom[$prop] ?? null;
    $places = $linkedPlaces[$prop] ?? [];
@endphp
@if ($from)
    <p class="text-[11px] mt-1 font-semibold" style="color:var(--primary)">
        ↙ Filled from {{ $from }} — save to keep it here.
    </p>
@elseif ($places)
    <p class="text-[11px] text-gray-400 mt-1" title="Saving a change here also updates these parts of your site">
        ⇄ {{ isset($note) ? $note.' also updates' : 'Also updates' }} {{ implode(', ', $places) }}
    </p>
@endif
