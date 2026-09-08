// Olux Studio — click-to-edit agent for CMS-embedded renderer previews.
// Active ONLY when the shell is iframed by the CMS /connect page with
// ?olx-edit=1. Blocks (data-olx-key) outline and select; text fields
// (data-olx-field) edit IN PLACE: click → type → Enter/blur saves,
// Escape cancels — same olx-field-edit contract as connect.js.
export default defineNuxtPlugin(() => {
  if (typeof window === 'undefined') return
  const editMode = /[?&]olx-edit=1(&|$)/.test(window.location.search) && window.parent !== window
  if (!editMode) return

  const style = document.createElement('style')
  style.textContent = `
    [data-olx-key][data-olx-kind]{cursor:pointer;outline:1px dashed rgba(227,135,4,.45);outline-offset:-1px;transition:outline-color .15s}
    .olx-hot{outline:3px solid #e38704 !important;outline-offset:-3px}
    /* Positioning context for the ::after overlay — added by JS ONLY when the
       block is position:static, so fixed/sticky headers keep their position. */
    .olx-rel{position:relative}
    .olx-hot::after{content:'';position:absolute;inset:0;background:rgba(227,135,4,.14);pointer-events:none;z-index:2147483000}
    /* Editable areas highlight with a BOLD translucent fill on HOVER only. */
    [data-olx-field]:not(img){cursor:text;border-radius:4px;transition:background .15s,box-shadow .15s}
    [data-olx-field]:not(img):hover{background:rgba(227,135,4,.18);
      box-shadow:0 0 0 2px rgba(227,135,4,.65)}
    img[data-olx-field]:hover{outline:2px dashed rgba(227,135,4,.9);outline-offset:3px;cursor:pointer}
    /* EMPTY fields (e.g. a freshly added item) stay visible with a ghost hint.
       .olx-empty is toggled by the agent from the field's REAL text (the CSS
       :empty selector can't see past the injected ✕ control). */
    [data-olx-field].olx-empty{min-width:8ch;min-height:1em;
      box-shadow:inset 0 0 0 1.5px rgba(227,135,4,.45);background:rgba(227,135,4,.06)}
    [data-olx-field].olx-empty::before{content:'Click to type…';opacity:.45;font-style:italic}
    [data-olx-field][contenteditable]{outline:2px solid #6366f1 !important;outline-offset:2px;
      background:rgba(99,102,241,.08);box-shadow:none;min-width:1ch}
    [data-olx-field][contenteditable]:empty::before{content:''}
    .olx-save-btn{position:fixed;z-index:2147483647;width:26px;height:26px;border-radius:50%;
      border:0;background:#6366f1;color:#fff;font:700 13px/1 system-ui;cursor:pointer;
      box-shadow:0 2px 8px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center}
    .olx-save-btn:hover{background:#4f46e5}
    .olx-link-pop{position:fixed;z-index:2147483647;background:#fff;color:#111;border-radius:12px;
      box-shadow:0 10px 30px rgba(0,0,0,.28);padding:10px;width:280px;font:12px/1.4 system-ui,sans-serif}
    .olx-link-pop label{display:block;font-weight:700;font-size:10px;color:#666;margin:6px 0 2px;text-transform:uppercase}
    .olx-link-pop input{width:100%;padding:6px 8px;border:1px solid #ddd;border-radius:8px;font:12px system-ui;box-sizing:border-box}
    .olx-link-pop .olx-lp-row{display:flex;gap:6px;justify-content:flex-end;margin-top:8px}
    .olx-link-pop button{border:0;border-radius:8px;padding:6px 12px;font:700 12px system-ui;cursor:pointer}
    .olx-link-pop .olx-lp-save{background:#6366f1;color:#fff}
    .olx-link-pop .olx-lp-cancel{background:#eee;color:#444}
    #olx-edit-badge{position:fixed;right:10px;top:10px;z-index:2147483647;background:#111;color:#fff;
      font:600 11px/1 system-ui,sans-serif;padding:6px 10px;border-radius:999px;opacity:.85;pointer-events:none}
    .olx-add-item{display:block;margin:10px auto 16px;padding:8px 16px;border-radius:999px;
      border:1.5px dashed #e38704;color:#e38704;background:rgba(227,135,4,.08);
      font:700 12px/1 system-ui,sans-serif;cursor:pointer;position:relative;z-index:2147483001;
      opacity:0;pointer-events:none;transition:opacity .15s;
      /* Consistent placement in ANY layout: own centered row, natural size —
         never a stretched grid cell or flex item. */
      grid-column:1/-1;justify-self:center;align-self:center;flex-basis:100%;
      width:max-content;height:max-content;max-width:220px}
    /* While an inline edit is active, injected controls get out of the way. */
    .olx-editing .olx-item-x{opacity:0 !important;pointer-events:none !important}
    [data-olx-key]:hover .olx-add-item{opacity:1;pointer-events:auto}
    .olx-add-item:hover{background:rgba(227,135,4,.18)}
    .olx-add-item.olx-add-float{position:absolute;left:50%;bottom:0;transform:translate(-50%,110%);
      margin:0;flex-basis:auto;grid-column:auto;justify-self:auto;align-self:auto;background:#fff}
    .olx-item-x{margin-left:8px;width:20px;height:20px;border-radius:50%;border:1px solid #e38704;
      color:#e38704;background:#fff;font:700 11px/1 system-ui;cursor:pointer;
      opacity:0;pointer-events:none;transition:opacity .15s;vertical-align:middle}
    [data-olx-item]:hover>.olx-item-x,.olx-hot .olx-item-x{opacity:1;pointer-events:auto}
    .olx-item-x:hover{background:#e38704;color:#fff}
  `
  document.head.appendChild(style)

  // A collection living inside fixed/sticky chrome (navbars) must not gain
  // flow-affecting controls — the header would grow and cover the page.
  const inFixedChrome = (el: Element): boolean => {
    for (let n: Element | null = el; n && n !== document.body; n = n.parentElement) {
      const p = getComputedStyle(n).position
      if (p === 'fixed' || p === 'sticky') return true
    }
    return false
  }

  // The shell may live under a base path (/nuxt-preview/{key}/) — the CMS
  // stores LOGICAL hrefs (/shop), so strip/ignore the base in the editor.
  let BASE = ''
  try { BASE = ((useRuntimeConfig().app?.baseURL as string) || '/').replace(/\/$/, '') } catch { /* non-nuxt context */ }
  const logicalHref = (href: string) => BASE && href.startsWith(BASE + '/') ? href.slice(BASE.length) : href

  const post = (msg: Record<string, unknown>) =>
    window.parent.postMessage({ source: 'olx-connect', ...msg }, '*')

  // In-place refresh: after a save the CMS posts olx-refresh-content — pull
  // fresh content and let Vue re-render, instead of reloading the iframe.
  window.addEventListener('message', async (e) => {
    const d = e.data
    if (!d || d.source !== 'olx-cms' || d.type !== 'olx-refresh-content') return
    try {
      const site = new URLSearchParams(window.location.search).get('site')
      if (site) {
        const res = await fetch(`/api/sites/${encodeURIComponent(site)}/content`, { cache: 'no-store' })
        if (res.ok) useState<any>('olux-content-data').value = await res.json()
      }
    } catch { /* keep current content */ }
    // Live-app composables (useCms page.json + collections) refresh on this.
    window.dispatchEvent(new Event('olux:refresh'))
  })

  // Badge — updated once blocks exist (they mount after content loads).
  const badge = document.createElement('div')
  badge.id = 'olx-edit-badge'
  badge.textContent = 'Olux edit'
  const attach = () => { document.body ? document.body.appendChild(badge) : setTimeout(attach, 100) }
  attach()
  let tries = 0
  const count = () => {
    const n = document.querySelectorAll('[data-olx-key][data-olx-kind]').length
    if (n > 0) badge.textContent = `Olux edit · ${n} blocks · click text to edit`
    else if (tries++ < 40) setTimeout(count, 250)
  }
  count()

  // ── "Add item" buttons for repeatable lists ─────────────────────────────
  // Row-based lists live as component nodes labelled "{Prefix} {n} {Field}".
  // For every block that has such rows, append an add button at the bottom
  // of the section; clicking asks the editor to clone a new row.
  const prefixesFor = (key: string): string[] => {
    try {
      const data = useState<any>('olux-content-data').value
      for (const p of data?.pages ?? []) {
        const b = (p.wireframe ?? []).find((x: any) => typeof x.type === 'string' && x.type.endsWith(':' + key))
        if (!b) continue
        const found = new Set<string>()
        for (const n of b.nodes ?? []) {
          const m = /^(.+?) 1 (.+)$/.exec(n.label || '')
          if (m) found.add(m[1])
        }
        return [...found]
      }
    } catch { /* content not loaded yet */ }
    return []
  }
  const injectAdders = () => {
    // Marked COLLECTIONS (data-olx-kind="collection" + data-olx-item rows):
    // add via the CMS collection machinery; ✕ removes a row by position.
    document.querySelectorAll('[data-olx-kind="collection"][data-olx-key]').forEach((el) => {
      const host = el as HTMLElement
      const key = host.getAttribute('data-olx-key') || ''
      if (!host.querySelector('.olx-add-item')) {
        const btn = document.createElement('button')
        btn.className = 'olx-add-item' + (inFixedChrome(host) ? ' olx-add-float' : '')
        btn.type = 'button'
        btn.textContent = '＋ Add item'
        btn.addEventListener('click', (e) => {
          e.preventDefault()
          e.stopPropagation()
          post({ type: 'olx-item-add', id: null, key, componentKey: null, field: null })
        })
        host.appendChild(btn)
      }
      host.querySelectorAll('[data-olx-item]').forEach((row, index) => {
        if (row.querySelector(':scope > .olx-item-x')) return
        const x = document.createElement('button')
        x.className = 'olx-item-x'
        x.type = 'button'
        x.textContent = '✕'
        x.title = 'Remove this item'
        x.setAttribute('contenteditable', 'false') // never part of the editable text
        x.addEventListener('click', (e) => {
          e.preventDefault()
          e.stopPropagation()
          post({ type: 'olx-item-remove-idx', key, index })
        })
        row.appendChild(x)
      })
    })

    document.querySelectorAll('[data-olx-key][data-olx-kind="component"]').forEach((el) => {
      const host = el as HTMLElement
      if (host.querySelector('.olx-add-item')) return
      const key = host.getAttribute('data-olx-key') || ''
      for (const prefix of prefixesFor(key)) {
        const btn = document.createElement('button')
        btn.className = 'olx-add-item' + (inFixedChrome(host) ? ' olx-add-float' : '')
        btn.type = 'button'
        btn.textContent = `＋ Add ${prefix.replace(/^Fallback /, '').toLowerCase()}`
        btn.addEventListener('click', (e) => {
          e.preventDefault()
          e.stopPropagation()
          post({ type: 'olx-node-item-add', key, prefix })
        })
        host.appendChild(btn)
      }
    })
  }
  // Field text EXCLUDING injected controls (✕ / add buttons) — those must
  // never leak into saved content or empty-checks. (Same rule as connect.js.)
  const textOf = (el: HTMLElement): string => {
    const c = el.cloneNode(true) as HTMLElement
    c.querySelectorAll('.olx-item-x, .olx-add-item').forEach((n) => n.remove())
    return (c.textContent || '').trim()
  }

  // Ghost "Click to type…" hint for EMPTY fields. CSS :empty can't see past
  // the injected ✕, so toggle a class from the real (stripped) text instead.
  const markEmpties = () => {
    document.querySelectorAll('[data-olx-field]:not(img)').forEach((el) => {
      const f = el as HTMLElement
      if (f.isContentEditable) { f.classList.remove('olx-empty'); return }
      f.classList.toggle('olx-empty', textOf(f) === '')
    })
  }

  // Marker registration (same contract as connect.js reportUnknown): fields
  // that exist in the MARKUP but not on the CMS component (dynamic field()
  // bindings the extractor can't see) get created server-side, seeded from
  // the rendered fallback text — so the sidebar always matches the page and
  // every marked field persists its edits. Once per page path.
  const registered = new Set<string>()
  const registerMarkers = () => {
    const path = window.location.pathname
    if (registered.has(path)) return
    const markers: Record<string, unknown>[] = []
    document.querySelectorAll('[data-olx-key][data-olx-kind="component"]').forEach((el) => {
      const key = el.getAttribute('data-olx-key') || ''
      const fields: Record<string, string>[] = []
      el.querySelectorAll('[data-olx-field]').forEach((f) => {
        if (f.closest('[data-olx-item]') || f.closest('[data-olx-kind="collection"]')) return
        if (f.closest('[data-olx-key][data-olx-kind]') !== el) return // belongs to a nested marker
        const name = f.getAttribute('data-olx-field') || ''
        const value = f instanceof HTMLImageElement ? (f.getAttribute('src') || '') : textOf(f as HTMLElement)
        if (name && fields.length < 20) fields.push({ field: name, value })
      })
      if (key && fields.length) markers.push({ kind: 'component', key, fields })
    })
    if (markers.length) {
      registered.add(path)
      post({ type: 'olx-register', markers: markers.slice(0, 20) })
    }
  }

  setInterval(() => { injectAdders(); markEmpties(); registerMarkers() }, 1500) // idempotent; covers late mounts + route changes

  const blockOf = (el: EventTarget | null): HTMLElement | null =>
    el instanceof Element ? (el.closest('[data-olx-key][data-olx-kind]') as HTMLElement | null) : null
  const fieldOf = (el: EventTarget | null): HTMLElement | null =>
    el instanceof Element ? (el.closest('[data-olx-field]') as HTMLElement | null) : null
  // Inline editing suits plain text targets only — images and interactive
  // elements are edited via the sidebar instead (same rule as connect.js).
  const canInlineEdit = (f: HTMLElement) =>
    ['IMG', 'INPUT', 'TEXTAREA', 'SELECT'].indexOf(f.tagName) === -1

  let hot: HTMLElement | null = null
  document.addEventListener('mouseover', (e) => {
    const f = fieldOf(e.target)
    post({ type: 'olx-hover-field', field: f ? f.getAttribute('data-olx-field') : null })
    const b = blockOf(e.target)
    if (b === hot) return
    hot?.classList.remove('olx-hot', 'olx-rel')
    hot = b
    if (hot) {
      // Never override fixed/sticky/absolute positioning (a fixed navbar
      // would jump out of place and flicker) — only ground static blocks.
      if (getComputedStyle(hot).position === 'static') hot.classList.add('olx-rel')
      hot.classList.add('olx-hot')
    }
  }, true)

  const startInlineEdit = (block: HTMLElement, field: HTMLElement) => {
    if (field.isContentEditable) return
    const original = textOf(field)
    // Injected controls inside the field would swallow the caret and the
    // typed text — remove them for the edit; the interval re-adds them.
    field.querySelectorAll('.olx-item-x, .olx-add-item').forEach((n) => n.remove())
    field.classList.remove('olx-empty')
    document.documentElement.classList.add('olx-editing') // hide ✕ controls while typing
    field.setAttribute('contenteditable', 'plaintext-only')
    if (!field.isContentEditable) field.setAttribute('contenteditable', 'true')

    // Save (✓) control at the field's bottom-right — clicking it commits,
    // exactly like Enter/blur. mousedown (not click) so the field doesn't
    // blur-cancel before we can act; blur() then triggers the normal save.
    const saveBtn = document.createElement('button')
    saveBtn.type = 'button'
    saveBtn.className = 'olx-save-btn'
    saveBtn.textContent = '✓'
    saveBtn.title = 'Save'
    saveBtn.setAttribute('contenteditable', 'false')
    const placeSave = () => {
      const r = field.getBoundingClientRect()
      saveBtn.style.left = Math.max(4, r.right - 13) + 'px'
      saveBtn.style.top = Math.min(window.innerHeight - 30, r.bottom - 13) + 'px'
    }
    placeSave()
    document.body.appendChild(saveBtn)
    saveBtn.addEventListener('mousedown', (ev) => { ev.preventDefault(); field.blur() })
    field.addEventListener('input', placeSave)
    window.addEventListener('scroll', placeSave, true)
    window.addEventListener('resize', placeSave)
    const removeSave = () => {
      saveBtn.remove()
      field.removeEventListener('input', placeSave)
      window.removeEventListener('scroll', placeSave, true)
      window.removeEventListener('resize', placeSave)
    }

    field.focus()
    try { // caret at the end
      const range = document.createRange()
      range.selectNodeContents(field)
      range.collapse(false)
      const sel = window.getSelection()
      sel?.removeAllRanges()
      sel?.addRange(range)
    } catch { /* best effort */ }
    let fired = false
    const done = () => {
      if (fired) return
      fired = true
      removeSave()
      document.documentElement.classList.remove('olx-editing')
      field.removeAttribute('contenteditable')
      field.removeEventListener('blur', done)
      field.removeEventListener('keydown', onKey)
      const value = textOf(field)
      if (value === original) return
      // A field inside a collection row saves to THAT row (by position);
      // everything else saves to the component node by field key.
      const row = field.closest('[data-olx-item]')
      const colHost = row?.closest('[data-olx-kind="collection"][data-olx-key]')
      if (row && colHost) {
        const index = [...colHost.querySelectorAll('[data-olx-item]')].indexOf(row)
        post({
          type: 'olx-field-edit-idx',
          key: colHost.getAttribute('data-olx-key'),
          field: field.getAttribute('data-olx-field'),
          value,
          index,
        })
        return
      }
      post({
        type: 'olx-field-edit',
        id: null,
        key: block.getAttribute('data-olx-key'),
        kind: block.getAttribute('data-olx-kind') || 'component',
        field: field.getAttribute('data-olx-field'),
        value,
        itemId: null,
      })
    }
    const onKey = (ev: KeyboardEvent) => {
      if (ev.key === 'Enter') { ev.preventDefault(); field.blur() }
      if (ev.key === 'Escape') {
        fired = true
        removeSave()
        document.documentElement.classList.remove('olx-editing')
        field.removeAttribute('contenteditable')
        field.textContent = original
        field.blur()
        fired = false // allow future edits; nothing was posted
        field.removeEventListener('blur', done)
        field.removeEventListener('keydown', onKey)
      }
    }
    field.addEventListener('blur', done)
    field.addEventListener('keydown', onKey)
  }

  // ── Link/button editor: label + href in a small popover ────────────────
  let linkPop: HTMLElement | null = null
  const closeLinkPop = () => { linkPop?.remove(); linkPop = null }
  const openLinkEditor = (a: HTMLElement) => {
    closeLinkPop()
    // The editable label: the link itself when marked, or a marked child.
    const labelEl = (a.matches('[data-olx-field]') ? a : a.querySelector('[data-olx-field]')) as HTMLElement | null
    const labelField = labelEl?.getAttribute('data-olx-field') || null
    const block = a.closest('[data-olx-key][data-olx-kind]') as HTMLElement | null
    if (!block) return // outside any editable block → inert

    const row = a.closest('[data-olx-item]')
    const colHost = row?.closest('[data-olx-kind="collection"][data-olx-key]') as HTMLElement | null
    const index = row && colHost ? [...colHost.querySelectorAll('[data-olx-item]')].indexOf(row) : null

    const pop = document.createElement('div')
    pop.className = 'olx-link-pop'
    pop.setAttribute('contenteditable', 'false')
    pop.innerHTML = `
      <label>Label</label><input class="olx-lp-label" type="text">
      <label>Link URL</label><input class="olx-lp-href" type="text" placeholder="/page or https://…">
      <div class="olx-lp-row"><button type="button" class="olx-lp-cancel">Cancel</button>
      <button type="button" class="olx-lp-save">✓ Save</button></div>`
    const r = a.getBoundingClientRect()
    pop.style.left = Math.min(window.innerWidth - 292, Math.max(6, r.left)) + 'px'
    pop.style.top = Math.min(window.innerHeight - 150, r.bottom + 8) + 'px'
    document.body.appendChild(pop)
    linkPop = pop
    const labelIn = pop.querySelector('.olx-lp-label') as HTMLInputElement
    const hrefIn = pop.querySelector('.olx-lp-href') as HTMLInputElement
    const oldLabel = ((labelEl || a).textContent || '').trim()
    const oldHref = logicalHref(a.getAttribute('href') || '')
    labelIn.value = oldLabel
    hrefIn.value = oldHref
    labelIn.focus()
    labelIn.select()

    const save = () => {
      const label = labelIn.value.trim()
      const href = hrefIn.value.trim()
      // Optimistic: marked label element, or a plain-text anchor.
      if (label) {
        if (labelEl) labelEl.textContent = label
        else if (a.children.length === 0) a.textContent = label
      }
      if (href) a.setAttribute('href', BASE ? BASE + (href.startsWith('/') ? href : '/' + href) : href)
      post({
        type: 'olx-link-edit',
        key: (colHost || block).getAttribute('data-olx-key'),
        kind: colHost ? 'collection' : (block.getAttribute('data-olx-kind') || 'component'),
        labelField,
        label,
        href,
        index,
        oldLabel,
        oldHref,
      })
      closeLinkPop()
    }
    pop.querySelector('.olx-lp-save')!.addEventListener('click', save)
    pop.querySelector('.olx-lp-cancel')!.addEventListener('click', closeLinkPop)
    pop.addEventListener('keydown', (ev) => {
      if ((ev as KeyboardEvent).key === 'Enter') { ev.preventDefault(); save() }
      if ((ev as KeyboardEvent).key === 'Escape') closeLinkPop()
    })
    // Clicking elsewhere closes the popover.
    setTimeout(() => document.addEventListener('mousedown', function onDoc(ev) {
      if (linkPop && !(ev.target instanceof Node && linkPop.contains(ev.target))) {
        closeLinkPop()
        document.removeEventListener('mousedown', onDoc)
      }
    }), 0)
  }

  document.addEventListener('click', (e) => {
    if (!(e.target instanceof Element)) return
    if (e.target.closest('.olx-add-item, .olx-item-x')) return // our buttons handle themselves
    if (e.target.closest('.olx-link-pop')) return // link editor handles itself

    // Unmarked interactive elements (hamburger menus, accordions, tabs,
    // sliders…) keep their REAL behaviour in edit mode — that's how content
    // hidden behind them (e.g. the mobile menu) becomes reachable to edit.
    const interactive = e.target.closest('button, summary, [role="button"], input, select, textarea, label')
    if (interactive && !interactive.closest('[data-olx-field]')) return

    // Links NEVER navigate in edit mode. Clicking a link (or a button that is
    // a marked field) opens a small editor for its label + URL instead.
    const a = e.target instanceof Element ? (e.target.closest('a[href], a, button[data-olx-field]') as HTMLElement | null) : null
    if (a) {
      e.preventDefault()
      e.stopPropagation()
      openLinkEditor(a)
      return
    }

    const b = blockOf(e.target)
    if (!b) return
    const f = fieldOf(e.target)
    if (f && f.isContentEditable) return // typing inside an active edit
    e.preventDefault()
    e.stopPropagation()
    if (f && canInlineEdit(f)) {
      startInlineEdit(b, f)
      return
    }
    post({
      type: 'olx-edit-select',
      id: null,
      key: b.getAttribute('data-olx-key'),
      kind: b.getAttribute('data-olx-kind') || 'component',
    })
  }, true)
})
