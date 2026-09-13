// Olux CMS client SDK — dependency-free ESM, runs in the browser AND Node 18+.
//
//   import { createCms } from './lib/olux-cms.mjs'
//   const cms = createCms({ url: 'http://localhost:8000', site: 'hairco', key: process.env.OLUX_API_KEY })
//
// Two layers:
//   READ   — public endpoints, no key needed. Safe in the browser.
//            page(), posts.list/get, form().schema/submit, booking.*
//   MANAGE — full CRUD. Requires a MANAGEMENT key (Bearer). Server-side only:
//            never ship a management key in browser code. Methods throw
//            "management key required" when no key was given.
//
// All methods return parsed JSON and throw Error {status, message} on non-2xx.

/**
 * @param {{url: string, site: string, key?: string}} cfg
 */
export function createCms({ url, site, key = '' }) {
  const base = url.replace(/\/$/, '')

  const call = async (path, { method = 'GET', body, auth = false } = {}) => {
    if (auth && !key) throw new Error(`management key required for ${method} ${path}`)
    const res = await fetch(`${base}${path}`, {
      method,
      headers: {
        'Accept': 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(auth ? { 'Authorization': `Bearer ${key}`, 'X-Olux-Site': site } : {}),
      },
      ...(body ? { body: JSON.stringify(body) } : {}),
    })
    const json = await res.json().catch(() => ({}))
    if (!res.ok) {
      const e = new Error(`${method} ${path} → ${res.status}: ${json.message || JSON.stringify(json).slice(0, 200)}`)
      e.status = res.status
      e.errors = json.errors
      throw e
    }
    return json
  }
  const pub = p => call(`/api/sites/${encodeURIComponent(site)}${p}`)                       // public site routes
  const mgmt = (p, o = {}) => call(`/api/sites/${encodeURIComponent(site)}${p}`, { ...o, auth: true })
  const ctx = (p, o = {}) => call(`/api/site${p}`, { ...o, auth: true })                    // token-context routes

  /** '/about' → 'about', '/' → 'index' — mirrors connect.js/page.json slugs. */
  const slugOf = path => {
    const p = String(path || '/').split('?')[0].replace(/\/+$/, '')
    return p === '' ? 'index' : p.replace(/^\/+/, '').replace(/\//g, '-').toLowerCase()
  }

  /** Manifest `fields:{…}` shorthand → flat node payload (nested via parent_index). */
  const nodesFromFields = fields => {
    const nodes = []
    const label = k => k.replace(/[-_]/g, ' ').replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/\b\w/g, c => c.toUpperCase())
    for (const [k, v] of Object.entries(fields || {})) {
      if (v !== null && typeof v === 'object' && !('value' in v)) {
        const parent = nodes.push({ label: label(k), type: 'text', value: '', order: nodes.length }) - 1
        for (const [ck, cv] of Object.entries(v)) {
          const spec = cv !== null && typeof cv === 'object' ? cv : { value: cv }
          nodes.push({ label: label(ck), type: spec.type || 'text', value: String(spec.value ?? ''), parent_index: parent, order: nodes.length })
        }
      } else {
        const spec = v !== null && typeof v === 'object' ? v : { value: v }
        nodes.push({ label: label(k), type: spec.type || 'text', value: String(spec.value ?? ''), order: nodes.length })
      }
    }
    return nodes
  }
  const componentBody = ({ fields, nodes, ...rest }) => ({ ...rest, ...(fields ? { nodes: nodesFromFields(fields) } : nodes ? { nodes } : {}) })

  return {
    // ── READ (public — browser-safe) ─────────────────────────────────────

    /** Published page.json for a path, wrapped with keyed accessors. */
    async page(path = '/') {
      const doc = await call(`/api/v1/sites/${encodeURIComponent(site)}/pages/${slugOf(path)}.json`)
      const index = list => Object.fromEntries((list || []).map(r => [String(r.key || '').toLowerCase(), r]))
      const comps = index(doc.componentData), cols = index(doc.collectionData), forms = index(doc.formData)
      const dig = (o, p) => String(p).split('.').reduce((x, k) => (x == null ? x : x[k]), o)
      return {
        raw: doc,
        component: k => comps[k.toLowerCase()] || null,
        collection: k => cols[k.toLowerCase()] || null,
        form: k => forms[k.toLowerCase()] || null,
        /** field('hero', 'cta.label', 'Book') — dotted paths supported. */
        field: (k, path2, fallback = '') => {
          const v = dig(comps[k.toLowerCase()]?.fields, path2)
          return v == null || v === '' ? fallback : v
        },
        items: k => cols[k.toLowerCase()]?.items || [],
      }
    },

    posts: {
      list: () => pub('/posts'),
      get: slug => pub(`/posts/${encodeURIComponent(slug)}`),
      // manage:
      create: p => ctx('/posts', { method: 'POST', body: p }),
      update: (slug, p) => ctx(`/posts/${encodeURIComponent(slug)}`, { method: 'PATCH', body: p }),
      remove: slug => ctx(`/posts/${encodeURIComponent(slug)}`, { method: 'DELETE' }),
    },

    /** Public form workflow: schema() to render, submit(values) to send. */
    form(name) {
      const n = encodeURIComponent(name)
      return {
        schema: () => pub(`/form/${n}`),
        submit: values => call(`/api/sites/${encodeURIComponent(site)}/form/${n}`, { method: 'POST', body: values }),
      }
    },

    booking: {
      config: () => pub('/booking/config'),
      availability: params => pub(`/booking/availability?${new URLSearchParams(params)}`),
      book: payload => call(`/api/sites/${encodeURIComponent(site)}/booking`, { method: 'POST', body: payload }),
      lookup: ref => pub(`/booking/${encodeURIComponent(ref)}`),
      // manage (bookings.manage):
      services: {
        list: () => ctx('/services'),
        create: p => ctx('/services', { method: 'POST', body: p }),
        update: (slug, p) => ctx(`/services/${encodeURIComponent(slug)}`, { method: 'PATCH', body: p }),
        remove: slug => ctx(`/services/${encodeURIComponent(slug)}`, { method: 'DELETE' }),
      },
      /** Merge availability into the site's booking settings (days, open_time, close_time, slot_minutes, lead_hours, horizon_days). */
      settings: p => ctx('/booking-settings', { method: 'PATCH', body: p }),
    },

    /** Store products. Public reads need no key; manage needs store.manage. */
    products: {
      list: () => pub('/products'),
      get: slug => pub(`/products/${encodeURIComponent(slug)}`),
      // manage (store.manage):
      listAll: () => ctx('/products/manage'),
      create: p => ctx('/products/manage', { method: 'POST', body: p }),
      update: (slug, p) => ctx(`/products/manage/${encodeURIComponent(slug)}`, { method: 'PATCH', body: p }),
      remove: slug => ctx(`/products/manage/${encodeURIComponent(slug)}`, { method: 'DELETE' }),
    },

    // ── MANAGE (management key · server-side only) ───────────────────────

    components: {
      list: () => pub('/components'),
      get: id => pub(`/components/${encodeURIComponent(id)}`),
      /** create({name, fields: {heading: 'Hi', image: {type:'image', value:'/x.jpg'}, cta: {label:'Go', href:'/y'}}, page_ids?: []}) */
      create: p => ctx('/components', { method: 'POST', body: componentBody(p) }),
      update: (id, p) => ctx(`/components/${encodeURIComponent(id)}`, { method: 'PATCH', body: componentBody(p) }),
      remove: id => ctx(`/components/${encodeURIComponent(id)}`, { method: 'DELETE' }),
    },

    collections: {
      list: () => pub('/collections'),
      get: id => pub(`/collections/${encodeURIComponent(id)}`),
      /** create({name, schema: ['title','price'], type?, is_public?}) — schema becomes the field defs */
      create: ({ schema, ...p }) => ctx('/collections', {
        method: 'POST',
        body: { type: 'grid', is_public: true, ...p, ...(schema ? { fields: schema.map(k => ({ key: k, name: k, label: k, type: 'text' })) } : {}) },
      }),
      update: (id, p) => ctx(`/collections/${encodeURIComponent(id)}`, { method: 'PATCH', body: p }),
      remove: id => ctx(`/collections/${encodeURIComponent(id)}`, { method: 'DELETE' }),
    },

    items: colId => ({
      add: (data, status = 'published') => ctx(`/collections/${colId}/items`, { method: 'POST', body: { data, status } }),
      update: (itemId, data) => ctx(`/collections/${colId}/items/${itemId}`, { method: 'PATCH', body: { data } }),
      remove: itemId => ctx(`/collections/${colId}/items/${itemId}`, { method: 'DELETE' }),
    }),

    forms: {
      list: () => pub('/forms'),
      /** fields: [{key, label, type, required?, placeholder?, options?}] */
      create: p => ctx('/forms', { method: 'POST', body: p }),
      update: (name, p) => ctx(`/forms/${encodeURIComponent(name)}`, { method: 'PATCH', body: p }),
      remove: name => ctx(`/forms/${encodeURIComponent(name)}`, { method: 'DELETE' }),
    },

    pages: {
      list: () => ctx('/pages'),
      create: p => ctx('/pages', { method: 'POST', body: { is_published: true, ...p } }),
      update: (id, p) => ctx(`/pages/${encodeURIComponent(id)}`, { method: 'PATCH', body: p }),
      remove: id => ctx(`/pages/${encodeURIComponent(id)}`, { method: 'DELETE' }),
    },

    /** Republish page.json for every live page so client sites pick changes up. */
    publish: () => ctx('/connect/publish', { method: 'POST' }),
  }
}
