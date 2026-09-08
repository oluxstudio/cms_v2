<x-mail::message>
# Delivery request from {{ $business }}

You've been asked to deliver **order {{ $order->displayNumber() }}** ({{ $order->items->sum('qty') }} item{{ $order->items->sum('qty') === 1 ? '' : 's' }}).

<x-mail::panel>
**Deliver to:**
{{ $order->shipping_address ?: 'Address to be confirmed with the store.' }}

@if($order->customer_name || $order->customer_phone)
**Customer:** {{ $order->customer_name ?: '—' }}@if($order->customer_phone) · {{ $order->customer_phone }}@endif
@endif
</x-mail::panel>

Open your delivery page to see the details and mark the order as picked up or delivered:

<x-mail::button :url="$courierUrl">
Open delivery page
</x-mail::button>

Thanks,<br>
{{ $business }}
</x-mail::message>
