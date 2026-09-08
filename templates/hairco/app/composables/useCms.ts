import { ref } from 'vue'
import { createCms } from '../../lib/olux-cms.mjs'

// CMS content for the current page. Sources, freshest wins:
//   1. LIVE page.json from the CMS (via the olux-cms SDK read layer)
//   2. the static /cms.json snapshot (regenerate with `npm run cms:pull`)
//   3. each component's built-in fallback text
// Shape both sources normalise to: components.<key>.<fieldPath> (dotted for
// nested, e.g. components.bookCta.href, components.hero['cta.label']).
// Build-time snapshot (written by pull-cms.mjs alongside public/cms.json):
// CMS content is present from the FIRST render — no fallback flash. The
// runtime fetches below only refresh it (newer snapshot, then live page.json).
import snapshot from '../cms-snapshot.json'

const data = ref<any>({ components: {}, ...(snapshot as any) })
let loaded: Promise<void> | null = null

const flatten = (fields: any, prefix = ''): Record<string, any> => {
  const out: Record<string, any> = {}
  for (const [k, v] of Object.entries(fields || {})) {
    const key = prefix ? `${prefix}.${k}` : k
    if (v && typeof v === 'object' && !('src' in (v as any))) Object.assign(out, flatten(v, key))
    else out[key] = (v as any)?.src ?? v
  }
  return out
}

export function useCms() {
  const pullCms = async () => {
      const { site, apiBase, baked } = useOluxSite()
      // Snapshot first (instant, same-origin), then live page.json on top —
      // but the snapshot is THIS template's own content, so skip it when the
      // app is rendering a different site (CMS preview / instant subdomain).
      if (site === baked) {
        try {
          const snap = await fetch('/cms.json').then(r => (r.ok ? r.json() : null))
          if (snap) data.value = { components: {}, ...snap }
        } catch (_) { /* no snapshot */ }
      }
      try {
        const cms = createCms({ url: apiBase || window.location.origin, site })
        // Route path, not location.pathname — the app may live under a base
        // path (/nuxt-preview/hairco/) that must not become part of the slug.
        let path = window.location.pathname
        try { path = useRoute().path || path } catch (_) { /* outside setup */ }
        const page = await cms.page(path)
        const live: Record<string, any> = {}
        for (const rec of page.raw.componentData || []) live[rec.key] = flatten(rec.fields)
        data.value = { ...data.value, components: { ...data.value.components, ...live } }
      } catch (_) { /* CMS unreachable — snapshot/fallbacks carry the page */ }
      // Olux Studio: tenant COLLECTIONS — the snapshot only carries the
      // template's own rows, so fetch the rendering site's collections from
      // the CMS and key them the way items() expects (camelCase of the slug).
      try {
        const { site: s2, apiBase: ab2 } = useOluxSite()
        const res = await fetch(`${ab2 || window.location.origin}/api/sites/${encodeURIComponent(s2)}/collections`)
        if (res.ok) {
          const body = await res.json()
          const map: Record<string, any[]> = {}
          for (const c of body.collections || []) {
            const key = String(c.slug || c.name || '').toLowerCase().replace(/[^a-z0-9]+([a-z0-9])/g, (_m: string, ch: string) => ch.toUpperCase())
            if (key) map[key] = (c.items || []).map((i: any) => i.data || i)
          }
          if (Object.keys(map).length) {
            data.value = { ...data.value, collections: { ...(data.value.collections || {}), ...map } }
            // The header menu (desktop + mobile) reads the top-level nav key —
            // feed it the tenant's own Navigation collection.
            const navRows = map.navigation || map.nav
            if (Array.isArray(navRows) && navRows.length) data.value = { ...data.value, nav: navRows }
          }
        }
      } catch (_) { /* keep snapshot/fallback rows */ }
  }
  if (typeof window !== 'undefined' && !loaded) {
    loaded = pullCms()
    window.addEventListener('olux:refresh', () => { pullCms() })
  }

  /** CMS value for a component field (dotted paths ok), or the fallback. */
  const field = (component: string, key: string, fallback = '') => {
    const v = data.value?.components?.[component]?.[key]
    return v == null || v === '' ? fallback : v
  }

  /** CMS collection items (from the snapshot), or the fallback rows. */
  const items = <T = Record<string, any>>(collection: string, fallback: T[] = []): T[] => {
    const rows = data.value?.collections?.[collection]
    return Array.isArray(rows) && rows.length ? rows : fallback
  }

  return { cms: data, field, items }
}
