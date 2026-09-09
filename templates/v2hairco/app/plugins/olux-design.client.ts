// Injected by Olux Studio when this template was published to the marketplace.
// Applies CMS design settings at runtime, on top of the template's own styling:
//   1. Site theme → the template's CSS custom properties (only values the user
//      changed from the template defaults are injected — a pristine site stays
//      pixel-identical to the shipped app). Body font swaps load Google Fonts.
//   2. Per-block Motion effects: hover (lift/glow/zoom/border) and scroll
//      enter/leave animations for the olux-anim-* classes bound by blocks.
// The theme variable map + template defaults live in ~/olux-theme (generated
// per template at publish time from the extracted design tokens).
import { oluxThemeMap, oluxThemeDefaults } from '~/olux-theme'
import { ensureOluxContent } from '~/composables/useOluxContent'

const EFFECTS_CSS = `
[class*="olux-anim-"] { transition: opacity .6s ease, transform .6s ease; }
.olux-anim-fade:not(.olux-inview) { opacity: 0; }
.olux-anim-slide:not(.olux-inview) { opacity: 0; transform: translateY(32px); }
.olux-anim-slide-left:not(.olux-inview) { opacity: 0; transform: translateX(48px); }
.olux-anim-zoom:not(.olux-inview) { opacity: 0; transform: scale(.92); }
/* Leave animations reuse the hidden state when scrolled back out — blocks
   without an anim_out keep .olux-inview once earned (enter-only). */
.olux-hover-lift { transition: transform .25s ease, box-shadow .25s ease; }
.olux-hover-lift:hover { transform: translateY(-6px); box-shadow: 0 18px 40px -18px rgba(0,0,0,.28); }
.olux-hover-glow { transition: box-shadow .25s ease; }
.olux-hover-glow:hover { box-shadow: 0 0 0 3px rgba(99,102,241,.25), 0 12px 40px -12px rgba(99,102,241,.45); }
.olux-hover-zoom { transition: transform .25s ease; }
.olux-hover-zoom:hover { transform: scale(1.015); }
.olux-hover-border { transition: box-shadow .25s ease; }
.olux-hover-border:hover { box-shadow: inset 0 0 0 2px currentColor; }
`

export default defineNuxtPlugin(() => {
  // ── Effects stylesheet (static, once) ──
  const effects = document.createElement('style')
  effects.id = 'olux-effects'
  effects.textContent = EFFECTS_CSS
  document.head.appendChild(effects)

  // ── Scroll-driven enter/leave animations ──
  const io = new IntersectionObserver(
    (entries) => {
      for (const e of entries) {
        const el = e.target as HTMLElement
        if (e.isIntersecting) {
          el.classList.add('olux-inview')
        } else if (/(^|\s)olux-anim-out-/.test(el.className)) {
          // Only blocks with a leave animation animate back out.
          el.classList.remove('olux-inview')
        }
      }
    },
    { threshold: 0.15 },
  )
  const observed = new WeakSet<Element>()
  const scan = () => {
    document.querySelectorAll('[class*="olux-anim-"]').forEach((el) => {
      if (observed.has(el)) return
      observed.add(el)
      io.observe(el)
    })
  }
  // Vue mounts/reorders blocks at any time — a MutationObserver keeps the IO fed.
  new MutationObserver(() => scan()).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] })
  scan()

  // ── Site theme → template CSS variables ──
  const { data } = ensureOluxContent()
  const applyTheme = () => {
    const theme: Record<string, string> = data.value?.site?.theme ?? {}
    const decl: string[] = []
    let fontFamily = ''
    for (const [key, vars] of Object.entries(oluxThemeMap)) {
      const value = String(theme[key] ?? '').trim()
      const def = String((oluxThemeDefaults as Record<string, string>)[key] ?? '').trim()
      // Inject only real user changes — defaults keep the template's own CSS.
      if (value === '' || value.toLowerCase() === def.toLowerCase()) continue
      for (const v of vars) {
        decl.push(key === 'font' ? `${v}: '${value}', sans-serif` : `${v}: ${value}`)
      }
      if (key === 'font') fontFamily = value
    }
    const base = String(theme.base_size ?? '').trim()
    const baseDef = String((oluxThemeDefaults as Record<string, string>).base_size ?? '16px')
    const html = base !== '' && base !== baseDef ? `html { font-size: ${base}; }` : ''

    let tag = document.getElementById('olux-theme') as HTMLStyleElement | null
    if (!decl.length && !html) {
      tag?.remove()
      return
    }
    if (!tag) {
      tag = document.createElement('style')
      tag.id = 'olux-theme'
      document.head.appendChild(tag)
    }
    tag.textContent = (decl.length ? `:root { ${decl.join('; ')}; }\n` : '') + html

    // Swapped body font: make sure the family is actually available.
    if (fontFamily && fontFamily !== 'system-ui' && !document.getElementById('olux-theme-font')) {
      const link = document.createElement('link')
      link.id = 'olux-theme-font'
      link.rel = 'stylesheet'
      link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(fontFamily)}:wght@300;400;500;600;700;800&display=swap`
      document.head.appendChild(link)
    }
  }
  watch(data, applyTheme, { immediate: true })
})
