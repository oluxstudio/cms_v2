/**
 * Olux booking embed — drop-in widget for ANY external website.
 *
 *   <div id="olux-booking"></div>
 *   <script src="https://YOUR-CMS-HOST/booking-embed.js"
 *           data-site="your-site-name"
 *           data-target="#olux-booking"></script>
 *
 * It talks to the public booking API (CORS-open):
 *   GET  /api/sites/{site}/booking/config
 *   GET  /api/sites/{site}/booking/availability
 *   POST /api/sites/{site}/booking
 * Paid services redirect the visitor to Stripe Checkout (checkout_url).
 */
(function () {
  'use strict';

  var script = document.currentScript;
  var SITE = script.getAttribute('data-site');
  var ORIGIN = script.getAttribute('data-origin') || new URL(script.src).origin;
  var API = ORIGIN + '/api/sites/' + encodeURIComponent(SITE) + '/booking';
  var mount = document.querySelector(script.getAttribute('data-target') || '#olux-booking');
  if (!SITE || !mount) { console.error('[olux-booking] data-site and a mount element are required'); return; }

  // ── styles (scoped under .olx-bk) ────────────────────────────────────────
  var css = '.olx-bk{font:14px/1.5 system-ui,sans-serif;color:#111;max-width:640px}' +
    '.olx-bk *{box-sizing:border-box}' +
    '.olx-bk-card{border:1px solid #e2e2e8;border-radius:12px;padding:12px 14px;margin:0 0 8px;cursor:pointer;background:#fff}' +
    '.olx-bk-card:hover{border-color:#6366f1}.olx-bk-card.on{border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.25)}' +
    '.olx-bk-name{font-weight:700}.olx-bk-meta{color:#666;font-size:12px}' +
    '.olx-bk h4{margin:16px 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#555}' +
    '.olx-bk-pills{display:flex;flex-wrap:wrap;gap:6px}' +
    '.olx-bk-pill{border:1px solid #d6d6de;border-radius:999px;background:#fff;padding:6px 12px;font-size:13px;cursor:pointer}' +
    '.olx-bk-pill.on{background:#6366f1;color:#fff;border-color:#6366f1}' +
    '.olx-bk-pill small{display:block;font-size:10px;opacity:.75}' +
    '.olx-bk input,.olx-bk textarea,.olx-bk select{width:100%;border:1px solid #d6d6de;border-radius:8px;padding:8px 10px;font:inherit;margin:2px 0 8px}' +
    '.olx-bk-row{display:flex;gap:8px}.olx-bk-row>*{flex:1}' +
    '.olx-bk-btn{background:#6366f1;color:#fff;border:0;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;font:inherit}' +
    '.olx-bk-btn:disabled{opacity:.5;cursor:default}' +
    '.olx-bk-note{background:#f4f4fb;border-radius:8px;padding:8px 10px;font-size:12px;color:#444;margin:8px 0}' +
    '.olx-bk-err{color:#dc2626;font-size:13px;margin:8px 0}' +
    '.olx-bk-ok{background:#ecfdf5;border:1px solid #10b98155;border-radius:12px;padding:14px}';
  var style = document.createElement('style'); style.textContent = css; document.head.appendChild(style);

  // ── state ────────────────────────────────────────────────────────────────
  var services = [], svc = null, resource = 0, slot = null, dep = null;
  var el = function (h) { var d = document.createElement('div'); d.innerHTML = h; return d.firstElementChild; };
  var CUR = {gbp:["£",0],usd:["$",0],eur:["€",1],ngn:["₦",0],cad:["CA$",0],aud:["A$",0],jpy:["¥",0],chf:["CHF",0],inr:["₹",0],zar:["R",0],kes:["KSh",0],ghs:["GH₵",0],sek:["kr",1],nok:["kr",1],dkk:["kr",1],pln:["zł",1],brl:["R$",0],mxn:["MX$",0],aed:["AED",1]};
  var money = function (c, cur) { var m = CUR[(cur || 'gbp').toLowerCase()]; var a = (c / 100).toFixed(2); return m ? (m[1] ? a + ' ' + m[0] : m[0] + a) : a + ' ' + (cur || '').toUpperCase(); };
  var api = function (path, opts) {
    return fetch(API + path, Object.assign({ headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' } }, opts))
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); });
  };

  mount.className = 'olx-bk';
  mount.innerHTML = '<p class="olx-bk-meta">Loading services…</p>';

  api('/config').then(function (r) {
    services = (r.body.services || []);
    render();
  }).catch(function () { mount.innerHTML = '<p class="olx-bk-err">Could not load booking services.</p>'; });

  function render() {
    mount.innerHTML = '';
    mount.appendChild(el('<h4>Choose a service</h4>'));
    services.forEach(function (s) {
      var priceLabel = s.kind === 'stay' ? s.price + '/night' : s.price;
      var card = el('<div class="olx-bk-card' + (svc === s ? ' on' : '') + '"><span class="olx-bk-name">' +
        (s.icon || '') + ' ' + s.name + '</span><div class="olx-bk-meta">' + s.type +
        (s.duration && s.kind === 'slot' ? ' · ' + s.duration + ' min' : '') + ' · ' + priceLabel +
        (s.deposit_cents ? ' · deposit' : '') + '</div></div>');
      card.onclick = function () { svc = s; resource = 0; slot = null; dep = null; render(); loadAvailability(); };
      mount.appendChild(card);
    });
    if (!svc) return;

    // resource pills (staff / rooms) with optional "from" price
    if ((svc.resources || []).length && svc.kind !== 'trip') {
      mount.appendChild(el('<h4>Choose ' + (svc.resource_noun || 'resource') + '</h4>'));
      var pills = el('<div class="olx-bk-pills"></div>');
      var any = el('<button type="button" class="olx-bk-pill' + (resource === 0 ? ' on' : '') + '">Any</button>');
      any.onclick = function () { resource = 0; render(); loadAvailability(); };
      pills.appendChild(any);
      svc.resources.forEach(function (res) {
        var p = el('<button type="button" class="olx-bk-pill' + (resource === res.id ? ' on' : '') + '">' + res.name +
          (res.price_cents ? '<small>from ' + money(res.price_cents, svc.currency) + '</small>' : '') + '</button>');
        p.onclick = function () { resource = res.id; render(); loadAvailability(); };
        pills.appendChild(p);
      });
      mount.appendChild(pills);
    }

    mount.appendChild(el('<div id="olx-avail"></div>'));
    mount.appendChild(el('<div id="olx-form"></div>'));
    renderForm();
  }

  function loadAvailability() {
    var box = mount.querySelector('#olx-avail');
    if (!box) return;
    box.innerHTML = '<p class="olx-bk-meta">Checking availability…</p>';

    if (svc.kind === 'trip') {
      api('/availability?service=' + svc.slug).then(function (r) {
        box.innerHTML = '<h4>Choose departure</h4>';
        var pills = el('<div class="olx-bk-pills"></div>');
        (r.body.departures || []).forEach(function (d) {
          var p = el('<button type="button" class="olx-bk-pill">' + d.origin + ' → ' + d.destination +
            '<small>' + d.departs_label + ' · ' + d.seats_left + ' seats · ' + money(d.price_cents, svc.currency) + '</small></button>');
          p.onclick = function () { dep = d; pills.querySelectorAll('.on').forEach(function (x) { x.classList.remove('on'); }); p.classList.add('on'); };
          pills.appendChild(p);
        });
        box.appendChild(pills);
      });
      return;
    }

    if (svc.kind === 'stay') {
      box.innerHTML = '<h4>Dates</h4><div class="olx-bk-row"><label>Check-in<input type="date" id="olx-in"></label>' +
        '<label>Check-out<input type="date" id="olx-out"></label></div><div id="olx-quote"></div>';
      ['olx-in', 'olx-out'].forEach(function (id) {
        box.querySelector('#' + id).onchange = function () {
          var i = box.querySelector('#olx-in').value, o = box.querySelector('#olx-out').value;
          if (!i || !o) return;
          api('/availability?service=' + svc.slug + '&check_in=' + i + '&check_out=' + o + (resource ? '&resource=' + resource : ''))
            .then(function (r) {
              box.querySelector('#olx-quote').innerHTML = r.body.available
                ? '<div class="olx-bk-note">' + r.body.nights + ' night(s) — total ' + money(r.body.total_cents, svc.currency) + '</div>'
                : '<p class="olx-bk-err">' + (r.body.message || 'Not available for those dates.') + '</p>';
            });
        };
      });
      return;
    }

    // slot: open dates → times
    api('/availability?service=' + svc.slug).then(function (r) {
      box.innerHTML = '<h4>Pick a day</h4>';
      var days = el('<div class="olx-bk-pills"></div>');
      (r.body.openDates || []).forEach(function (d) {
        // API returns "Y-m-d" strings (or {date,label} objects) — accept both.
        var date = typeof d === 'string' ? d : d.date;
        var label = typeof d === 'string'
          ? new Date(d + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
          : d.label;
        var p = el('<button type="button" class="olx-bk-pill">' + label + '</button>');
        p.onclick = function () {
          days.querySelectorAll('.on').forEach(function (x) { x.classList.remove('on'); }); p.classList.add('on');
          slot = null;
          api('/availability?service=' + svc.slug + '&date=' + date + (resource ? '&resource=' + resource : '')).then(function (rr) {
            var t = box.querySelector('#olx-times'); t.innerHTML = '<h4>Pick a time</h4>';
            var times = el('<div class="olx-bk-pills"></div>');
            (rr.body.slots || []).forEach(function (s) {
              var tp = el('<button type="button" class="olx-bk-pill">' + s.label + '</button>');
              tp.onclick = function () { slot = s.iso; times.querySelectorAll('.on').forEach(function (x) { x.classList.remove('on'); }); tp.classList.add('on'); };
              times.appendChild(tp);
            });
            if (!(rr.body.slots || []).length) times.appendChild(el('<p class="olx-bk-meta">No free times that day.</p>'));
            t.appendChild(times);
          });
        };
        days.appendChild(p);
      });
      box.appendChild(days);
      box.appendChild(el('<div id="olx-times"></div>'));
    });
  }

  function renderForm() {
    var f = mount.querySelector('#olx-form');
    f.innerHTML = '<h4>Your details</h4>' +
      '<input id="olx-name" placeholder="Full name">' +
      '<div class="olx-bk-row"><input id="olx-email" type="email" placeholder="Email">' +
      '<input id="olx-phone" placeholder="Phone (optional)"></div>' +
      (svc.form_fields || []).map(function (ff) {
        var star = ff.required ? ' *' : '';
        var attrs = 'class="olx-bk-ff" data-key="' + ff.key + '"';
        switch (ff.type) {
          case 'textarea':
            return '<textarea rows="2" ' + attrs + ' placeholder="' + ff.label + star + '"></textarea>';
          case 'select':
            return '<label>' + ff.label + star + '<select ' + attrs + '><option value=""></option>' +
              (ff.options || []).map(function (o) { return '<option>' + o + '</option>'; }).join('') + '</select></label>';
          case 'checkbox':
            return '<label style="display:flex;align-items:center;gap:8px;margin:2px 0 8px"><input type="checkbox" ' + attrs + ' data-checkbox="1" style="width:auto;margin:0">' + ff.label + star + '</label>';
          case 'number':
          case 'date':
            return '<label>' + ff.label + star + '<input type="' + ff.type + '" ' + attrs + '></label>';
          default:
            return '<input type="text" ' + attrs + ' placeholder="' + ff.label + star + '">';
        }
      }).join('') +
      '<textarea id="olx-notes" rows="2" placeholder="Message (optional)"></textarea>' +
      (svc.kind === 'trip' ? '<label>Seats<input id="olx-qty" type="number" min="1" value="1"></label>' : '') +
      (svc.deposit_cents ? '<div class="olx-bk-note">A deposit confirms your booking — the balance is due later.</div>' : '') +
      '<button type="button" class="olx-bk-btn" id="olx-go">' + (svc.requires_payment ? (svc.deposit_cents ? 'Pay deposit & book' : 'Pay & book') : 'Book now') + '</button>' +
      '<div id="olx-msg"></div>';

    f.querySelector('#olx-go').onclick = function () {
      var msg = f.querySelector('#olx-msg');
      var payload = { service: svc.slug, name: f.querySelector('#olx-name').value, email: f.querySelector('#olx-email').value, phone: f.querySelector('#olx-phone').value || null, notes: f.querySelector('#olx-notes').value || null };
      var fields = {};
      f.querySelectorAll('.olx-bk-ff').forEach(function (i) {
        var v = i.getAttribute('data-checkbox') ? (i.checked ? 'Yes' : '') : i.value;
        if (v) fields[i.getAttribute('data-key')] = v;
      });
      if (Object.keys(fields).length) payload.fields = fields;
      if (resource) payload.resource_id = resource;
      if (svc.kind === 'slot') { if (!slot) return msg.innerHTML = '<p class="olx-bk-err">Pick a day and time first.</p>'; payload.start = slot; }
      if (svc.kind === 'stay') {
        payload.check_in = mount.querySelector('#olx-in') && mount.querySelector('#olx-in').value;
        payload.check_out = mount.querySelector('#olx-out') && mount.querySelector('#olx-out').value;
        if (!payload.check_in || !payload.check_out) return msg.innerHTML = '<p class="olx-bk-err">Pick your dates first.</p>';
      }
      if (svc.kind === 'trip') { if (!dep) return msg.innerHTML = '<p class="olx-bk-err">Pick a departure first.</p>'; payload.departure_id = dep.id; payload.qty = parseInt(f.querySelector('#olx-qty').value || '1', 10); }

      this.disabled = true; msg.innerHTML = '';
      api('', { method: 'POST', body: JSON.stringify(payload) }).then(function (r) {
        f.querySelector('#olx-go').disabled = false;
        if (!r.ok) { msg.innerHTML = '<p class="olx-bk-err">' + (r.body.message || 'Booking failed.') + '</p>'; return; }
        if (r.body.checkout_url) { window.location.href = r.body.checkout_url; return; }
        mount.innerHTML = '<div class="olx-bk-ok"><strong>✓ ' + (r.body.message || 'Booking received!') + '</strong>' +
          '<p class="olx-bk-meta">Reference: ' + r.body.reference + (r.body.resource ? ' · ' + r.body.resource : '') + '</p></div>';
      }).catch(function () { f.querySelector('#olx-go').disabled = false; msg.innerHTML = '<p class="olx-bk-err">Network error — try again.</p>'; });
    };
  }
})();
