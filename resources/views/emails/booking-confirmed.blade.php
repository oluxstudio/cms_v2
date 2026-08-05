@php $name = ucwords(str_replace('-', ' ', $site->name)); @endphp
<x-mail::message>
# Booking {{ $booking->status === 'confirmed' ? 'confirmed ✅' : 'received 📩' }}

Hi {{ $booking->customer_name }},

Your booking with **{{ $name }}** {{ $booking->status === 'confirmed' ? 'is confirmed' : 'was received and is awaiting confirmation' }}.

**Reference: {{ $booking->reference }}**

{{ $summary }}

@if($booking->total_cents > 0)
**Total: {{ $booking->formattedTotal() }}**
@if($booking->paid_cents > 0 && $booking->balanceCents() > 0)
Paid: {{ $booking->formattedPaid() }} · **Balance due at arrival: {{ $booking->formattedBalance() }}**
@endif
@endif

@if($booking->notes)
> {{ $booking->notes }}
@endif

If you need to change or cancel, just reply to this email and mention your reference.

Thanks,<br>
{{ $name }}
</x-mail::message>
