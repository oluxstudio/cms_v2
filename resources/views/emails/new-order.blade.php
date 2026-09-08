<x-mail::message>
# New order — {{ $order->formattedTotal() }} 🎉

**{{ $order->customer_name ?: ($order->customer_email ?: 'A customer') }}** just paid for:

<x-mail::panel>
@foreach($order->items as $it)
- {{ $it->qty }} × {{ $it->name }}
@endforeach

**Total: {{ $order->formattedTotal() }}**
</x-mail::panel>

@if($order->shipping_address)
**Deliver to:**
{{ $order->shipping_address }}
@endif

<x-mail::button :url="$adminUrl">
View the order
</x-mail::button>

— Olux Studio
</x-mail::message>
