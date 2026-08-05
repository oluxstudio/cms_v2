@php $name = ucwords(str_replace('-', ' ', $site->name)); @endphp
<x-mail::message>
# Invoice {{ $invoice->number }}

Hi {{ $invoice->customer_name }},

**{{ $name }}** has sent you an invoice for **{{ $invoice->formattedTotal() }}**@if($invoice->due_date), due **{{ $invoice->due_date->format('F j, Y') }}**@endif.

@foreach($invoice->items as $item)
- {{ $item['description'] }} — {{ $item['qty'] }} × {{ \App\Support\Money::format((int) $item['unit_cents'], $invoice->currency) }}
@endforeach
@if($invoice->tax_cents > 0)
- Tax ({{ rtrim(rtrim(number_format($invoice->tax_bp / 100, 2), '0'), '.') }}%) — {{ \App\Support\Money::format((int) $invoice->tax_cents, $invoice->currency) }}
@endif

<x-mail::button :url="$invoice->payUrl()">
View & pay invoice
</x-mail::button>

@if($invoice->notes)
> {{ $invoice->notes }}
@endif

You can see all your invoices any time on [your billing page]({{ $invoice->portalUrl() }}).

If you have any questions, just reply to this email.

Thanks,<br>
{{ $name }}

<img src="{{ url("preview/{$site->name}/invoice/{$invoice->public_token}/open.gif") }}" width="1" height="1" alt="">
</x-mail::message>
