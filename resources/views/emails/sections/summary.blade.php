{{-- Submission summary — key => value pairs from $summary --}}
@if(!empty($summary))
<tr>
    <td style="padding:8px 32px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #eef0f3;border-radius:12px;">
            <tr><td style="padding:14px 16px 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;">What you sent</td></tr>
            @foreach($summary as $label => $value)
            <tr>
                <td style="padding:4px 16px;font-size:13px;color:#374151;">
                    <strong style="color:#111827;">{{ \Illuminate\Support\Str::headline((string) $label) }}:</strong>
                    {{ is_array($value) ? implode(', ', $value) : $value }}
                </td>
            </tr>
            @endforeach
            <tr><td style="height:12px;"></td></tr>
        </table>
    </td>
</tr>
@endif
