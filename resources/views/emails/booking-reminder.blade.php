@php $name = ucwords(str_replace('-', ' ', $site->name)); @endphp
<x-mail::message>
# See you soon ⏰

Hi {{ $booking->customer_name }},

A quick reminder — your booking with **{{ $name }}** is coming up:

**{{ $booking->service?->name ?? 'Appointment' }}** — {{ $booking->starts_at?->format('l, F j, Y \a\t g:i A') }}
@if($booking->resource)
With: {{ $booking->resource->name }}
@endif

**Reference: {{ $booking->reference }}**

@if($booking->balanceCents() > 0)
Balance due at arrival: **{{ $booking->formattedBalance() }}**
@endif

Need to change or cancel? Just reply to this email and mention your reference.

See you soon,<br>
{{ $name }}
</x-mail::message>
