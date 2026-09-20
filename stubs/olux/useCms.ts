import { ref } from 'vue'

const DEFAULT_SITE = ''

// Olux Studio pipeline stub — lean CMS data-source layer, COLLECTIONS only.
// Block field content flows through useOluxContent; this composable feeds
// data-source collections (profiles, media galleries, …) with the CMS
// site's rows. Each row keeps its item `id` + collection `_cid`, so
// engagement beacons (view/play analytics) work out of the box. Unreachable
// CMS ⇒ the authored fallback array carries the page unchanged.
const data = ref<{ collections: Record<string, any[]> }>({ collections: {} })
const ready = ref(false)
let loaded: Promise<void> | null = null

export function useCms() {
  // Preview shells mount under a base path — collection rows keep raw
  // /assets/… values, so rebase them for whatever base this build runs at.
  const rebase = (rows: any[]): any[] => {
    const pub: any = (useRuntimeConfig() as any).public || {}
    const base = ((useRuntimeConfig() as any).app?.baseURL || '/').replace(/\/$/, '')
    const cms = (pub.bookingApiBase || pub.cmsApiBase || '').replace(/\/$/, '')
    return rows.map(r => Object.fromEntries(Object.entries(r).map(([k, v]) => {
      if (typeof v !== 'string') return [k, v]
      if (v.startsWith('/assets/') && base) return [k, base + v]
      if (v.startsWith('/storage/') && cms) return [k, cms + v] // Assets-page media
      return [k, v]
    })))
  }
  const cacheKey = (site: string) => `olux-cms-collections:${site}`

  const pull = async () => {
    try {
      const pub: any = (useRuntimeConfig() as any).public || {}
      const site = pub.cmsSite || pub.oluxSite || ''
      const apiBase = pub.bookingApiBase || pub.cmsApiBase || ''
      if (!site) return
      const res = await fetch(`${apiBase || window.location.origin}/api/sites/${encodeURIComponent(site)}/collections`, { cache: 'no-store' })
      if (!res.ok) return
      const body = await res.json()
      const map: Record<string, any[]> = {}
      for (const c of body.collections || []) {
        const key = String(c.slug || c.name || '').toLowerCase()
          .replace(/[^a-z0-9]+([a-z0-9])/g, (_m: string, ch: string) => ch.toUpperCase())
        if (key) map[key] = rebase((c.items || []).map((i: any) => ({ id: i.id, _cid: c.id, ...(i.data || {}) })))
      }
      if (Object.keys(map).length) {
        data.value = { collections: { ...data.value.collections, ...map } }
        try { localStorage.setItem(cacheKey(site), JSON.stringify(map)) } catch {}
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
      const pub: any = (useRuntimeConfig() as any).public || {}
      const cached = editing ? null : localStorage.getItem(cacheKey(pub.cmsSite || pub.oluxSite || DEFAULT_SITE))
      if (cached) {
        data.value = { collections: JSON.parse(cached) }
        ready.value = true
      }
    } catch { /* no snapshot — the fetch gate below covers it */ }
    loaded = pull().finally(() => { ready.value = true })
    window.addEventListener('olux:refresh', () => pull())
  }

  /** CMS collection rows (id + data merged), or the authored fallback. */
  const items = <T = Record<string, any>>(collection: string, fallback: T[] = []): T[] => {
    const rows = data.value.collections[collection]
    return Array.isArray(rows) && rows.length ? (rows as T[]) : fallback
  }

  return { data, ready, items }
}
