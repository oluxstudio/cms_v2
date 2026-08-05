<x-mail::message>
# New booking 🎉

**{{ $booking->customer_name }}** ({{ $booking->customer_email }}{{ $booking->customer_phone ? ' · '.$booking->customer_phone : '' }}) just booked:

{{ $summary }}

**Reference:** {{ $booking->reference }}
**Status:** {{ str_replace('_', ' ', $booking->status) }}
@if($booking->total_cents > 0)
**Total:** {{ $booking->formattedTotal() }}
@if($booking->paid_cents > 0 && $booking->balanceCents() > 0)
**Paid:** {{ $booking->formattedPaid() }} · **Balance due:** {{ $booking->formattedBalance() }}
@endif
@endif
@if($booking->notes)

> {{ $booking->notes }}
@endif

<x-mail::button :url="$adminUrl">
Review in Bookings
</x-mail::button>

{{ config('app.name', 'Olux') }}
</x-mail::message>
