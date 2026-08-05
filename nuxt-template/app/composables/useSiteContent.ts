import { ref } from 'vue'

export interface NodeT { id?: number; parent?: number; label: string; type: string; value: string; order?: number }
export interface ComponentT { name: string; type?: string; variant?: string; shape?: string; component?: string | null; order?: number; settings?: Record<string, any> | null; nodes: NodeT[]; children?: ComponentT[] }
export interface PageT { name: string; url: string; keywords?: string; description?: string; attributes?: Record<string, string>; wireframe?: ComponentT[]; components: ComponentT[] }
export interface SiteT { name: string; domain?: string; description?: string; theme?: Record<string, string> }
export interface ContentT { site: SiteT; pages: PageT[] }

const content = ref<ContentT | null>(null)
const error = ref<string | null>(null)
const loaded = ref(false)

/** Site name to query in API mode — taken from the ?site= query string. */
function siteParam(): string {
  if (typeof window === 'undefined') return ''
  return new URLSearchParams(window.location.search).get('site') || ''
}

/** Template ref to preview (?template=) — renders a template before it is installed. */
function templateParam(): string {
  if (typeof window === 'undefined') return ''
  return new URLSearchParams(window.location.search).get('template') || ''
}

/**
 * Load site content. In a generated build a baked ./content.json sits next to
 * the app; in preview (api mode, or when no content.json exists) we fall back
 * to the live CMS API using ?site=. Mirrors the old single-file loadContent().
 */
async function load(): Promise<void> {
  if (loaded.value) return
  const cfg = useRuntimeConfig()
  const mode = cfg.public.dataMode

  // 1. Try the baked content.json (static/generated build)
  if (mode !== 'api') {
    try {
      const baseURL = cfg.app?.baseURL || '/'
      const data = await $fetch<ContentT>(`${baseURL}content.json`.replace(/\/+/g, '/'))
      if (data?.site) { content.value = data; loaded.value = true; return }
    } catch (_) { /* fall through to API */ }
  }

  const api = (cfg.public.apiBase || '/').replace(/\/$/, '')

  // 2. Template preview by ?template= (renders a template before install)
  const tpl = templateParam()
  if (tpl) {
    try {
      const data = await $fetch<ContentT>(`${api}/api/templates/preview/${encodeURIComponent(tpl)}`)
      content.value = data
    } catch (e) {
      error.value = 'Failed to load template preview.'
    }
    loaded.value = true
    return
  }

  // 3. Live API by ?site=
  const site = siteParam()
  if (!site) { error.value = 'No site specified. Add ?site=your-site-name'; loaded.value = true; return }
  try {
    const data = await $fetch<ContentT>(`${api}/api/sites/${encodeURIComponent(site)}/content`)
    content.value = data
  } catch (e) {
    error.value = 'Failed to load site content.'
  }
  loaded.value = true
}

export function useSiteContent() {
  return { content, error, loaded, load, siteParam, templateParam }
}
