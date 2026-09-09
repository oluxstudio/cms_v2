// Olux Studio — the current page's content-sources config (set in the CMS
// page's Sources tab): {products: {enabled, category, tags, limit}, posts: …}.
// Works in BOTH contexts: the CMS renderer shell (olux-content-data state)
// and the standalone live app. `loadPageSources()` AWAITS the data so blocks
// can apply the filter to their very first fetch.
import { computed, ref } from 'vue'

let fetched: Promise<any> | null = null
const fallback = ref<any>(null)

const ensure = (): Promise<any> => {
  if (!fetched) {
    const olux = useOluxSite()
    const base = (olux.apiBase || '').replace(/\/$/, '') || window.location.origin
    fetched = $fetch(`${base}/api/sites/${encodeURIComponent(olux.site)}/content`)
      .then((d: any) => { fallback.value = d; return d })
      .catch(() => null)
  }
  return fetched
}

const pageFrom = (data: any, path: string) => {
  const pages = data?.pages ?? (data?.page ? [data.page] : [])
  const clean = path.replace(/\/+$/, '') || '/'
  return pages.find((p: any) => ((p.url || '/').replace(/\/+$/, '') || '/') === clean)
    ?? (pages.length === 1 ? pages[0] : null)
}

/** Await the current page's sources config ({} when none). */
export const loadPageSources = async (): Promise<Record<string, any>> => {
  if (typeof window === 'undefined') return {}
  const route = useRoute()
  const shellState = useState<any>('olux-content-data', () => null)
  const data = shellState.value || await ensure()
  const page = pageFrom(shellState.value || data, route.path)
  return (page && typeof page.sources === 'object' && page.sources) || {}
}

/** Reactive variant for templates that render after data arrives. */
export const usePageSources = () => {
  const route = useRoute()
  const shellState = useState<any>('olux-content-data', () => null)
  if (typeof window !== 'undefined' && !shellState.value) ensure()

  return computed<Record<string, any>>(() => {
    const page = pageFrom(shellState.value || fallback.value, route.path)
    return (page && typeof page.sources === 'object' && page.sources) || {}
  })
}
