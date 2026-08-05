// Injected by Olux Studio when this template was published to the marketplace.
// Block components call useOluxContent(blockKey) and fall back to their original
// hardcoded values — with no CMS content present the app renders exactly as the
// author shipped it. When content.json (baked export) or the CMS API (preview)
// is available, edited text/images/items and hide/show flags take over.
type NodeT = { label: string; type: string; value: string }
type SettingsT = {
  hidden?: boolean
  bg?: string
  color?: string
  align?: string
  padding?: string
  css?: string
  anim_in?: string
  anim_out?: string
  hover?: string
} | null
type BlockT = { type: string; nodes?: NodeT[]; settings?: SettingsT }
type Row = Record<string, string>

let fetchStarted = false

async function loadContent(): Promise<any | null> {
  try {
    const cfg = useRuntimeConfig()
    const base = (cfg.app?.baseURL || '/').replace(/\/+$/, '/')
    // CMS previews pass ?site= — fetch live content from the API. Baked exports
    // (no query param) read the content.json shipped alongside the build.
    const site = new URLSearchParams(window.location.search).get('site')
    const url = site
      ? `/api/sites/${encodeURIComponent(site)}/content`
      : `${base}content.json?v=${Date.now()}` // bust any HTTP cache on baked exports
    const res = await fetch(url, { cache: 'no-store' })
    if (!res.ok) return null
    return await res.json()
  } catch {
    return null
  }
}

/**
 * Shared content states + one-time fetch trigger. Block composables and the
 * olux-design plugin both read the same reactive payload.
 */
export const ensureOluxContent = () => {
  const data = useState<any | null>('olux-content-data', () => null)
  const loaded = useState<boolean>('olux-content-loaded', () => false)

  if (import.meta.client && !fetchStarted) {
    fetchStarted = true
    loadContent().then((d) => {
      data.value = d
      loaded.value = true
    })
  }

  return { data, loaded }
}

export const useOluxContent = (blockKey: string) => {
  const { data, loaded } = ensureOluxContent()

  const route = useRoute()

  const findBlock = (): BlockT | null => {
    const pages = data.value?.pages ?? (data.value?.page ? [data.value.page] : [])
    if (!pages.length) return null
    const path = route.path.replace(/\/+$/, '') || '/'
    // STRICT page match — same rule as useOluxPageOrder, so a block's content
    // and its presence/order always agree about which page is showing. The
    // single-page fallback covers baked one-page exports served at any path.
    const page = pages.find((p: any) => ((p.url || '/').replace(/\/+$/, '') || '/') === path)
      ?? (pages.length === 1 ? pages[0] : null)
    const wf: BlockT[] = page?.wireframe ?? []
    return wf.find((b) => typeof b.type === 'string' && b.type.endsWith(`:${blockKey}`)) ?? null
  }

  /**
   * Rebase root-absolute asset paths onto the app's baseURL (preview sub-paths).
   * Written to survive the preview build's literal '/assets/ → {base}/assets/
   * rewrite: the regex form isn't matched by that replace, and already-prefixed
   * values (rewritten fallbacks) pass through untouched instead of doubling.
   */
  const rebase = (v: string): string => {
    const base = (useRuntimeConfig().app?.baseURL || '/').replace(/\/+$/, '')
    if (base && v.startsWith(base + '/')) return v
    if (!/^\/assets\//.test(v)) return v
    return base ? base + v : v
  }

  /** Editable text/image/url value by node label, else the shipped fallback. */
  const t = (label: string, fallback: string): string => {
    const n = findBlock()?.nodes?.find((x) => x.label === label)
    const v = n && String(n.value ?? '').trim() !== '' ? String(n.value) : fallback
    return rebase(v)
  }

  /**
   * Repeatable items reconstructed from "{prefix} {n} {Field}" nodes, mapped
   * back onto the component's original row shape. `strip` removes baked path
   * prefixes (e.g. image folders re-added by the template's own interpolation).
   * Returns a computed so v-for re-renders once content loads.
   */
  const items = (prefix: string, fields: Record<string, string>, fallback: Row[], strip: Record<string, string> = {}) =>
    computed<Row[]>(() => {
      const block = findBlock()
      if (!block?.nodes?.length) return fallback
      const rows: Row[] = []
      for (let i = 1; i <= 500; i++) {
        const row: Row = {}
        let found = false
        for (const [label, key] of Object.entries(fields)) {
          const n = block.nodes.find((x) => x.label === `${prefix} ${i} ${label}`)
          if (n) {
            found = true
            let v = String(n.value ?? '')
            const p = strip[key]
            if (p) {
              // The build step may rebase the baked prefix (/assets/… →
              // /nuxt-preview/{key}/assets/…) while CMS values stay raw —
              // strip whichever variant the value actually carries.
              const raw = p.replace(/^.*?\/assets\//, '/assets/')
              if (v.startsWith(p)) v = v.slice(p.length)
              else if (v.startsWith(raw)) v = v.slice(raw.length)
            }
            row[key] = v
          }
        }
        if (!found) break
        rows.push(row)
      }
      return rows.length ? rows : fallback
    })

  /** Reactive t() for values consumed in <script setup> (e.g. shared consts). */
  const tRef = (label: string, fallback: string) => computed<string>(() => t(label, fallback))

  /**
   * Plain string list reconstructed from "{prefix} {n}" nodes (string-array
   * data like FAQ questions). Returns a computed so v-for re-renders.
   */
  const list = (prefix: string, fallback: string[]) =>
    computed<string[]>(() => {
      const block = findBlock()
      if (!block?.nodes?.length) return fallback
      const out: string[] = []
      for (let i = 1; i <= 500; i++) {
        const n = block.nodes.find((x) => x.label === `${prefix} ${i}`)
        if (!n) break
        out.push(String(n.value ?? ''))
      }
      return out.length ? out : fallback
    })

  /**
   * Hidden when the CMS says so — or when content loaded and this block was
   * deleted from the page. With no CMS content at all, always render.
   */
  const hidden = (): boolean => {
    if (!loaded.value || !data.value) return false
    const block = findBlock()
    if (!block) return true
    return block.settings?.hidden === true
  }

  /**
   * Inline style for the block's root element from the CMS Style settings
   * (background, text colour, alignment, padding, raw custom CSS). Empty when
   * nothing was configured — the template's own styling stays untouched.
   */
  const rootStyle = computed<string>(() => {
    const s = findBlock()?.settings
    if (!s) return ''
    const padMap: Record<string, string> = { none: '0', sm: '28px 0', md: '64px 0', lg: '104px 0' }
    const parts: string[] = []
    if (s.bg) parts.push(`background:${s.bg}`)
    if (s.color) parts.push(`color:${s.color}`)
    if (s.align && s.align !== 'left') parts.push(`text-align:${s.align}`)
    if (s.padding && padMap[s.padding]) parts.push(`padding:${padMap[s.padding]}`)
    if (s.css) parts.push(String(s.css).replace(/;\s*$/, ''))
    return parts.join(';')
  })

  /**
   * Effect classes for the block's root element from the CMS Motion settings.
   * The olux-design plugin ships matching CSS + an IntersectionObserver that
   * toggles `.olux-inview` for scroll-driven enter/leave animations.
   */
  const rootClass = computed<string[]>(() => {
    const s = findBlock()?.settings
    if (!s) return []
    const out: string[] = []
    if (s.anim_in && s.anim_in !== 'none') out.push(`olux-anim-${s.anim_in}`)
    if (s.anim_out && s.anim_out !== 'none') out.push(`olux-anim-out-${s.anim_out}`)
    if (s.hover && s.hover !== 'none') out.push(`olux-hover-${s.hover}`)
    return out
  })

  return { t, tRef, items, list, hidden, rootStyle, rootClass }
}

/**
 * Ordered block components for a page, driven by the CMS wireframe: the page
 * renders `<component :is>` in exactly the order (and multiplicity) configured
 * in the Build canvas. Falls back to the template's original order when no CMS
 * content is available, so a pristine app renders identically.
 *
 * `blocks` maps blockKey → imported component, in the template's original order.
 * `pageUrl` may be a getter (catch-all pages are reused across navigations, so
 * the URL must be read reactively inside the computed).
 */
export const useOluxPageOrder = (pageUrl: string | (() => string), blocks: Record<string, any>) => {
  const { data, loaded } = ensureOluxContent()

  return computed<{ key: string; comp: any }[]>(() => {
    const fallback = Object.entries(blocks).map(([key, comp]) => ({ key, comp }))
    const d = data.value
    if (!loaded.value || !d) return fallback
    const pages = d.pages ?? (d.page ? [d.page] : [])
    const raw = typeof pageUrl === 'function' ? pageUrl() : pageUrl
    const url = raw.replace(/\/+$/, '') || '/'
    const page = pages.find((p: any) => ((p.url || '/').replace(/\/+$/, '') || '/') === url)
    // Unknown page → original composition; a MATCHED page renders exactly its
    // own wireframe, even when empty (a fresh page must not inherit Home).
    if (!page) return fallback
    const wf: any[] = page.wireframe ?? []
    const out: { key: string; comp: any }[] = []
    for (const b of wf) {
      const t = typeof b.type === 'string' ? b.type : ''
      const key = t.startsWith('app:') ? t.split(':')[2] : null
      if (key && blocks[key]) out.push({ key, comp: blocks[key] })
    }
    return out
  })
}
