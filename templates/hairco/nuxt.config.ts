// Hair Co. — salon & beauty template authored to the Olux conventions:
// real page files, section headlines outside loops, data arrays for lists,
// :root design tokens, Google fonts. Based on the Hair Co. demo design
// (cream + beige + near-black, Urbanist headings, Roboto body).
export default defineNuxtConfig({
  ssr: false,
  compatibilityDate: '2026-01-01',
  runtimeConfig: {
    public: {
      // CMS origin serving /api/sites/<site>/booking — override with NUXT_PUBLIC_BOOKING_API_BASE
      bookingApiBase: 'http://localhost:8000',
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
        { rel: 'stylesheet', href: '/assets/stylesheets/styles.css' },
      ],
    },
  },
})
