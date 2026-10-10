@php
    $copy = match ($state) {
        'confirmed' => ['You\'re subscribed', 'Thanks for confirming — you\'ll now get emails from '.$siteName.'.'],
        'inactive' => ['Nothing to confirm', 'This address isn\'t waiting for confirmation any more.'],
        'ask' => ['Unsubscribe?', 'Stop emails from '.$siteName.' to '.$subscription->email.'.'],
        default => ['You\'re unsubscribed', $subscription->email.' won\'t get any more emails from '.$siteName.'.'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $copy[0] }} · {{ $siteName }}</title>
    <style>
        :root { color-scheme: light dark; --bg:#f3f4f6; --card:#fff; --ink:#111827; --muted:#6b7280; --line:#e5e7eb; --btn:#111827; --on-btn:#fff; }
        @media (prefers-color-scheme: dark) { :root { --bg:#14151f; --card:#1d1e2a; --ink:#f3f4f6; --muted:#9ca3af; --line:rgba(255,255,255,.08); --btn:#f3f4f6; --on-btn:#111827; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px 16px; background:var(--bg); color:var(--ink);
               font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; }
        .card { width:100%; max-width:440px; background:var(--card); border:1px solid var(--line); border-radius:20px; padding:32px 28px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .logo { max-height:40px; max-width:200px; margin:0 auto 18px; display:block; }
        .name { font-weight:800; font-size:15px; color:var(--muted); margin:0 0 18px; }
        h1 { font-size:22px; margin:0 0 8px; letter-spacing:-.01em; }
        p { margin:0; color:var(--muted); line-height:1.6; font-size:15px; overflow-wrap:anywhere; }
        button { margin-top:22px; border:0; border-radius:12px; padding:12px 22px; font-weight:700; font-size:15px; background:var(--btn); color:var(--on-btn); cursor:pointer; }
    </style>
</head>
<body>
    <main class="card">
        @if ($logo)
            <img class="logo" src="{{ $logo }}" alt="{{ $siteName }}">
        @else
            <p class="name">{{ $siteName }}</p>
        @endif
        <h1>{{ $copy[0] }}</h1>
        <p>{{ $copy[1] }}</p>
        @if ($state === 'ask')
            <form method="POST" action="{{ route('newsletter.unsubscribe.post', $subscription->token) }}">
                @csrf
                @if ($sendToken !== '')<input type="hidden" name="c" value="{{ $sendToken }}">@endif
                <button type="submit">Unsubscribe</button>
            </form>
        @endif
    </main>
</body>
</html>
