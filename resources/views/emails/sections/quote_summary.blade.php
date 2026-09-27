{{-- Estimator results — $data: {results:[{label,formatted}], reference} --}}
@if(!empty($data['results']))
<tr>
    <td style="padding:8px 32px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #eef0f3;border-radius:12px;">
            <tr><td colspan="2" style="padding:14px 16px 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;">Your estimate @if(!empty($data['reference']))· {{ $data['reference'] }}@endif</td></tr>
            @foreach($data['results'] as $row)
            <tr>
                <td style="padding:4px 16px;font-size:13px;color:#374151;">{{ $row['label'] }}</td>
                <td align="right" style="padding:4px 16px;font-size:13px;color:#111827;font-weight:700;">{{ $row['formatted'] }}</td>
            </tr>
            @endforeach
            <tr><td colspan="2" style="height:12px;"></td></tr>
        </table>
    </td>
</tr>
@endif
