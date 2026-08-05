// Verita — a small consultancy template authored to the Olux conventions:
// real page files, section headlines outside loops, data arrays for lists,
// :root design tokens, Google fonts. Serves as the pipeline conformance app.
export default defineNuxtConfig({
  ssr: false,
  compatibilityDate: '2026-01-01',
  app: {
    head: {
      title: 'Verita — Clarity in Consulting',
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&family=Inter:wght@400;500;700&display=swap' },
        { rel: 'stylesheet', href: '/assets/stylesheets/styles.css' },
      ],
    },
  },
})
