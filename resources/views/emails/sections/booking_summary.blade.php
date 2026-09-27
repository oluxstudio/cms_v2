{{-- Booking details box — $data: {summary, reference, total, paid, balance, notes} --}}
@if(!empty($data))
<tr>
    <td style="padding:8px 32px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #eef0f3;border-radius:12px;">
            <tr><td style="padding:14px 16px 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;">Booking details</td></tr>
            @if(!empty($data['summary']))<tr><td style="padding:4px 16px;font-size:13px;color:#374151;">{{ $data['summary'] }}</td></tr>@endif
            @if(!empty($data['reference']))<tr><td style="padding:4px 16px;font-size:13px;color:#374151;"><strong style="color:#111827;">Reference:</strong> {{ $data['reference'] }}</td></tr>@endif
            @if(!empty($data['total']))<tr><td style="padding:4px 16px;font-size:13px;color:#374151;"><strong style="color:#111827;">Total:</strong> {{ $data['total'] }}</td></tr>@endif
            @if(!empty($data['paid']))<tr><td style="padding:4px 16px;font-size:13px;color:#374151;"><strong style="color:#111827;">Paid:</strong> {{ $data['paid'] }}</td></tr>@endif
            @if(!empty($data['balance']))<tr><td style="padding:4px 16px;font-size:13px;color:#374151;"><strong style="color:#111827;">Balance due at arrival:</strong> {{ $data['balance'] }}</td></tr>@endif
            @if(!empty($data['notes']))<tr><td style="padding:4px 16px;font-size:13px;color:#6b7280;font-style:italic;">{{ $data['notes'] }}</td></tr>@endif
            <tr><td style="height:12px;"></td></tr>
        </table>
    </td>
</tr>
@endif
