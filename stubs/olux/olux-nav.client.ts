// Injected by Olux Studio when this template was published to the marketplace.
// Two navigation duties for CMS-driven multi-page sites:
//   1. Preview frames pass ?page=/about-us — open that page once the router is
//      ready (the app itself always boots at its base path).
//   2. Template menus use plain <a href="/about-us"> anchors. A native browser
//      navigation would leave the SPA (and under a preview sub-path, hit the
//      CMS backend). Route same-origin path links through the router instead.
export default defineNuxtPlugin(() => {
  const router = useRouter()

  router.isReady().then(() => {
    const wanted = new URLSearchParams(window.location.search).get('page')
    const current = router.currentRoute.value.path.replace(/\/+$/, '') || '/'
    if (wanted && wanted.startsWith('/') && wanted !== current) {
      const query = { ...router.currentRoute.value.query }
      delete query.page
      router.replace({ path: wanted, query })
    }
  })

  document.addEventListener(
    'click',
    (e) => {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return
      const a = (e.target as Element)?.closest?.('a[href]')
      if (!a || (a.getAttribute('target') || '') === '_blank') return
      const href = a.getAttribute('href') || ''
      // Only bare same-origin paths: hashes, full URLs, protocol-relative,
      // mailto/tel and file-ish paths (contain a dot) stay native.
      if (!href.startsWith('/') || href.startsWith('//') || href.includes('.')) return
      e.preventDefault()
      // NuxtLink hrefs are rendered WITH the app base (/nuxt-preview/{key});
      // pushing them raw would prefix the base a second time — strip it.
      const base = (useRuntimeConfig().app?.baseURL || '/').replace(/\/+$/, '')
      let path = href
      if (base && path.startsWith(base + '/')) path = path.slice(base.length)
      // Keep ?site=… so a manual reload on the new URL still finds the CMS.
      router.push({ path, query: router.currentRoute.value.query })
    },
    { capture: true },
  )
})
