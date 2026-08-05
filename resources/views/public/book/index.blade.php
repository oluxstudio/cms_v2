<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased">

    <header class="bg-white border-b border-gray-100">
        <div class="max-w-4xl mx-auto px-6 py-5">
            <h1 class="text-lg font-bold tracking-tight">{{ ucwords(str_replace('-', ' ', $site->name)) }} <span class="text-gray-400 font-normal">· Book online</span></h1>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-10" id="bk">

        {{-- Step rail --}}
        <div class="flex items-center gap-3 mb-8" id="bk-rail">
            <template id="bk-rail-t"></template>
        </div>

        {{-- STEP 1 · service cards (old design) --}}
        <section data-step="1">
            <p class="text-sm text-gray-500 mb-6">Choose a service to get started.</p>
            <div id="bk-services" class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <p class="text-sm text-gray-400">Loading services…</p>
            </div>
        </section>

        {{-- STEP 2 · when --}}
        <section data-step="2" class="hidden">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <div id="bk-resources" class="mb-5 hidden">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2" id="bk-res-label">Choose</p>
                    <div class="flex flex-wrap gap-2" id="bk-res-pills"></div>
                </div>
                <div id="bk-when"></div>
            </div>
        </section>

        {{-- STEP 3 · details --}}
        <section data-step="3" class="hidden">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-base font-bold mb-4">Your details</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-xs font-semibold text-gray-500">Full name *
                        <input id="bk-name" type="text" class="bk-input" placeholder="Jane Doe"></label>
                    <label class="block text-xs font-semibold text-gray-500">Email *
                        <input id="bk-email" type="email" class="bk-input" placeholder="jane@example.com"></label>
                    <label class="block text-xs font-semibold text-gray-500">Phone
                        <input id="bk-phone" type="text" class="bk-input" placeholder="Optional"></label>
                    <div id="bk-qty-wrap" class="hidden"><label class="block text-xs font-semibold text-gray-500">Seats
                        <input id="bk-qty" type="number" min="1" value="1" class="bk-input"></label></div>
                </div>
                <div id="bk-custom" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4"></div>
                <label class="block text-xs font-semibold text-gray-500 mt-4">Message
                    <textarea id="bk-notes" rows="2" class="bk-input" placeholder="Anything we should know? (optional)"></textarea></label>
                <div id="bk-paynote" class="hidden mt-4 text-xs text-gray-500 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3"></div>
                <p id="bk-error" class="hidden text-sm text-rose-600 mt-4"></p>
                <div class="flex items-center justify-between mt-6">
                    <button type="button" class="bk-back px-4 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100">← Back</button>
                    <button type="button" id="bk-submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Book now</button>
                </div>
            </div>
        </section>

        {{-- SUCCESS --}}
        <section data-step="4" class="hidden">
            <div class="bg-white rounded-2xl border border-emerald-200 shadow-sm p-8 text-center">
                <span class="text-5xl">✅</span>
                <h2 class="text-lg font-bold mt-4" id="bk-ok-title">Booking received</h2>
                <p class="text-sm text-gray-500 mt-2" id="bk-ok-msg"></p>
                <p class="text-sm font-bold mt-3" id="bk-ok-ref"></p>
                <button type="button" onclick="location.reload()" class="mt-6 px-5 py-2 rounded-xl bg-gray-900 text-white text-sm font-semibold">Book another</button>
            </div>
        </section>
    </main>

    <style>
        .bk-input { display:block; width:100%; margin-top:.35rem; padding:.6rem .8rem; border:1px solid #e5e7eb; border-radius:.75rem; font-size:.875rem; font-weight:400; color:#111827; background:#fff; }
        .bk-input:focus { outline:2px solid #6366f1; outline-offset:0; border-color:transparent; }
        .bk-pill { padding:.5rem 1rem; border:1px solid #e5e7eb; border-radius:9999px; background:#fff; font-size:.8rem; font-weight:600; cursor:pointer; transition:all .15s; }
        .bk-pill:hover { border-color:#a5b4fc; }
        .bk-pill.on { background:#6366f1; color:#fff; border-color:#6366f1; }
        .bk-pill small { display:block; font-size:.65rem; font-weight:500; opacity:.75; }
    </style>

    <script>
    (function () {
        'use strict';
        var API = @json(url('/api/sites/'.$site->name.'/booking'));
        var services = [], svc = null, resource = 0, slot = null, dep = null, stayQuote = null;
        var $ = function (s, r) { return (r || document).querySelector(s); };
        var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
        var CUR = {gbp:["£",0],usd:["$",0],eur:["€",1],ngn:["₦",0],cad:["CA$",0],aud:["A$",0],jpy:["¥",0],chf:["CHF",0],inr:["₹",0],zar:["R",0],kes:["KSh",0],ghs:["GH₵",0],sek:["kr",1],nok:["kr",1],dkk:["kr",1],pln:["zł",1],brl:["R$",0],mxn:["MX$",0],aed:["AED",1]};
        var money = function (c, cur) { var m = CUR[(cur || 'gbp').toLowerCase()]; var a = (c / 100).toFixed(2); return m ? (m[1] ? a + ' ' + m[0] : m[0] + a) : a + ' ' + (cur || '').toUpperCase(); };
        var api = function (path, opts) {
            return fetch(API + path, Object.assign({ headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' } }, opts))
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); });
        };

        // ── step rail ────────────────────────────────────────────────────
        var STEPS = ['Service', 'When', 'Details'];
        function goStep(n) {
            $$('#bk section').forEach(function (s) { s.classList.toggle('hidden', +s.getAttribute('data-step') !== n); });
            var rail = $('#bk-rail'); rail.innerHTML = '';
            STEPS.forEach(function (label, i) {
                var done = n > i + 1, active = n === i + 1;
                rail.insertAdjacentHTML('beforeend',
                    '<div class="flex items-center gap-1.5">' +
                    '<span class="w-7 h-7 rounded-full grid place-items-center text-xs font-bold ' +
                    (done ? 'bg-emerald-500 text-white' : active ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-400') + '">' + (done ? '✓' : i + 1) + '</span>' +
                    '<span class="text-xs font-semibold ' + (active ? 'text-gray-900' : 'text-gray-400') + '">' + label + '</span>' +
                    (i < STEPS.length - 1 ? '<span class="w-6 h-px bg-gray-200 ml-1.5"></span>' : '') + '</div>');
            });
            rail.classList.toggle('hidden', n === 4);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        $$('.bk-back').forEach(function (b) { b.onclick = function () { goStep(2); }; });

        // ── step 1: service cards (old card design) ──────────────────────
        api('/config').then(function (r) {
            services = r.body.services || [];
            var grid = $('#bk-services'); grid.innerHTML = '';
            if (!services.length) { grid.innerHTML = '<p class="text-sm text-gray-400">No services are available to book yet.</p>'; return; }
            services.forEach(function (s) {
                var meta = s.kind === 'stay' ? s.price + ' / night'
                         : s.kind === 'trip' ? 'from ' + s.price
                         : '🕒 ' + s.duration + ' min · ' + s.price;
                var card = document.createElement('a');
                card.href = '#';
                card.className = 'group bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md hover:border-indigo-200 transition';
                card.innerHTML = '<h2 class="text-base font-bold group-hover:text-indigo-600 transition-colors">' + (s.icon || '📅') + ' ' + s.name + '</h2>' +
                    (s.description ? '<p class="text-sm text-gray-500 mt-1.5">' + s.description + '</p>' : '') +
                    '<div class="flex items-center gap-3 mt-4 text-xs font-medium text-gray-500"><span>' + s.type + '</span>' +
                    '<span class="text-gray-300">·</span><span class="font-bold text-gray-800">' + meta + '</span>' +
                    (s.deposit_cents ? '<span class="text-gray-300">·</span><span class="text-indigo-500 font-semibold">deposit</span>' : '') + '</div>';
                card.onclick = function (e) { e.preventDefault(); pick(s); };
                grid.appendChild(card);
            });
        }).catch(function () { $('#bk-services').innerHTML = '<p class="text-sm text-rose-500">Could not load services.</p>'; });

        function pick(s) {
            svc = s; resource = 0; slot = null; dep = null; stayQuote = null;
            renderResources(); renderWhen(); renderDetailsPrep();
            goStep(2);
        }

        // ── resources pills ──────────────────────────────────────────────
        function renderResources() {
            var box = $('#bk-resources'), pills = $('#bk-res-pills');
            var list = (svc.resources || []);
            box.classList.toggle('hidden', !list.length || svc.kind === 'trip');
            if (!list.length) return;
            $('#bk-res-label').textContent = 'Choose ' + (svc.resource_noun || 'resource');
            pills.innerHTML = '';
            var mk = function (id, html) {
                var b = document.createElement('button'); b.type = 'button';
                b.className = 'bk-pill' + (resource === id ? ' on' : ''); b.innerHTML = html;
                b.onclick = function () { resource = id; renderResources(); renderWhen(); };
                return b;
            };
            pills.appendChild(mk(0, 'Any'));
            list.forEach(function (r) {
                pills.appendChild(mk(r.id, r.name + (r.price_cents ? '<small>from ' + money(r.price_cents, svc.currency) + '</small>' : '')));
            });
        }

        // ── step 2: when (per kind) ──────────────────────────────────────
        function renderWhen() {
            var box = $('#bk-when');
            box.innerHTML = '<p class="text-sm text-gray-400">Checking availability…</p>';

            if (svc.kind === 'trip') {
                api('/availability?service=' + svc.slug).then(function (r) {
                    box.innerHTML = '<p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Choose departure</p>';
                    var wrap = document.createElement('div'); wrap.className = 'flex flex-wrap gap-2';
                    (r.body.departures || []).forEach(function (d) {
                        var b = document.createElement('button'); b.type = 'button'; b.className = 'bk-pill';
                        b.innerHTML = d.origin + ' → ' + d.destination + '<small>' + d.departs_label + ' · ' + d.seats_left + ' seats · ' + money(d.price_cents, svc.currency) + '</small>';
                        b.onclick = function () { dep = d; $$('.on', wrap).forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on'); footer(); };
                        wrap.appendChild(b);
                    });
                    if (!(r.body.departures || []).length) wrap.innerHTML = '<p class="text-sm text-gray-400">No upcoming departures.</p>';
                    box.appendChild(wrap); footer();
                });
                return;
            }

            if (svc.kind === 'stay') {
                box.innerHTML =
                    '<p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Your dates</p>' +
                    '<div class="grid grid-cols-2 gap-4 max-w-md">' +
                    '<label class="block text-xs font-semibold text-gray-500">Check-in<input type="date" id="bk-in" class="bk-input"></label>' +
                    '<label class="block text-xs font-semibold text-gray-500">Check-out<input type="date" id="bk-out" class="bk-input"></label></div>' +
                    '<div id="bk-quote" class="mt-3"></div>';
                var refresh = function () {
                    var i = $('#bk-in').value, o = $('#bk-out').value;
                    if (!i || !o) return;
                    api('/availability?service=' + svc.slug + '&check_in=' + i + '&check_out=' + o + (resource ? '&resource=' + resource : ''))
                        .then(function (r) {
                            stayQuote = r.body.available ? r.body : null;
                            $('#bk-quote').innerHTML = r.body.available
                                ? '<div class="text-sm bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3"><b>' + r.body.nights + ' night(s)</b> — total <b>' + money(r.body.total_cents, svc.currency) + '</b></div>'
                                : '<p class="text-sm text-rose-600">' + (r.body.message || 'Not available for those dates.') + '</p>';
                            footer();
                        });
                };
                $('#bk-in').onchange = refresh; $('#bk-out').onchange = refresh;
                footer();
                return;
            }

            // slot
            api('/availability?service=' + svc.slug).then(function (r) {
                box.innerHTML = '<p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Pick a day</p>';
                var days = document.createElement('div'); days.className = 'flex flex-wrap gap-2';
                (r.body.openDates || []).forEach(function (d) {
                    var date = typeof d === 'string' ? d : d.date;
                    var label = typeof d === 'string'
                        ? new Date(d + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
                        : d.label;
                    var b = document.createElement('button'); b.type = 'button'; b.className = 'bk-pill'; b.textContent = label;
                    b.onclick = function () {
                        $$('.on', days).forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on'); slot = null; footer();
                        api('/availability?service=' + svc.slug + '&date=' + date + (resource ? '&resource=' + resource : '')).then(function (rr) {
                            var t = $('#bk-times');
                            t.innerHTML = '<p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2 mt-5">Pick a time</p>';
                            var times = document.createElement('div'); times.className = 'flex flex-wrap gap-2';
                            (rr.body.slots || []).forEach(function (s) {
                                var tb = document.createElement('button'); tb.type = 'button'; tb.className = 'bk-pill'; tb.textContent = s.label;
                                tb.onclick = function () { slot = s.iso; $$('.on', times).forEach(function (x) { x.classList.remove('on'); }); tb.classList.add('on'); footer(); };
                                times.appendChild(tb);
                            });
                            if (!(rr.body.slots || []).length) times.innerHTML = '<p class="text-sm text-gray-400">No free times that day.</p>';
                            t.appendChild(times);
                        });
                    };
                    days.appendChild(b);
                });
                if (!(r.body.openDates || []).length) days.innerHTML = '<p class="text-sm text-gray-400">No open days right now.</p>';
                box.appendChild(days);
                box.insertAdjacentHTML('beforeend', '<div id="bk-times"></div>');
                footer();
            });
        }

        // continue button under step 2
        function footer() {
            var old = $('#bk-foot'); if (old) old.remove();
            var ok = svc.kind === 'trip' ? !!dep : svc.kind === 'stay' ? !!stayQuote : !!slot;
            $('#bk-when').insertAdjacentHTML('afterend',
                '<div id="bk-foot" class="flex items-center justify-between mt-6">' +
                '<button type="button" id="bk-b1" class="px-4 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100">← Back</button>' +
                '<button type="button" id="bk-n1" class="px-6 py-2.5 rounded-xl text-sm font-bold ' +
                (ok ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed') + '">Continue →</button></div>');
            $('#bk-b1').onclick = function () { goStep(1); };
            $('#bk-n1').onclick = function () { if (ok) goStep(3); };
        }

        // ── step 3: details + custom fields + payment note ───────────────
        function renderDetailsPrep() {
            $('#bk-qty-wrap').classList.toggle('hidden', svc.kind !== 'trip');
            var custom = $('#bk-custom'); custom.innerHTML = '';
            (svc.form_fields || []).forEach(function (ff) {
                var star = ff.required ? ' *' : '';
                var wrap = document.createElement('label');
                wrap.className = 'block text-xs font-semibold text-gray-500' + (ff.type === 'textarea' ? ' sm:col-span-2' : '');
                if (ff.type === 'checkbox') {
                    wrap.className = 'flex items-center gap-2 text-sm font-normal text-gray-700 sm:col-span-2';
                    wrap.innerHTML = '<input type="checkbox" class="bk-ff" data-key="' + ff.key + '" data-checkbox="1"> ' + ff.label + star;
                } else if (ff.type === 'select') {
                    wrap.innerHTML = ff.label + star + '<select class="bk-input bk-ff" data-key="' + ff.key + '"><option value=""></option>' +
                        (ff.options || []).map(function (o) { return '<option>' + o + '</option>'; }).join('') + '</select>';
                } else if (ff.type === 'textarea') {
                    wrap.innerHTML = ff.label + star + '<textarea rows="2" class="bk-input bk-ff" data-key="' + ff.key + '"></textarea>';
                } else {
                    wrap.innerHTML = ff.label + star + '<input type="' + (ff.type === 'number' || ff.type === 'date' ? ff.type : 'text') + '" class="bk-input bk-ff" data-key="' + ff.key + '">';
                }
                custom.appendChild(wrap);
            });
            var note = $('#bk-paynote');
            if (svc.requires_payment) {
                note.classList.remove('hidden');
                note.innerHTML = svc.deposit_cents
                    ? '💳 A <b>deposit</b> confirms your booking — you pay it securely online and the balance is due later.'
                    : '💳 Payment is required to confirm — you\'ll be taken to secure checkout.';
                $('#bk-submit').textContent = svc.deposit_cents ? 'Pay deposit & book' : 'Pay & book';
            } else {
                note.classList.add('hidden');
                $('#bk-submit').textContent = 'Book now';
            }
        }

        $('#bk-submit').onclick = function () {
            var err = $('#bk-error'); err.classList.add('hidden');
            var payload = {
                service: svc.slug,
                name: $('#bk-name').value, email: $('#bk-email').value,
                phone: $('#bk-phone').value || null, notes: $('#bk-notes').value || null,
            };
            if (resource) payload.resource_id = resource;
            if (svc.kind === 'slot') payload.start = slot;
            if (svc.kind === 'stay') { payload.check_in = $('#bk-in').value; payload.check_out = $('#bk-out').value; }
            if (svc.kind === 'trip') { payload.departure_id = dep.id; payload.qty = parseInt($('#bk-qty').value || '1', 10); }
            var fields = {};
            $$('.bk-ff').forEach(function (i) {
                var v = i.getAttribute('data-checkbox') ? (i.checked ? 'Yes' : '') : i.value;
                if (v) fields[i.getAttribute('data-key')] = v;
            });
            if (Object.keys(fields).length) payload.fields = fields;

            var btn = this; btn.disabled = true;
            api('', { method: 'POST', body: JSON.stringify(payload) }).then(function (r) {
                btn.disabled = false;
                if (!r.ok) { err.textContent = r.body.message || 'Booking failed — please check your details.'; err.classList.remove('hidden'); return; }
                if (r.body.checkout_url) { window.location.href = r.body.checkout_url; return; }
                $('#bk-ok-msg').textContent = r.body.message || '';
                $('#bk-ok-ref').textContent = 'Reference: ' + r.body.reference + (r.body.resource ? ' · ' + r.body.resource : '');
                goStep(4);
            }).catch(function () { btn.disabled = false; err.textContent = 'Network error — please try again.'; err.classList.remove('hidden'); });
        };

        goStep(1);
    })();
    </script>
</body>
</html>
