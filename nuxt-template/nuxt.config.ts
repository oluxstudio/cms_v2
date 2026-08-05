// Nuxt 4 config. Client-rendered SPA so the same build works for:
//  - preview  : reads the live API via ?site=  (NUXT_PUBLIC_DATA_MODE=api)
//  - generated: reads a baked ./content.json    (default)
export default defineNuxtConfig({
  compatibilityVersion: 4,
  ssr: false,
  devtools: { enabled: false },

  runtimeConfig: {
    public: {
      // 'static' → fetch ./content.json ; 'api' → fetch /api/sites/{site}/content
      dataMode: 'static',
      apiBase: '/',
    },
  },

  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      meta: [{ charset: 'utf-8' }, { name: 'viewport', content: 'width=device-width, initial-scale=1' }],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap' },
      ],
    },
  },

  css: ['~/assets/css/main.css'],
})
