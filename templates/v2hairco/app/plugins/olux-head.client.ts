// Olux Studio — page metadata from the CMS.
// Applies the CMS page's meta (title, description, keywords, Open Graph,
// canonical, robots) to the document head, reactively per route. Falls back
// to whatever the template's own useHead() set when the CMS has no value.
export default defineNuxtPlugin(() => {
  if (typeof window === 'undefined') return

  const route = useRoute()
  const data = useState<any>('olux-content-data')

  const pageFor = (path: string) => {
    const pages = data.value?.pages ?? (data.value?.page ? [data.value.page] : [])
    const clean = path.replace(/\/+$/, '') || '/'
    return pages.find((p: any) => ((p.url || '/').replace(/\/+$/, '') || '/') === clean) ?? null
  }

  const apply = () => {
    const page = pageFor(route.path)
    if (!page) return
    const attrs = page.attributes || {}
    const business = data.value?.site?.business_name || data.value?.site?.name || ''
    const title = attrs.title || (page.name && business ? `${page.name} — ${business}` : page.name)
    const description = page.description || attrs.description || ''

    const meta: any[] = []
    if (description) meta.push({ name: 'description', content: description })
    if (page.keywords) meta.push({ name: 'keywords', content: page.keywords })
    if (attrs.robots) meta.push({ name: 'robots', content: attrs.robots })
    const ogTitle = attrs.og_title || title
    if (ogTitle) meta.push({ property: 'og:title', content: ogTitle })
    const ogDesc = attrs.og_description || description
    if (ogDesc) meta.push({ property: 'og:description', content: ogDesc })
    if (attrs.og_image) meta.push({ property: 'og:image', content: attrs.og_image })

    const link: any[] = []
    if (attrs.canonical_url) link.push({ rel: 'canonical', href: attrs.canonical_url })

    useHead({ ...(title ? { title } : {}), meta, link })
  }

  // Re-apply on navigation and whenever fresh CMS content lands.
  watch(() => route.path, apply)
  watch(data, apply)
  window.addEventListener('olux:refresh', apply)
  apply()
})
