# TekStack — Nuxt 4 reproduction

A faithful Nuxt 4 reproduction of the TekStack IT-solutions template
([source demo](https://zrtechsolutions.com/demo/html/tekstack/index.html)).

This is the **design reference** used to test the Olux template pipeline: it is the
"absolute exact design" that an Olux template package (layout blocks + theme tokens +
fonts) should reproduce when installed on a site.

## Design tokens (from the source `styles.css`)

| Token        | Value                       |
|--------------|-----------------------------|
| Primary      | `#f8830f` (orange)          |
| Text default | `#222222`                   |
| Heading font | `Comfortaa`, cursive        |
| Body font    | `Poppins`, sans-serif       |
| Framework    | Bootstrap 5 + Bootstrap Icons + Font Awesome 4 |
| Motion       | AOS · Swiper · GLightbox    |

## Run

```bash
npm install
npm run dev      # http://localhost:3000
```

`npm run generate` produces a static build in `.output/public`.

## Structure

```
app/
  app.vue                  # initialises AOS / Swiper / GLightbox / nav / scroll-top
  pages/index.vue          # composes the section blocks
  components/
    AppHeader.vue          # topbar + nav
    HeroBlock.vue
    ServicesBlock.vue
    AboutBlock.vue
    WhyUsBlock.vue
    PortfolioBlock.vue
    CtaBlock.vue
    TestimonialsBlock.vue
    TeamBlock.vue
    PricingBlock.vue
    FaqBlock.vue
    BlogBlock.vue
    ClientsBlock.vue
    ContactBlock.vue
    AppFooter.vue
public/assets/
  stylesheets/styles.css   # the template's own stylesheet (verbatim)
  images/**                # all template images (verbatim)
```

Fidelity notes:
- The original `/assets/**` layout is preserved verbatim, so every `url()` in
  `styles.css` resolves exactly as on the source site.
- Vendor CSS/JS is loaded from CDN (pinned) in `nuxt.config.ts` to keep the dependency
  surface to Nuxt only.
- Repeating blocks (services, team, portfolio, testimonials, pricing, FAQ, blog, clients)
  are driven by local data arrays so each block is a genuine reusable component.
