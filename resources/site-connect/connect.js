/*!
 * Olux Site Connect — connect.js (dependency-free, < 36 KB, never throws).
 * Modes: hydrate (fill DOM from page.json), collect (POST snapshot to ingest),
 * edit (CMS iframe overlay: outline + click → postMessage the component).
 * Install: <script src=".../connect.js" data-site-name data-site-token defer>
 */
(function () {
  'use strict';

  var SUPPORTED_SCHEMA = 2;

  // document.currentScript is null when a framework injects the tag — fall back
  // to locating our script by its data attributes.
  var self = document.currentScript
    || document.querySelector('script[data-site-name][src*="connect"]')
    || document.querySelector('script[data-site-name]');
  if (!self) return;

  var siteName = self.getAttribute('data-site-name');
  var token = self.getAttribute('data-site-token');
  if (!siteName || !token) return;

  // The CMS origin is wherever connect.js was served from.
  var cmsOrigin;
  try {
    cmsOrigin = new URL(self.src).origin;
  } catch (e) {
    return;
  }

  function log(msg, err) {
    // Quiet by default; opt in with data-debug for local testing.
    if (self.hasAttribute('data-debug')) console.warn('[olux-connect] ' + msg, err || '');
  }

  // Edit mode: CMS iframes the site with ?olx-edit=1 → overlay + hydrate.
  var editMode = /[?&]olx-edit=1(&|$)/.test(location.search) && window.parent !== window;

  // --- boot ---------------------------------------------------------------
  ready(function () {
    if (editMode) {
      editBoot();
      return;
    }
    api('/api/v1/connect/status?path=' + encodeURIComponent(location.pathname))
      .then(function (status) {
        if (!status) return;
        if (status.mode === 'hydrate' && status.pageJsonUrl) {
          hydrate(status);
        } else if (status.mode === 'collect') {
          // Wait for the SPA to hydrate so the snapshot has real content.
          whenSettled(collect);
        }
      })
      .catch(function (e) { log('status failed', e); });
  });

  // --- edit mode (CMS preview iframe) -------------------------------------
  function pageJsonUrl() {
    var path = location.pathname.replace(/\/+$/, '');
    var slug = path === '' ? 'index' : path.replace(/^\/+/, '').replace(/\//g, '-').toLowerCase();
    return cmsOrigin + '/api/v1/sites/' + encodeURIComponent(siteName) + '/pages/' + encodeURIComponent(slug) + '.json';
  }

  function editBoot() {
    // Delegated overlay attaches immediately — no SPA timing race.
    attachEditOverlay();

    // Poll page.json and re-apply EVERY tick (idempotent) — version-gating
    // left late SPA renders and client-side route changes unhydrated.
    var poll = function () {
      fetch(pageJsonUrl() + '?t=' + (new Date()).getTime(), { credentials: 'omit' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (doc) {
          if (! doc || (doc.schemaVersion || 0) !== SUPPORTED_SCHEMA) return;
          var v = (doc.siteData || {}).version || 0;
          var matched = 0;
          try {
            applyTheme((doc.siteData || {}).theme);
            injectStyles((doc.pageData || {}).styles);
            matched = applyContents(doc);
            decorateCollections(doc);
            reportUnknown(doc);
          } catch (e) { log('apply error', e); }
          badge('Olux edit · v' + v + ' · ' + matched + ' matched');
        })
        .catch(function (e) { log('poll failed', e); });
    };
    poll();
    setInterval(poll, 1500);
    // After a mutating click, poll quickly for a few seconds so the change
    // shows up near-instantly instead of waiting for the next regular tick.
    window.__olxFastPoll = function () {
      for (var i = 1; i <= 6; i++) setTimeout(poll, i * 400);
    };
  }

  function fastPoll() {
    if (window.__olxFastPoll) window.__olxFastPoll();
  }

  // One set of document listeners; closest [data-olx-*] ancestor is the target.
  function attachEditOverlay() {
    if (window.__olxEdit) return;
    window.__olxEdit = true;
    injectEditStyles();

    var current = null;
    var curField = null;
    var unhover = function (el) {
      el.classList.remove('olx-hover');
      if (el.__olxPosFix) { el.style.position = ''; el.__olxPosFix = false; }
    };
    var setHoverField = function (f, owner) {
      if (curField === f) return;
      if (curField) curField.classList.remove('olx-field-hover');
      curField = f;
      if (f) f.classList.add('olx-field-hover');
      // Mirror the hover into the CMS inspector (highlights the node input).
      post({ type: 'olx-hover-field',
        field: f ? f.getAttribute('data-olx-field') : null,
        key: attr(owner, 'data-olx-key'), id: attr(owner, 'data-olx-id') });
    };
    document.addEventListener('mouseover', function (e) {
      var el = closestEditable(e.target);
      if (current && current !== el) unhover(current);
      if (el) {
        el.classList.add('olx-hover');
        // The kind·key chip needs a positioning context.
        if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; el.__olxPosFix = true; }
        current = el;
      }
      var f = e.target.closest ? e.target.closest('[data-olx-field]') : null;
      setHoverField(el && f && el.contains(f) ? f : null, el);
    });
    document.addEventListener('mouseleave', function () {
      if (current) { unhover(current); current = null; }
      setHoverField(null, null);
    }, true);
    document.addEventListener('click', function (e) {
      var t = e.target;
      // Injected collection controls (✕ per item, + on the collection).
      if (t.classList && (t.classList.contains('olx-item-x') || t.classList.contains('olx-item-add'))) {
        e.preventDefault();
        e.stopPropagation();
        // Instant feedback + a fast poll burst so the change lands quickly.
        // OPTIMISTIC UI: reflect the change immediately; the poll reconciles
        // with the server's truth moments later (and reverts on failure).
        if (t.classList.contains('olx-item-x')) {
          var victim = t.closest('[data-olx-item-id]');
          if (victim) {
            victim.style.transition = 'opacity .15s';
            victim.style.opacity = '.25';
            setTimeout(function () { if (victim.parentNode) victim.parentNode.removeChild(victim); }, 150);
          }
        } else {
          t.textContent = 'Adding…';
          var host = t.parentNode;
          if (host && host.__olxMould) {
            // Pending marker: fillItems keeps a ghost visible across stale
            // polls until the server's item actually shows up (or 8s pass).
            host.__olxPendingAdd = {
              count: host.querySelectorAll('[data-olx-item-id]').length,
              until: (new Date()).getTime() + 8000,
            };
            appendGhost(host);
          }
        }
        t.classList.add('olx-busy');
        t.blur();
        fastPoll();
        var owner = closestEditable(t);
        post(t.classList.contains('olx-item-x')
          ? { type: 'olx-item-remove', id: attr(owner, 'data-olx-id'), key: attr(owner, 'data-olx-key'), itemId: t.getAttribute('data-olx-for') }
          : { type: 'olx-item-add', id: attr(owner, 'data-olx-id'), key: attr(owner, 'data-olx-key'),
              componentKey: attr(t, 'data-olx-comp'), field: attr(t, 'data-olx-fpath') });
        return;
      }
      var el = closestEditable(t);
      if (! el) return;
      e.preventDefault();
      e.stopPropagation();
      post({
        type: 'olx-edit-select',
        id: attr(el, 'data-olx-id'),
        key: attr(el, 'data-olx-key'),
        kind: el.getAttribute('data-olx-kind') || 'component'
      });
      // Text fields become editable in place; blur/Enter saves via the CMS.
      var field = t.closest ? t.closest('[data-olx-field]') : null;
      if (field && el.contains(field) && canInlineEdit(field)) startInlineEdit(el, field);
      log('clicked ' + (attr(el, 'data-olx-key') || attr(el, 'data-olx-id')));
    }, true);

    log('edit overlay (delegated) ready');
  }

  function post(msg) {
    msg.source = 'olx-connect';
    // Target the CMS origin only — if any other page frames this site, edit
    // payloads must not be readable by it.
    parent.postMessage(msg, cmsOrigin);
  }

  function attr(el, name) {
    return el ? (el.getAttribute(name) || null) : null;
  }

  // Text of a field EXCLUDING injected controls (✕ / + / ghost) — those must
  // never leak into saved content or empty-checks.
  function textOf(node) {
    var c = node.cloneNode(true);
    Array.prototype.slice.call(c.querySelectorAll('.olx-item-x,.olx-item-add,.olx-ghost')).forEach(function (x) {
      if (x.parentNode) x.parentNode.removeChild(x);
    });
    return (c.textContent || '').trim();
  }

  // Inline editing suits plain text targets only — images and links (href
  // values) are edited in the CMS inspector instead.
  function canInlineEdit(field) {
    return ['IMG', 'A', 'INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].indexOf(field.tagName) === -1;
  }

  function startInlineEdit(owner, field) {
    if (field.isContentEditable) return;
    // Injected controls (✕ / +) inside the field would swallow the caret and
    // the typed text — remove them for the edit; decoration re-adds them.
    Array.prototype.slice.call(field.querySelectorAll('.olx-item-x,.olx-item-add')).forEach(function (c) {
      if (c.parentNode) c.parentNode.removeChild(c);
    });
    // A placeholder is a prompt, not content — clear it before editing starts.
    if (/\bolx-placeholder\b/.test(field.className)) {
      Array.prototype.slice.call(field.childNodes).forEach(function (n) {
        if (n.nodeType === 3) field.removeChild(n);
      });
      field.className = field.className.replace(/\s*olx-placeholder\b/, '');
    }
    field.setAttribute('contenteditable', 'plaintext-only');
    if (! field.isContentEditable) field.setAttribute('contenteditable', 'true');
    field.focus();
    // Caret at the END of the text — typing must never start inside a child.
    try {
      var range = document.createRange();
      range.selectNodeContents(field);
      range.collapse(false);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    } catch (err) { /* selection best-effort */ }
    var fired = false;
    var done = function () {
      if (fired) return;
      fired = true;
      field.removeAttribute('contenteditable');
      field.removeEventListener('blur', done);
      field.removeEventListener('keydown', onKey);
      var item = field.closest('[data-olx-item-id]');
      post({
        type: 'olx-field-edit',
        id: attr(owner, 'data-olx-id'),
        key: attr(owner, 'data-olx-key'),
        kind: owner.getAttribute('data-olx-kind') || 'component',
        field: field.getAttribute('data-olx-field'),
        value: textOf(field),
        itemId: item ? item.getAttribute('data-olx-item-id') : null
      });
    };
    var onKey = function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); field.blur(); }
      if (ev.key === 'Escape') { field.removeEventListener('blur', done); field.removeAttribute('contenteditable'); field.blur(); }
    };
    field.addEventListener('blur', done);
    field.addEventListener('keydown', onKey);
  }

  // Marker-first registration: unmatched markers (and matched components with
  // fields the record lacks) are reported to the CMS preview, which creates
  // them. Once per key per page load.
  var reported = {};

  function reportUnknown(doc) {
    var known = {};
    [['componentData', 'component'], ['postData', 'post'], ['collectionData', 'collection'], ['formData', 'form']]
      .forEach(function (p) {
        (doc[p[0]] || []).forEach(function (r) { known[p[1] + ':' + String(r.key || '').toLowerCase()] = r; });
      });
    var out = [];
    var els = document.querySelectorAll('[data-olx-key][data-olx-kind]');
    for (var i = 0; i < els.length && out.length < 20; i++) {
      var el = els[i];
      var kind = el.getAttribute('data-olx-kind') || 'component';
      var key = el.getAttribute('data-olx-key') || '';
      var id = kind + ':' + key.toLowerCase();
      if (! key || reported[id]) continue;
      var rec = known[id];
      var desc = null;
      if (! rec) desc = describe(el, kind, key);
      else if (kind === 'component' || kind === 'post') {
        var missing = fieldSpecs(el).filter(function (f) { return resolvePath(rec.fields || rec, f.field) === undefined; });
        if (missing.length) desc = { kind: kind, key: key, fields: missing };
      }
      if (desc) { reported[id] = 1; out.push(desc); }
    }
    if (out.length) post({ type: 'olx-register', markers: out });
  }

  function fieldSpecs(root) {
    var seenNames = {};
    // Skip fields inside an item template unless scanning that template; the
    // same field name on several elements yields ONE spec (no duplicate nodes).
    return fields(root).filter(function (n) {
      var item = n.closest('[data-olx-item]');
      if (item && item !== root) return false;
      var nm = n.getAttribute('data-olx-field');
      if (seenNames[nm]) return false;
      seenNames[nm] = 1;
      return true;
    })
      .map(function (n) {
        var name = n.getAttribute('data-olx-field');
        if (n.tagName === 'IMG') return { field: name, type: 'image', value: n.getAttribute('src') || '' };
        if (/href|url|link/i.test(name)) return { field: name, type: 'url', value: n.getAttribute('href') || (n.textContent || '').trim() };
        return { field: name, type: 'text', value: (n.textContent || '').trim() };
      });
  }

  function describe(el, kind, key) {
    if (kind === 'collection') {
      var tpl = el.querySelector('[data-olx-item]');
      if (! tpl) return null;
      var specs = fieldSpecs(tpl);
      var item = {};
      specs.forEach(function (s) { item[s.field] = s.value; });
      return { kind: kind, key: key, schema: specs.map(function (s) { return s.field; }), item: item };
    }
    if (kind === 'form') {
      var f = el.tagName === 'FORM' ? el : el.querySelector('form');
      if (! f) return null;
      var controls = Array.prototype.slice.call(f.querySelectorAll('input[name],textarea[name],select[name]'))
        .filter(function (c) { return (c.type || '') !== 'hidden' && (c.type || '') !== 'submit'; })
        .map(function (c) {
          var type = c.tagName === 'TEXTAREA' ? 'textarea' : c.tagName === 'SELECT' ? 'select' : (c.type || 'text');
          return { key: c.name, type: type, label: c.placeholder || c.name, required: !! c.required };
        });
      return { kind: kind, key: key, fields: controls };
    }
    return { kind: kind, key: key, fields: fieldSpecs(el) };
  }

  // Add ✕ / + controls after every apply (clones are rebuilt each poll, so
  // controls re-inject too). Applies to standalone collections AND array
  // fields (collections linked inside a component).
  function decorateCollections(doc) {
    index(doc.collectionData).forEach(function (pair) { decorateItems(pair[0], null, null); });
    index(doc.componentData).forEach(function (pair) {
      var el = pair[0], rec = pair[1];
      fields(el).forEach(function (node) {
        var path = node.getAttribute('data-olx-field');
        if (Array.isArray(resolvePath(rec.fields || rec, path))) decorateItems(node, rec.key, path);
      });
    });
  }

  function decorateItems(el, compKey, fieldPath) {
    Array.prototype.slice.call(el.querySelectorAll('[data-olx-item-id]')).forEach(function (item) {
      if (item.querySelector('.olx-item-x')) return;
      // Never inject controls into an item mid-edit — the caret would land
      // inside the button and swallow the typed text.
      if (item.isContentEditable || item.querySelector('[contenteditable]')) return;
      var x = document.createElement('button');
      x.className = 'olx-item-x';
      x.setAttribute('data-olx-for', item.getAttribute('data-olx-item-id'));
      x.textContent = '✕';
      if (getComputedStyle(item).position === 'static') item.style.position = 'relative';
      item.appendChild(x);
    });
    if (! el.querySelector('.olx-item-add')) {
      var add = document.createElement('button');
      add.className = 'olx-item-add';
      add.textContent = '+ Add item';
      // Embedded lists: the CMS resolves the linked collection from these.
      if (compKey) { add.setAttribute('data-olx-comp', compKey); add.setAttribute('data-olx-fpath', fieldPath); }
      el.appendChild(add);
    }
  }

  function closestEditable(node) {
    return node && node.closest ? node.closest('[data-olx-id],[data-olx-key]') : null;
  }

  function injectEditStyles() {
    if (document.getElementById('olx-edit-css')) return;
    var s = document.createElement('style');
    s.id = 'olx-edit-css';
    s.textContent =
      '.olx-editable{cursor:pointer;transition:outline .08s;}' +
      // Block hover: outline + translucent wash = "this content is editable".
      '.olx-hover{outline:2px solid #f97316 !important;outline-offset:-2px;cursor:pointer;' +
        'background-image:linear-gradient(rgba(249,115,22,.07),rgba(249,115,22,.07)) !important;}' +
      // Kind · key chip in the block corner (component / collection / form).
      '.olx-hover::before{content:attr(data-olx-kind) " \\00b7 " attr(data-olx-key);position:absolute;top:0;left:0;' +
        'z-index:2147483000;background:#f97316;color:#fff;font:700 10px/1 system-ui;padding:3px 7px;' +
        'border-radius:0 0 8px 0;text-transform:lowercase;pointer-events:none;}' +
      // Field (node) hover inside a block: its own indigo wash + dashed edge.
      '.olx-hover [data-olx-field].olx-field-hover{outline:2px dashed #6366f1 !important;outline-offset:1px;' +
        'background-image:linear-gradient(rgba(99,102,241,.12),rgba(99,102,241,.12)) !important;border-radius:4px;}' +
      '[data-olx-field][contenteditable]{outline:2px dashed #f97316 !important;outline-offset:2px;cursor:text;}' +
      '.olx-item-x{position:absolute;top:4px;right:4px;z-index:2147483000;width:20px;height:20px;border:0;' +
        'border-radius:50%;background:#ef4444;color:#fff;font:700 11px/20px system-ui;cursor:pointer;padding:0;}' +
      '.olx-item-add{display:block;margin:8px auto 0;border:1px dashed #f97316;border-radius:8px;background:#fff7ed;' +
        'color:#c2410c;font:600 12px system-ui;padding:6px 12px;cursor:pointer;}' +
      '.olx-busy{opacity:.5;pointer-events:none;animation:olxpulse 1s infinite;}' +
      '.olx-ghost{opacity:.45;animation:olxpulse 1s infinite;}' +
      '.olx-placeholder{opacity:.45;font-style:italic;}' +
      '@keyframes olxpulse{50%{opacity:.25;}}';
    document.head.appendChild(s);
  }

  // --- collect: serialise page + CSS + links, POST once for ingestion -----
  function collect() {
    try {
      var payload = {
        url: location.href,
        html: document.documentElement.outerHTML,
        styles: sameOriginCss(),
        meta: pageMeta(),
        links: internalLinks()
      };
      fetch(cmsOrigin + '/api/v1/connect/ingest', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + token,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'omit',
        body: JSON.stringify(payload)
      }).then(function (r) { log('ingest ' + r.status); })
        .catch(function (e) { log('ingest failed', e); });
    } catch (e) {
      log('collect error', e);
    }
  }

  function sameOriginCss() {
    var css = '';
    for (var i = 0; i < document.styleSheets.length; i++) {
      var sheet = document.styleSheets[i];
      try {
        // Accessing cssRules throws for cross-origin sheets — skip those.
        var rules = sheet.cssRules;
        for (var j = 0; j < rules.length; j++) css += rules[j].cssText + '\n';
      } catch (e) { /* cross-origin, ignore */ }
    }
    return css;
  }

  function pageMeta() {
    return {
      title: document.title,
      description: metaContent('description') || metaContent('og:description'),
      ogImage: metaContent('og:image')
    };
  }

  function metaContent(name) {
    var el = document.querySelector('meta[name="' + name + '"], meta[property="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  }

  function internalLinks() {
    var out = [], seen = {};
    var anchors = document.querySelectorAll('a[href]');
    for (var i = 0; i < anchors.length; i++) {
      var href = anchors[i].href;
      if (!href) continue;
      try {
        var u = new URL(href, location.href);
        if (u.origin === location.origin && !seen[u.href] && !u.hash) {
          seen[u.href] = 1;
          out.push(u.href.split('#')[0]);
        }
      } catch (e) { /* skip bad href */ }
    }
    return out.slice(0, 200);
  }

  // --- hydrate ------------------------------------------------------------
  function hydrate(status) {
    fetch(status.pageJsonUrl, { credentials: 'omit' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (doc) {
        if (!doc) return;
        if ((doc.schemaVersion || 0) !== SUPPORTED_SCHEMA) {
          log('schema mismatch — skipping (' + doc.schemaVersion + ')');
          return;
        }
        // Exported HTML baked at an equal/newer version needs no patch.
        var site = doc.siteData || {};
        var page = doc.pageData || {};
        var baked = parseInt(document.documentElement.getAttribute('data-olx-version') || '0', 10);
        if (baked && baked >= (site.version || 0)) {
          log('baked version up to date — no patch');
          return;
        }
        // An SPA may render after DOMContentLoaded — retry until something
        // matches (then twice more for stragglers), bounded at ~15s.
        var expected = (doc.componentData || []).length + (doc.postData || []).length
          + (doc.collectionData || []).length;
        var tries = 0, extra = 2;
        var attempt = function () {
          var matched = 0;
          try {
            applyTheme(site.theme);
            injectStyles(page.styles);
            matched = applyContents(doc);
          } catch (e) {
            log('hydrate error (DOM left intact)', e);
            return;
          }
          log('hydrate applied — matched ' + matched + '/' + expected);
          if (matched >= expected || ++tries > 30 || (matched > 0 && --extra < 0)) return;
          setTimeout(attempt, 500);
        };
        attempt();
      })
      .catch(function (e) { log('page.json fetch failed', e); });
  }

  function applyContents(doc) {
    var comps = index(doc.componentData); comps.forEach(fillComponent);
    var posts = index(doc.postData); posts.forEach(fillComponent);
    var cols = index(doc.collectionData); cols.forEach(fillCollection);
    index(doc.formData).forEach(wireForm);
    return comps.length + posts.length + cols.length;
  }

  // Edit-mode status badge: version + how many records matched the DOM.
  function badge(text) {
    var b = document.getElementById('olx-badge');
    if (! b) {
      b = document.createElement('div');
      b.id = 'olx-badge';
      b.style.cssText = 'position:fixed;top:8px;right:8px;z-index:2147483647;background:#111;color:#fff;'
        + 'font:600 11px system-ui,sans-serif;padding:4px 9px;border-radius:8px;opacity:.85;pointer-events:none';
      (document.body || document.documentElement).appendChild(b);
    }
    b.textContent = text;
  }

  // Match a record to a DOM element by data-olx-id, else by data-olx-key.
  // Keys compare case-insensitively: markup authors write camelCase
  // ("workingHours") while collection keys are lowercase slugs ("workinghours").
  function index(records) {
    var byKey = {};
    Array.prototype.slice.call(document.querySelectorAll('[data-olx-key]')).forEach(function (el) {
      var k = (el.getAttribute('data-olx-key') || '').toLowerCase();
      if (! (k in byKey)) byKey[k] = el;
    });
    var pairs = [];
    (records || []).forEach(function (rec) {
      var el = document.querySelector('[data-olx-id="' + cssEscape(rec.id) + '"]');
      if (! el && rec.key) el = byKey[String(rec.key).toLowerCase()];
      if (el) pairs.push([el, rec]);
    });
    return pairs;
  }

  // Component/post: fill [data-olx-field] descendants; dotted paths supported.
  function fillComponent(pair) {
    var el = pair[0], rec = pair[1];
    var source = rec.fields || rec;
    fields(el).forEach(function (node) {
      var value = resolvePath(source, node.getAttribute('data-olx-field'));
      // Array field = a collection linked INSIDE the component — render it
      // with the [data-olx-item] template found in the field element.
      if (Array.isArray(value)) fillItems(node, value);
      else setField(node, value);
    });
  }

  // Clone the preserved [data-olx-item] template once per item (shared by
  // standalone collections and collection-typed component fields).
  function fillItems(el, items) {
    // Don't rebuild under an in-progress inline EDIT — but a focused button
    // (e.g. the + control itself) must not block the refresh.
    var a = document.activeElement;
    if (a && el.contains(a) && (a.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName))) return;
    if (! el.__olxMould) {
      var tpl = el.querySelector('[data-olx-item]');
      if (! tpl) return; // SPA hasn't rendered the template yet — a later poll will.
      var m = tpl.cloneNode(true);
      m.removeAttribute('data-olx-item');
      el.__olxMould = m;
      el.__olxParent = tpl.parentNode;
    }
    var parent = el.__olxParent;
    Array.prototype.slice.call(el.querySelectorAll('[data-olx-item],[data-olx-item-id]')).forEach(function (n) {
      if (n.parentNode) n.parentNode.removeChild(n);
    });
    items.forEach(function (item) {
      var clone = el.__olxMould.cloneNode(true);
      clone.setAttribute('data-olx-item-id', item.id || '');
      fields(clone).forEach(function (node) {
        setField(node, resolvePath(item, node.getAttribute('data-olx-field')));
        // Edit mode: an all-empty item would be invisible — show an editable
        // placeholder instead (cleared before an inline edit begins).
        if (editMode && node.tagName !== 'IMG' && ! textOf(node)) {
          node.insertBefore(document.createTextNode('New item — click to edit'), node.firstChild);
          node.className += ' olx-placeholder';
        }
      });
      var href = item.href || item.url;
      if (href && clone.tagName === 'A') clone.setAttribute('href', String(href));
      parent.appendChild(clone);
    });
    // A pending add keeps its ghost until the server item lands (or times out).
    var pending = el.__olxPendingAdd;
    if (pending) {
      if (items.length > pending.count || (new Date()).getTime() > pending.until) {
        el.__olxPendingAdd = null;
        var addBtn = el.querySelector('.olx-item-add');
        if (addBtn) { addBtn.classList.remove('olx-busy'); addBtn.textContent = '+ Add item'; }
      } else {
        appendGhost(el);
      }
    }
  }

  function appendGhost(el) {
    if (! el.__olxMould || ! el.__olxParent) return;
    var ghost = el.__olxMould.cloneNode(true);
    ghost.setAttribute('data-olx-item-id', 'ghost');
    ghost.className += ' olx-ghost';
    el.__olxParent.appendChild(ghost);
  }

  // Standalone collection: same item-template fill, keyed by the record.
  function fillCollection(pair) {
    if (pair[1].items) fillItems(pair[0], pair[1].items);
  }

  // Form: point action at the CMS submit URL; fetch-submit + inline message.
  function wireForm(pair) {
    var el = pair[0], rec = pair[1];
    if (el.tagName !== 'FORM') el = el.querySelector('form') || el;
    if (!el || el.tagName !== 'FORM') return;
    if (rec.submitUrl) el.setAttribute('action', rec.submitUrl);

    // Applies repeat (edit-mode poll re-applies every tick) — wire once.
    if (el.__olxWired) return;
    el.__olxWired = true;
    el.addEventListener('submit', function (ev) {
      if (!rec.submitUrl) return;
      ev.preventDefault();
      var body = new FormData(el);
      fetch(rec.submitUrl, { method: 'POST', body: body, credentials: 'omit' })
        .then(function (r) {
          showMessage(el, r.ok ? (rec.successMessage || 'Thanks — we’ll be in touch.')
                               : 'Sorry, something went wrong. Please try again.');
          if (r.ok) el.reset();
        })
        .catch(function () { showMessage(el, 'Network error. Please try again.'); });
    });
  }

  // --- DOM helpers --------------------------------------------------------
  function setField(node, value) {
    if (value == null) return;
    // Don't clobber a field the user is typing in (edit-mode inline editing).
    if (node.isContentEditable || node === document.activeElement || node.contains(document.activeElement)) return;
    var tag = node.tagName;
    if (value && typeof value === 'object' && 'src' in value) {
      // Image field {src, alt}
      if (tag === 'IMG') { node.src = value.src; if (value.alt != null) node.alt = value.alt; }
      else { node.style.backgroundImage = 'url("' + value.src + '")'; }
      return;
    }
    if (tag === 'IMG') { node.src = String(value); return; }
    if (tag === 'A') {
      // URL-ish values fill the href; anything else is the link's label.
      var s = String(value);
      if (/^(https?:|mailto:|tel:|#|\/)/.test(s)) { node.setAttribute('href', s); if (!node.children.length && !node.textContent.trim()) node.textContent = s; }
      else node.textContent = s;
      return;
    }
    if (tag === 'INPUT' || tag === 'TEXTAREA') { node.value = String(value); return; }
    if (typeof value === 'object') return; // nested object with no img/text target
    node.textContent = String(value);
  }

  // The root itself may be the field (e.g. a nav link with data-olx-item +
  // data-olx-field on one element) — include it, not just descendants. Fields
  // owned by a marker NESTED inside root belong to that marker, not root.
  function fields(root) {
    var list = Array.prototype.slice.call(root.querySelectorAll('[data-olx-field]')).filter(function (n) {
      var owner = n.closest('[data-olx-key],[data-olx-id]');
      return ! owner || owner === root || ! root.contains(owner);
    });
    if (root.hasAttribute && root.hasAttribute('data-olx-field')) list.unshift(root);
    return list;
  }

  function resolvePath(obj, path) {
    if (!path) return undefined;
    return path.split('.').reduce(function (o, k) { return o == null ? o : o[k]; }, obj);
  }

  function applyTheme(theme) {
    if (!theme) return;
    var r = document.documentElement.style;
    if (theme.colors) {
      if (theme.colors.primary) r.setProperty('--olx-primary', theme.colors.primary);
      if (theme.colors.surface) r.setProperty('--olx-surface', theme.colors.surface);
      if (theme.colors.text) r.setProperty('--olx-text', theme.colors.text);
    }
    if (theme.fonts) {
      if (theme.fonts.heading) r.setProperty('--olx-font-heading', theme.fonts.heading);
      if (theme.fonts.body) r.setProperty('--olx-font-body', theme.fonts.body);
    }
    if (theme.containerMax) r.setProperty('--olx-container-max', theme.containerMax);
  }

  function injectStyles(css) {
    if (!css) return;
    var id = 'olx-page-styles';
    var style = document.getElementById(id);
    if (!style) {
      style = document.createElement('style');
      style.id = id;
      document.head.appendChild(style);
    }
    style.textContent = css;
  }

  function showMessage(form, text) {
    var box = form.querySelector('[data-olx-form-message]');
    if (!box) {
      box = document.createElement('p');
      box.setAttribute('data-olx-form-message', '');
      form.appendChild(box);
    }
    box.textContent = text;
  }

  // --- utilities ----------------------------------------------------------
  function api(path) {
    return fetch(cmsOrigin + path, {
      headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
      credentials: 'omit'
    }).then(function (r) { return r.ok ? r.json() : null; });
  }

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  // Full load + two rAFs + a short delay, so the SPA has hydrated first.
  function whenSettled(fn) {
    var go = function () {
      requestAnimationFrame(function () {
        requestAnimationFrame(function () { setTimeout(fn, 300); });
      });
    };
    if (document.readyState === 'complete') go();
    else window.addEventListener('load', go);
  }

  function cssEscape(s) {
    return String(s).replace(/["\\]/g, '\\$&');
  }
})();
