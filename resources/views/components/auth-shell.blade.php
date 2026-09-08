{{-- The login/register card shell (dark studio theme) with the hero panel fixed
     LEFT and the slot on the right — used by /start so the signup steps that
     come after email verification look like the register panel they follow. --}}
@props(['tagline' => 'Join thousands of creators<br>building with Olux CMS.'])
<div class="auth-screen min-h-screen flex items-center justify-center p-4">
    <div class="auth-card w-full max-w-[65rem] rounded-3xl shadow-2xl overflow-hidden relative md:flex">

        {{-- Hero panel --}}
        <div class="hidden md:block md:w-1/2 relative flex-shrink-0">
            <div class="absolute inset-0 m-3 rounded-2xl overflow-hidden"
                 style="background:linear-gradient(160deg,var(--primary) 0%,var(--primary-3) 62%,var(--primary-4) 100%)">
                {{-- Grid overlay --}}
                <div class="absolute inset-0 opacity-[0.07]"
                     style="background-image:linear-gradient(rgba(255,255,255,1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,1) 1px,transparent 1px);background-size:36px 36px"></div>

                {{-- Drifting brand shapes (landing-style ambient) --}}
                <span class="auth-drift" style="top:12%;left:12%;width:16px;height:16px;--d:16s"></span>
                <span class="auth-drift" style="top:70%;left:80%;width:26px;height:26px;--d:22s"></span>
                <span class="auth-drift dot" style="top:30%;left:78%;width:12px;height:12px;--d:19s"></span>
                <span class="auth-drift dot" style="top:82%;left:18%;width:20px;height:20px;--d:25s"></span>

                {{-- What the studio actually does — a live dashboard feed mock --}}
                <div class="absolute inset-x-8 top-14 space-y-2.5">
                    <div class="auth-feed-row">New estimate request <small>2 min ago</small></div>
                    <div class="auth-feed-row">Booking confirmed — £120 <small>1 h ago</small></div>
                    <div class="auth-feed-row">Invoice INV-014 paid <small>3 h ago</small></div>
                    <div class="auth-feed-row">New contact captured <small>today</small></div>
                    <div class="auth-feed-row">Site published — live with SSL <small>today</small></div>
                </div>

                <div class="absolute bottom-8 left-0 right-0 text-center px-8">
                    <p class="text-white text-base font-semibold leading-snug">{!! $tagline !!}</p>
                    <p class="text-white/40 text-xs mt-2">Your complete website studio.</p>
                </div>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="relative w-full md:w-1/2 px-6 py-8 md:px-10 md:py-6 flex flex-col justify-start z-10">
            <div class="flex items-center gap-2 mb-6">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:linear-gradient(120deg,var(--primary),var(--primary-2))">
                    <x-app-logo-icon class="w-4 h-4 fill-current text-white" />
                </div>
                <span class="font-bold text-lg auth-logo-text">Olux CMS<span style="color:var(--primary)">.</span></span>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    /* ── Landing-page theme applied to auth ─────────────────────────────── */
    @font-face { font-family: 'junegull'; src: url('/fonts/junegull.otf'); font-display: swap; }
    @font-face { font-family: 'garet'; font-weight: 400; src: url('/fonts/Garet-Book.woff2') format('woff2'); font-display: swap; }
    @font-face { font-family: 'comforta'; font-weight: 400; src: url('/fonts/Comfortaa-Regular.ttf'); font-display: swap; }
    @font-face { font-family: 'comforta'; font-weight: 700; src: url('/fonts/Comfortaa-Bold.ttf'); font-display: swap; }

    .auth-screen {
        --primary: #e38704; --primary-2: #f77315; --primary-3: #5e3802; --primary-4: #3a2301;
        --penta: #fbbf24; --bg: #120f14; --on-bg: #f8f5f2; --on-bg-soft: rgba(248,245,242,.87);
        --surface: #1b1620; --line-inv: rgba(255,255,255,.12);
        background: var(--bg); font-family: 'garet', sans-serif; color: var(--on-bg);
    }
    .auth-card { background: var(--surface); border: 1px solid var(--line-inv); }
    @media (min-width: 768px) { .auth-card { min-height: 710px; } }
    .auth-screen h1 { font-family: 'junegull', 'trebuchet ms', sans-serif; text-transform: uppercase; color: var(--on-bg); font-weight: 400; }
    .auth-logo-text { font-family: 'junegull', sans-serif; color: var(--on-bg); }
    .auth-screen label, .auth-screen .text-xs, .auth-screen .text-sm { font-family: 'comforta', sans-serif; }

    /* Inputs on dark */
    .auth-screen input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]), .auth-screen textarea, .auth-screen select {
        background: rgba(255,255,255,.05); border-color: var(--line-inv); color: var(--on-bg);
    }
    .auth-screen input::placeholder, .auth-screen textarea::placeholder { color: rgba(248,245,242,.55); }
    .auth-screen input:-webkit-autofill, .auth-screen input:-webkit-autofill:hover, .auth-screen input:-webkit-autofill:focus, .auth-screen textarea:-webkit-autofill {
        -webkit-box-shadow: 0 0 0 1000px #241d29 inset; -webkit-text-fill-color: var(--on-bg); caret-color: var(--on-bg); border-color: var(--line-inv); transition: background-color 9999s ease-out;
    }

    /* Social buttons + divider on dark */
    .auth-screen .grid.grid-cols-2 a { border-color: var(--line-inv); color: var(--on-bg-soft); }
    .auth-screen .grid.grid-cols-2 a:hover { background: rgba(255,255,255,.06); border-color: var(--primary); }
    .auth-screen .h-px { background: var(--line-inv); }
    .auth-screen .signup-steps .step-bg { background: var(--surface); }
    .auth-screen .text-gray-600, .auth-screen .text-gray-700 { color: var(--on-bg-soft); }

    /* Submit buttons: orange gradient with glow */
    .auth-submit { background: linear-gradient(120deg, var(--primary), var(--primary-2)); box-shadow: 0 12px 26px -12px rgba(227,135,4,.55); font-family: 'comforta', sans-serif; }
    .auth-submit:hover { filter: brightness(1.1); transform: translateY(-1px); }

    /* Hero panel extras */
    .auth-feed-row {
        display: flex; justify-content: space-between; align-items: center;
        background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.15);
        border-radius: 12px; padding: 11px 15px; color: #fff;
        font-family: 'comforta', sans-serif; font-size: 12.5px; font-weight: 700;
    }
    .auth-feed-row small { opacity: .7; font-weight: 400; }
    .auth-drift { position: absolute; display: block; border-radius: 22%; background: rgba(255,255,255,.35); animation: auth-drift var(--d, 18s) ease-in-out infinite; }
    .auth-drift.dot { border-radius: 9999px; background: rgba(251,191,36,.55); }
    @keyframes auth-drift {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50%      { transform: translate(14px, -18px) rotate(25deg); }
    }
    @media (prefers-reduced-motion: reduce) { .auth-drift { animation: none; } }
</style>
<style>
    /* Wizard fields inside the dark shell */
    .auth-screen .olx-field, .auth-screen textarea.olx-field { background: rgba(255,255,255,.05); border-color: var(--line-inv); color: var(--on-bg); }
    .auth-screen .olx-field:focus { box-shadow: 0 0 0 2px rgba(227,135,4,.45); }
    .auth-screen .olx-primary { background: linear-gradient(120deg, var(--primary), var(--primary-2)); box-shadow: 0 12px 26px -12px rgba(227,135,4,.55); font-family: 'comforta', sans-serif; }
    .auth-screen .olx-primary:hover { filter: brightness(1.1); background: linear-gradient(120deg, var(--primary), var(--primary-2)); }
    .auth-screen .olx-type { border-color: var(--line-inv); color: var(--on-bg-soft); }
    .auth-screen .olx-type:hover { border-color: var(--primary); }
    .auth-screen .olx-type.is-on { border-color: var(--primary); background: rgba(227,135,4,.12); color: var(--penta); }
    .auth-screen .olx-suffix { background: rgba(255,255,255,.05); border-color: var(--line-inv); color: rgba(248,245,242,.55); }
    .auth-screen .olx-wrap { border-color: var(--line-inv); }
    .auth-screen .olx-wrap input { color: var(--on-bg); }
    .auth-screen .text-gray-900, .auth-screen .text-gray-800 { color: var(--on-bg); }
    .auth-screen .text-gray-400, .auth-screen .text-gray-500 { color: rgba(248,245,242,.6); }
    .auth-screen .text-indigo-500, .auth-screen .text-indigo-600 { color: var(--penta); }
    .auth-screen .text-emerald-600 { color: #34d399; }
    .auth-screen .signup-steps li.is-on, .auth-screen .signup-steps li.is-done { color: var(--primary); }
    .auth-screen .signup-steps .step-done { background: var(--primary); }
    .auth-screen .signup-steps .step-on { box-shadow: 0 0 0 2px var(--primary); }
</style>
