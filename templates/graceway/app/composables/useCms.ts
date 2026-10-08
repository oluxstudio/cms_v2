import { ref } from 'vue'


// Graceway's lean CMS layer: COLLECTIIONS only. Block field content flows
// through the pipeline's useOluxContent stub; this composable feeds the
// data-source collections (Media, Songs, …) with the CMS site's rows —
// each row keeps its item id + collection id so engagement beacons work.
const data = ref<{ collections: Record<string, any[]> }>({ collections: {} })
// per collection: each block's own selection ("Items in this block") — view key → item ids
const views = ref<Record<string, Record<string, string[]>>>({})
const ready = ref(false)
let loaded: Promise<void> | null = null
// the cached collections the first paint used (null: none) — see pull()
let snapshot: string | null = null

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
      const { site, apiBase } = useOluxSite()
      if (!site) return // a shared preview without ?site= — the template's samples only
      const res = await fetch(`${apiBase || window.location.origin}/api/sites/${encodeURIComponent(site)}/collections`, { cache: 'no-store' })
      if (!res.ok) return
      const body = await res.json()
      const map: Record<string, any[]> = {}
      const viewMap: Record<string, Record<string, string[]>> = {}
      for (const c of body.collections || []) {
        const key = String(c.slug || c.name || '').toLowerCase()
          .replace(/[^a-z0-9]+([a-z0-9])/g, (_m: string, ch: string) => ch.toUpperCase())
        if (key) {
          map[key] = rebase((c.items || []).map((i: any) => ({ id: i.id, _cid: c.id, ...(i.data || {}) })))
          viewMap[key] = Object.fromEntries(Object.entries(c.views || {}).map(([k, v]: [string, any]) => [k, (v?.ids || []).map(String)]))
        }
      }
      views.value = viewMap
      if (Object.keys(map).length) {
        const fresh = JSON.stringify(map)
        data.value = { collections: { ...data.value.collections, ...map } }
        try { localStorage.setItem(cacheKey(site), fresh) } catch {}
        // The first paint used an older snapshot: pages read collection rows at
        // setup, so remount the page (app.vue) when the fresh rows differ.
        if (snapshot !== null && snapshot !== fresh) {
          snapshot = fresh
          window.dispatchEvent(new Event('olux:collections-updated'))
        }
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
      // The snapshot of the site being SHOWN (?site=… in a CMS preview), never the
      // baked default — else one site's preview first paints another's rows.
      const shown = useOluxSite().site
      const cached = editing || !shown ? null : localStorage.getItem(cacheKey(shown))
      if (cached) {
        data.value = { collections: JSON.parse(cached) }
        snapshot = cached
        ready.value = true
      }
    } catch { /* no snapshot — the fetch gate below covers it */ }
    loaded = pull().finally(() => { ready.value = true })
    // After a CMS save: re-read the collections, then tell the app so the
    // page re-renders in place (app.vue) — no iframe reload, scroll kept.
    window.addEventListener('olux:refresh', async () => {
      await pull()
      window.dispatchEvent(new Event('olux:collections-updated'))
    })
  }

  /** CMS collection rows (id + data merged); the authored fallback only in a template preview. */
  // false only in a template gallery preview (?template=KEY) — see useOluxSite()
  const isSite = useOluxSite().isSite
  const items = <T = Record<string, any>>(collection: string, fallback: T[] = []): T[] => {
    const rows = data.value.collections[collection]
    if (Array.isArray(rows) && rows.length) return rows as T[]
    // A real site shows only its own entries; samples are for the template preview.
    return isSite ? [] : fallback
  }

  /**
   * The entries a block chose to show from a collection (Edit site › "Items in
   * this block"), in its order — by the block's id ("#…", one copy per page)
   * or its slug. null when the block has no selection (show its default).
   */
  const viewIds = (collection: string, block: { id?: string, slug?: string }): string[] | null => {
    const v = views.value[collection] || {}
    return (block.id && v['#' + block.id]) || (block.slug && v[block.slug]) || null
  }

  return { data, ready, items, isSite, viewIds }
}
