{{-- Shown on a live site's own address while Site Properties › Maintenance is on (503). --}}
@php
    $accent = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($site->theme['accent'] ?? '')) ? $site->theme['accent'] : '#6366f1';
    $phone = collect($p['phones'])->firstWhere('value')['value'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ $p['locale']['language'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $p['name'] }} — back soon</title>
    @if (! empty($p['icons'][32]))<link rel="icon" type="image/png" href="{{ $p['icons'][32] }}">@endif
    <style>
        body { margin:0; font-family:ui-sans-serif,system-ui,sans-serif; background:#f7f7fb; color:#111827;
               min-height:100vh; display:flex; align-items:center; justify-content:center; text-align:center; padding:24px; box-sizing:border-box; }
        .card { max-width:520px; }
        img { max-height:72px; max-width:220px; margin-bottom:20px; }
        h1 { font-size:1.9rem; font-weight:800; margin:0 0 .6rem; }
        p { color:#4b5563; font-size:1rem; line-height:1.55; margin:0 0 1rem; }
        .bar { width:56px; height:4px; border-radius:4px; background:{{ $accent }}; margin:0 auto 22px; }
        a { color:{{ $accent }}; font-weight:700; text-decoration:none; }
        @media (prefers-color-scheme: dark) { body { background:#111320; color:#f3f4f6 } p { color:#c7cad3 } }
    </style>
</head>
<body>
    <div class="card">
        @if ($p['logo'])<img src="{{ $p['logo'] }}" alt="{{ $p['name'] }}">@endif
        <div class="bar"></div>
        <h1>{{ $p['name'] }}</h1>
        <p>{{ $p['status']['maintenance_message'] ?: 'We\'re making a few improvements — back very soon.' }}</p>
        @if ($p['email'] || $phone)
            <p>
                @if ($p['email'])<a href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a>@endif
                @if ($p['email'] && $phone) · @endif
                @if ($phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>@endif
            </p>
        @endif
    </div>
</body>
</html>
