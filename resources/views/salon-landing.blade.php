<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Olux for Salons &amp; Barbers — website, bookings &amp; reminders, £79/month</title>
    <meta name="description" content="The all-in-one system for hair salons and barbershops: a proper website, online booking with deposits, per-chair calendars, automatic reminders and review requests — £79/month, no marketplace competing for your clients.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        @font-face { font-family: 'junegull'; src: url('/fonts/junegull.otf'); font-display: swap; }
        @font-face { font-family: 'garet'; font-weight: 400; src: url('/fonts/Garet-Book.woff2') format('woff2'); font-display: swap; }
        @font-face { font-family: 'garet'; font-weight: 700; src: url('/fonts/Garet-Heavy.woff2') format('woff2'); font-display: swap; }

        :root {
            --primary: #e38704; --primary-2: #f77315; --ink: #1d1410; --paper: #fdf6ec;
            --surface: #fff; --muted: #7a6a5c; --line: rgba(29,20,16,.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'garet', system-ui, sans-serif; background: var(--paper); color: var(--ink); line-height: 1.6; }
        .wrap { max-width: 1060px; margin: 0 auto; padding: 0 22px; }
        h1, h2 { font-family: 'junegull', 'garet', sans-serif; line-height: 1.15; letter-spacing: .2px; }
        header { padding: 22px 0; }
        header .wrap { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .logo { font-family: 'junegull', sans-serif; font-size: 20px; color: var(--primary-2); text-decoration: none; }
        .btn { display: inline-block; padding: 13px 26px; border-radius: 999px; font-weight: 700; text-decoration: none;
               background: linear-gradient(120deg, var(--primary), var(--primary-2)); color: #fff; box-shadow: 0 8px 24px rgba(227,135,4,.35); }
        .btn.ghost { background: none; box-shadow: none; color: var(--ink); border: 2px solid var(--line); }
        .hero { padding: 56px 0 40px; text-align: center; }
        .hero h1 { font-size: clamp(30px, 5.4vw, 52px); max-width: 20ch; margin: 0 auto 18px; }
        .hero h1 em { font-style: normal; color: var(--primary-2); }
        .hero p.lead { font-size: 19px; color: var(--muted); max-width: 56ch; margin: 0 auto 28px; }
        .hero .cta-row { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .chips { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top: 26px; }
        .chips span { background: var(--surface); border: 1px solid var(--line); border-radius: 999px; padding: 7px 14px; font-size: 13px; font-weight: 700; }
        section { padding: 46px 0; }
        section.alt { background: var(--surface); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        h2 { font-size: clamp(23px, 3.4vw, 32px); text-align: center; margin-bottom: 8px; }
        .sub { text-align: center; color: var(--muted); max-width: 60ch; margin: 0 auto 32px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 24px; }
        section.alt .card { background: var(--paper); }
        .card .k { font-size: 26px; }
        .card h3 { font-size: 17px; margin: 10px 0 6px; }
        .card p { font-size: 14.5px; color: var(--muted); }
        .wedge { max-width: 640px; margin: 0 auto; background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 28px; }
        .wedge ul { list-style: none; }
        .wedge li { padding: 9px 0 9px 30px; position: relative; font-size: 15.5px; }
        .wedge li::before { content: '✓'; position: absolute; left: 4px; color: var(--primary-2); font-weight: 700; }
        .price-card { max-width: 400px; margin: 0 auto; text-align: center; background: var(--ink); color: #fff; border-radius: 22px; padding: 38px 30px; }
        .price-card .amount { font-family: 'junegull', sans-serif; font-size: 54px; color: var(--primary-2); }
        .price-card .amount small { font-size: 17px; color: rgba(255,255,255,.7); font-family: 'garet', sans-serif; }
        .price-card ul { list-style: none; margin: 18px 0 24px; text-align: left; }
        .price-card li { padding: 6px 0 6px 26px; position: relative; font-size: 14.5px; color: rgba(255,255,255,.88); }
        .price-card li::before { content: '✓'; position: absolute; left: 0; color: var(--primary); font-weight: 700; }
        .founder { margin-top: 14px; font-size: 13px; color: rgba(255,255,255,.65); }
        footer { padding: 34px 0 44px; text-align: center; color: var(--muted); font-size: 13.5px; }
        footer a { color: var(--primary-2); }
    </style>
</head>
<body>

<header>
    <div class="wrap">
        <a class="logo" href="{{ route('landing') }}">Olux <span style="color:var(--ink)">· Salons</span></a>
        <nav style="display:flex; gap:10px; align-items:center">
            @if (config('app.salon_demo_url'))
                <a class="btn ghost" href="{{ config('app.salon_demo_url') }}" target="_blank" rel="noopener">See a live demo</a>
            @endif
            <a class="btn" href="{{ url('/register') }}">Start free</a>
        </nav>
    </div>
</header>

<section class="hero">
    <div class="wrap">
        <h1>Your salon's website, bookings &amp; reminders — <em>one system</em></h1>
        <p class="lead">Built for hair salons and barbershops. A proper website with online booking,
            deposits that stop no-shows, per-chair calendars and automatic reminders — for one flat price.</p>
        <div class="cta-row">
            <a class="btn" href="{{ url('/register') }}">Start your 14-day free trial</a>
            @if (config('app.salon_demo_url'))
                <a class="btn ghost" href="{{ config('app.salon_demo_url') }}" target="_blank" rel="noopener">View the demo salon</a>
            @endif
        </div>
        <div class="chips">
            <span>💈 Online booking</span><span>💳 Deposits</span><span>🪑 Per-chair calendars</span>
            <span>⏰ Auto reminders</span><span>⭐ Review requests</span><span>📇 Client CRM</span>
        </div>
    </div>
</section>

<section class="alt">
    <div class="wrap">
        <h2>The problems it solves</h2>
        <p class="sub">Everything below is switched on the day you join — no plugins, no extra subscriptions.</p>
        <div class="cards">
            <div class="card"><div class="k">🙅</div><h3>No-shows eating your day</h3>
                <p>Take a deposit on colour and long appointments at booking time. Card paid = they turn up. Automatic reminder emails ~24 hours before, so "I forgot" stops happening.</p></div>
            <div class="card"><div class="k">🗓️</div><h3>Double bookings &amp; DM chaos</h3>
                <p>Clients book themselves online, per chair or per stylist, only in slots you actually have. Your day view stays true — no more juggling Instagram DMs and a paper diary.</p></div>
            <div class="card"><div class="k">📉</div><h3>Quiet weeks</h3>
                <p>Automatic "time for your next cut?" emails go out a few weeks after each visit, and happy clients get a review request the next day — steady rebookings and a growing Google rating.</p></div>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <h2>Why not Fresha or Booksy?</h2>
        <p class="sub">Marketplace apps list your competitors next to your own booking page — and message your clients about other salons. Here, your clients stay <strong>yours</strong>.</p>
        <div class="wedge">
            <ul>
                <li><strong>Your own website</strong> on your own domain — not a profile on someone else's app</li>
                <li><strong>No marketplace</strong> promoting rival salons to your client list</li>
                <li><strong>No per-booking fees</strong> — one flat monthly price, keep 100% of every appointment</li>
                <li><strong>Your client list is yours</strong> — full CRM, export anytime</li>
                <li>Invoices, gift services, a blog and an AI assistant included when you want them</li>
            </ul>
        </div>
    </div>
</section>

<section class="alt">
    <div class="wrap">
        <h2>One price. Everything in.</h2>
        <p class="sub">No setup fee for founding clients. No contracts — cancel anytime.</p>
        <div class="price-card">
            <div class="amount">£79<small>/month</small></div>
            <ul>
                <li>Salon website — 5 pages, ready in a day</li>
                <li>Online booking with deposits (Stripe)</li>
                <li>Per-chair / per-stylist calendars</li>
                <li>Automatic reminders &amp; review requests</li>
                <li>Rebooking prompts that fill quiet weeks</li>
                <li>Client CRM, forms &amp; email inbox</li>
                <li>Hosting, SSL &amp; support included</li>
            </ul>
            <a class="btn" href="{{ url('/register') }}">Start free — no card needed</a>
            <p class="founder">Founding clients: setup fee waived · 14-day free trial</p>
        </div>
    </div>
</section>

<footer>
    <div class="wrap">
        Built on <a href="{{ route('landing') }}">Olux Studio</a> — websites, leads &amp; bookings for local businesses.
    </div>
</footer>

</body>
</html>
