<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Getting started — Olux Studio</title>
    <meta name="description" content="A step-by-step tutorial to set up your first site, capture leads, take bookings and payments, and go live with Olux Studio.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        @font-face { font-family: 'junegull'; src: url('/fonts/junegull.otf'); font-display: swap; }
        @font-face { font-family: 'garet'; font-weight: 400; src: url('/fonts/Garet-Book.woff2') format('woff2'); font-display: swap; }
        @font-face { font-family: 'garet'; font-weight: 700; src: url('/fonts/Garet-Heavy.woff2') format('woff2'); font-display: swap; }
        @font-face { font-family: 'comforta'; font-weight: 700; src: url('/fonts/Comfortaa-Bold.ttf'); font-display: swap; }
    </style>
    @vite('resources/css/app.css')
    <style>
        :root {
            --primary: #e38704; --primary-2: #f77315; --primary-3: #5e3802;
            --bg: #120f14; --on-bg: #f8f5f2; --on-bg-soft: rgba(248,245,242,.85);
            --surface: #1b1620; --line: rgba(255,255,255,.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'garet', sans-serif; color: var(--on-bg); background: var(--bg); line-height: 1.75; }
        a { color: var(--primary-2); text-decoration: none; }
        .wrap { max-width: 56rem; margin: 0 auto; padding: 0 1.25rem; }
        header.hero { padding: 4.5rem 0 2.5rem; text-align: center; border-bottom: 1px solid var(--line); }
        h1 { font-family: 'junegull', sans-serif; font-weight: 400; font-size: clamp(34px, 6vw, 60px); text-transform: uppercase; line-height: 1.1; }
        .eyebrow { font-family: 'comforta', sans-serif; font-size: .8rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--primary); }
        .lead { color: var(--on-bg-soft); max-width: 40rem; margin: 1rem auto 0; }
        .steps { padding: 3rem 0 2rem; display: grid; gap: 1.25rem; }
        .step { background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 1.5rem 1.75rem; display: flex; gap: 1.25rem; align-items: flex-start; }
        .num { flex: none; width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-family: 'junegull', sans-serif; font-size: 20px; color: #fff; background: linear-gradient(120deg, var(--primary), var(--primary-2)); }
        .step h3 { font-family: 'junegull', sans-serif; font-weight: 400; font-size: 22px; text-transform: uppercase; margin-bottom: .35rem; }
        .step p { color: var(--on-bg-soft); font-size: 1rem; }
        .cta-band { text-align: center; padding: 2.5rem 0 5rem; }
        .btn { display: inline-block; font-family: 'comforta', sans-serif; font-weight: 700; padding: 14px 42px; border-radius: 12px; background: linear-gradient(120deg, var(--primary), var(--primary-2)); color: #fff; box-shadow: 0 12px 26px -12px rgba(227,135,4,.55); transition: transform .15s, filter .15s; }
        .btn:hover { filter: brightness(1.1); transform: translateY(-2px); color:#fff; }
        .faq { border-top: 1px solid var(--line); padding: 2.5rem 0; }
        .faq h2 { font-family: 'junegull', sans-serif; font-weight: 400; text-transform: uppercase; font-size: 28px; margin-bottom: 1.25rem; }
        .faq details { border-bottom: 1px solid var(--line); padding: .9rem 0; }
        .faq summary { cursor: pointer; font-weight: 700; font-family: 'comforta', sans-serif; }
        .faq p { color: var(--on-bg-soft); font-size: .95rem; margin-top: .5rem; }
    </style>
</head>
<body>
    <header class="hero">
        <div class="wrap">
            <p class="eyebrow">Welcome to Olux Studio</p>
            <h1>Get your first site live</h1>
            <p class="lead">Everything you need to go from a blank account to a live website with lead capture, bookings and payments — in about 20 minutes.</p>
        </div>
    </header>

    <main class="wrap">
        <section class="steps">
            @php
                $steps = [
                    ['Create your site', 'From the site picker, click “New site”. Give it a name and pick a starting template or generate one from a prompt with the AI assistant.'],
                    ['Add your pages & content', 'Open the Page Builder to arrange components. Edit text, images and collections inline — every change is saved to your content library.'],
                    ['Capture leads with forms', 'Add a contact or enquiry form. Submissions land in your CRM and a branded receipt email is sent to both you and the visitor automatically.'],
                    ['Turn on paid features', 'Enable Store, Bookings or Donations from the Add-ons marketplace. Connect Stripe once and start taking payments.'],
                    ['Manage your assets', 'Upload images, video, audio and fonts to your media library. Keep an eye on your plan’s storage usage from the Assets page.'],
                    ['Publish & go live', 'Preview your site, then export it to your own domain. Your visitors get a fast static site backed by your live content.'],
                ];
            @endphp
            @foreach ($steps as $i => $s)
                <div class="step">
                    <div class="num">{{ $i + 1 }}</div>
                    <div>
                        <h3>{{ $s[0] }}</h3>
                        <p>{{ $s[1] }}</p>
                    </div>
                </div>
            @endforeach
        </section>

        <div class="cta-band">
            <a class="btn" href="{{ route('home') }}">Go to my dashboard</a>
        </div>

        <section class="faq">
            <h2>Common questions</h2>
            <details>
                <summary>How do I invite my team?</summary>
                <p>Open Account → Team and add staff by email. Roles control what each member can edit.</p>
            </details>
            <details>
                <summary>Where do form submissions go?</summary>
                <p>Every submission is stored under Contacts for the site, and a branded email is sent to you and the visitor. You can customise the email copy under Site → Emails.</p>
            </details>
            <details>
                <summary>How do I take payments?</summary>
                <p>Enable the Store or Donations add-on, connect your Stripe account once, and payments flow straight through Stripe Checkout.</p>
            </details>
            <details>
                <summary>Can I use my own domain?</summary>
                <p>Yes — export your site and point your domain at it. Your content stays editable in the CMS.</p>
            </details>
        </section>
    </main>
</body>
</html>
