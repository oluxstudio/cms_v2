<x-mail::message>
# {{ ucwords(str_replace('-', ' ', $site->name)) }}

Hi {{ $invoice->customer_name }},

@if($invoice->status === 'overdue')
Just a friendly reminder that invoice **{{ $invoice->number }}** for **{{ $invoice->formattedTotal() }}**@if($invoice->due_date) was due on **{{ $invoice->due_date->format('F j, Y') }}**@endif and is still outstanding. We'd appreciate it if you could settle it at your earliest convenience.
@else
Just a friendly heads-up that invoice **{{ $invoice->number }}** for **{{ $invoice->formattedTotal() }}**@if($invoice->due_date) is due on **{{ $invoice->due_date->format('F j, Y') }}**@endif.
@endif

<x-mail::button :url="$invoice->payUrl()">
Pay {{ $invoice->formattedTotal() }}
</x-mail::button>

You can see all your invoices any time on [your billing page]({{ $invoice->portalUrl() }}).

If you've already paid, please disregard this email — and thank you!

{{ ucwords(str_replace('-', ' ', $site->name)) }}

<img src="{{ url("preview/{$site->name}/invoice/{$invoice->public_token}/open.gif") }}" width="1" height="1" alt="">
</x-mail::message>
