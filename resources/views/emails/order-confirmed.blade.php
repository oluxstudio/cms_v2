<x-mail::message>
# Thank you for your order!

Hi{{ $order->customer_name ? ' '.$order->customer_name : '' }},

Your payment of **{{ $order->formattedTotal() }}** to {{ $business }} is confirmed. Here's what you ordered:

<x-mail::panel>
@foreach($order->items as $it)
- {{ $it->qty }} × {{ $it->name }} — {{ \App\Support\Money::format($it->lineTotalCents(), $order->currency) }}
@endforeach

**Total: {{ $order->formattedTotal() }}**
@if($order->vatLabel())
{{ $order->vatLabel() }}
@endif

Order number: **{{ $order->displayNumber() }}**
</x-mail::panel>

@if($order->fulfilment === 'collection')
**Collection** — we'll let you know as soon as your order is ready to collect.
@elseif($order->shipping_address)
**Delivering to:**
{{ $order->shipping_address }}
@endif

You can follow your order every step of the way — from payment to delivery — on your order page:

<x-mail::button :url="$statusUrl">
Track your order
</x-mail::button>

Thanks,<br>
{{ $business }}
</x-mail::message>
