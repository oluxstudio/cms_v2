// Visitor-tracking beacon. Pings the Olux CMS on first load and every route
// change so the site's Analytics dashboard fills with real traffic. Config is
// baked at export time into /analytics-config.json ({ trackBase, site }); when
// it's absent (e.g. local dev without export) the plugin quietly does nothing.
export default defineNuxtPlugin((nuxtApp) => {
  if (!import.meta.client) return

  let cfg: { trackBase?: string; site?: string } | null = null
  const loadConfig = async () => {
    if (cfg) return cfg
    try {
      cfg = await $fetch<{ trackBase?: string; site?: string }>('/analytics-config.json')
    } catch {
      cfg = {}
    }
    return cfg
  }

  // A per-tab session id (not personally identifying).
  const sessionId = () => {
    let s = sessionStorage.getItem('_ovid')
    if (!s) {
      s = Math.random().toString(36).slice(2) + Date.now().toString(36)
      sessionStorage.setItem('_ovid', s)
    }
    return s
  }

  const send = async () => {
    const c = await loadConfig()
    if (!c?.trackBase || !c?.site) return

    const body = JSON.stringify({
      path: location.pathname + location.search,
      referrer: document.referrer || null,
      language: navigator.language,
      session_id: sessionId(),
    })
    const url = `${c.trackBase.replace(/\/$/, '')}/api/sites/${encodeURIComponent(c.site)}/track`

    try {
      // text/plain is CORS-safelisted → no preflight (sendBeacon can't preflight).
      const blob = new Blob([body], { type: 'text/plain' })
      if (navigator.sendBeacon && navigator.sendBeacon(url, blob)) return
      await fetch(url, { method: 'POST', body, headers: { 'Content-Type': 'text/plain' }, keepalive: true, mode: 'no-cors' })
    } catch {
      /* tracking must never disrupt the site */
    }
  }

  nuxtApp.hook('app:mounted', () => { void send() })
  const router = useRouter()
  router.afterEach(() => { void send() })
})
