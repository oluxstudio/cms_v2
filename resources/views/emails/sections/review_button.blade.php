{{-- Review CTA — $data: {url, label} --}}
@if(!empty($data['url']))
<tr>
    <td align="center" style="padding:12px 32px 16px;">
        <a href="{{ $data['url'] }}" style="display:inline-block;background:#111827;color:#ffffff;font-size:14px;font-weight:700;padding:12px 28px;border-radius:10px;text-decoration:none;">{{ $data['label'] ?? 'Leave a review' }}</a>
    </td>
</tr>
@endif
