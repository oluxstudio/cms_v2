// Olux Studio — click-to-edit agent for CMS-embedded renderer previews.
// Active ONLY when the shell is iframed by the CMS /connect page with
// ?olx-edit=1. Blocks (data-olx-key) outline and select; text fields
// (data-olx-field) edit IN PLACE: click → type → Enter/blur saves,
// Escape cancels — same olx-field-edit contract as connect.js.
export default defineNuxtPlugin(() => {
  if (typeof window === 'undefined') return
  const editMode = /[?&]olx-edit=1(&|$)/.test(window.location.search) && window.parent !== window
  if (!editMode) return
  ;(window as any).oluxEditActive = true // scheduled-visibility rules stand down while editing

  const style = document.createElement('style')
  style.textContent = `
    [data-olx-key][data-olx-kind]{cursor:pointer;outline:1px dashed color-mix(in srgb, var(--olx-primary,#e38704) 45%, transparent);outline-offset:-1px;transition:outline-color .15s}
    /* Author-declared data-source panels: click loads the collection editor */
    [data-olx-panel],[olx-panel]{cursor:pointer}
    [data-olx-panel]:hover,[olx-panel]:hover{outline:2px dashed color-mix(in srgb, var(--olx-primary,#14a98f) 60%, transparent);outline-offset:-2px}
    .olx-hot{outline:3px solid var(--olx-primary,#e38704) !important;outline-offset:-3px}
    /* Floating name tag on the hovered section */
    #olx-hover-tag{position:fixed;z-index:2147483646;background:var(--olx-primary,#e38704);color:#fff;
      font:700 11px/1 system-ui,sans-serif;padding:5px 10px;border-radius:0 0 10px 0;
      pointer-events:none;display:none;box-shadow:0 2px 8px rgba(0,0,0,.25);white-space:nowrap}
    #olx-hover-tag small{font-weight:500;opacity:.8;margin-left:6px}
    /* Click fired → the editor panel is being loaded for this section */
    #olx-hover-tag .olx-spin{display:inline-block;width:9px;height:9px;margin-left:6px;
      border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;
      vertical-align:-1px;animation:olx-spin .6s linear infinite}
    @keyframes olx-spin{to{transform:rotate(360deg)}}
    /* Positioning context for the ::after overlay — added by JS ONLY when the
       block is position:static, so fixed/sticky headers keep their position. */
    .olx-rel{position:relative}
    .olx-hot::after{content:'';position:absolute;inset:0;background:color-mix(in srgb, var(--olx-primary,#e38704) 14%, transparent);pointer-events:none;z-index:2147483000}
    /* Editable areas highlight with a BOLD translucent fill on HOVER only. */
    [data-olx-field]:not(img){cursor:text;border-radius:4px;transition:background .15s,box-shadow .15s}
    [data-olx-field]:not(img):hover{background:color-mix(in srgb, var(--olx-primary,#e38704) 18%, transparent);
      box-shadow:0 0 0 2px color-mix(in srgb, var(--olx-primary,#e38704) 65%, transparent)}
    img[data-olx-field]:hover{outline:2px dashed color-mix(in srgb, var(--olx-primary,#e38704) 90%, transparent);outline-offset:3px;cursor:pointer}
    /* EMPTY fields (e.g. a freshly added item) stay visible with a ghost hint.
       .olx-empty is toggled by the agent from the field's REAL text (the CSS
       :empty selector can't see past the injected ✕ control). */
    /* Empty-field ghosts must NOT take space by default — inflating them
       pushed real layout down (hero no longer met the topbar). They pop in
       only while the pointer is over their block. */
    [data-olx-key]:hover [data-olx-field].olx-empty,
    [data-olx-field].olx-empty:hover{min-width:8ch;min-height:1em;
      box-shadow:inset 0 0 0 1.5px rgba(227,135,4,.45);background:rgba(227,135,4,.06)}
    [data-olx-key]:hover [data-olx-field].olx-empty::before,
    [data-olx-field].olx-empty:hover::before{content:'Click to type…';opacity:.45;font-style:italic}
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
    /* Entries light up on hover; the one open in the panel keeps a solid ring */
    [data-olx-panel] [data-olx-item]:hover,[data-olx-key][data-olx-kind] [data-olx-item]:hover{
      outline:2px dashed rgba(99,102,241,.8);outline-offset:2px;cursor:pointer;border-radius:6px}
    [data-olx-item].olx-item-active{outline:3px solid var(--olx-primary,#6366f1) !important;outline-offset:2px;border-radius:6px}
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
  window.addEventListener('message', (e) => {
    const d = e.data
    // The CMS hands over its admin theme colour so every editor highlight
    // (hover overlay, active rings, name tag) matches the edit panel.
    if (d && d.source === 'olx-cms' && d.type === 'olx-theme' && typeof d.primary === 'string' && /^[#a-z0-9(),.\s%-]+$/i.test(d.primary)) {
      document.documentElement.style.setProperty('--olx-primary', d.primary)
    }
    if (!d || d.source !== 'olx-cms' || d.type !== 'olx-edit-opened') return
    // The panel finished loading — the hover tag's "loading editor" spinner
    // gives way to the normal hint.
    const hint = document.querySelector('#olx-hover-tag small')
    if (hint && hint.querySelector('.olx-spin')) hint.textContent = 'editing in the panel →'
  })
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
        // ALWAYS a float overlay: an in-flow button changes the block's height
        // and the whole page drifts from the original (hero left the topbar).
        btn.className = 'olx-add-item olx-add-float'
        if (getComputedStyle(host).position === 'static') host.classList.add('olx-rel')
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
        // Same rule as collections: overlays only — in-flow buttons make the
        // edit preview drift from the original layout.
        btn.className = 'olx-add-item olx-add-float'
        if (getComputedStyle(host).position === 'static') host.classList.add('olx-rel')
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

  // Name tag pinned to the hovered section's top-left corner.
  const hoverTag = document.createElement('div')
  hoverTag.id = 'olx-hover-tag'
  const attachTag = () => { document.body ? document.body.appendChild(hoverTag) : setTimeout(attachTag, 100) }
  attachTag()
  const headline = (key: string) => key.replace(/[-_]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
  const placeTag = () => {
    if (!hot) { hoverTag.style.display = 'none'; return }
    const r = hot.getBoundingClientRect()
    hoverTag.style.left = Math.max(0, r.left) + 'px'
    hoverTag.style.top = Math.max(0, r.top) + 'px'
    hoverTag.style.display = 'block'
  }
  window.addEventListener('scroll', placeTag, { passive: true })
  window.addEventListener('resize', placeTag, { passive: true })

  let hot: HTMLElement | null = null
  // A section select is CLICK-driven; while the CMS loads the panel, the
  // hover tag's hint becomes a spinner + "loading" until olx-edit-opened.
  const tagLoading = () => {
    const hint = hoverTag.querySelector('small')
    if (!hint) return
    hint.textContent = 'loading editor'
    const sp = document.createElement('span')
    sp.className = 'olx-spin'
    hint.append(sp)
  }
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
      const key = hot.getAttribute('data-olx-key') || hot.getAttribute('data-olx-panel') || 'section'
      const kind = hot.getAttribute('data-olx-kind') === 'collection' || hot.hasAttribute('data-olx-panel') ? 'click to edit list' : 'click to edit'
      hoverTag.innerHTML = ''
      hoverTag.append(headline(key))
      const hint = document.createElement('small')
      hint.textContent = kind
      hoverTag.append(hint)
    }
    placeTag()
  }, true)
  document.addEventListener('mouseleave', () => { hot?.classList.remove('olx-hot', 'olx-rel'); hot = null; placeTag() })

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
    // a marked field) opens a small editor for its label + URL instead —
    // EXCEPT inside a collection entry or data-source panel, where the click
    // selects the entry (its link is edited in the panel).
    const a = e.target instanceof Element ? (e.target.closest('a[href], a, button[data-olx-field]') as HTMLElement | null) : null
    if (a && ! a.closest('[data-olx-item], [data-olx-panel], [olx-panel]')) {
      e.preventDefault()
      e.stopPropagation()
      openLinkEditor(a)
      return
    }
    if (a) {
      e.preventDefault() // still no navigation in edit mode
      e.stopPropagation()
    }

    // Panel selection works even OUTSIDE data-olx-key blocks (dynamic
    // pages keep their authored markup) — so it runs before the block guard.
    // Ring the clicked entry — it is now the one open in the edit panel.
    const markItem = (row: Element | null) => {
      document.querySelectorAll('[data-olx-item].olx-item-active').forEach((r) => r.classList.remove('olx-item-active'))
      row?.classList.add('olx-item-active')
    }
    // Templates sort/filter their grids (newest-first, tabs…), so a row's DOM
    // position rarely matches the CMS item order — send the row's visible text
    // too and let the CMS match the entry by content, index as fallback.
    const rowText = (row: Element | null) =>
      (row?.querySelector('h1,h2,h3,h4,h5,strong,b,[class*="title"]')?.textContent || row?.textContent || '')
        .replace(/\s+/g, ' ').trim().slice(0, 200) || null
    // Author-declared panel (data-olx-panel="<collection-slug>"): the click
    // loads that section's DATA SOURCE straight into the editor — the direct
    // route when generic block selection can't surface the rows.
    const panelHost = (e.target as Element).closest('[data-olx-panel], [olx-panel]')
    const panelSlug = panelHost?.getAttribute('data-olx-panel') || panelHost?.getAttribute('olx-panel') || ''
    if (panelHost && panelSlug) {
      const pRow = (e.target as Element).closest('[data-olx-item]')
      // Rows normally live INSIDE the panel; when a row IS the panel (no
      // wrapper element available), index among all rows sharing the slug.
      const pRows = pRow && panelHost === pRow
        ? [...document.querySelectorAll(`[data-olx-panel="${panelSlug}"][data-olx-item], [olx-panel="${panelSlug}"][data-olx-item]`)]
        : [...panelHost.querySelectorAll('[data-olx-item]')]
      const pIndex = pRow ? pRows.indexOf(pRow) : null
      tagLoading()
      markItem(pRow)
      post({
        type: 'olx-edit-select',
        id: null,
        key: panelSlug,
        kind: 'collection',
        itemIndex: pIndex,
        itemText: rowText(pRow),
        field: (e.target as Element).closest('[data-olx-field]')?.getAttribute('data-olx-field') || null,
      })
      return
    }
    const b = blockOf(e.target)
    if (!b) return
    const f = fieldOf(e.target)
    if (f && f.isContentEditable) return // typing inside an active edit
    e.preventDefault()
    e.stopPropagation()
    // Clicking ANY node opens the edit panel of the section it belongs to;
    // a clicked field also lights up its matching input in the panel.
    // In-place typing stays available on DOUBLE-click.
    const itemRow = (e.target as Element).closest('[data-olx-item]')
    const itemIndex = itemRow ? [...b.querySelectorAll('[data-olx-item]')].indexOf(itemRow) : null
    tagLoading()
    markItem(itemRow)
    post({
      type: 'olx-edit-select',
      id: null,
      key: b.getAttribute('data-olx-key'),
      kind: b.getAttribute('data-olx-kind') || 'component',
      itemIndex,
      itemText: rowText(itemRow),
      field: f ? f.getAttribute('data-olx-field') : null,
    })
    }, true)

  // In-place typing: double-click a text field to edit it right on the page
  // (single click opens the section's panel instead).
  document.addEventListener('dblclick', (e) => {
    const b = blockOf(e.target)
    const f = fieldOf(e.target)
    if (!b || !f || f.isContentEditable || !canInlineEdit(f)) return
    e.preventDefault()
    e.stopPropagation()
    startInlineEdit(b, f)
  }, true)
})
