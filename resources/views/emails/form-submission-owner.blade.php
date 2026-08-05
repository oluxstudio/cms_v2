<x-mail::message>
# New {{ $formLabel }} submission 📨

Someone just submitted the **{{ $formLabel }}** on **{{ ucwords(str_replace('-', ' ', $site->name)) }}**:

@foreach($fields as $key => $value)
**{{ \Illuminate\Support\Str::headline($key) }}:** {{ is_array($value) ? implode(', ', $value) : $value }}
@endforeach

<x-mail::button :url="$adminUrl">
View submissions
</x-mail::button>

{{ config('app.name', 'Olux') }}
</x-mail::message>
