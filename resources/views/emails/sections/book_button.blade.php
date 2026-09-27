{{-- Book-now CTA — $data: {url, label}; no url = "just reply" line --}}
<tr>
    <td align="center" style="padding:12px 32px 16px;">
        @if(!empty($data['url']))
            <a href="{{ $data['url'] }}" style="display:inline-block;background:#111827;color:#ffffff;font-size:14px;font-weight:700;padding:12px 28px;border-radius:10px;text-decoration:none;">{{ $data['label'] ?? 'Book now' }}</a>
        @else
            <span style="font-size:14px;color:#374151;">Reply to this email and we'll get you booked in.</span>
        @endif
    </td>
</tr>
