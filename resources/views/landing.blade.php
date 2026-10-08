<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Olux Studio — Websites, leads &amp; bookings in one platform</title>
    <meta name="description" content="Olux Studio CMS: build sites, capture leads, take bookings and payments, send invoices and go live on your own domain — with a built-in CRM and AI assistant.">
    <link rel="icon" href="{{asset('favicon.ico')}}">
    {{-- Brand fonts (Google Fonts) + the landing's font variables --}}
    <link rel="stylesheet" href="/fonts/google/fonts.css">{{-- self-hosted brand fonts: php artisan fonts:download --}}
    {{-- Tailwind (compiled app bundle). The landing's own CSS below lives in
         the `components` cascade layer, so TAILWIND UTILITIES ALWAYS WIN —
         add any utility class anywhere on this page and it applies. --}}
    @vite('resources/css/app.css')
    <style>
        /* Font FAMILIES are global (resources/css/app.css). The landing page only
           picks its own ROLES — beats the app bundle's defaults (loaded above). */
        html:root {
            --font-display: var(--font-madimi);     /* headings, logo, prices, big numbers */
            --font-header:  var(--font-momo);       /* section headers */
            --font-body:    var(--font-textmeone);  /* running text */
            --font-ui:      var(--font-kodchasan);  /* nav, eyebrows, tags, badges */
            --font-btn:     var(--font-gugi);       /* buttons */
            --font-accent:  var(--font-baumans);    /* flourishes */
        }
    </style>
    @php
        use App\Support\Money;
        $tiers = \App\Support\PlanCatalog::publicTiers();
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
        /* Body rhythm: --font-body, text-lg / leading-8 */
        body { font-family: var(--font-body); color: var(--on-bg); background: var(--bg); font-size: 1.125rem; line-height: 2rem; }
        h1, h2 { font-family: var(--font-header) !important; line-height: 1.15; font-weight: 400; }
        a { color: inherit; text-decoration: none; }
        /* Main-site container rhythm: px-4, prose capped near max-w-3xl+ */
        .wrap { max-width: 86rem; margin: 80px auto; padding: 0 1rem; }
        section { padding: 3.5rem 0; }               /* py-14 */
        /* .section-label: --font-ui, bold, uppercase, widest */
        .eyebrow { display: inline-block; font-family: var(--font-ui); font-size: 1.1875rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--primary); margin-bottom: .75rem; }
        /* .section-header: --font-display */
        .h2 { font-size: clamp(30px, 4.4vw, 40px); text-transform: uppercase; margin-bottom: .75rem; color: var(--on-bg); }
        .sub { color: var(--on-bg-soft); max-width: 48rem; font-size: 1.25rem; line-height: 1.8; }
        /* Alt bands: slightly lifted dark, same dark theme as the main site */
        .alt { background: var(--surface-2); opacity: 0.95; border-block: 1px solid var(--line-inv); color: var(--on-bg); }
        .alt .eyebrow { color: var(--primary); }
        .alt .h2 { color: var(--on-bg); }
        .alt .sub { color: var(--on-bg-soft); }
        .btn { display: inline-block; padding: 8px 30px !important; border-radius: 12px; font-weight: 700; font-size: 19px; transition: transform .15s, box-shadow .15s, background .15s; }
        .btn { font-family: var(--font-ui); }
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
        .nav-inner { display: flex; align-items: center; gap: 26px; height: 70px; }
        .logo { font-family: var(--font-display); font-size: 21px; }
        .logo b { color: var(--accent-on-bg); }
        /* Image logo: render as pure WHITE so it reads on the orange nav */
        .logo img { filter: brightness(0) invert(1); }
        .nav-links { display: flex; gap: 40px; font-family: var(--font-ui); font-size: 19px; font-weight: 700; color: var(--on-bg-soft); }
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
        .hero h1 { font-family: var(--font-header) !important; font-size: clamp(38px, 5.6vw, 74px); text-transform: uppercase; max-width: 900px; margin: 0 auto 16px; color: var(--on-bg); }
        .hero h1 em { font-style: normal; background: linear-gradient(92deg, var(--primary), var(--primary-2), var(--penta)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero .tagline { font-family: var(--font-ui); font-weight: 700; color: var(--penta); margin-bottom: 14px; font-size: .95rem; }
        .hero p.lede { color: var(--on-bg-soft); max-width: 600px; margin: 0 auto 26px; font-size:20px; }
        .stat-badges { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 28px; justify-content: center; }
        .stat-badges span { background: var(--on-bg-chip); color: var(--penta); border: 1px solid var(--line-inv); font-family: var(--font-ui); font-weight: 700; font-size: 16.5px; padding: 8px 14px; border-radius: 10px; }
        .hero-ctas { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 40px; margin-top: 30px; justify-content: center; }
        .stack { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center; color: var(--on-bg-soft); font-family: var(--font-ui); font-size: 12.5px; }
        .stack-space { margin-bottom: 90px; } /* extra room for the scroll cue */
        .stack span { border: 1px solid var(--line-inv); padding: 3px 30px; border-radius: 9999px; font-weight: 600; background: var(--surface); color: var(--on-bg); font-size: 16px; }
        /* Scroll cue — centered, pinned to the hero's bottom edge:
           label + "mouse" pill with a dropping amber dot */
        .scroll-cue {
            position: absolute; left: 50%; bottom: 92px; transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center; gap: 9px;
            font-family: var(--font-ui); font-size: 10.5px; letter-spacing: .34em;
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
        .marquee-track span { white-space: nowrap; font-family: var(--font-ui); font-weight: 700; font-size: 15px; color: var(--on-bg-soft); }
        .marquee-track span b { color: var(--primary); margin-right: 8px; font-size: 12px; vertical-align: 1px; }
        @keyframes marquee { from { transform: translateX(0) } to { transform: translateX(-50%) } }

        /* ── About: two columns, quote box, stats row ── */
        .about { display: grid; gap: 46px; grid-template-columns: 1.15fr .85fr; align-items: center; }
        @media (max-width: 860px) { .about { grid-template-columns: 1fr; } }
        .quote-box { border-left: 4px solid var(--primary); background: var(--on-bg-chip); border-radius: 0 14px 14px 0; padding: 18px 20px; font-family: var(--font-ui); font-weight: 700; color: var(--penta); margin: 22px 0; font-size: .95rem; }
        .mini-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        @media (max-width: 800px) { .mini-stats { grid-template-columns: 1fr; gap: 30px; } }
        .mini-stats b { display: block; font-family: var(--font-display); font-size: 28px; color: var(--primary); }
        .mini-stats span { font-size: 16.5px; color: var(--on-bg-soft); }
        .about-visual { background: linear-gradient(150deg, var(--primary), var(--primary-2)); font-family: var(--font-ui); border: 1px solid var(--line-inv); border-radius: 20px; padding: 26px; color: #fff; box-shadow: 0 26px 50px -26px rgba(227,135,4,.4); }
        .about-visual .row { background: rgba(255,255,255,.14); border-radius: 12px; padding: 13px 16px; margin-bottom: 10px; font-size: 17px; font-weight: 600; display: flex; justify-content: space-between; }
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
        .card h3 { font-family: var(--font-ui); font-weight: 700; font-size: 22px; margin: 20px 0 15px; color: var(--on-bg); letter-spacing:1px }
        .card p { font-size: 17px; line-height: 1.7; color: var(--on-bg-soft); margin-bottom: 12px; }
        .tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .tags span { font-family: var(--font-ui); font-size: 14px; font-weight: 700; background: rgba(227,135,4,.15); color: var(--penta); padding: 2px 20px; border-radius: 9999px; }

        /* ── Specialties: 6 cards ── */
        .cards-6 { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); margin-top: 36px; }

        /* ── Process: 4 numbered steps ── */
        .steps { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); margin-top: 42px; }
        .step { position: relative; background: var(--surface); border: 1px solid var(--line-inv); border-radius: 16px; padding: 50px 22px 22px; }
        .step .num { position: absolute; top: -17px; left: 20px; background: linear-gradient(120deg, var(--primary), var(--primary-2)); color: #fff; font-family: var(--font-display); font-size: 19px; padding: 7px 13px; border-radius: 10px; }
        .step h3 { font-family: var(--font-ui); font-weight: 700; font-size: 22px; margin-bottom: 8px; color: var(--on-bg); }
        .step ul { list-style: none; }
        .step li { font-size: 16px; line-height: 1.7; color: var(--on-bg-soft); padding: 3px 0 3px 18px; position: relative; }
        .step li::before { content: '→'; position: absolute; left: 0; color: var(--primary); }

        /* ── Pricing ── */
        .plans { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(205px, 1fr)); margin-top: 40px; align-items: stretch; }
        .plan { border: 1px solid var(--line-inv); border-radius: 16px; padding: 26px 20px; display: flex; flex-direction: column; background: var(--surface); position: relative; }
        .plan.hot { border: 2px solid var(--primary); box-shadow: 0 24px 46px -24px rgba(227,135,4,.5); }
        .plan .flag { background: linear-gradient(120deg, var(--primary), var(--primary-2)); }
        .plan .flag { position: absolute; top: -22px; left: 50%; transform: translateX(-50%); background: var(--primary); color: #fff; font-size: 17px; font-weight: 700; padding: 4px 18px; border-radius: 9999px; white-space: nowrap; }
        .plan h3 { font-size: 24px; color: var(--primary-2); }
        .plan .tag { font-size: 18px; color: var(--muted); margin: 2px 0 12px; }
        .plan .price { font-family: var(--font-display); font-size: 30px; font-weight: 800; }
        .plan .price small { font-size: 12.5px; color: var(--muted); font-family: var(--font-body); font-weight: 500; }
        .plan ul { list-style: none; margin: 14px 0 20px; flex: 1; }
        .plan li { font-size: 16.5px; color: var(--muted); padding: 4px 0 4px 20px; position: relative; }
        .plan li::before { content: '✓'; position: absolute; left: 0; color: var(--primary); font-weight: 800; }
        .plan .btn { text-align: center; padding: 11px 16px; }

        /* ── Carousel: quotes, arrows + dots + autoplay ── */
        .carousel { position: relative; max-width: 760px; margin: 40px auto 0; }
        .car-view { overflow: hidden; border-radius: 18px; }
        .car-track { display: flex; transition: transform .5s cubic-bezier(.4,0,.2,1); }
        .slide { display: flex; align-items: center; justify-content: center; min-height: 22em; min-width: 100%; background: var(--surface); border: 1px solid var(--line-inv); border-radius: 18px; padding: 38px 42px; text-align: center; }
        .tm-skel { display: block; height: 14px; border-radius: 8px; margin: 10px auto; background: var(--line-inv); animation: tm-pulse 1.4s ease-in-out infinite; }
        @keyframes tm-pulse { 50% { opacity: .45; } }
        .slide .stars { color: var(--primary); letter-spacing: .15em; font-size: 18px; margin-bottom: 10px; }
        /* Curly quote marks around each testimonial (no space before ::, or it targets the q's children) */
        .slide .box q::before { content: '\201C'; padding-right: 2rem; font-size: 2.5rem; color: var(--primary); }
        .slide .box q::after  { content: '\201D'; padding-left: 1rem; font-size: 2.5rem; color: var(--primary); }
		.slide q { font-family: var(--font-body); font-size: 22px; font-weight: 600; display: block; margin-bottom: 18px; }
        .slide .who { display: inline-flex; align-items: center; gap: 12px; }
        .slide .ava { width: 42px; height: 42px; border-radius: 9999px; background: linear-gradient(140deg, var(--primary), var(--primary-3)); color: #fff; font-weight: 800; display: grid; place-items: center; font-size: 14px; }
        .slide .who small { display: block; color: var(--muted); }
        .car-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 40px; height: 40px; border-radius: 9999px; border: 1px solid var(--line-inv); background: var(--surface); color: var(--penta); font-size: 17px; cursor: pointer; transition: all .15s; }
        .car-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .car-prev { left: -18px; } .car-next { right: -18px; }
        .dots { display: flex; gap: 8px; justify-content: center; margin-top: 18px; }
        .dots button { width: 9px; height: 9px; border-radius: 9999px; border: 0; background: var(--foreground); cursor: pointer; transition: all .2s; }
        .dots button.on { background: var(--primary); width: 24px; }

        /* ── FAQ accordion ── */
        details { border: 1px solid var(--line-inv); background: var(--surface); border-radius: 14px; padding: 16px 20px; margin-top: 12px; }
        details summary { cursor: pointer; font-weight: 700; font-size: 20px; list-style: none; display: flex; justify-content: space-between; align-items: center; font-family: var(--font-display); }
        details summary::-webkit-details-marker { display: none; }
        details summary::after { content: '+'; font-size: 20px; color: var(--primary); transition: transform .2s; }
        details[open] summary::after { transform: rotate(45deg); }
        details p { margin-top: 10px; font-size: 19px; color: var(--muted); }

        /* ── CTA band + footer ── */
        .band { background: linear-gradient(120deg, var(--primary-3), var(--primary-4)); border: 1px solid var(--line-inv); border-radius: 20px; padding: 56px 32px; text-align: center; color: #fff; }
        .band h2 { font-size: clamp(24px, 3.4vw, 34px); margin-bottom: 10px; }
        .band p { opacity: .88; margin-bottom: 26px; }
        .band .btn { background: #fff; color: var(--primary-4); }
        footer { background: var(--line-inv); border-top: 1px solid var(--line-inv); color: var(--on-bg-soft); padding: 52px 0 30px; }
        .foot-grid { display: grid; gap: 30px; grid-template-columns: 1.4fr 1fr 1fr 1fr; margin-bottom: 34px; }
        @media (max-width: 760px) { .foot-grid { grid-template-columns: 1fr 1fr; } }
        footer h4 { font-family: var(--font-display); color: var(--on-bg); font-size: 14px; margin-bottom: 12px; }
        footer a { display: block; font-size: 19px; padding: 3px 0; }
        footer a:hover { opacity: 1; color: #fff; }
        .foot-base { border-top: 1px solid rgba(255,255,255,.18); padding-top: 20px; font-size: 20px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; opacity: .85; }

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
        /* ── Detail panels (pricing + specialties): a card opens into one large card ── */
        .detail-back { display: inline-flex; align-items: center; gap: 10px; margin-top: 40px; padding: 10px 24px; border: 2px solid var(--primary);
            border-radius: 9999px; background: transparent; color: var(--primary); font-family: var(--font-ui); font-weight: 700; font-size: 16px;
            letter-spacing: .06em; text-transform: uppercase; cursor: pointer; transition: background-color .2s, color .2s; }
        .detail-back:hover { background: var(--primary); color: #fff; }
        .detail-card { position: relative; margin-top: 34px; border: 1px solid var(--primary); border-radius: 16px;
            padding: 40px 40px 34px; background: var(--surface);
            display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 28px 56px; align-items: start; }
        .detail-card > .col-b { border-left: 1px solid var(--line-inv); padding-left: 48px; }
        .detail-card .col-b .checks { margin-top: 0; }
        .detail-card .col-title { font-family: var(--font-ui); font-weight: 700; font-size: 14px; letter-spacing: .12em; text-transform: uppercase; color: var(--primary); margin: 0 0 6px; }
        .detail-card .flag { position: absolute; top: -15px; left: 26px; background: var(--primary); color: #fff; font-family: var(--font-ui);
            font-weight: 700; font-size: 15px; letter-spacing: .04em; text-transform: uppercase; padding: 3px 16px; border-radius: 9999px; }
        .detail-card h3 { font-family: var(--font-display); font-size: 28px; letter-spacing: .05em; text-transform: uppercase; color: var(--on-bg); margin: 0; }
        .detail-card .ico { margin-bottom: 14px; }
        .detail-price { font-family: var(--font-display); font-weight: 800; font-size: clamp(42px, 6vw, 60px); line-height: 1.1; color: var(--on-bg); margin: 14px 0 6px; }
        .detail-price .cur { color: var(--primary); font-size: .55em; font-weight: 500; vertical-align: .55em; margin-right: 2px; }
        .detail-price small { font-family: var(--font-body); font-size: 17px; font-weight: 500; color: var(--muted); }
        .detail-annual { font-size: 16px; color: var(--muted); margin-bottom: 4px; }
        .detail-lead { font-size: 18px; line-height: 1.85; color: var(--on-bg-soft); margin: 14px 0 6px; max-width: 42rem; }
        .checks { list-style: none; margin: 18px 0 8px; padding: 0; }
        .checks li { position: relative; padding-left: 34px; margin: 12px 0; font-size: 17.5px; line-height: 1.6; color: var(--on-bg-soft); }
        .checks li::before { content: '✓'; position: absolute; left: 0; top: 2px; width: 22px; height: 22px; border-radius: 9999px; display: grid; place-items: center;
            background: var(--primary); color: #fff; font-size: 13px; font-weight: 800; }
        .detail-specs { display: grid; grid-template-columns: minmax(9rem, 13rem) 1fr; gap: 0; margin: 22px 0 6px; border-top: 1px solid var(--line-inv); }
        .detail-specs dt, .detail-specs dd { padding: 11px 0; border-bottom: 1px solid var(--line-inv); font-size: 16px; }
        .detail-specs dt { font-family: var(--font-ui); font-weight: 700; color: var(--on-bg); padding-right: 14px; }
        .detail-specs dd { color: var(--on-bg-soft); }
        .detail-cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        .more { margin-top: 10px; align-self: flex-start; background: none; border: 0; padding: 0; font-family: var(--font-ui); font-weight: 700; font-size: 15.5px;
            color: var(--primary); text-decoration: underline; text-underline-offset: 4px; cursor: pointer; }
        .more:hover { color: var(--primary-2); }
        .plan .more { align-self: center; margin: 0 0 14px; }
        @media (max-width: 900px) {
            .detail-card { grid-template-columns: 1fr; padding: 34px 26px 28px; }
            .detail-card > .col-b { border-left: 0; padding-left: 0; border-top: 1px solid var(--line-inv); padding-top: 24px; }
        }
        @media (max-width: 640px) {
            .detail-card { padding: 30px 20px 24px; }
            .detail-specs { grid-template-columns: 1fr; }
            .detail-specs dt { border-bottom: 0; padding-bottom: 0; }
        }
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
            <a class="logo flex max-w-26 lg:max-w-30" href="/">
				<img class="w-full" src="{{Vite::asset('resources/images/logo.webp')}}" alt="Olux Studio" />
				<b>.</b>
			</a>
            <div class="nav-links">
                <a href="{{ route('templates') }}">Templates</a>
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
                <a class="btn btn-invert" href="{{ route('plan.start') }}">Start your Free Trial</a>
                <a class="btn bg-[var(--penta)] light:bg-[var(--primary-3)] text-black light:text-white " href="#pricing">See Pricing</a>
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
                <p class="sub">Most service businesses juggle a website builder, a CRM, a booking system, an invoicing tool and a spreadsheet of quotes — and copy the same customer between them. Olux folds all of it into one dashboard. A visitor who fills in your form becomes a contact; their quote, booking and invoice attach to that same contact; and every step shows up in one feed, so nothing slips between tools.</p>
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
            <p class="sub">Each engine does one job well, and they share the same contacts, calendar and payments — so a lead from your website can be quoted, booked and invoiced without typing anything twice.</p>
            <div class="cards-4">
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z"/></svg></span><h3>Content engine</h3><p>Pages, blog posts with a rich editor, collections and assets — served live to your site through the API.</p><div class="tags"><span>Pages</span><span>Posts</span><span>Assets</span><span>Collections</span></div></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg></span><h3>Lead engine</h3><p>Forms, interests and quotes become contacts with a lifecycle — first touch to won or lost.</p><div class="tags"><span>Forms</span><span>Contacts</span><span>Funnel</span><span>Alerts</span></div></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m4 0h4M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span><h3>Commerce engine</h3><p>Bookings with real availability, Stripe payments, invoices with reminders, and a storefront.</p><div class="tags"><span>Bookings</span><span>Invoices</span><span>Store</span><span>Stripe</span></div></div>
                <div class="card"><span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3m-7 6h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg></span><h3>Quote engine</h3><p>Named estimators with your own fields and calculator-built formulas — visitors get instant emailed quotes.</p><div class="tags"><span>Fields</span><span>Formulas</span><span>Auto-email</span></div></div>
            </div>
        </div>
    </section>

    {{-- ── Specialties: 6 cards — each opens a detail panel ── --}}
    @php
        $specialties = [
            'domain' => ['icon' => 'M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18', 'title' => 'Go live on your domain',
                'short' => 'Point DNS, verify with one click, publish — automatic SSL included. Edits appear on your domain instantly.',
                'long' => 'Your site works on a free olux address from the moment you create it. When you are ready, connect a domain you already own or buy one without leaving Olux — the Go live page shows the exact DNS records to add, checks them for you, and issues the security certificate automatically. After that, every edit you save is on your live domain straight away.',
                'points' => ['A free olux web address from day one', 'Buy a domain in-app, or connect one you own', 'DNS checks with plain-English hints if something is off', 'Automatic SSL (the padlock) — nothing to renew', 'Change your web address later; old links keep redirecting']],
            'team' => ['icon' => 'M9 12l2 2 4-4m5.6-2.6A11.95 11.95 0 0112 21 11.95 11.95 0 013.4 7.4 12 12 0 0112 3a12 12 0 018.6 4.4z', 'title' => 'Team & roles',
                'short' => 'Email-verified invitations and per-permission roles — everyone sees exactly what they should, nothing more.',
                'long' => 'Invite the people who help you run the business by email. Each invitation is verified before anyone gets in, and a role decides precisely what they can see and do — a receptionist can manage bookings without touching invoices, a designer can edit pages without seeing customers.',
                'points' => ['Email-verified invitations', 'Ready-made roles, or build your own permission by permission', 'Access per site when you run several', 'A history of who changed what', 'Remove someone in one click — their access ends immediately']],
            'ai' => ['icon' => 'M5 3v4M3 5h4m6-2l1.7 4.3L19 9l-4.3 1.7L13 15l-1.7-4.3L7 9l4.3-1.7L13 3zM17 15v4m-2-2h4', 'title' => 'AI assistant',
                'short' => 'Ask for a page, a form or a whole section in plain English and it appears in your site.',
                'long' => 'Describe what you need — “add a services page with prices”, “make a contact form with a budget field” — and the assistant builds it inside your site, ready to edit. It also answers questions about your business from your own data: how many leads came in this week, which services are booked most.',
                'points' => ['Builds pages, sections and forms from a sentence', 'Edits content you already have', 'Answers questions using your own leads and bookings', 'Everything it makes is normal, editable content', 'A monthly allowance on each plan, shown in your account']],
            'feed' => ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'title' => 'One dashboard feed',
                'short' => 'Every submission, quote, booking and invoice lands in a grouped activity feed with notifications.',
                'long' => 'Instead of checking five inboxes, open one dashboard. New enquiries, quotes, bookings, payments and overdue invoices appear in a single feed, grouped so you can see what needs you today. Alerts and on-screen notifications tell you the moment something happens — including when a long task like an upload or import finishes in the background.',
                'points' => ['Every form, quote, booking and payment in one place', 'Alerts for what needs attention (overdue, unpaid, new)', 'Background tasks tell you when they are done', 'Tasks you can assign to your team', 'Works the same on your phone']],
            'email' => ['icon' => 'M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'title' => 'Email automation',
                'short' => 'Quote emails you draft yourself, booking confirmations, invoice sends and reminders — all automatic.',
                'long' => 'The emails a service business sends every day go out on their own, with your logo and wording. Customers get a receipt when they submit a form, a confirmation and reminder for their booking, their invoice with a pay-online link, a polite nudge if it is overdue, and — after the visit — a request for a review or a prompt to book again.',
                'points' => ['Form receipts and instant quote emails', 'Booking confirmations, reminders and cancellations', 'Invoices with a pay-online link, plus overdue reminders', 'Review requests and “time to rebook” prompts', 'Branded with your logo and your own wording']],
            'api' => ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'title' => 'API-first',
                'short' => 'Every feature is a documented JSON API, so a site built with any framework plugs straight in.',
                'long' => 'Olux is built so developers are never boxed in. Content, collections, forms, posts, bookings and the store are all available through documented JSON APIs (and GraphQL), so a site hand-built in any framework can read its content from Olux and send leads and bookings back. You can also export a site as a standalone app.',
                'points' => ['Documented JSON APIs for every feature', 'A GraphQL endpoint for flexible queries', 'API keys you can limit to one site and specific actions', 'Pre-built page JSON for fast static sites', 'Export a site as a standalone Nuxt app']],
        ];
    @endphp
    <section id="specialties">
        <div class="wrap">
            <span class="eyebrow">Specialties</span>
            <h2 class="h2">The details that make it feel effortless</h2>
            <p class="sub">The small things that save hours every week. Open any card to see what it does, how it works, and what is included.</p>
            <div class="cards-6" data-detail-grid>
                @foreach ($specialties as $sk => $sp)
                    <div class="card" style="display:flex;flex-direction:column">
                        <span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sp['icon'] }}"/></svg></span>
                        <h3>{{ $sp['title'] }}</h3>
                        <p>{{ $sp['short'] }}</p>
                        <button type="button" class="more" style="margin-top:auto" data-open="spec-{{ $sk }}" aria-controls="spec-{{ $sk }}">Learn more →</button>
                    </div>
                @endforeach
            </div>
            @foreach ($specialties as $sk => $sp)
                <div class="detail" id="spec-{{ $sk }}" hidden>
                    <button type="button" class="detail-back" data-close>← Back to features</button>
                    <article class="detail-card" aria-labelledby="spec-{{ $sk }}-title">
                        <div class="col-a">
                            <span class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sp['icon'] }}"/></svg></span>
                            <h3 id="spec-{{ $sk }}-title">{{ $sp['title'] }}</h3>
                            <p class="detail-lead">{{ $sp['long'] }}</p>
                            <div class="detail-cta">
                                <a class="btn btn-primary" href="{{ route('plan.start') }}">Try it free for 14 days</a>
                                <a class="btn btn-ghost" href="#pricing">See plans</a>
                            </div>
                        </div>
                        <div class="col-b">
                            <p class="col-title">What's included</p>
                            <ul class="checks">
                                @foreach ($sp['points'] as $pt)<li>{{ $pt }}</li>@endforeach
                            </ul>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── Process: 4 numbered steps ── --}}
    <section id="process" class="alt">
        <div class="wrap">
            <span class="eyebrow">How it works</span>
            <h2 class="h2">Live in four steps</h2>
            <p class="sub">Most businesses go from sign-up to a working site in an evening. Start from a ready-made template, make it yours, switch on the tools you need, then connect your domain — you can come back and change any of it later.</p>
            <div class="steps">
                <div class="step"><span class="num">01</span><h3>Create</h3><ul><li>Sign up free</li><li>Pick a template for your trade</li><li>No card required</li></ul></div>
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
            <p class="sub">Every plan starts with a 14-day free trial with everything unlocked — no card required. Pay monthly and cancel anytime, or pay yearly and get two months free. Choose a plan to see exactly what it includes before you sign up.</p>
            <div class="plans" data-detail-grid>
                @foreach ($tiers as $key => $t)
                <div class="plan {{ ($t['highlight'] ?? false) ? 'hot' : '' }}">
                    @if ($t['highlight'] ?? false)<span class="flag">Most popular</span>@endif
                    <h3>{{ $t['name'] }}</h3>
                    <p class="tag">{{ $t['tagline'] }}</p>
                    <p class="price">
                        @if ($t['price_cents'] === 0) Free @else {{ ! empty($t['price_prefix']) ? $t['price_prefix'].' ' : '' }}{{ Money::format($t['price_cents'], 'gbp') }}<small>/month</small> @endif
                    </p>
                    <ul>
                        @foreach ($t['features'] as $f)<li>{{ $f }}</li>@endforeach
                    </ul>
                    {{-- Opens the plan's full details; signing up starts from there. --}}
                    <button type="button" class="btn {{ ($t['highlight'] ?? false) ? 'btn-primary' : 'btn-ghost' }}" data-open="plan-{{ $key }}" aria-controls="plan-{{ $key }}">
                        {{ $t['price_cents'] === 0 ? 'Start free trial' : 'Get started' }}
                    </button>
                </div>
                @endforeach
            </div>
            @php $compare = (array) config('plans.compare'); @endphp
            @foreach ($tiers as $key => $t)
                <div class="detail" id="plan-{{ $key }}" hidden>
                    <button type="button" class="detail-back" data-close>← Back to pricing</button>
                    <article class="detail-card" aria-labelledby="plan-{{ $key }}-title">
                        @if ($t['highlight'] ?? false)<span class="flag">Most popular</span>@endif
                        <div class="col-a">
                        <h3 id="plan-{{ $key }}-title">{{ $t['name'] }}</h3>
                        <p class="detail-price">
                            @if ($t['price_cents'] === 0)
                                Free
                            @else
                                @if (! empty($t['price_prefix']))<small>{{ $t['price_prefix'] }} </small>@endif<span class="cur">£</span>{{ number_format($t['price_cents'] / 100, ($t['price_cents'] % 100) ? 2 : 0) }}<small> /month</small>
                            @endif
                        </p>
                        @if (! empty($t['annual_price_cents']))
                            <p class="detail-annual">or £{{ number_format($t['annual_price_cents'] / 100) }} a year — two months free</p>
                        @endif
                        <p class="detail-lead">{{ $t['description'] ?? $t['tagline'] }}</p>
                        <div class="detail-cta">
                            <a class="btn btn-primary" href="{{ route('plan.start', ['plan' => $key]) }}">{{ $t['price_cents'] === 0 ? 'Start free trial' : 'Start with '.$t['name'] }}</a>
                            <button type="button" class="btn btn-ghost" data-close>Compare all plans</button>
                        </div>
                        </div>
                        <div class="col-b">
                            <p class="col-title">What's included</p>
                            <ul class="checks">
                                @foreach ($t['features'] as $f)<li>{{ $f }}</li>@endforeach
                            </ul>
                            @php $rows = collect($compare)->filter(fn ($r) => isset($r[$key]))->map(fn ($r) => $r[$key]); @endphp
                            @if ($rows->isNotEmpty())
                                <dl class="detail-specs">
                                    @foreach ($rows as $label => $value)
                                        <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── Carousel: testimonials (Admin › Testimonials; arrows + dots + autoplay) ── --}}
    <section class="alt" id="testimonials">
        <div class="wrap">
            <span class="eyebrow">What it feels like</span>
            <h2 class="h2">Built for the way service businesses actually work</h2>
            <p class="sub">Olux is shaped around real days: an enquiry at lunchtime, a quote by teatime, a booking for next week and an invoice paid on the way out.</p>
            {{-- Testimonials: lazy Livewire island fed by the same query as GET /api/testimonials (Admin › Testimonials) --}}
            <livewire:landing-testimonials />
            <p style="text-align:center;margin-top:14px"><a class="more" href="{{ route('testimonials.create') }}">Using Olux? Share your experience →</a></p>
        </div>
    </section>

    {{-- ── FAQ accordion ── --}}
    <section id="faq">
        <div class="wrap" style="max-width:760px">
            <span class="eyebrow">FAQ</span>
            <h2 class="h2">Questions, answered</h2>
            <p class="sub" style="margin-bottom:22px">The things people usually ask before they start. Anything else — write to us and a person will reply.</p>
            <details open><summary>Do I need a credit card to try it?</summary><p>No. The 14-day trial unlocks every feature with no card. When it ends you pick a plan — your content and settings stay exactly as you left them.</p></details>
            <details><summary>Can I use my own domain?</summary><p>Yes — connect any domain from the Go live page. We show the exact DNS records, verify them for you, and issue SSL automatically.</p></details>
            <details><summary>How do payments work?</summary><p>Connect your own Stripe account and bookings, orders and invoices are paid directly to you. Your plan subscription is separate and cancellable anytime.</p></details>
            <details><summary>Can my team help manage the site?</summary><p>Yes — invite teammates by email. Invitations are verified, and roles control precisely which pages and actions each person can access.</p></details>
            <details><summary>Is there a free plan after the trial?</summary><p>Yes. The Free plan keeps one site online on a free olux address (with a small “Made with Olux” badge), with contacts and up to 20 online bookings a month. Upgrade whenever you want your own domain and the full toolkit.</p></details>
            <details><summary>What happens to my content if I change templates?</summary><p>Your data stays exactly where it is — contacts, collections, posts, forms and submissions all belong to your site, not the template. A new template only brings its own layout, pages and starter text; the previous template's pages are set aside (not deleted) and come back if you switch again.</p></details>
            <details><summary>Can I change plans later?</summary><p>Any time, from your account. Upgrades apply straight away; if you move to a smaller plan, we tell you first if anything (like business mailboxes) needs tidying up.</p></details>
            <details><summary>If I buy a domain through Olux, who owns it?</summary><p>You do. Domains are registered in your name and connected to your site automatically — Growth includes a free .co.uk for the first year, Pro any domain.</p></details>
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
                    <h4 style="font-size:22px">🟠 Olux.</h4>
                    <p style="font-size:19px;opacity:.85;max-width:240px">Websites, leads &amp; bookings in one platform — by Olux Studio.</p>
                </div>
                <div>
                    <h4>Explore</h4>
                    <a href="#toolkit">Toolkit</a>
                    <a href="#specialties">Features</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#faq">FAQ</a>
                    <a href="{{ route('landing.salons') }}">For salons &amp; barbers</a>
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
        // ── Detail panels (pricing + specialties): a card's "details" button swaps
        //    the section's grid for that item's full card; Back (or Esc) returns. ──
        (function () {
            var opener = null;
            function sectionOf(el) { return el.closest('section'); }
            function open(id, btn) {
                var panel = document.getElementById(id);
                if (!panel) return;
                var sec = sectionOf(panel);
                sec.querySelectorAll('.detail').forEach(function (d) { d.hidden = true; });
                sec.querySelector('[data-detail-grid]').hidden = true;
                panel.hidden = false;
                opener = btn || null;
                sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var back = panel.querySelector('.detail-back');
                if (back) back.focus({ preventScroll: true });
            }
            function close(sec) {
                sec.querySelectorAll('.detail').forEach(function (d) { d.hidden = true; });
                sec.querySelector('[data-detail-grid]').hidden = false;
                sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (opener && sec.contains(opener)) opener.focus({ preventScroll: true });
                opener = null;
            }
            document.addEventListener('click', function (e) {
                var o = e.target.closest('[data-open]');
                if (o) { open(o.getAttribute('data-open'), o); return; }
                var c = e.target.closest('[data-close]');
                if (c) close(sectionOf(c));
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                var shown = document.querySelector('.detail:not([hidden])');
                if (shown) close(sectionOf(shown));
            });
        })();

    </script>
</body>
</html>
