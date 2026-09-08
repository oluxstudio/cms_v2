// Hair Co. — salon & beauty template authored to the Olux conventions:
// real page fiSles, section headlines outside loops, data arrays for lists,
// :root design tokens, Google fonts. Based on the Hair Co. demo design
// (cream + beige + near-black, Urbanist headings, Roboto body).
import { defineNuxtConfig } from 'nuxt/config'

// Site Connect token for the connect.js tag. Fetched from the CMS at
// build/dev-server start using the (server-side) management key, so .env only
// needs OLUX_CMS / OLUX_SITE / OLUX_API_KEY. An explicit OLUX_SITE_TOKEN env
// var still wins; with neither available the connect.js tag is omitted.
const CMS = process.env.OLUX_CMS || 'http://localhost:8000'
async function resolveSiteToken(): Promise<string> {
  if (process.env.OLUX_SITE_TOKEN) return process.env.OLUX_SITE_TOKEN
  if (!process.env.OLUX_API_KEY) return ''
  try {
    const res = await fetch(`${CMS}/api/site/connect-token`, {
      headers: {
        Authorization: `Bearer ${process.env.OLUX_API_KEY}`,
        'X-Olux-Site': process.env.OLUX_SITE || 'hairco',
        Accept: 'application/json',
      },
    })
    if (!res.ok) {
      console.warn(`[olux] connect-token fetch failed (${res.status}) — connect.js tag omitted`)
      return ''
    }
    return (await res.json()).token || ''
  } catch (e) {
    console.warn('[olux] CMS unreachable — connect.js tag omitted', e)
    return ''
  }
}
const SITE_TOKEN = await resolveSiteToken()

export default defineNuxtConfig({
  ssr: false,
  compatibilityDate: '2026-01-01',
  runtimeConfig: {
    public: {
      // CMS origin for runtime API calls (booking, forms, useCms live layer).
      // Defaults to OLUX_CMS so one .env var switches everything; a
      // NUXT_PUBLIC_BOOKING_API_BASE env var still overrides when they differ.
      bookingApiBase: process.env.OLUX_CMS || 'http://localhost:8000',
      // Site name in the CMS — used by useCms()/the olux-cms SDK read layer.
      cmsSite: process.env.OLUX_SITE || 'hairco',
    },
  },
  app: {
    head: {
      title: 'Hair Co. — Salon & Hair Care',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1.0' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Urbanist:wght@500;600;700;800&family=Roboto:wght@300;400;500&display=swap' },
        // Build stamp busts browser caches — /assets/ has no Cache-Control header, so
        // without it visitors keep a stale stylesheet after a deploy.
        { rel: 'stylesheet', href: `/assets/stylesheets/styles.css?v=${Date.now().toString(36)}` },
      ],
      // Olux Site Connect — collect mode ingests the (hydrated) page into the CMS.
      // Because this is a pure SPA (ssr:false), ingestion must run in the browser;
      // the connector waits for hydration before snapshotting.
      // The token is fetched from the CMS with OLUX_API_KEY at dev-server
      // start / build time (see resolveSiteToken above) — restart after
      // changing env vars. With no token resolvable, the tag is omitted.
      script: SITE_TOKEN
        ? [
            {
              src: `${CMS}/connect.js?v=4`,
              'data-site-name': process.env.OLUX_SITE || 'hairco',
              'data-site-token': SITE_TOKEN,
              // Console logging only against a local CMS (or when forced).
              ...(CMS.includes('localhost') || process.env.OLUX_CONNECT_DEBUG
                ? { 'data-debug': '' } : {}),
              defer: true,
            },
          ]
        : [],
    },
  },
})
