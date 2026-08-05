<x-mail::message>
# Thanks — we got your {{ $formLabel }} ✅

Your {{ $formLabel }} to **{{ ucwords(str_replace('-', ' ', $site->name)) }}** was received.
We'll get back to you as soon as possible.

**What you sent:**

@foreach($fields as $key => $value)
**{{ \Illuminate\Support\Str::headline($key) }}:** {{ is_array($value) ? implode(', ', $value) : $value }}
@endforeach

No action is needed — this is just a copy for your records.

{{ ucwords(str_replace('-', ' ', $site->name)) }}
</x-mail::message>
