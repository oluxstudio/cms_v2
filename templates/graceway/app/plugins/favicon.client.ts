// Use the CMS site's uploaded favicon (siteData.icon in the published
// page.json) as the browser favicon; the static /favicon.png stays as the
// fallback when the CMS is unreachable or the site has no icon asset.
//
// The icon is registered through useHead — mutating the <link> tag directly
// doesn't stick, because Nuxt's head manager re-renders it (back to the
// static icon) on the next head update (route change, useHead title, …).
export default defineNuxtPlugin(() => {
  const { site, apiBase } = useOluxSite()

  const icon = ref('')
  useHead({
    link: computed(() => icon.value
      ? [
          // key'd entries replace the static icons for as long as an icon is set
          { key: 'favicon', rel: 'icon', href: icon.value },
          { key: 'apple-touch-icon', rel: 'apple-touch-icon', href: icon.value },
        ]
      : []),
  })

  const apply = (href?: string | null) => {
    if (!href) return
    // relative CMS asset paths (/storage/…) live on the CMS origin
    icon.value = href.startsWith('/') ? `${apiBase || window.location.origin}${href}` : href
  }

  // 1. the pipeline-injected content layer already fetches page.json —
  //    pick the icon up from its state as soon as it lands
  const data = useState<any | null>('olux-content-data', () => null)
  watch(data, v => apply(v?.siteData?.icon), { immediate: true, deep: false })

  // 2. pristine template / direct deploys: fetch the published page.json ourselves
  fetch(`${apiBase || window.location.origin}/api/v1/sites/${encodeURIComponent(site)}/pages/index.json`)
    .then(r => (r.ok ? r.json() : null))
    .then(b => apply(b?.siteData?.icon))
    .catch(() => { /* CMS unreachable — static favicon carries on */ })
})
