{{-- The landing page's studio theme, shared by the public template pages:
     same fonts + palette, with primary-tinted borders and shadows. --}}
<style>
    @font-face { font-family: 'junegull'; src: url('/fonts/junegull.otf'); font-display: swap; }
    @font-face { font-family: 'garet'; font-weight: 400; src: url('/fonts/Garet-Book.woff2') format('woff2'); font-display: swap; }
    @font-face { font-family: 'comforta'; font-weight: 400; src: url('/fonts/Comfortaa-Regular.ttf'); font-display: swap; }
    @font-face { font-family: 'comforta'; font-weight: 700; src: url('/fonts/Comfortaa-Bold.ttf'); font-display: swap; }

    :root {
        --primary: #e38704; --primary-2: #f77315; --primary-3: #5e3802; --primary-4: #3a2301;
        --penta: #fbbf24; --bg: #120f14; --on-bg: #f8f5f2; --on-bg-soft: rgba(248,245,242,.87);
        --surface: #1b1620; --line: color-mix(in srgb, var(--primary) 26%, transparent);
        --shadow: 0 14px 40px -14px color-mix(in srgb, var(--primary) 45%, transparent);
    }

    /* Landing's light (apricot) remap — same token system, same cookie. */
    .light {
        --bg: #fbdeb5; --on-bg: #2b1c0a; --on-bg-soft: rgba(43,28,10,.87);
        --surface: #fff3dd; --penta: #913f01;
        --line: color-mix(in srgb, var(--primary) 38%, transparent);
        --shadow: 0 14px 40px -14px color-mix(in srgb, var(--primary) 55%, transparent);
    }
    .light .pub-theme .text-gray-900 { color: var(--on-bg) !important; }
    .light .pub-theme .text-gray-500, .light .pub-theme .text-gray-400 { color: rgba(43,28,10,.55) !important; }
    .light .pub-theme input, .light .pub-theme select { background: rgba(255,255,255,.6) !important; color: var(--on-bg) !important; }
    .light .pub-theme .bg-gray-100, .light .pub-theme .bg-gray-50, .light .pub-theme .bg-indigo-50 { background: var(--surface-2, #f4cf9a) !important; color: var(--on-bg-soft) !important; }

    .pub-theme { background: var(--bg); color: var(--on-bg); font-family: 'garet', sans-serif; min-height: 100vh; position: relative; }
    :root { --surface-2: #17121c; --tile: #262031; }
    .light { --surface-2: #f4cf9a; --tile: #fff3dd; }

    /* Landing's ambient drifting shapes. */
    .ambient { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
    .pub-theme > *:not(.ambient) { position: relative; z-index: 1; }
    .ambient span { position: absolute; display: block; animation: drift var(--dur, 18s) ease-in-out var(--delay, 0s) infinite; opacity: .35; }
    .ambient .sq { border-radius: 22%; }
    .ambient .dot { border-radius: 9999px; }
    @keyframes drift {
        0%, 100% { transform: translate(0, 0) rotate(0deg) scale(1); }
        25%      { transform: translate(4vw, -6vh) rotate(35deg) scale(1.12); }
        50%      { transform: translate(-3vw, 5vh) rotate(-20deg) scale(.92); }
        75%      { transform: translate(5vw, 3vh) rotate(15deg) scale(1.05); }
    }
    @media (prefers-reduced-motion: reduce) { .ambient span { animation: none; } }
    .ambient .c0 { background: rgba(255,255,255,.5); }
    .ambient .c1 { background: rgba(255,255,255,.32); }
    .ambient .c2 { background: rgba(255,225,196,.55); }
    .ambient .c3 { background: rgba(227,135,4,.45); }
    .ambient .c4 { background: rgba(247,115,21,.35); }
    .ambient .c5 { background: rgba(251,191,36,.4); }
    .light .ambient .c0 { background: rgba(227,135,4,.3); }
    .light .ambient .c1 { background: rgba(94,56,2,.22); }
    .light .ambient .c2 { background: rgba(247,115,21,.3); }
    .light .ambient .c3 { background: rgba(227,135,4,.4); }

    /* Template tiles: landing-style elevated cards — solid tile background, primary border. */
    .pub-theme .alt { background: var(--tile) !important; border: 1px solid var(--line) !important; color: var(--on-bg); }
    .pub-theme h1, .pub-theme h2, .pub-logo { font-family: 'junegull', 'trebuchet ms', sans-serif; text-transform: uppercase; font-weight: 400; }
    .pub-theme .text-gray-900 { color: var(--on-bg) !important; }
    .pub-theme .text-gray-700, .pub-theme .text-gray-600 { color: var(--on-bg-soft) !important; }
    .pub-theme .text-gray-500, .pub-theme .text-gray-400 { color: rgba(248,245,242,.55) !important; }
    .pub-theme .text-indigo-500, .pub-theme .text-indigo-600, .pub-theme .text-indigo-400 { color: var(--penta) !important; }

    /* Cards & framed surfaces: primary border always; the primary-tinted
       glow shadow appears on HOVER (landing-style lift). */
    .pub-theme .bg-white, .pub-theme [class*="dark:bg-[#1d1e2a]"] { background: var(--surface) !important; }
    .pub-theme .rounded-xl.overflow-hidden, .pub-theme .rounded-2xl { border-color: var(--line) !important; }
    .pub-theme .shadow-sm, .pub-theme .shadow-lg { box-shadow: none !important; transition: box-shadow .2s, transform .15s; }
    .pub-theme .shadow-sm:hover, .pub-theme .shadow-lg:hover,
    .pub-theme a.group:hover .shadow-sm, .pub-theme a.group:hover [class*="rounded-xl"] { box-shadow: var(--shadow) !important; }
    .pub-theme header { background: color-mix(in srgb, var(--bg) 88%, transparent) !important; border-bottom: 1px solid var(--line) !important; }

    /* Inputs on dark */
    .pub-theme input, .pub-theme select { background: rgba(255,255,255,.05) !important; border-color: var(--line) !important; color: var(--on-bg) !important; }
    .pub-theme input::placeholder { color: rgba(248,245,242,.5); }

    /* Primary CTA (Use template / Get started) — landing-style gradient + glow */
    .pub-theme .bg-gray-900, .pub-theme .bg-indigo-600 {
        background: linear-gradient(120deg, var(--primary), var(--primary-2)) !important; color: #fff !important;
        box-shadow: 0 12px 26px -12px rgba(227,135,4,.55); font-family: 'comforta', sans-serif;
    }
    .pub-theme .bg-gray-900:hover, .pub-theme .bg-indigo-600:hover { filter: brightness(1.1); }

    /* Chips / tags */
    .pub-theme .bg-gray-100, .pub-theme .bg-gray-50, .pub-theme .bg-indigo-50 { background: rgba(255,255,255,.06) !important; color: var(--on-bg-soft) !important; }
    .pub-theme .border-gray-200, .pub-theme .border-gray-100, .pub-theme [class*="border-gray-200/70"] { border-color: var(--line) !important; }
    .pub-theme details { background: var(--surface) !important; border-color: var(--line) !important; }
    .pub-theme details:hover, .pub-theme aside > div:hover { box-shadow: var(--shadow); transition: box-shadow .2s; }

    /* Landing's theme toggle, verbatim. */
    .theme-toggle {
        width: 40px; height: 40px; border-radius: 9999px; cursor: pointer;
        border: 2px solid var(--line); background: transparent;
        font-size: 16px; line-height: 1; display: grid; place-items: center;
        transition: border-color .2s, transform .15s;
    }
    .theme-toggle:hover { border-color: var(--primary); transform: translateY(-1px); }
    .theme-toggle .tt-moon { display: none; }
    .light .theme-toggle .tt-sun { display: none; }
    .light .theme-toggle .tt-moon { display: block; }
</style>

<script>
    // Follow the landing page's saved theme (same localStorage key), before first paint.
    if (localStorage.getItem('olux_landing_theme') === 'light') {
        document.documentElement.classList.add('light');
    }
    // Same toggle + storage key as the landing page.
    function toggleTheme() {
        var light = document.documentElement.classList.toggle('light');
        localStorage.setItem('olux_landing_theme', light ? 'light' : 'dark');
        document.cookie = 'theme=' + (light ? 'light' : 'dark') + ';path=/;max-age=31536000;SameSite=Lax';
    }
</script>
