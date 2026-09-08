import { ref, computed } from 'vue'
import { createCms } from '../../lib/olux-cms.mjs'

// Per-page <head> from CMS page metadata (page.json → pageData: name +
// meta.description / meta.keywords / meta.ogImage). Call once per page with a
// fallback title — the fallback shows until (or unless) the CMS answers, so
// pages still have sensible titles offline. The brand suffix comes from the
// CMS "Site Logo" component when present.
export function useCmsHead(fallbackTitle: string) {
  const title = ref(fallbackTitle)
  const description = ref('')
  const keywords = ref('')
  const ogImage = ref('')

  useHead({
    title,
    meta: computed(() => [
      ...(description.value ? [
        { name: 'description', content: description.value },
        { property: 'og:description', content: description.value },
      ] : []),
      ...(keywords.value ? [{ name: 'keywords', content: keywords.value }] : []),
      ...(ogImage.value ? [{ property: 'og:image', content: ogImage.value }] : []),
      { property: 'og:title', content: title.value },
    ]),
  })

  if (typeof window !== 'undefined') {
    const route = useRoute()
    const { site, apiBase } = useOluxSite()
    const { field } = useCms()
    createCms({ url: apiBase || window.location.origin, site })
      .page(route.path)
      .then((page: any) => {
        const pd = page?.raw?.pageData || {}
        const meta = pd.meta || {}
        if (pd.name) {
          const brand = `${field('siteLogo', 'text', '')}${field('siteLogo', 'accent', '')}`.trim()
          title.value = brand ? `${pd.name} — ${brand}` : pd.name
        }
        if (meta.description) description.value = meta.description
        if (meta.keywords) keywords.value = meta.keywords
        if (meta.ogImage) ogImage.value = meta.ogImage
      })
      .catch(() => { /* CMS unreachable — fallback title stands */ })
  }

  return { title, description, keywords, ogImage }
}
