@php $name = ucwords(str_replace('-', ' ', $site->name)); @endphp
<x-mail::message>
# Booking cancelled ❌

Hi {{ $booking->customer_name }},

Your booking with **{{ $name }}** has been cancelled.

**Reference: {{ $booking->reference }}**

{{ $summary }}

@if($booking->paid_cents > 0)
You paid **{{ $booking->formattedPaid() }}** on this booking — {{ $name }} will be in touch about a refund if applicable.
@endif

If this is unexpected or you'd like to rebook, just reply to this email and mention your reference.

Thanks,<br>
{{ $name }}
</x-mail::message>
