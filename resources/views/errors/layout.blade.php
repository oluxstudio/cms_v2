<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('app.name', 'Olux') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* theme tokens (light default → orange/cream; dark → navy/baby blue) */
        :root{ --primary:#f97316; --primary-2:#fdba74; }
        @media (prefers-color-scheme: dark){ :root{ --primary:#1e3a8a; --primary-2:#7dd3fc; } }
        *{box-sizing:border-box;margin:0;padding:0}
        html,body{height:100%}
        body{
            font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:#475569;
            position:relative;overflow:hidden;
            /* clean light canvas + faint dotted texture — no gray frame */
            background:
                radial-gradient(rgba(148,163,184,.18) 1px, transparent 1px) 0 0 / 18px 18px,
                linear-gradient(180deg,#fbfbfd 0%,#f6f7fb 100%);
        }

        /* ── floating gradient balls (rise upward, loop forever) ── */
        .balls{position:absolute;inset:0;overflow:hidden;z-index:0;pointer-events:none}
        .ball{
            position:absolute;bottom:-260px;border-radius:50%;
            background:radial-gradient(circle at 35% 28%, var(--primary-2) 0%, var(--primary) 78%);
            box-shadow:0 24px 60px rgba(15,23,42,.16);
            opacity:.92;will-change:transform;
            animation:floatUp linear infinite;
        }
        @keyframes floatUp{
            0%   {transform:translateY(0)        translateX(0)}
            50%  {transform:translateY(-65vh)    translateX(var(--drift,0))}
            100% {transform:translateY(-135vh)   translateX(0)}
        }

        /* ── content ── */
        .stage{position:relative;z-index:2;height:100%;display:flex;align-items:center}
        .inner{width:100%;max-width:1100px;margin:0 auto;padding:0 clamp(24px,6vw,72px)}
        .watermark{
            position:absolute;top:50%;left:clamp(16px,5vw,64px);transform:translateY(-62%);
            font-size:clamp(9rem,34vw,26rem);font-weight:800;line-height:.8;letter-spacing:-.05em;
            color:color-mix(in srgb, var(--primary) 13%, #ffffff);z-index:1;user-select:none;pointer-events:none;
        }
        .copy{position:relative;z-index:3;max-width:520px}
        .copy h1{font-size:clamp(2.4rem,6vw,3.6rem);font-weight:800;color:#3f4754;letter-spacing:-.02em}
        .copy p{font-size:clamp(.95rem,2.4vw,1.15rem);color:#94a3b8;margin-top:.4rem}
        .copy .home{
            display:inline-flex;align-items:center;gap:.55rem;margin-top:1.8rem;
            font-weight:700;font-size:.95rem;color:var(--primary);text-decoration:none;
        }
        .copy .home svg{transition:transform .2s}
        .copy .home:hover svg{transform:translateX(6px)}

        @media (prefers-color-scheme: dark){
            body{color:#cbd5e1;background:
                radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px) 0 0 / 18px 18px,
                linear-gradient(180deg,#0c0e16 0%,#11131c 100%)}
            .watermark{color:color-mix(in srgb, var(--primary-2) 14%, transparent)}
            .copy h1{color:#e2e8f0}.copy p{color:#64748b}
            /* navy is too dark on a dark bg — use the baby-blue secondary for the link */
            .copy .home{color:var(--primary-2)}
        }
    </style>
</head>
<body>
    {{-- balls: fixed configs so each render is stable; negative delays pre-fill the screen --}}
    @php
        $balls = [
            ['l'=>'4%',  's'=>120, 'd'=>16, 'delay'=>-2,  'drift'=>'30px'],
            ['l'=>'16%', 's'=>70,  'd'=>13, 'delay'=>-8,  'drift'=>'-20px'],
            ['l'=>'30%', 's'=>180, 'd'=>22, 'delay'=>-14, 'drift'=>'40px'],
            ['l'=>'44%', 's'=>90,  'd'=>15, 'delay'=>-4,  'drift'=>'-30px'],
            ['l'=>'55%', 's'=>150, 'd'=>19, 'delay'=>-11, 'drift'=>'25px'],
            ['l'=>'68%', 's'=>110, 'd'=>17, 'delay'=>-6,  'drift'=>'-35px'],
            ['l'=>'80%', 's'=>220, 'd'=>24, 'delay'=>-18, 'drift'=>'30px'],
            ['l'=>'90%', 's'=>80,  'd'=>14, 'delay'=>-3,  'drift'=>'-15px'],
            ['l'=>'-2%', 's'=>160, 'd'=>21, 'delay'=>-9,  'drift'=>'20px'],
        ];
    @endphp
    <div class="balls">
        @foreach ($balls as $b)
            <span class="ball" style="
                left:{{ $b['l'] }};
                width:{{ $b['s'] }}px;height:{{ $b['s'] }}px;
                animation-duration:{{ $b['d'] }}s;animation-delay:{{ $b['delay'] }}s;
                --drift:{{ $b['drift'] }};
                @if($b['s']>170) filter:blur(2px);opacity:.8; @endif"></span>
        @endforeach
    </div>

    <div class="stage">
        <div class="inner">
            <div class="watermark">@yield('code')</div>
            <div class="copy">
                <h1>@yield('heading', 'Ooops!')</h1>
                <p>@yield('message')</p>
                <a class="home" href="{{ url('/') }}">
                    home page
                    <svg width="22" height="14" viewBox="0 0 24 14" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2 7h19m0 0l-6-5m6 5l-6 5"/></svg>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
