// Graceway — a modern church template: teal page frame, floating white canvas,
// authored to the Olux conventions: real page files, section headlines outside
// loops, data arrays for lists, :root design tokens, Google fonts.
// Palette: deep ocean blue + fresh green.
export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      // CMS origin for runtime API calls (collections, beacons, forms).
      bookingApiBase: process.env.OLUX_CMS || 'http://localhost:8000',
      // Site name in the CMS — used by useCms()/useOluxSite().
      cmsSite: process.env.OLUX_SITE || 'graceway',
    },
  },

  ssr: false,
  compatibilityDate: '2026-01-01',
  app: {
    head: {
      title: 'CAC Blackburn — Christ Apostolic Church',
      link: [
        // Fonts ship WITH the template (public/assets/fonts) — no CDN dependency.
        { rel: 'stylesheet', href: '/assets/fonts/fonts.css' },
        { rel: 'stylesheet', href: '/assets/stylesheets/styles.css' },
        { rel: 'icon', type: 'image/png', href: '/favicon.png' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
      ],
    },
  },
})
