{{--
    Public Privacy Policy — /privacy (linked from the landing footer, the sign-up
    panel and the signup wizard; /privacy#data-deletion is the data-deletion URL
    given to Meta / X / TikTok). Text in [SQUARE BRACKETS] is a placeholder to
    fill in. Bump $updated whenever the wording changes.
--}}
@php
    $updated = '10 October 2026';
    $email = 'privacy@oluxstudio.com';
    $toc = [
        'who-we-are' => 'Who we are',
        'data-we-collect' => 'What data we collect',
        'how-we-use-it' => 'How and why we use it',
        'social-login' => 'Signing in with social accounts',
        'ai-assistant' => 'The AI assistant',
        'sharing' => 'Who we share data with',
        'retention' => 'How long we keep data',
        'security' => 'How we keep data safe',
        'your-rights' => 'Your rights',
        'data-deletion' => 'How to delete your data',
        'cookies' => 'Cookies',
        'children' => 'Children',
        'changes' => 'Changes to this policy',
        'contact' => 'Contact us',
    ];
@endphp
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy – Olux CMS</title>
    <meta name="description" content="How Olux CMS collects, uses, shares and protects personal data, your rights under UK GDPR, and how to delete your data.">
    <meta property="og:title" content="Privacy Policy – Olux CMS">
    <meta property="og:description" content="How Olux CMS handles personal data, your rights under UK GDPR, and how to delete your data.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/privacy') }}">
    <link rel="canonical" href="{{ url('/privacy') }}">
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
            --primary: #e38704; --primary-2: #f77315;
            --bg: #120f14; --on-bg: #f8f5f2; --on-bg-soft: rgba(248,245,242,.85);
            --surface: #1b1620; --line: rgba(255,255,255,.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'garet', sans-serif; color: var(--on-bg); background: var(--bg); line-height: 1.75; }
        a { color: var(--primary-2); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .wrap { max-width: 52rem; margin: 0 auto; padding: 0 1.25rem; }
        .topbar { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 0; }
        .brand { font-family: 'junegull', sans-serif; color: var(--on-bg); font-size: 20px; }
        .brand span { color: var(--primary); }
        .topbar nav a { font-family: 'comforta', sans-serif; font-weight: 700; font-size: .85rem; margin-left: 1.1rem; color: var(--on-bg-soft); }
        header.hero { padding: 3rem 0 2.25rem; border-bottom: 1px solid var(--line); }
        .eyebrow { font-family: 'comforta', sans-serif; font-size: .8rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--primary); }
        h1 { font-family: 'junegull', sans-serif; font-weight: 400; font-size: clamp(34px, 6vw, 56px); text-transform: uppercase; line-height: 1.1; margin-top: .4rem; }
        .lead { color: var(--on-bg-soft); max-width: 42rem; margin-top: 1rem; }
        .updated { margin-top: .9rem; font-size: .85rem; color: rgba(248,245,242,.6); }
        .toc { margin: 2.25rem 0 .5rem; background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 1.4rem 1.6rem; }
        .toc h2 { font-family: 'comforta', sans-serif; font-weight: 700; font-size: .8rem; letter-spacing: .12em; text-transform: uppercase; color: var(--primary); margin: 0 0 .6rem; border: 0; padding: 0; }
        .toc ol { columns: 2; column-gap: 2rem; padding-left: 1.2rem; }
        .toc li { break-inside: avoid; font-size: .95rem; }
        @media (max-width: 560px) { .toc ol { columns: 1; } }
        main section { padding: 2.25rem 0 .5rem; scroll-margin-top: 1rem; }
        main h2 { font-family: 'junegull', sans-serif; font-weight: 400; text-transform: uppercase; font-size: clamp(22px, 3.4vw, 28px); line-height: 1.2; margin-bottom: .9rem; }
        main h3 { font-family: 'comforta', sans-serif; font-weight: 700; font-size: 1rem; margin: 1.3rem 0 .4rem; }
        main p, main li { color: var(--on-bg-soft); }
        main p + p { margin-top: .75rem; }
        main ul, main ol { padding-left: 1.3rem; margin: .5rem 0 .75rem; }
        main ul { list-style: disc; } main ol, .toc ol { list-style: decimal; }
        li::marker { color: var(--primary); }
        main li + li { margin-top: .3rem; }
        strong { color: var(--on-bg); }
        .box { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 1.1rem 1.3rem; margin: 1rem 0; }
        .box.hl { border-color: rgba(227,135,4,.55); }
        .table-wrap { overflow-x: auto; margin: .75rem 0; border: 1px solid var(--line); border-radius: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; min-width: 34rem; }
        th, td { text-align: left; vertical-align: top; padding: .7rem .9rem; border-bottom: 1px solid var(--line); }
        th { font-family: 'comforta', sans-serif; font-weight: 700; font-size: .78rem; letter-spacing: .06em; text-transform: uppercase; color: var(--on-bg); background: var(--surface); }
        td { color: var(--on-bg-soft); }
        tr:last-child td { border-bottom: 0; }
        .ph { color: #fbbf24; font-weight: 700; }
        .back { display: inline-block; margin-top: .6rem; font-size: .85rem; }
        footer { border-top: 1px solid var(--line); margin-top: 3rem; padding: 1.75rem 0 2.5rem; font-size: .88rem; color: rgba(248,245,242,.6); }
        footer .wrap { display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; justify-content: space-between; }
        footer a { color: var(--on-bg-soft); }
    </style>
</head>
<body>
    <div class="wrap topbar">
        <a href="{{ route('landing') }}" class="brand">Olux<span>.</span></a>
        <nav>
            <a href="{{ route('landing') }}">Home</a>
            <a href="{{ route('login') }}">Sign in</a>
        </nav>
    </div>

    <header class="hero">
        <div class="wrap">
            <p class="eyebrow">Legal</p>
            <h1>Privacy Policy</h1>
            <p class="lead">This policy explains what personal data Olux CMS collects, why we collect it, who we share it with, how long we keep it, and the choices and rights you have. We have tried to keep it short and in plain English.</p>
            <p class="updated">Last updated: {{ $updated }}</p>
        </div>
    </header>

    <main class="wrap">
        <nav class="toc" aria-label="Contents">
            <h2>Contents</h2>
            <ol>
                @foreach ($toc as $id => $label)
                    <li><a href="#{{ $id }}">{{ $label }}</a></li>
                @endforeach
            </ol>
        </nav>

        {{-- 1 --}}
        <section>
            <h2 id="who-we-are">1. Who we are</h2>
            <p>Olux CMS (“Olux”, “we”, “us”) is an online platform for small businesses such as salons, tradespeople and clinics. It brings together website hosting, a content management system, a customer list (CRM), bookings, invoicing, domain registration, business email and an AI assistant.</p>
            <p>Olux CMS is run by Olux Studio, a trading name of <span class="ph">[COMPANY LEGAL NAME]</span>, a company registered in England and Wales (company number <span class="ph">[COMPANY NUMBER]</span>), registered office <span class="ph">[REGISTERED ADDRESS]</span>. We are registered with the Information Commissioner’s Office (ICO), registration number <span class="ph">[ICO REGISTRATION NUMBER]</span>.</p>

            <h3>Two kinds of people, two roles</h3>
            <p>We handle personal data about two different groups, and our legal role is different for each:</p>
            <div class="box">
                <p><strong>1. Our customers</strong> — the business owners and their team members who have an Olux CMS account. For this data we are the <strong>data controller</strong>: we decide how and why it is used, and this policy applies in full.</p>
                <p><strong>2. Our customers’ clients</strong> — the people who visit, book, buy, get invoiced by, or contact a business through a website hosted on Olux CMS. For this data <strong>the business is the data controller</strong> and we are its <strong>data processor</strong>: we only store and process it on the business’s behalf and on its instructions, under a data processing agreement. If you are one of these people, the business’s own privacy notice explains how it uses your data, and you should contact the business first to use your rights. We will help the business respond.</p>
            </div>
        </section>

        {{-- 2 --}}
        <section>
            <h2 id="data-we-collect">2. What data we collect</h2>

            <h3>About our customers (account holders)</h3>
            <ul>
                <li><strong>Account details:</strong> your name, email address, phone number, password (stored only as a secure hash), business name and type, and profile picture.</li>
                <li><strong>Billing details:</strong> your plan, billing address, payment history and invoices. Card details are entered directly with Stripe; we never see or store your full card number.</li>
                <li><strong>Social login data:</strong> if you sign in with Google or Facebook (or another platform we offer, such as Instagram, X or TikTok), we receive your name, email address, profile picture and the platform’s user ID for you. See <a href="#social-login">section 4</a>.</li>
                <li><strong>Domain and email details:</strong> if you buy a domain or business mailbox through us, the registrant contact details the domain registry requires (name, address, email, phone).</li>
                <li><strong>Usage and device data:</strong> pages you open in the dashboard, actions you take (for example “site published”), log-in times, IP address, browser and device type. We keep a security log of sign-ins and important account changes.</li>
                <li><strong>Support messages:</strong> anything you send us by email or through the app.</li>
            </ul>

            <h3>Content you put on the platform</h3>
            <ul>
                <li><strong>Site content:</strong> the text, images, files, products, services and settings you add to your websites.</li>
                <li><strong>CRM, booking and invoice data:</strong> the details of your own clients that you or your website collect — for example names, contact details, booking times, form messages, orders, invoices and payments. We process this as your data processor (see section 1).</li>
            </ul>

            <h3>About visitors to sites hosted on Olux CMS</h3>
            <ul>
                <li><strong>What they choose to give:</strong> details entered into forms, bookings, checkouts, newsletter sign-ups, reviews and the AI assistant chat.</li>
                <li><strong>Visit statistics:</strong> pages viewed, referring site, browser and device type, so the business can see how its site is doing.</li>
                <li><strong>Approximate location from IP address:</strong> we look up the town and country an IP address belongs to using a MaxMind GeoLite2 database that runs on our own servers (the IP address is not sent to MaxMind). We use this for visit statistics and fraud and abuse prevention, not to track individuals.</li>
            </ul>

            <h3>Cookies</h3>
            <p>We use a small number of cookies. See <a href="#cookies">section 11</a>.</p>
        </section>

        {{-- 3 --}}
        <section>
            <h2 id="how-we-use-it">3. How and why we use it</h2>
            <p>UK data protection law says we need a “lawful basis” for each use of personal data. This table shows what we do with our customers’ data and the basis we rely on.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>What we do</th><th>Lawful basis</th></tr></thead>
                    <tbody>
                        <tr><td>Create and run your account, host your sites, and provide the features you use (CRM, bookings, invoicing, domains, email, AI assistant)</td><td><strong>Contract</strong> — we need it to provide the service you signed up for</td></tr>
                        <tr><td>Take payments, send receipts and manage your subscription</td><td><strong>Contract</strong></td></tr>
                        <tr><td>Register domains and set up business email in your name</td><td><strong>Contract</strong>; registry rules also require some details</td></tr>
                        <tr><td>Let you sign in with a social account</td><td><strong>Contract</strong> (it is how you chose to sign in) and <strong>consent</strong> (you approve it on the platform’s screen)</td></tr>
                        <tr><td>Send service messages: verification codes, security alerts, booking and payment notifications, changes to your plan</td><td><strong>Contract</strong></td></tr>
                        <tr><td>Keep the platform secure: sign-in logs, fraud and abuse checks, spam filtering on forms</td><td><strong>Legitimate interests</strong> — protecting our customers and our service</td></tr>
                        <tr><td>Understand how the product is used and improve it</td><td><strong>Legitimate interests</strong> — we use aggregated or minimal data where we can</td></tr>
                        <tr><td>Answer support requests</td><td><strong>Contract</strong> or <strong>legitimate interests</strong></td></tr>
                        <tr><td>Send product news and offers</td><td><strong>Consent</strong> (or, for existing customers, the “soft opt-in” under PECR). You can unsubscribe at any time.</td></tr>
                        <tr><td>Keep accounting and tax records, and respond to lawful requests from authorities</td><td><strong>Legal obligation</strong></td></tr>
                    </tbody>
                </table>
            </div>
            <p>For data we process for a business (its clients’ data), we only use it to provide our service to that business, as described in our data processing terms. We do not sell it, and we do not use it for our own marketing.</p>
        </section>

        {{-- 4 --}}
        <section>
            <h2 id="social-login">4. Signing in with social accounts</h2>
            <p>You can create an account or sign in using an account you already have with another platform instead of a password. Today we offer <strong>Google</strong> and <strong>Facebook</strong>. Where we offer <strong>Instagram, X (Twitter) or TikTok</strong>, the same rules apply.</p>
            <p>When you choose this, the platform asks you to approve sharing some details with us. We receive:</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Platform</th><th>What we receive</th></tr></thead>
                    <tbody>
                        <tr><td>Google</td><td>Name, email address, profile picture, Google user ID</td></tr>
                        <tr><td>Facebook (Meta)</td><td>Name, email address, profile picture, app-scoped Facebook user ID</td></tr>
                        <tr><td>Instagram (Meta)</td><td>Username, profile picture, Instagram user ID (Instagram does not share an email address)</td></tr>
                        <tr><td>X (Twitter)</td><td>Name, username, profile picture, X user ID, and email address if you allow it</td></tr>
                        <tr><td>TikTok</td><td>Display name, profile picture, TikTok user ID (open ID)</td></tr>
                    </tbody>
                </table>
            </div>
            <ul>
                <li>We use this information <strong>only to create your account, sign you in and keep your account secure</strong> (for example, linking it to an existing account with the same email address).</li>
                <li>We do not receive or store your password for that platform.</li>
                <li>We do not access your friends, followers, messages or posts.</li>
                <li><strong>We never post anything on your behalf</strong> without your separate, explicit permission.</li>
                <li>We do not sell this data or use it for advertising.</li>
            </ul>
            <p>You can disconnect us at any time from the platform’s settings, and you can ask us to delete the data we received. See <a href="#data-deletion">How to delete your data</a>.</p>
        </section>

        {{-- 5 --}}
        <section>
            <h2 id="ai-assistant">5. The AI assistant</h2>
            <p>Businesses can switch on an AI assistant on their website and in their dashboard. To work, it sends information to a third-party AI provider (currently <strong>DeepSeek</strong>):</p>
            <ul>
                <li>the business’s site content (pages, services, prices, opening hours, FAQs) so it can answer questions accurately;</li>
                <li>the messages a visitor types into the chat; and</li>
                <li>where the visitor asks it to, the details needed to take an action such as making a booking or sending an enquiry (for example name, email, phone and preferred time).</li>
            </ul>
            <p>The provider processes this only to generate the reply. Please don’t type sensitive information (such as health details or payment card numbers) into the chat. Answers are generated automatically and can be wrong; the assistant does not make decisions that have legal or similarly significant effects on anyone.</p>
            <p>Businesses can turn the assistant off at any time. <span class="ph">[CONFIRM: whether the AI provider keeps or trains on data, and where it processes it.]</span></p>
        </section>

        {{-- 6 --}}
        <section>
            <h2 id="sharing">6. Who we share data with</h2>
            <p>We don’t sell personal data. We share it only with trusted service providers (“sub-processors”) that help us run Olux CMS, under contracts that require them to protect it and use it only for the service they provide to us.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Provider</th><th>What they do</th><th>Location</th></tr></thead>
                    <tbody>
                        <tr><td>Stripe</td><td>Payments for Olux plans and domains, and payments businesses take from their clients</td><td>UK, EU, USA</td></tr>
                        <tr><td>Brevo</td><td>Sending emails (verification codes, notifications, receipts, newsletters)</td><td>EU (France)</td></tr>
                        <tr><td>Openprovider</td><td>Domain registration and business email mailboxes</td><td>EU (Netherlands)</td></tr>
                        <tr><td>Hostinger</td><td>Server hosting for the platform and its databases</td><td><span class="ph">[DATA CENTRE LOCATION]</span></td></tr>
                        <tr><td>Cloudflare</td><td>File storage, content delivery and protection against attacks</td><td>Global network</td></tr>
                        <tr><td>DeepSeek</td><td>The AI assistant (see section 5)</td><td><span class="ph">[CONFIRM — e.g. China]</span></td></tr>
                        <tr><td>Google, Meta (Facebook, Instagram), X, TikTok</td><td>Social sign-in, when you choose it (see section 4)</td><td>USA and other countries</td></tr>
                        <tr><td>WhatsApp Business and SMS providers</td><td>Booking reminders and notifications by text message, where a business turns this on <span class="ph">[NAME SMS PROVIDER]</span></td><td><span class="ph">[LOCATION]</span></td></tr>
                    </tbody>
                </table>
            </div>
            <p>We may also share data with professional advisers (such as accountants and lawyers), with a buyer if our business is sold (they would have to respect this policy), or with the police, HMRC or other authorities when the law requires it.</p>

            <h3>International transfers</h3>
            <p>Some providers process data outside the UK. When that happens we make sure it is protected by an approved safeguard: a UK “adequacy” decision for the country (for example the EU), the UK–US Data Bridge where the US provider is certified, or the UK International Data Transfer Agreement / Addendum to the EU Standard Contractual Clauses. You can ask us for details of the safeguard for any transfer.</p>
        </section>

        {{-- 7 --}}
        <section>
            <h2 id="retention">7. How long we keep data</h2>
            <ul>
                <li><strong>Account data:</strong> for as long as your account is open, then for up to <span class="ph">[90 DAYS]</span> after it closes (so we can restore it if closed by mistake and settle any final bills), after which it is deleted.</li>
                <li><strong>Site content, CRM, booking and invoice data you store with us:</strong> until you delete it or close your account, then deleted within the same period. Backups are overwritten within <span class="ph">[35 DAYS]</span>.</li>
                <li><strong>Invoices and payment records:</strong> 6 years from the end of the financial year they relate to, because HMRC requires it.</li>
                <li><strong>Security and sign-in logs:</strong> up to <span class="ph">[12 MONTHS]</span>.</li>
                <li><strong>Visit statistics:</strong> up to <span class="ph">[26 MONTHS]</span>, after which they are kept only in anonymous, aggregated form.</li>
                <li><strong>Support emails:</strong> up to <span class="ph">[2 YEARS]</span> after the conversation ends.</li>
            </ul>
        </section>

        {{-- 8 --}}
        <section>
            <h2 id="security">8. How we keep data safe</h2>
            <ul>
                <li>All connections to Olux CMS and to hosted sites use encryption (HTTPS/TLS).</li>
                <li>Passwords are stored only as strong one-way hashes. Sign-up email addresses are verified with a one-time code.</li>
                <li>Platform administrators must use two-factor authentication.</li>
                <li>Each business’s data is kept separate from other businesses’ data, and team members only see what their role allows.</li>
                <li>Payment card details are handled by Stripe, which is certified to PCI DSS Level 1.</li>
                <li>We keep sign-in and account-change logs, limit staff access to what they need, and keep our software up to date.</li>
                <li>Regular backups protect against data loss.</li>
            </ul>
            <p>No system is perfectly secure. If a personal data breach puts your rights at risk, we will tell you and the ICO as the law requires.</p>
        </section>

        {{-- 9 --}}
        <section>
            <h2 id="your-rights">9. Your rights</h2>
            <p>Under UK GDPR you have the right to:</p>
            <ul>
                <li><strong>Access</strong> — get a copy of the personal data we hold about you.</li>
                <li><strong>Rectification</strong> — have inaccurate data corrected. You can change most account details yourself in Settings.</li>
                <li><strong>Erasure</strong> — have your data deleted (see <a href="#data-deletion">section 10</a>).</li>
                <li><strong>Restriction</strong> — ask us to pause using your data while a concern is looked into.</li>
                <li><strong>Portability</strong> — get data you gave us in a common, machine-readable format, or have it sent to another provider.</li>
                <li><strong>Objection</strong> — object to uses based on legitimate interests, and to direct marketing at any time.</li>
                <li><strong>Withdraw consent</strong> — where we rely on consent, you can withdraw it at any time. This doesn’t affect what we did before.</li>
            </ul>
            <p>To use any of these rights, email <a href="mailto:{{ $email }}">{{ $email }}</a>. We will reply within one month, and it is free in most cases. We may need to confirm your identity first.</p>
            <p>If you are a client of a business that uses Olux CMS, please contact that business first — it controls your data. If you contact us, we will pass your request on to the business.</p>
            <div class="box">
                <p><strong>Complaints.</strong> If you are unhappy with how we have handled your data, please tell us first so we can try to put it right. You also have the right to complain to the Information Commissioner’s Office (ICO), the UK data protection regulator: <a href="https://ico.org.uk/make-a-complaint/" target="_blank" rel="noopener">ico.org.uk</a> or 0303 123 1113.</p>
            </div>
        </section>

        {{-- 10 --}}
        <section>
            <h2 id="data-deletion">10. How to delete your data</h2>
            <p>You can delete your Olux CMS account and its data at any time. Choose whichever way suits you:</p>

            <div class="box hl">
                <h3 style="margin-top:0">Option 1 — Delete your account yourself</h3>
                <ol>
                    <li>Sign in at <a href="{{ route('login') }}">cms.oluxstudio.com</a>.</li>
                    <li>Open <strong>Settings → Account Management</strong>.</li>
                    <li>Click <strong>Delete account</strong> and confirm.</li>
                </ol>
                <p>This permanently deletes your account, your sites and their content.</p>
            </div>

            <div class="box hl">
                <h3 style="margin-top:0">Option 2 — Ask us by email</h3>
                <p>Email <a href="mailto:{{ $email }}?subject=Data%20deletion%20request">{{ $email }}</a> <strong>from the email address on your account</strong>, with the subject <strong>“Data deletion request”</strong>. If you signed up with a social account, tell us which one (for example Facebook). We will confirm when it is done, within <strong>30 days</strong>.</p>
            </div>

            <div class="box hl">
                <h3 style="margin-top:0">Option 3 — Remove Olux from Facebook</h3>
                <ol>
                    <li>In Facebook, go to <strong>Settings &amp; privacy → Settings → Apps and websites</strong>.</li>
                    <li>Find <strong>Olux</strong> and click <strong>Remove</strong>.</li>
                </ol>
                <p>This stops Facebook sharing any more data with us. To also delete the data we already received, use Option 1 or 2. Other platforms have similar settings: Google (<em>Google Account → Security → Your connections to third-party apps &amp; services</em>), Instagram (<em>Settings → Website permissions → Apps and websites</em>), X (<em>Settings → Security and account access → Apps and sessions → Connected apps</em>) and TikTok (<em>Settings and privacy → Security → Apps and services permissions</em>).</p>
            </div>

            <h3>What we may have to keep</h3>
            <p>Some records must be kept by law even after you delete your account — for example invoices and payment records, which HMRC requires us to keep for 6 years. We keep only what is needed, use it for nothing else, and delete it once we no longer have to keep it. Backups containing your data are overwritten within <span class="ph">[35 DAYS]</span>.</p>
            <p>If you are a client of a business that uses Olux CMS and want your data deleted, please contact that business; we will act on its instructions.</p>
        </section>

        {{-- 11 --}}
        <section>
            <h2 id="cookies">11. Cookies</h2>
            <p>Cookies are small files stored in your browser. We only use cookies needed to run the service and remember your choices. We do not use advertising or cross-site tracking cookies.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Cookie</th><th>What it’s for</th><th>How long</th></tr></thead>
                    <tbody>
                        <tr><td>Session cookie</td><td>Keeps you signed in as you move between pages</td><td>Until you close your browser or sign out</td></tr>
                        <tr><td>XSRF-TOKEN</td><td>Security: protects forms against cross-site request forgery</td><td>Session</td></tr>
                        <tr><td>remember_web_*</td><td>Keeps you signed in if you tick “Remember me”</td><td>Up to 400 days, or until you sign out</td></tr>
                        <tr><td>theme</td><td>Remembers whether you chose light or dark mode</td><td>1 year</td></tr>
                        <tr><td>Stripe cookies</td><td>Set by Stripe on payment pages to prevent fraud</td><td>See Stripe’s cookie policy</td></tr>
                    </tbody>
                </table>
            </div>
            <p>Because these cookies are strictly necessary, the law does not require consent for them. If we ever add optional cookies (such as analytics), we will ask for your consent first. You can also block or delete cookies in your browser settings, but parts of the service may stop working.</p>
            <p>Websites that businesses build on Olux CMS may use their own cookies; their own privacy notices explain these.</p>
        </section>

        {{-- 12 --}}
        <section>
            <h2 id="children">12. Children</h2>
            <p>Olux CMS is a service for businesses and is not intended for anyone under 18. We do not knowingly collect personal data from children to create accounts. If you think a child has given us personal data, contact us and we will delete it.</p>
        </section>

        {{-- 13 --}}
        <section>
            <h2 id="changes">13. Changes to this policy</h2>
            <p>We may update this policy when our service or the law changes. The “Last updated” date at the top shows when it last changed. If we make important changes, we will tell account holders by email or in the dashboard before they take effect.</p>
            <p><strong>Last updated: {{ $updated }}</strong></p>
        </section>

        {{-- 14 --}}
        <section>
            <h2 id="contact">14. Contact us</h2>
            <p>Questions about this policy or your data? Get in touch:</p>
            <ul>
                <li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>
                <li>Post: <span class="ph">[COMPANY LEGAL NAME]</span>, <span class="ph">[REGISTERED ADDRESS]</span></li>
            </ul>
            <a href="#top" class="back" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">↑ Back to top</a>
        </section>
    </main>

    <footer>
        <div class="wrap">
            <span>© {{ date('Y') }} Olux Studio. All rights reserved.</span>
            <span><a href="{{ route('landing') }}">Home</a> · <a href="{{ route('privacy') }}">Privacy</a> · <a href="mailto:{{ $email }}">{{ $email }}</a></span>
        </div>
    </footer>
</body>
</html>
