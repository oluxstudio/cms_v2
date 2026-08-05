// Nuxt 4 config — TekStack faithful reproduction.
// SPA (ssr:false) keeps the vendor JS (Bootstrap/AOS/Swiper/GLightbox) simple, and
// the original /assets/** layout is preserved so every CSS url() resolves exactly.
export default defineNuxtConfig({
  compatibilityVersion: 4,
  ssr: false,
  devtools: { enabled: false },

  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      title: 'Tekstack - IT Solutions & Software Solutions',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1.0' },
      ],
      link: [
        { rel: 'icon', href: '/assets/images/favicon.png' },
        { rel: 'apple-touch-icon', href: '/assets/images/apple-touch-icon.png' },
        // Fonts — Comfortaa (headings) + Poppins (body), exactly as the source.
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Comfortaa:wght@700&family=Poppins:wght@300;400&display=swap' },
        // Vendor CSS (CDN, pinned to the versions BootstrapMade ships).
        { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' },
        { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' },
        { rel: 'stylesheet', href: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' },
        { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css' },
        { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css' },
        { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css' },
        // Template stylesheet — LAST so it wins. Local copy keeps url(../images/*) intact.
        { rel: 'stylesheet', href: '/assets/stylesheets/styles.css' },
      ],
      script: [
        { src: 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', tagPosition: 'bodyClose' },
        { src: 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js', tagPosition: 'bodyClose' },
        { src: 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', tagPosition: 'bodyClose' },
        { src: 'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js', tagPosition: 'bodyClose' },
      ],
    },
  },
})
