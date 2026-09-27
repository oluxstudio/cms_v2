{{-- Invoice details + pay button — $data: {number, total, due, items[], tax, pay_url, portal_url} --}}
@if(!empty($data))
<tr>
    <td style="padding:8px 32px 8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #eef0f3;border-radius:12px;">
            <tr><td colspan="2" style="padding:14px 16px 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;">Invoice {{ $data['number'] ?? '' }}@if(!empty($data['due'])) · due {{ $data['due'] }}@endif</td></tr>
            @foreach(($data['items'] ?? []) as $item)
            <tr>
                <td style="padding:4px 16px;font-size:13px;color:#374151;">{{ $item['description'] }} @if(!empty($item['qty'])) × {{ $item['qty'] }}@endif</td>
                <td align="right" style="padding:4px 16px;font-size:13px;color:#111827;font-weight:600;">{{ $item['amount'] ?? '' }}</td>
            </tr>
            @endforeach
            @if(!empty($data['tax']))
            <tr>
                <td style="padding:4px 16px;font-size:13px;color:#374151;">Tax</td>
                <td align="right" style="padding:4px 16px;font-size:13px;color:#111827;font-weight:600;">{{ $data['tax'] }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding:8px 16px 14px;font-size:13px;font-weight:700;color:#111827;">Total</td>
                <td align="right" style="padding:8px 16px 14px;font-size:13px;font-weight:800;color:#111827;">{{ $data['total'] ?? '' }}</td>
            </tr>
        </table>
    </td>
</tr>
@if(!empty($data['pay_url']))
<tr>
    <td align="center" style="padding:8px 32px 12px;">
        <a href="{{ $data['pay_url'] }}" style="display:inline-block;background:#111827;color:#ffffff;font-size:14px;font-weight:700;padding:12px 28px;border-radius:10px;text-decoration:none;">View &amp; pay invoice</a>
    </td>
</tr>
@endif
@if(!empty($data['portal_url']))
<tr>
    <td align="center" style="padding:0 32px 12px;font-size:12px;color:#9ca3af;">
        You can see all your invoices any time on <a href="{{ $data['portal_url'] }}" style="color:#6b7280;">your billing page</a>.
    </td>
</tr>
@endif
@endif
