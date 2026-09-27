{{-- Order lines + track button — $data: {items[], total, vat, number, fulfilment, shipping_address, status_url} --}}
@if(!empty($data))
<tr>
    <td style="padding:8px 32px 8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #eef0f3;border-radius:12px;">
            <tr><td colspan="2" style="padding:14px 16px 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;">Order {{ $data['number'] ?? '' }}</td></tr>
            @foreach(($data['items'] ?? []) as $item)
            <tr>
                <td style="padding:4px 16px;font-size:13px;color:#374151;">{{ $item['qty'] }} × {{ $item['name'] }}</td>
                <td align="right" style="padding:4px 16px;font-size:13px;color:#111827;font-weight:600;">{{ $item['amount'] ?? '' }}</td>
            </tr>
            @endforeach
            <tr>
                <td style="padding:8px 16px 6px;font-size:13px;font-weight:700;color:#111827;">Total</td>
                <td align="right" style="padding:8px 16px 6px;font-size:13px;font-weight:800;color:#111827;">{{ $data['total'] ?? '' }}</td>
            </tr>
            @if(!empty($data['vat']))<tr><td colspan="2" style="padding:0 16px 6px;font-size:12px;color:#6b7280;">{{ $data['vat'] }}</td></tr>@endif
            @if(($data['fulfilment'] ?? null) === 'collection')
                <tr><td colspan="2" style="padding:4px 16px 14px;font-size:13px;color:#374151;"><strong style="color:#111827;">Collection</strong> — we'll let you know as soon as your order is ready to collect.</td></tr>
            @elseif(!empty($data['shipping_address']))
                <tr><td colspan="2" style="padding:4px 16px 14px;font-size:13px;color:#374151;"><strong style="color:#111827;">Delivering to:</strong> {{ $data['shipping_address'] }}</td></tr>
            @else
                <tr><td colspan="2" style="height:12px;"></td></tr>
            @endif
        </table>
    </td>
</tr>
@if(!empty($data['status_url']))
<tr>
    <td align="center" style="padding:8px 32px 16px;">
        <a href="{{ $data['status_url'] }}" style="display:inline-block;background:#111827;color:#ffffff;font-size:14px;font-weight:700;padding:12px 28px;border-radius:10px;text-decoration:none;">Track your order</a>
    </td>
</tr>
@endif
@endif
