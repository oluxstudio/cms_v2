import { getCurrentInstance, ref } from 'vue'

const DEFAULT_SITE = ''

// Olux Studio pipeline stub — lean CMS data-source layer, COLLECTIONS only.
// Block field content flows through useOluxContent; this composable feeds
// data-source collections (profiles, media galleries, …) with the CMS
// site's rows. Each row keeps its item `id` + collection `_cid`, so
// engagement beacons (view/play analytics) work out of the box. Unreachable
// CMS ⇒ the authored fallback array carries the page unchanged.
//
// SITE DATA ONLY ON A SITE: when the app renders a real site (?site=, the
// injected window.__OLUX_SITE__, or a baked deploy) it shows that site's rows
// and nothing else — an empty or missing collection renders EMPTY, never the
// template's authored samples. The samples (fallback) are for the template
// preview only (gallery: /nuxt-preview/{key}/?template={key}, no site).
//
// Per-block selections: a block can show only some entries of a collection
// (Edit page → "Items in this block": how many, sort, search). The CMS sends
// them as collection.views[block-slug].ids; items() picks the calling block's
// view automatically (EventsGridBlock.vue → "events-grid"), else every row.
const data = ref<{ collections: Record<string, any[]>, views: Record<string, Record<string, string[]>> }>({ collections: {}, views: {} })
const ready = ref(false)
let loaded: Promise<void> | null = null

/** "EventsGridBlock" → "events-grid" (matches the CMS block slug). */
/** The site this render is for, and whether it is a real site (vs the template preview). */
const resolveSite = (): { site: string, isSite: boolean } => {
  const pub: any = (useRuntimeConfig() as any).public || {}
  const baked = String(pub.cmsSite || pub.oluxSite || DEFAULT_SITE)
  if (typeof window === 'undefined') return { site: baked, isSite: !!baked }
  const q = new URLSearchParams(window.location.search)
  const injected = typeof (window as any).__OLUX_SITE__ === 'string' ? String((window as any).__OLUX_SITE__) : ''
  const site = injected || q.get('site') || (q.has('template') ? '' : baked)
  return { site, isSite: !!site && !q.has('template') }
}

const blockSlug = (name?: string): string => String(name || '').replace(/Block$/, '')
  .replace(/([a-z0-9])([A-Z])/g, '$1-$2').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '').toLowerCase()

export function useCms() {
  // The block calling useCms() (setup runs synchronously, so the instance is
  // known here — items() itself usually runs later inside a computed).
  // Child components count as their block (a Typewriter inside HeroBlock reads
  // as "hero"): walk up to the nearest …Block / SiteHeader / SiteFooter.
  const callerBlock = (() => {
    let inst: any = getCurrentInstance()
    const own = blockSlug(inst?.type?.__name || inst?.type?.name)
    for (let i = 0; inst && i < 12; i++, inst = inst.parent) {
      const n = String(inst.type?.__name || inst.type?.name || '')
      if (/Block$|^SiteHeader$|^SiteFooter$/.test(n)) return blockSlug(n)
    }
    return own
  })()
  // Preview shells mount under a base path — collection rows keep raw
  // /assets/… values, so rebase them for whatever base this build runs at.
  const rebase = (rows: any[]): any[] => {
    const pub: any = (useRuntimeConfig() as any).public || {}
    const base = ((useRuntimeConfig() as any).app?.baseURL || '/').replace(/\/$/, '')
    const cms = (pub.bookingApiBase || pub.cmsApiBase || '').replace(/\/$/, '')
    // Every path gets rebased — inside lists/objects and on every line of a
    // one-path-per-line value (a gallery field), not just top-level strings.
    const one = (p: string): string => {
      if (p.startsWith('/assets/') && base) return base + p
      if (p.startsWith('/storage/') && cms) return cms + p // Assets-page media
      return p
    }
    const fix = (v: any): any => {
      if (typeof v === 'string') return v.includes('\n') ? v.split('\n').map(l => one(l.trim())).join('\n') : one(v)
      if (Array.isArray(v)) return v.map(fix)
      if (v && typeof v === 'object') return Object.fromEntries(Object.entries(v).map(([k, x]) => [k, fix(x)]))
      return v
    }
    return rows.map(r => fix(r))
  }
  const cacheKey = (site: string) => `olux-cms-collections:${site}`

  const pull = async () => {
    try {
      const pub: any = (useRuntimeConfig() as any).public || {}
      const { site } = resolveSite()
      const apiBase = pub.bookingApiBase || pub.cmsApiBase || ''
      if (!site) return
      const res = await fetch(`${apiBase || window.location.origin}/api/sites/${encodeURIComponent(site)}/collections`, { cache: 'no-store' })
      if (!res.ok) return
      const body = await res.json()
      const map: Record<string, any[]> = {}
      const views: Record<string, Record<string, string[]>> = {}
      for (const c of body.collections || []) {
        const key = String(c.slug || c.name || '').toLowerCase()
          .replace(/[^a-z0-9]+([a-z0-9])/g, (_m: string, ch: string) => ch.toUpperCase())
        if (!key) continue
        map[key] = rebase((c.items || []).map((i: any) => ({ id: i.id, _cid: c.id, ...(i.data || {}) })))
        views[key] = Object.fromEntries(Object.entries(c.views || {}).map(([b, v]: [string, any]) => [b, (v?.ids || []).map(String)]))
      }
      if (Object.keys(map).length) {
        data.value = { collections: { ...data.value.collections, ...map }, views: { ...data.value.views, ...views } }
        try {
          localStorage.setItem(cacheKey(site), JSON.stringify(map))
          localStorage.setItem(cacheKey(site) + ':views', JSON.stringify(views))
        } catch {}
      }
    } catch (_) { /* CMS unreachable — authored fallbacks carry the page */ }
  }
  if (typeof window !== 'undefined' && !loaded) {
    // Snapshot-first: last known collections hydrate synchronously so the
    // first paint is CMS data; the fetch revalidates in the background.
    try {
      // Inside the connect editor the preview must ALWAYS show the freshly
      // saved data — skip the snapshot there and wait for the live fetch.
      const editing = new URLSearchParams(window.location.search).get('olx-edit') === '1'
      const { site } = resolveSite()
      const cached = editing ? null : localStorage.getItem(cacheKey(site))
      if (cached) {
        const views = localStorage.getItem(cacheKey(site) + ':views')
        data.value = { collections: JSON.parse(cached), views: views ? JSON.parse(views) : {} }
        ready.value = true
      }
    } catch { /* no snapshot — the fetch gate below covers it */ }
    loaded = pull().finally(() => { ready.value = true })
    window.addEventListener('olux:refresh', () => pull())
  }

  const { isSite } = resolveSite()

  /**
   * CMS collection rows (id + data merged). On a real site that is ALL it
   * ever returns (empty collection → []); the authored fallback only shows
   * in the template preview.
   * When the calling block (or opts.block) has a selection saved on the CMS,
   * only those rows come back, in that order. opts.all ignores it.
   */
  const items = <T = Record<string, any>>(collection: string, fallback: T[] = [], opts: { block?: string, all?: boolean } = {}): T[] => {
    const rows = data.value.collections[collection]
    if (!Array.isArray(rows) || !rows.length) return isSite ? [] : fallback
    const ids = opts.all ? undefined : data.value.views[collection]?.[opts.block ? blockSlug(opts.block) : callerBlock]
    if (!ids) return rows as T[]
    const byId = new Map(rows.map((r: any) => [String(r.id), r]))
    return ids.map(id => byId.get(id)).filter(Boolean) as T[]
  }

  return { data, ready, items }
}
