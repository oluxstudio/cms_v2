<x-mail::message>
# New {{ $formLabel }} submission 📨

Someone just submitted the **{{ $formLabel }}** on **{{ ucwords(str_replace('-', ' ', $site->name)) }}**.

@foreach($rows as $label => $value)
**{{ $label }}**<br>
{!! nl2br(e($value)) !!}

@endforeach
<x-mail::button :url="$adminUrl">
View this response
</x-mail::button>

{{ config('app.name', 'Olux') }}
</x-mail::message>
