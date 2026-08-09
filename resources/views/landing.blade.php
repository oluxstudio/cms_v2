<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Olux Studio — Websites, leads &amp; bookings in one platform</title>
    <meta name="description" content="Olux Studio CMS: build sites, capture leads, take bookings and payments, send invoices and go live on your own domain — with a built-in CRM and AI assistant.">
    <link rel="icon" href="{{asset('favicon.ico')}}">
    {{-- Brand fonts — same files the main site (oluxstudio.com) serves --}}
    <style>
        @font-face { font-family: 'junegull'; src: url('/fonts/junegull.otf'); font-display: swap; }
        @font-face { font-family: 'garet'; font-weight: 400; src: url('/fonts/Garet-Book.woff2') format('woff2'); font-display: swap; }
        @font-face { font-family: 'garet'; font-weight: 700; src: url('/fonts/Garet-Heavy.woff2') format('woff2'); font-display: swap; }
        @font-face { font-family: 'comforta'; font-weight: 400; src: url('/fonts/Comfortaa-Regular.ttf'); font-display: swap; }
        @font-face { font-family: 'comforta'; font-weight: 700; src: url('/fonts/Comfortaa-Bold.ttf'); font-display: swap; }
    </style>
    {{-- Tailwind (compiled app bundle). The landing's own CSS below lives in
         the `components` cascade layer, so TAILWIND UTILITIES ALWAYS WIN —
         add any utility class anywhere on this page and it applies. --}}
    @vite('resources/css/app.css')
    @php
        use App\Support\Money;
        $tiers = collect(config('plans.tiers'))->sortBy('order');
    @endphp
    <style>
    /* Theme pair for buttons — UNLAYERED on purpose: these names also exist in
       app.css (unlayered), and only unlayered definitions here can win.
       The two colors INVERT with the theme toggle. */
    :root { --background: #120f14; --foreground: #f8f5f2; }   /* dark theme  */
    .light { --background: #fbdeb5; --foreground: #2b1c0a; }  /* light theme */

    /* Full landing palette — UNLAYERED so these names (esp. --primary) beat
       app.css's own :root definitions. */
    /* ── Palette: the MAIN SITE's default theme (assets/styles/main.css) ── */
    :root {
        --primary: #e38704;             /* rgb(227,135,4) */
        --primary-2: #f77315;           /* secondary rgb(247,115,21) */
        --primary-3: #5e3802;           /* tertiary rgb(94,56,2) */
        --primary-4: #3a2301;           /* deepest shade */
        --penta: #fbbf24;               /* rgb(251,191,36) amber */
        --bg: #120f14;              /* back rgb(18,15,20) */
        --on-bg: #f8f5f2;               /* fore rgb(248,245,242) */
        --on-bg-soft: rgba(248,245,242,.87);
        --on-bg-chip: rgba(255,255,255,.07);
        --surface: #1b1620;             /* elevated card on dark */
        --surface-2: #17121c;           /* alt band */
        --line-inv: rgba(255,255,255,.12);
        --accent-on-bg: var(--penta);
    }

    /* ── LIGHT THEME: warm apricot re-map of the same token system ── */
    .light {
        --bg: #fbdeb5;              /* apricot canvas */
        --on-bg: #2b1c0a;               /* deep warm brown text */
        --on-bg-soft: rgba(43,28,10,.87);
        --on-bg-chip: rgba(94,56,2,.08);
        --surface: #fff3dd;             /* cards lift LIGHTER than the canvas */
        --surface-2: #f4cf9a;           /* alt bands sink DEEPER than the canvas */
        --line-inv: rgba(94,56,2,.2);
        --penta: #913f01;               /* amber deepened for contrast */
        --accent-on-bg: #b45309;			
			--foreground: #241a10; 
			--background: #fbbf24;
    }


    @layer components { /* below Tailwind's `utilities` layer → utilities override this sheet */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        /* Main-site body rhythm: garet, text-lg / leading-8 */
        body { font-family: 'garet', sans-serif; color: var(--on-bg); background: var(--bg); font-size: 1.125rem; line-height: 2rem; }
        h1, h2, h3 { font-family: 'junegull', 'trebuchet ms', sans-serif; line-height: 1.15; font-weight: 400; }
        a { color: inherit; text-decoration: none; }
        /* Main-site container rhythm: px-4, prose capped near max-w-3xl+ */
        .wrap { max-width: 96rem; margin: 80px auto; padding: 0 1rem; }
        section { padding: 3.5rem 0; }               /* py-14 */
        /* .section-label from the main site: comforta, bold, uppercase, widest */
        .eyebrow { display: inline-block; font-family: 'comforta', sans-serif; font-size: .875rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--primary); margin-bottom: .75rem; }
        /* .section-header: junegull display */
        .h2 { font-size: clamp(30px, 4.4vw, 48px); text-transform: uppercase; margin-bottom: .75rem; color: var(--on-bg); }
        .sub { color: var(--on-bg-soft); max-width: 48rem; font-size: 1rem; line-height: 1.8; }
        /* Alt bands: slightly lifted dark, same dark theme as the main site */
        .alt { background: var(--surface-2); opacity: 0.95; border-block: 1px solid var(--line-inv); color: var(--on-bg); }
        .alt .eyebrow { color: var(--primary); }
        .alt .h2 { color: var(--on-bg); }
        .alt .sub { color: var(--on-bg-soft); }
        .btn { display: inline-block; padding: 15px 46px !important; border-radius: 12px; font-weight: 700; font-size: 19px; transition: transform .15s, box-shadow .15s, background .15s; }
        .btn { font-family: 'comforta', sans-serif; }
        .btn-primary { background: linear-gradient(120deg, var(--primary), var(--primary-2)); color: #fff; box-shadow: 0 12px 26px -12px rgba(227,135,4,.55); }
        .btn-primary:hover { filter: brightness(1.1); transform: translateY(-2px); }
        /* Inverted button: light pill on dark */
        /* Inverted pill: text-color fill + canvas-color label — the pair always
           contrasts, in BOTH themes, with no per-theme override needed. */
        .btn-invert { background: var(--primary-2); color: var(--foreground); box-shadow: 0 12px 26px -12px rgba(0,0,0,.5); }
        .btn-invert:hover { filter: brightness(1.12); transform: translateY(-2px); }
        /* Contrast button: canvas-on-text inversion — flips with the theme toggle */
        .btn-dark { background: var(--background); color: var(--foreground); border: 2px solid var(--foreground); }
        .btn-dark:hover { background: var(--foreground); color: var(--background); transform: translateY(-2px); }
        .btn-ghost { border: 2px solid var(--line-inv); color: var(--on-bg); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--penta); }

        /* ── Nav: sticky, logo left, links center, CTA at the end ── */
        nav { position: sticky; top: 0; z-index: 60; background: color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line-inv); color: var(--on-bg); }
        .nav-inner { display: flex; align-items: center; gap: 26px; height: 96px; }
        .logo { font-family: 'junegull', sans-serif; font-size: 21px; }
        .logo b { color: var(--accent-on-bg); }
        /* Image logo: render as pure WHITE so it reads on the orange nav */
        .logo img { filter: brightness(0) invert(1); }
        .nav-links { display: flex; gap: 40px; font-family: 'comforta', sans-serif; font-size: 16.5px; font-weight: 700; color: var(--on-bg-soft); }
        .nav-links a:hover { color: var(--penta); }
        .nav-cta { display: flex; gap: 20px; margin-left: 8px; align-items: center;}
        .nav-cta .btn { padding: 9px 18px; }
        /* Hamburger — mobile only */
        .nav-burger { display: none; width: 40px; height: 40px; border-radius: 9999px; border: 2px solid var(--line-inv); background: transparent; color: var(--on-bg); font-size: 17px; line-height: 1; cursor: pointer; transition: border-color .2s; }
        .nav-burger:hover { border-color: var(--primary); }
        .nav-auth-mobile { display: none; }

        @media (max-width: 800px) {
            .nav-inner { height: 68px; gap: 10px; }
            /* Links collapse into a full-width dropdown under the bar */
            .nav-links {
                display: none;
                position: absolute; top: 100%; left: 0; right: 0;
                flex-direction: column; gap: 2px;
                background: color-mix(in srgb, var(--bg) 97%, transparent);
                backdrop-filter: blur(10px);
                border-bottom: 1px solid var(--line-inv);
                padding: 12px 16px 16px;
            }
            nav.nav-open .nav-links { display: flex; }
            .nav-links a { padding: 11px 10px; border-radius: 10px; }
            .nav-links a:hover { background: var(--on-bg-chip); }
            /* Auth buttons move into the panel; the THEME TOGGLE stays on top */
            .nav-cta { margin-left: auto; gap: 10px; }
            .nav-cta > .btn { display: none; }
            .nav-burger { display: grid; place-items: center; }
            .nav-auth-mobile { display: flex; gap: 10px; margin-top: 12px; }
            .nav-auth-mobile .btn { display: block; flex: 1; text-align: center; padding: 12px 10px !important; font-size: 15px; }
        }

        /* ── Ambient background: FIXED layer, elements drift while the page
              scrolls over them (same idea as the app's ambient blobs) ── */
        .ambient { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
        .ambient span {
            position: absolute; display: block;
            animation: drift var(--dur, 18s) ease-in-out var(--delay, 0s) infinite;
            opacity: .35;
        }
        .ambient .sq { border-radius: 22%; }
        .ambient .dot { border-radius: 9999px; }
        @keyframes drift {
            0%, 100% { transform: translate(0, 0) rotate(0deg) scale(1); }
            25%      { transform: translate(4vw, -6vh) rotate(35deg) scale(1.12); }
            50%      { transform: translate(-3vw, 5vh) rotate(-20deg) scale(.92); }
            75%      { transform: translate(5vw, 3vh) rotate(15deg) scale(1.05); }
        }
        @media (prefers-reduced-motion: reduce) { .ambient span { animation: none; } }

        /* ── Hero: CENTERED single column ── */
        .hero { padding: 92px 0 118px; position: relative; text-align: center; } /* extra bottom room for the pinned scroll cue */
        .hero h1 { font-size: clamp(38px, 5.6vw, 74px); text-transform: uppercase; max-width: 900px; margin: 0 auto 16px; color: var(--on-bg); }
        .hero h1 em { font-style: normal; background: linear-gradient(92deg, var(--primary), var(--primary-2), var(--penta)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero .tagline { font-family: 'comforta', sans-serif; font-weight: 700; color: var(--penta); margin-bottom: 14px; font-size: .95rem; }
        .hero p.lede { color: var(--on-bg-soft); max-width: 600px; margin: 0 auto 26px; font-size: 16px; }
        .stat-badges { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 28px; justify-content: center; }
        .stat-badges span { background: var(--on-bg-chip); color: var(--penta); border: 1px solid var(--line-inv); font-family: 'comforta', sans-serif; font-weight: 700; font-size: 12.5px; padding: 8px 14px; border-radius: 10px; }
        .hero-ctas { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 40px; margin-top: 30px; justify-content: center; }
        .stack { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center; color: var(--on-bg-soft); font-family: 'comforta', sans-serif; font-size: 12.5px; }
        .stack-space { margin-bottom: 90px; } /* extra room for the scroll cue */
        .stack span { border: 1px solid var(--line-inv); padding: 3px 30px; border-radius: 9999px; font-weight: 600; background: var(--surface); color: var(--on-bg); font-size: 14px; }
        /* Scroll cue — centered, pinned to the hero's bottom edge:
           label + "mouse" pill with a dropping amber dot */
        .scroll-cue {
            position: absolute; left: 50%; bottom: 92px; transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center; gap: 9px;
            font-family: 'comforta', sans-serif; font-size: 10.5px; letter-spacing: .34em;
            font-weight: 700; color: var(--on-bg-soft); text-indent: .34em; /* balance tracking */
            transition: color .2s;
        }
        .scroll-cue:hover { color: var(--penta); }
        .scroll-cue i {
            display: block; width: 22px; height: 36px; position: relative;
            border: 2px solid var(--line-inv); border-radius: 9999px;
            transition: border-color .2s;
        }
        .scroll-cue:hover i { border-color: var(--penta); }
        .scroll-cue i::before {
            content: ''; position: absolute; left: 50%; top: 6px; margin-left: -2px;
            width: 4px; height: 8px; border-radius: 4px; background: var(--penta);
            animation: cue-drop 1.8s cubic-bezier(.4, 0, .6, 1) infinite;
        }
        @keyframes cue-drop {
            0%   { transform: translateY(0); opacity: 0; }
            25%  { opacity: 1; }
            70%  { transform: translateY(13px); opacity: 1; }
            100% { transform: translateY(16px); opacity: 0; }
        }
        @media (prefers-reduced-motion: reduce) { .scroll-cue i::before { animation: none; opacity: 1; } }

        /* ── Marquee: continuous horizontal scroll ── */
        .marquee { border-block: 1px solid var(--line-inv); background: var(--surface-2); padding: 18px 0; overflow: hidden; }
        .marquee-track { display: flex; gap: 44px; width: max-content; animation: marquee 28s linear infinite; }
        .marquee:hover .marquee-track { animation-play-state: paused; }
        .marquee-track span { white-space: nowrap; font-family: 'comforta', sans-serif; font-weight: 700; font-size: 15px; color: var(--on-bg-soft); }
        .marquee-track span b { color: var(--primary); margin-right: 8px; font-size: 12px; vertical-align: 1px; }
        @keyframes marquee { from { transform: translateX(0) } to { transform: translateX(-50%) } }

        /* ── About: two columns, quote box, stats row ── */
        .about { display: grid; gap: 46px; grid-template-columns: 1.15fr .85fr; align-items: center; }
        @media (max-width: 860px) { .about { grid-template-columns: 1fr; } }
        .quote-box { border-left: 4px solid var(--primary); background: var(--on-bg-chip); border-radius: 0 14px 14px 0; padding: 18px 20px; font-family: 'comforta', sans-serif; font-weight: 700; color: var(--penta); margin: 22px 0; font-size: .95rem; }
        .mini-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        @media (max-width: 800px) { .mini-stats { grid-template-columns: 1fr; gap: 30px; } }
        .mini-stats b { display: block; font-family: 'junegull', sans-serif; font-size: 28px; color: var(--primary); }
        .mini-stats span { font-size: 12.5px; color: var(--on-bg-soft); }
        .about-visual { background: linear-gradient(150deg, var(--primary), var(--primary-2)); border: 1px solid var(--line-inv); border-radius: 20px; padding: 26px; color: #fff; box-shadow: 0 26px 50px -26px rgba(227,135,4,.4); }
        .about-visual .row { background: rgba(255,255,255,.14); border-radius: 12px; padding: 13px 16px; margin-bottom: 10px; font-size: 13px; font-weight: 600; display: flex; justify-content: space-between; }
        .about-visual .row small { opacity: .75; font-weight: 400; }

        /* ── Arsenal: 4-column skill cards ── */
        .cards-4 { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-top: 36px; }
        .card { background: var(--surface); border: 1px solid var(--line-inv); border-radius: 16px; padding: 1.5rem; transition: transform .18s, box-shadow .18s, border-color .18s; }
        .card:hover, .step:hover, .plan:hover {
            transform: translateY(-5px); border-color: var(--primary);
            box-shadow: 0 0 0 1px var(--primary),
                        0 0 28px color-mix(in srgb, var(--primary) 55%, transparent),
                        0 22px 38px -22px rgba(227,135,4,.4);
        }
        .ico { width: 46px; height: 46px; border-radius: 13px; display: grid; place-items: center;
               background: color-mix(in srgb, var(--primary) 15%, transparent);
               border: 1px solid color-mix(in srgb, var(--primary) 35%, transparent); color: var(--primary); }
        .ico svg { width: 23px; height: 23px; }
        .card h3 { font-family: 'comforta', sans-serif; font-weight: 700; font-size: 19px; margin: 20px 0 15px; color: var(--on-bg); }
        .card p { font-size: 13px; line-height: 1.7; color: var(--on-bg-soft); margin-bottom: 12px; }
        .tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .tags span { font-family: 'comforta', sans-serif; font-size: 11px; font-weight: 700; background: rgba(227,135,4,.15); color: var(--penta); padding: 2px 20px; border-radius: 9999px; }

        /* ── Specialties: 6 cards ── */
        .cards-6 { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-top: 36px; }

        /* ── Process: 4 numbered steps ── */
        .steps { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); margin-top: 42px; }
        .step { position: relative; background: var(--surface); border: 1px solid var(--line-inv); border-radius: 16px; padding: 50px 22px 22px; }
        .step .num { position: absolute; top: -17px; left: 20px; background: linear-gradient(120deg, var(--primary), var(--primary-2)); color: #fff; font-family: 'junegull', sans-serif; font-size: 13px; padding: 7px 13px; border-radius: 10px; }
        .step h3 { font-family: 'comforta', sans-serif; font-weight: 700; font-size: 18px; margin-bottom: 8px; color: var(--on-bg); }
        .step ul { list-style: none; }
        .step li { font-size: 13px; line-height: 1.7; color: var(--on-bg-soft); padding: 3px 0 3px 18px; position: relative; }
        .step li::before { content: '→'; position: absolute; left: 0; color: var(--primary); }

        /* ── Pricing ── */
        .plans { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(205px, 1fr)); margin-top: 40px; align-items: stretch; }
        .plan { border: 1px solid var(--line-inv); border-radius: 16px; padding: 26px 20px; display: flex; flex-direction: column; background: var(--surface); position: relative; }
        .plan.hot { border: 2px solid var(--primary); box-shadow: 0 24px 46px -24px rgba(227,135,4,.5); }
        .plan .flag { background: linear-gradient(120deg, var(--primary), var(--primary-2)); }
        .plan .flag { position: absolute; top: -13px; left: 50%; transform: translateX(-50%); background: var(--primary); color: #fff; font-size: 11px; font-weight: 700; padding: 4px 14px; border-radius: 9999px; white-space: nowrap; }
        .plan h3 { font-size: 16px; color: var(--primary-2); }
        .plan .tag { font-size: 12px; color: var(--muted); margin: 2px 0 12px; }
        .plan .price { font-family: 'Sora', sans-serif; font-size: 30px; font-weight: 800; }
        .plan .price small { font-size: 12.5px; color: var(--muted); font-family: 'Inter', sans-serif; font-weight: 500; }
        .plan ul { list-style: none; margin: 14px 0 20px; flex: 1; }
        .plan li { font-size: 12.5px; color: var(--muted); padding: 4px 0 4px 20px; position: relative; }
        .plan li::before { content: '✓'; position: absolute; left: 0; color: var(--primary); font-weight: 800; }
        .plan .btn { text-align: center; padding: 11px 16px; }

        /* ── Carousel: quotes, arrows + dots + autoplay ── */
        .carousel { position: relative; max-width: 760px; margin: 40px auto 0; }
        .car-view { overflow: hidden; border-radius: 18px; }
        .car-track { display: flex; transition: transform .5s cubic-bezier(.4,0,.2,1); }
        .slide { min-width: 100%; background: var(--surface); border: 1px solid var(--line-inv); border-radius: 18px; padding: 38px 42px; text-align: center; }
        .slide q { font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 600; display: block; margin-bottom: 18px; }
        .slide .who { display: inline-flex; align-items: center; gap: 12px; }
        .slide .ava { width: 42px; height: 42px; border-radius: 9999px; background: linear-gradient(140deg, var(--primary), var(--primary-3)); color: #fff; font-weight: 800; display: grid; place-items: center; font-size: 14px; }
        .slide .who small { display: block; color: var(--muted); }
        .car-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 40px; height: 40px; border-radius: 9999px; border: 1px solid var(--line-inv); background: var(--surface); color: var(--penta); font-size: 17px; cursor: pointer; transition: all .15s; }
        .car-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .car-prev { left: -18px; } .car-next { right: -18px; }
        .dots { display: flex; gap: 8px; justify-content: center; margin-top: 18px; }
        .dots button { width: 9px; height: 9px; border-radius: 9999px; border: 0; background: var(--line); cursor: pointer; transition: all .2s; }
        .dots button.on { background: var(--primary); width: 24px; }

        /* ── FAQ accordion ── */
        details { border: 1px solid var(--line-inv); background: var(--surface); border-radius: 14px; padding: 16px 20px; margin-top: 12px; }
        details summary { cursor: pointer; font-weight: 700; font-size: 14.5px; list-style: none; display: flex; justify-content: space-between; align-items: center; font-family: 'Sora', sans-serif; }
        details summary::-webkit-details-marker { display: none; }
        details summary::after { content: '+'; font-size: 20px; color: var(--primary); transition: transform .2s; }
        details[open] summary::after { transform: rotate(45deg); }
        details p { margin-top: 10px; font-size: 13.5px; color: var(--muted); }

        /* ── CTA band + footer ── */
        .band { background: linear-gradient(120deg, var(--primary-3), var(--primary-4)); border: 1px solid var(--line-inv); border-radius: 20px; padding: 56px 32px; text-align: center; color: #fff; }
        .band h2 { font-size: clamp(24px, 3.4vw, 34px); margin-bottom: 10px; }
        .band p { opacity: .88; margin-bottom: 26px; }
        .band .btn { background: #fff; color: var(--primary-4); }
        footer { background: var(--line-inv); border-top: 1px solid var(--line-inv); color: var(--on-bg-soft); padding: 52px 0 30px; }
        .foot-grid { display: grid; gap: 30px; grid-template-columns: 1.4fr 1fr 1fr 1fr; margin-bottom: 34px; }
        @media (max-width: 760px) { .foot-grid { grid-template-columns: 1fr 1fr; } }
        footer h4 { font-family: 'junegull', sans-serif; color: var(--on-bg); font-size: 14px; margin-bottom: 12px; }
        footer a { display: block; font-size: 13px; padding: 3px 0; }
        footer a:hover { opacity: 1; color: #fff; }
        .foot-base { border-top: 1px solid rgba(255,255,255,.18); padding-top: 20px; font-size: 12.5px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; opacity: .85; }

        /* ── Theme toggle (next to the auth buttons) ── */
        .theme-toggle {
            width: 40px; height: 40px; border-radius: 9999px; cursor: pointer;
            border: 2px solid var(--line-inv); background: transparent;
            font-size: 16px; line-height: 1; display: grid; place-items: center;
            transition: border-color .2s, transform .15s;
        }
        .theme-toggle:hover { border-color: var(--primary); transform: translateY(-1px); }
        .theme-toggle .tt-moon { display: none; }
        .light .theme-toggle .tt-sun { display: none; }
        .light .theme-toggle .tt-moon { display: block; }

        /* Ambient shape tints — theme-aware (dark set / light set) */
        .ambient .c0 { background: rgba(255,255,255,.5); }
        .ambient .c1 { background: rgba(255,255,255,.32); }
        .ambient .c2 { background: rgba(255,225,196,.55); }
        .ambient .c3 { background: rgba(227,135,4,.45); }
        .ambient .c4 { background: rgba(247,115,21,.4); }
        .ambient .c5 { background: rgba(251,191,36,.4); }
        .light .ambient .c0 { background: rgba(227,135,4,.3); }
        .light .ambient .c1 { background: rgba(94,56,2,.22); }
        .light .ambient .c2 { background: rgba(247,115,21,.3); }
        .light .ambient .c3 { background: rgba(227,135,4,.4); }
        .light .ambient .c4 { background: rgba(180,83,9,.3); }
        .light .ambient .c5 { background: rgba(251,191,36,.45); }

        body, nav, .slide, details, .alt, .marquee { transition: background-color .3s, color .3s, border-color .3s; }
        /* Tiles: ONE combined transition — smooth hover lift + glow AND theme
           cross-fade (a later transition rule would otherwise wipe the earlier one) */
        .card, .step, .plan {
            transition:
                transform .28s cubic-bezier(.4, 0, .2, 1),
                box-shadow .28s cubic-bezier(.4, 0, .2, 1),
                border-color .28s ease,
                background-color .3s, color .3s;
            will-change: transform;
        }
        .light .logo img { filter: brightness(0); } /* black logo on light nav */
        .light .eyebrow { color: var(--penta); }             /* deep amber on apricot */
        .light .marquee-track span b { color: var(--penta); }
        .light .hero h1 em { background: linear-gradient(92deg, var(--primary-2), #b45309); -webkit-background-clip: text; background-clip: text; }
    } /* /@layer components */
    </style>
</head>
<body>
    <script>
        // Apply the saved theme before first paint (no flash), default = dark.
        if (localStorage.getItem('olux_landing_theme') === 'light') {
            document.documentElement.classList.add('light');
        }
        function toggleTheme() {
            var light = document.documentElement.classList.toggle('light');
            localStorage.setItem('olux_landing_theme', light ? 'light' : 'dark');
        }
        function toggleNav(btn) {
            var open = document.querySelector('nav').classList.toggle('nav-open');
            btn.textContent = open ? '✕' : '☰';
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        }
        // Tapping a menu link closes the panel again.
        document.addEventListener('click', function (e) {
            if (e.target.closest('.nav-links a')) {
                var nav = document.querySelector('nav');
                nav.classList.remove('nav-open');
                var b = nav.querySelector('.nav-burger');
                if (b) { b.textContent = '☰'; b.setAttribute('aria-expanded', 'false'); }
            }
        });
    </script>

    {{-- ── Ambient drifting shapes: fixed behind everything, page scrolls
         over them while they float (mirrors the app's ambient background) ── --}}
    <div class="ambient" aria-hidden="true">
        @foreach (range(1, 39) as $i)
            @php $size = rand(10, 46); @endphp
            <span class="{{ $i % 3 === 0 ? 'dot' : 'sq' }} c{{ $i % 6 }}"
                  style="top:{{ rand(3, 95) }}%;left:{{ rand(2, 96) }}%;width:{{ $size }}px;height:{{ $size }}px;--dur:{{ rand(14, 30) }}s;--delay:-{{ rand(0, 12) }}s"></span>
        @endforeach
    </div>

    {{-- ── Nav ── --}}
    <nav>
        <div class="my-2 wrap nav-inner justify-between">
            <a class="logo flex max-w-26 lg:max-w-36" href="/">
				<img class="w-full" src="{{Vite::asset('resources/images/logo.webp')}}" alt="Olux Studio" />
				<b>.</b>
			</a>
            <div class="nav-links">
                <a href="#toolkit">Toolkit</a>
                <a href="#specialties">Features</a>
                <a href="#process">How it works</a>
                <a href="#pricing">Pricing</a>
                <a href="#faq">FAQ</a>
                {{-- Mobile-only: auth actions live inside the menu panel --}}
                <div class="nav-auth-mobile">
                    @auth
                        <a class="btn btn-invert" href="{{ route('home') }}">Open app →</a>
                    @else
                        <a class="btn btn-ghost" href="{{ route('login') }}">Sign in</a>
                        <a class="btn btn-primary" href="{{ route('register') }}">Get started</a>
                    @endauth
                </div>
            </div>
            <div class="nav-cta">
                <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Switch between dark and light theme" title="Toggle theme">
                    <span class="tt-sun">☀️</span><span class="tt-moon">🌙</span>
                </button>
                @auth
                    <a class="btn btn-invert" href="{{ route('home') }}">Open app →</a>
                @else
                    <a class="btn btn-ghost" href="{{ route('login') }}">Sign in</a>
                    <a class="btn btn-invert" href="{{ route('register') }}">Get started</a>
                @endauth
                <button type="button" class="nav-burger" onclick="toggleNav(this)" aria-label="Open menu" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>

    {{-- ── Hero: left-aligned stack ── --}}
    <header class="hero min-h-screen">
        <div class="wrap mt-30">
            <span class="eyebrow">Olux Studio CMS</span>
            <h1>Your website, leads &amp; bookings — <em>one studio</em>.</h1>
            <p class="tagline">CMS · CRM · Bookings · Invoices · Estimators · AI</p>
            <p class="lede">Build and manage sites, capture every lead into a built-in CRM, take bookings and payments, quote jobs automatically — then go live on your own domain with free SSL.</p>
            <div class="stat-badges">
                <span>14-day free trial</span>
                <span>20+ public APIs</span>
                <span>∞ Content updates, live instantly</span>
            </div>
            <div class="hero-ctas">
                <a class="btn btn-invert" href="{{ route('plan.start') }}">Start your free trial</a>
                <a class="btn bg-[var(--penta)] light:bg-[var(--primary-3)] text-black light:text-white " href="#pricing">See pricing</a>
            </div>
            <div class="stack stack-space ">
                Built in: <span>Pages</span><span>Posts</span><span>Forms</span><span>Contacts</span><span>Bookings</span><span>Invoices</span><span>Estimators</span><span>AI assistant</span>
            </div>
            <a class="scroll-cue" href="#about" aria-label="Scroll to the next section">SCROLL <i></i></a>
        </div>
    </header>

    {{-- ── Marquee: continuously scrolling module strip ── --}}
    <div class="marquee" aria-hidden="true">
        <div class="marquee-track">
            @foreach ([1, 2] as $loop)
                <span><b>◆</b>Pages &amp; Posts</span>
                <span><b>◆</b>Built-in CRM</span>
                <span><b>◆</b>Bookings</span>
                <span><b>◆</b>Invoices</span>
                <span><b>◆</b>Quote estimators</span>
                <span><b>◆</b>Asset library</span>
                <span><b>◆</b>Analytics</span>
                <span><b>◆</b>Team &amp; roles</span>
                <span><b>◆</b>Custom domains</span>
                <span><b>◆</b>AI assistant</span>
                <span><b>◆</b>API-first</span>
            @endforeach
        </div>
    </div>

    {{-- ── About: two columns + quote box + stats ── --}}
    <section id="about">
        <div class="wrap about">
            <div>
                <span class="eyebrow">Why Olux</span>
                <h2 class="h2">One login replaces five tools</h2>
                <p class="sub">Most service businesses juggle a website builder, a CRM, a booking system, an invoicing tool and a spreadsheet of quotes. Olux folds all of it into one dashboard — every form, quote, booking and payment lands in the same feed.</p>
                <div class="quote-box">“Set up your site tonight — take your first booking tomorrow.”</div>
                <div class="mini-stats">
                    <div><b>3-in-1</b><span>CMS · CRM · commerce</span></div>
                    <div><b>1 click</b><span>to go live with SSL</span></div>
                    <div><b>0 cards</b><span>needed for the trial</span></div>
                </div>
            </div>
            <div class="about-visual">
                <div class="row">📥 New estimate request <small>2 min ago</small></div>
                <div class="row">📅 Booking confirmed — £120 <small>1 h ago</small></div>
                <div class="row">💷 Invoice INV-014 paid <small>3 h ago</small></div>
                <div class="row">🤝 New contact from “Kitchen refit” <small>today</small></div>
                <div class="row">✨ AI built page “Services” <small>today</small></div>
            </div>
        </div>
    </section>

    {{-- ── Arsenal: 4 skill cards with tags ── --}}
    <section id="toolkit" class="alt">
        <div class="wrap">
            <span class="eyebrow">The toolkit</span>
            <h2 class="h2">Four engines under one roof</h2>
            <div class="cards-4">
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z"/></svg></span><h3>Content engine</h3><p>Pages, blog posts with a rich editor, collections and assets — served live to your site through the API.</p><div class="tags"><span>Pages</span><span>Posts</span><span>Assets</span><span>Collections</span></div></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg></span><h3>Lead engine</h3><p>Forms, interests and quotes become contacts with a lifecycle — first touch to won or lost.</p><div class="tags"><span>Forms</span><span>Contacts</span><span>Funnel</span><span>Alerts</span></div></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m4 0h4M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span><h3>Commerce engine</h3><p>Bookings with real availability, Stripe payments, invoices with reminders, and a storefront.</p><div class="tags"><span>Bookings</span><span>Invoices</span><span>Store</span><span>Stripe</span></div></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3m-7 6h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg></span><h3>Quote engine</h3><p>Named estimators with your own fields and calculator-built formulas — visitors get instant emailed quotes.</p><div class="tags"><span>Fields</span><span>Formulas</span><span>Auto-email</span></div></div>
            </div>
        </div>
    </section>

    {{-- ── Specialties: 6 cards ── --}}
    <section id="specialties">
        <div class="wrap">
            <span class="eyebrow">Specialties</span>
            <h2 class="h2">The details that make it feel effortless</h2>
            <div class="cards-6">
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18"/></svg></span><h3>Go live on your domain</h3><p>Point DNS, verify with one click, publish — automatic SSL included. Edits appear on your domain instantly.</p></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.6-2.6A11.95 11.95 0 0112 21 11.95 11.95 0 013.4 7.4 12 12 0 0112 3a12 12 0 018.6 4.4z"/></svg></span><h3>Team &amp; roles</h3><p>Email-verified invitations and per-permission roles — everyone sees exactly what they should, nothing more.</p></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4m6-2l1.7 4.3L19 9l-4.3 1.7L13 15l-1.7-4.3L7 9l4.3-1.7L13 3zM17 15v4m-2-2h4"/></svg></span><h3>AI assistant</h3><p>Ask for a page, a form or a whole section in plain English and it appears in your site.</p></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></span><h3>One dashboard feed</h3><p>Every submission, quote, booking and invoice lands in a grouped activity feed with notifications.</p></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span><h3>Email automation</h3><p>Quote emails you draft yourself, booking confirmations, invoice sends and reminders — all automatic.</p></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></span><h3>API-first</h3><p>Every feature is a documented JSON API, so a site built with any framework plugs straight in.</p></div>
            </div>
        </div>
    </section>

    {{-- ── Process: 4 numbered steps ── --}}
    <section id="process" class="alt">
        <div class="wrap">
            <span class="eyebrow">How it works</span>
            <h2 class="h2">Live in four steps</h2>
            <div class="steps">
                <div class="step"><span class="num">01</span><h3>Create</h3><ul><li>Sign up free</li><li>Name your site</li><li>No card required</li></ul></div>
                <div class="step"><span class="num">02</span><h3>Build</h3><ul><li>Add pages &amp; posts</li><li>Upload assets</li><li>Or let the AI do it</li></ul></div>
                <div class="step"><span class="num">03</span><h3>Switch on</h3><ul><li>Bookings &amp; invoices</li><li>Quote estimators</li><li>Forms &amp; CRM are always on</li></ul></div>
                <div class="step"><span class="num">04</span><h3>Go live</h3><ul><li>Connect your domain</li><li>Verify DNS in-app</li><li>SSL issues itself</li></ul></div>
            </div>
        </div>
    </section>

    {{-- ── Pricing (live from config/plans.php) ── --}}
    <section id="pricing">
        <div class="wrap">
            <span class="eyebrow">Pricing</span>
            <h2 class="h2">Start free, grow when you do</h2>
            <p class="sub">Every plan starts with a 14-day free trial with everything unlocked — no card required. Monthly, cancel anytime.</p>
            <div class="plans">
                @foreach ($tiers as $key => $t)
                <div class="plan {{ ($t['highlight'] ?? false) ? 'hot' : '' }}">
                    @if ($t['highlight'] ?? false)<span class="flag">Most popular</span>@endif
                    <h3>{{ $t['name'] }}</h3>
                    <p class="tag">{{ $t['tagline'] }}</p>
                    <p class="price">
                        @if ($t['price_cents'] === 0) Free @else {{ Money::format($t['price_cents'], 'gbp') }}<small>/month</small> @endif
                    </p>
                    <ul>
                        @foreach ($t['features'] as $f)<li>{{ $f }}</li>@endforeach
                    </ul>
                    <a class="btn {{ ($t['highlight'] ?? false) ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('plan.start', ['plan' => $key]) }}">
                        {{ $t['price_cents'] === 0 ? 'Start free trial' : 'Get started' }}
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Carousel: rotating quotes (arrows + dots + autoplay) ── --}}
    <section class="alt">
        <div class="wrap">
            <span class="eyebrow">What it feels like</span>
            <h2 class="h2">Built for the way service businesses actually work</h2>
            <div class="carousel" id="carousel">
                <div class="car-view">
                    <div class="car-track" id="car-track">
                        <div class="slide"><q>“Every enquiry used to live in three inboxes. Now the form, the quote and the booking are one contact with a history.”</q><span class="who"><span class="ava">TR</span><span><b>Tunde R.</b><small>Cleaning company owner*</small></span></span></div>
                        <div class="slide"><q>“I built our price calculator myself — tapped the formula out like a calculator, and clients get their quote by email in seconds.”</q><span class="who"><span class="ava">MK</span><span><b>Maya K.</b><small>Landscaping studio*</small></span></span></div>
                        <div class="slide"><q>“Going live was pointing our domain and clicking verify. The certificate sorted itself out — content edits show up instantly.”</q><span class="who"><span class="ava">JD</span><span><b>Jon D.</b><small>Agency developer*</small></span></span></div>
                    </div>
                </div>
                <button class="car-btn car-prev" onclick="carGo(-1)" aria-label="Previous">‹</button>
                <button class="car-btn car-next" onclick="carGo(1)" aria-label="Next">›</button>
                <div class="dots" id="car-dots"></div>
            </div>
            <p style="text-align:center;font-size:11px;color:var(--muted);margin-top:14px">* Illustrative examples of how teams use Olux.</p>
        </div>
    </section>

    {{-- ── FAQ accordion ── --}}
    <section id="faq">
        <div class="wrap" style="max-width:760px">
            <span class="eyebrow">FAQ</span>
            <h2 class="h2">Questions, answered</h2>
            <details open><summary>Do I need a credit card to try it?</summary><p>No. The 14-day trial unlocks every feature with no card. When it ends you pick a plan — your content and settings stay exactly as you left them.</p></details>
            <details><summary>Can I use my own domain?</summary><p>Yes — connect any domain from the Go live page. We show the exact DNS records, verify them for you, and issue SSL automatically.</p></details>
            <details><summary>How do payments work?</summary><p>Connect your own Stripe account and bookings, orders and invoices are paid directly to you. Your plan subscription is separate and cancellable anytime.</p></details>
            <details><summary>Can my team help manage the site?</summary><p>Yes — invite teammates by email. Invitations are verified, and roles control precisely which pages and actions each person can access.</p></details>
            <details><summary>I build sites by hand — can I still use this?</summary><p>Absolutely. Every feature is a documented JSON API (content, forms, quotes, bookings, posts and more), so any framework can read content from Olux and submit leads back.</p></details>
        </div>
    </section>

    {{-- ── CTA band ── --}}
    <section style="padding-top:0">
        <div class="wrap">
            <div class="band">
                <h2>From first page to first payment.</h2>
                <p>Set up your site tonight — take your first booking tomorrow.</p>
                <a class="btn" href="{{ route('plan.start') }}">Start your free trial</a>
            </div>
        </div>
    </section>

    {{-- ── Footer: multi-column ── --}}
    <footer>
        <div class="wrap">
            <div class="foot-grid">
                <div>
                    <h4 style="font-size:18px">🟠 Olux.</h4>
                    <p style="font-size:13px;opacity:.85;max-width:240px">Websites, leads &amp; bookings in one platform — by Olux Studio.</p>
                </div>
                <div>
                    <h4>Explore</h4>
                    <a href="#toolkit">Toolkit</a>
                    <a href="#specialties">Features</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#faq">FAQ</a>
                </div>
                <div>
                    <h4>Account</h4>
                    <a href="{{ route('login') }}">Sign in</a>
                    <a href="{{ route('register') }}">Sign up</a>
                    <a href="{{ route('register') }}">Start free trial</a>
                </div>
                <div>
                    <h4>Elsewhere</h4>
                    <a href="https://oluxstudio.com" target="_blank" rel="noopener">oluxstudio.com</a>
                    <a href="https://github.com/oluxstudio" target="_blank" rel="noopener">GitHub</a>
                </div>
            </div>
            <div class="foot-base">
                <span>© {{ date('Y') }} Olux Studio. All rights reserved.</span>
                <span>Set up tonight. Booked tomorrow.</span>
            </div>
        </div>
    </footer>

    <script>
        // ── Carousel: manual arrows + dots + autoplay (pauses on hover) ──
        (function () {
            var track = document.getElementById('car-track');
            var dotsWrap = document.getElementById('car-dots');
            var n = track.children.length, i = 0, timer;

            for (var d = 0; d < n; d++) {
                var b = document.createElement('button');
                b.setAttribute('aria-label', 'Slide ' + (d + 1));
                (function (idx) { b.addEventListener('click', function () { go(idx); }); })(d);
                dotsWrap.appendChild(b);
            }
            function paint() {
                track.style.transform = 'translateX(-' + (i * 100) + '%)';
                Array.from(dotsWrap.children).forEach(function (dot, idx) { dot.className = idx === i ? 'on' : ''; });
            }
            function go(idx) { i = (idx + n) % n; paint(); restart(); }
            window.carGo = function (dir) { go(i + dir); };
            function restart() { clearInterval(timer); timer = setInterval(function () { go(i + 1); }, 5000); }
            document.getElementById('carousel').addEventListener('mouseenter', function () { clearInterval(timer); });
            document.getElementById('carousel').addEventListener('mouseleave', restart);
            paint(); restart();
        })();
    </script>
</body>
</html>
