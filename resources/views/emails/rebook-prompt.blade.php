@php $name = ucwords(str_replace('-', ' ', $site->name)); @endphp
<x-mail::message>
# Time for your next visit? 💈

Hi {{ $booking->customer_name }},

It's been about {{ $weeks }} weeks since your last **{{ strtolower($booking->service?->name ?? 'visit') }}** with **{{ $name }}** — the perfect time to book your next one.

@if($bookingUrl)
<x-mail::button :url="$bookingUrl">
Book now
</x-mail::button>
@else
Reply to this email and we'll get you booked in.
@endif

See you soon,<br>
{{ $name }}
</x-mail::message>
