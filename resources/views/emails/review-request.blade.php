@php $name = ucwords(str_replace('-', ' ', $site->name)); @endphp
<x-mail::message>
# How did we do? ⭐

Hi {{ $booking->customer_name }},

Thanks for visiting **{{ $name }}**! We hope you loved your {{ strtolower($booking->service?->name ?? 'visit') }}.

If you have a spare minute, a quick review makes a huge difference to a small business like ours:

<x-mail::button :url="$reviewUrl">
Leave a review
</x-mail::button>

Thank you — and see you next time!

{{ $name }}
</x-mail::message>
