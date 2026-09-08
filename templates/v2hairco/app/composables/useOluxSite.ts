// Which CMS site this build is rendering, and where the CMS is.
//
// The same built app serves THREE situations:
//   1. the client's own deploy (hairco.oluxstudio.com) — site + CMS baked in
//      from .env at build time (OLUX_SITE / OLUX_CMS);
//   2. the CMS's in-page preview (/nuxt-preview/hairco/?site=NAME) — site from
//      the query string, CMS = the origin serving the app;
//   3. an instant subdomain or live custom domain served BY the CMS — the
//      shell injects window.__OLUX_SITE__, CMS = the origin.
// Cases 2 and 3 are what make one template render every tenant.
export function useOluxSite() {
  const cfg = (useRuntimeConfig().public || {}) as any
  const baked = (cfg.cmsSite as string) || 'hairco'

  if (typeof window === 'undefined') {
    return { site: baked, cmsServed: false, apiBase: ((cfg.bookingApiBase as string) || '').replace(/\/$/, '') }
  }

  const w = window as any
  let query: string | null = null
  try { query = new URL(window.location.href).searchParams.get('site') } catch (_) { /* noop */ }

  const injected = typeof w.__OLUX_SITE__ === 'string' && w.__OLUX_SITE__ ? (w.__OLUX_SITE__ as string) : null
  const site = injected || query || baked
  // Served by the CMS itself → talk to the CMS on this origin, whatever was baked.
  const cmsServed = !!injected || !!query || window.location.pathname.startsWith('/nuxt-preview/')
  const origin = window.location.origin
  const apiBase = cmsServed ? origin : (((cfg.bookingApiBase as string) || '').replace(/\/$/, '') || origin)

  return { site, cmsServed, apiBase, baked }
}
