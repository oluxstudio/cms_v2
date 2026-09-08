import { ref, computed } from 'vue'

// Shared shop basket — one module-level state used by the shop grid, the
// product detail page, the cart drawer and the header badge. Persisted per
// site in localStorage (`olux-cart:{site}`), announced via the
// `olux-cart-changed` window event (header badge / other tabs).

export type ShopProduct = {
  slug: string
  name: string
  description?: string
  price: string
  price_cents: number
  currency?: string
  image?: string | null
  inventory: number | null
  in_stock: boolean
  category?: string | null
  tags?: string[]
  rating?: number | null
  review_count?: number
  reviews_enabled?: boolean
}

const cart = ref<Record<string, number>>({})
const cartOpen = ref(false)
/** Products we know about (from the list or a detail fetch) — cart lines need
 * names/prices, and the basket can hold slugs fetched on another page. */
const catalog = ref<Record<string, ShopProduct>>({})
const paying = ref(false)
const email = ref('')
// Customer details — the client site collects everything; Stripe is payment-only.
const name = ref('')
const phone = ref('')
const fulfilment = ref<'delivery' | 'collection'>('delivery')
const address1 = ref('')
const address2 = ref('')
const city = ref('')
const postcode = ref('')
const notes = ref('')
const consent = ref(false)
const errorMsg = ref('')
let hydrated = false

export function useCart() {
  const olux = useOluxSite()
  const siteName = olux.site
  const apiBase = (olux.apiBase || '').replace(/\/$/, '')
    || (typeof window !== 'undefined' ? window.location.origin : '')
  const api = `${apiBase}/api/sites/${encodeURIComponent(siteName)}`
  const CART_KEY = `olux-cart:${siteName}`

  const load = () => {
    try { cart.value = JSON.parse(localStorage.getItem(CART_KEY) || '{}') } catch { cart.value = {} }
  }
  const cartCount = computed(() => Object.values(cart.value).reduce((a, b) => a + b, 0))
  const persist = () => {
    try { localStorage.setItem(CART_KEY, JSON.stringify(cart.value)) } catch {}
    try { window.dispatchEvent(new CustomEvent('olux-cart-changed', { detail: { count: cartCount.value } })) } catch {}
  }
  if (typeof window !== 'undefined' && !hydrated) {
    hydrated = true
    load()
    window.addEventListener('storage', load) // other tabs
  }

  const registerProducts = (list: ShopProduct[]) => {
    const next = { ...catalog.value }
    for (const p of list || []) if (p?.slug) next[p.slug] = p
    catalog.value = next
  }

  const cartLines = computed(() => Object.entries(cart.value)
    .map(([slug, qty]) => ({ p: catalog.value[slug], qty }))
    .filter(l => !!l.p) as { p: ShopProduct; qty: number }[])
  const cartTotal = computed(() => cartLines.value.reduce((sum, l) => sum + l.p.price_cents * l.qty, 0))
  const money = (cents: number) => {
    const sample = cartLines.value[0]?.p || Object.values(catalog.value)[0]
    return (sample?.price || '£0').replace(/[\d.,]+/, (cents / 100).toFixed(2))
  }

  const maxFor = (p: ShopProduct) => (p.inventory === null ? 99 : Math.max(0, p.inventory))

  /** Interest beacon (view | add_to_cart) — fire-and-forget. */
  const beacon = (slug: string, event: 'view' | 'add_to_cart') => {
    try { $fetch(`${api}/products/${encodeURIComponent(slug)}/event`, { method: 'POST', body: { event } }).catch(() => {}) } catch {}
  }

  const add = (p: ShopProduct, qty = 1) => {
    registerProducts([p])
    const cur = cart.value[p.slug] || 0
    const next = Math.min(cur + qty, maxFor(p))
    if (next === cur) return
    cart.value = { ...cart.value, [p.slug]: next }
    persist()
    beacon(p.slug, 'add_to_cart')
    cartOpen.value = true
  }

  const setQty = (slug: string, qty: number) => {
    const p = catalog.value[slug]
    qty = Math.max(0, Math.min(qty, p ? maxFor(p) : 99))
    const next = { ...cart.value }
    if (qty === 0) delete next[slug]
    else next[slug] = qty
    cart.value = next
    persist()
  }

  const clear = () => { cart.value = {}; persist() }

  /** Start the CMS headless checkout; redirects the browser to Stripe.
   * Success/cancel always return to /shop/, where the notice is handled. */
  const canCheckout = computed(() =>
    cartLines.value.length > 0
    && name.value.trim() !== '' && email.value.trim() !== '' && phone.value.trim() !== ''
    && (fulfilment.value === 'collection'
      || (address1.value.trim() !== '' && city.value.trim() !== '' && postcode.value.trim() !== '')))

  const checkout = async () => {
    if (!canCheckout.value || paying.value) return
    paying.value = true
    errorMsg.value = ''
    try {
      const res: any = await $fetch(`${api}/store/checkout`, {
        method: 'POST',
        body: {
          lines: cartLines.value.map(l => ({ slug: l.p.slug, qty: l.qty })),
          name: name.value.trim(),
          email: email.value.trim(),
          phone: phone.value.trim(),
          fulfilment: fulfilment.value,
          address_line1: fulfilment.value === 'delivery' ? address1.value.trim() : undefined,
          address_line2: fulfilment.value === 'delivery' ? (address2.value.trim() || undefined) : undefined,
          city: fulfilment.value === 'delivery' ? city.value.trim() : undefined,
          postcode: fulfilment.value === 'delivery' ? postcode.value.trim() : undefined,
          notes: notes.value.trim() || undefined,
          consent: consent.value,
          // Base-aware: the preview shell lives under /nuxt-preview/{key}/,
          // so the return URL must keep the app's baseURL or Stripe lands on a 404.
          return_url: window.location.origin
            + ((useRuntimeConfig().app?.baseURL || '/').replace(/\/$/, ''))
            + '/shop/',
        },
      })
      if (res?.checkout_url) window.location.href = res.checkout_url
    } catch (e: any) {
      errorMsg.value = e?.data?.message || 'Could not start checkout — please try again or contact us.'
    } finally {
      paying.value = false
    }
  }

  return {
    api, siteName,
    cart, cartOpen, catalog, paying, email, errorMsg,
    name, phone, fulfilment, address1, address2, city, postcode, notes, consent, canCheckout,
    cartCount, cartLines, cartTotal, money, maxFor,
    registerProducts, beacon, add, setQty, clear, checkout, persist,
  }
}
