<script setup lang="ts">
const oluxCms = useOluxContent('book-store')
const oluxFb: Record<string, string> = {"Text":"No products match this filter yet.","Text B":"\u2190 Previous","Text C":"Next \u2192","Text D":"Checkout \u2192","Text E":"Order","Subheadline":"Your basket","Text F":"Secure card payment. You'll get an email receipt."}
// Church store — products come from the site's CMS store (owner manages them
// under Store); the authored books seed new sites via the template manifest.
// Layout: category sidebar · filterable product grid with pagination ·
// recommendations carousel · newsletter CTA band.
import type { StoreBook as Book } from '../composables/useSiteContent'

const { bookStore } = useSiteContent()
const authored: Book[] = bookStore.books

const books = ref<Book[]>(authored)
const apiBase = () => {
  const pub: any = (useRuntimeConfig() as any).public || {}
  return { base: pub.bookingApiBase || window.location.origin, site: pub.cmsSite || 'graceway' }
}
onMounted(async () => {
  try {
    const { base, site } = apiBase()
    const res = await fetch(`${base}/api/sites/${encodeURIComponent(site)}/products`)
    if (!res.ok) return
    const body = await res.json()
    const rows = (body.products || []).filter((p: any) => p.slug && p.name)
    if (rows.length) books.value = rows
  } catch { /* CMS unreachable — authored seed carries the page */ }
})

// template-relative asset paths need the app base (preview shells mount
// under /nuxt-preview/{key}/); storage + absolute URLs pass through.
const img = (b: Book) => {
  const base = ((useRuntimeConfig() as any).app?.baseURL || '/').replace(/\/$/, '')
  return b.image.startsWith('/assets/') ? base + b.image : b.image
}
const price = (b: Book) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: (b.currency || 'gbp').toUpperCase() }).format(b.price_cents / 100)

// ── sidebar filters ──
// on mobile the sidebar is collapsed behind the filter button
const filtersOpen = ref(false)
const categories = computed(() => [...new Set(books.value.map(b => b.category).filter(Boolean))])
const activeCat = ref<string | null>(null)
const activeQuick = ref<'new' | 'best' | 'discount' | null>(null)
const pickCat = (c: string | null) => { activeCat.value = c; activeQuick.value = null; page.value = 1; filtersOpen.value = false }
const pickQuick = (k: 'new' | 'best' | 'discount') => {
  activeQuick.value = activeQuick.value === k ? null : k
  page.value = 1
}
const filtered = computed(() => books.value.filter(b => {
  if (activeCat.value && b.category !== activeCat.value) return false
  if (activeQuick.value === 'new' && !b.isNew) return false
  if (activeQuick.value === 'best' && !b.bestSeller) return false
  if (activeQuick.value === 'discount' && !b.onDiscount) return false
  return true
}))

// ── pagination ──
const PER_PAGE = 9
const page = ref(1)
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const shown = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))

// recommendations: best sellers first, topped up with the rest
const recs = computed(() => {
  const best = books.value.filter(b => b.bestSeller)
  const rest = books.value.filter(b => !b.bestSeller)
  return [...best, ...rest].slice(0, 4)
})

// ── cart + buy flow: qty + contact details → CMS headless checkout → Stripe ──
const cart = ref<{ book: Book; qty: number }[]>([])
const cartCount = computed(() => cart.value.reduce((n, l) => n + l.qty, 0))
const cartTotal = computed(() => cart.value.reduce((n, l) => n + l.book.price_cents * l.qty, 0))
const money = (cents: number, currency = 'gbp') => new Intl.NumberFormat('en-GB', { style: 'currency', currency: currency.toUpperCase() }).format(cents / 100)
const addToCart = (b: Book) => {
  const line = cart.value.find(l => l.book.slug === b.slug)
  if (line) line.qty = Math.min(10, line.qty + 1)
  else cart.value.push({ book: b, qty: 1 })
}
const removeLine = (slug: string) => { cart.value = cart.value.filter(l => l.book.slug !== slug) }

const checkoutOpen = ref(false)
const form = ref({ name: '', email: '', phone: '' })
const fulfilment = ref<'collection' | 'delivery'>('collection')
const address = ref({ address_line1: '', city: '', postcode: '' })
const sending = ref(false)
const buyError = ref('')

const buyNow = (b: Book) => { if (!cart.value.find(l => l.book.slug === b.slug)) addToCart(b); openCheckout() }
const openCheckout = () => { if (cart.value.length) { checkoutOpen.value = true; buyError.value = '' } }
const close = () => { checkoutOpen.value = false }
const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') close() }
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

const buy = async () => {
  if (!cart.value.length) return
  sending.value = true
  buyError.value = ''
  try {
    const { base, site } = apiBase()
    const res = await fetch(`${base}/api/sites/${encodeURIComponent(site)}/store/checkout`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        lines: cart.value.map(l => ({ slug: l.book.slug, qty: l.qty })),
        ...form.value,
        fulfilment: fulfilment.value,
        ...(fulfilment.value === 'delivery' ? address.value : {}),
        return_url: window.location.href,
      }),
    })
    const body = await res.json().catch(() => ({} as any))
    if (res.ok && body.url) { window.location.href = body.url; return }
    buyError.value = body.message || 'Checkout is unavailable right now — please contact us and we will set your copies aside.'
  } catch {
    buyError.value = 'Could not reach the store — please try again.'
  } finally {
    sending.value = false
  }
}

// ── newsletter CTA band ──
const ctaEmail = ref('')
const ctaSent = ref(false)
const { submit: ctaSubmit, sending: ctaSending } = useCmsForm('store-newsletter')
const sendCta = async () => { if (await ctaSubmit({ email: ctaEmail.value })) ctaSent.value = true }
</script>

<template>
  <section class="bookstore" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">{{ bookStore.eyebrow }}</p>
        <h2 data-olx-field="Headline">{{ bookStore.title }}</h2>
      </div>

      <div class="store-layout">
        <!-- mobile: filters live behind this toggle -->
        <button type="button" class="store-filter-toggle" :aria-expanded="filtersOpen" @click="filtersOpen = !filtersOpen">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 5h18M6 12h12M10 19h4"/></svg>
          Filters
          <span v-if="activeCat || activeQuick" class="dot" aria-hidden="true"></span>
        </button>

        <!-- category sidebar -->
        <aside class="store-side" :class="{ open: filtersOpen }">
          <h3>{{ bookStore.categoryTitle }}</h3>
          <button type="button" class="store-cat all" :class="{ on: !activeCat && !activeQuick }" @click="pickCat(null)">
            {{ bookStore.allLabel }} <span class="count">{{ books.length }}</span>
          </button>
          <button
            v-for="c in categories" :key="c" type="button"
            class="store-cat" :class="{ on: activeCat === c }" @click="pickCat(c)"
          >{{ c }}</button>
          <div class="store-quick">
            <button
              v-for="q in bookStore.quickFilters" :key="q.key" type="button"
              class="store-cat" :class="{ on: activeQuick === q.key }" @click="pickQuick(q.key)"
            >{{ q.icon }} {{ q.label }}</button>
          </div>
        </aside>

        <!-- product grid + pagination -->
        <div class="store-main">
          <div class="bs-grid store-grid">
            <article v-for="b in shown" :key="b.slug" class="bs-card">
              <div class="bs-cover">
                <img :src="img(b)" :alt="`Cover of ${b.name}`" loading="lazy">
                <span class="bs-cat">{{ b.category }}</span>
              </div>
              <div class="bs-body">
                <div class="store-titlerow">
                  <h3>{{ b.name }}</h3>
                  <b class="bs-price">{{ price(b) }}</b>
                </div>
                <p v-if="b.rating" class="store-rating">⭐ {{ b.rating.toFixed(1) }} <span v-if="b.reviews">({{ b.reviews }} Reviews)</span></p>
                <p class="desc">{{ b.description }}</p>
                <div class="store-actions">
                  <button class="btn ghost store-btn" type="button" @click="addToCart(b)">Add to Cart</button>
                  <button class="btn store-btn" type="button" @click="buyNow(b)">Buy Now</button>
                </div>
              </div>
            </article>
          </div>
          <p data-olx-field="text" v-if="!shown.length" class="store-empty">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>

          <nav v-if="pages > 1" class="store-pager" aria-label="Store pages">
            <button data-olx-field="textB" type="button" :disabled="page <= 1" @click="page--">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</button>
            <button
              v-for="n in pages" :key="n" type="button"
              class="num" :class="{ on: n === page }" @click="page = n"
            >{{ n }}</button>
            <button data-olx-field="textC" type="button" :disabled="page >= pages" @click="page++">{{ oluxCms.t('Text C', oluxFb['Text C']) }}</button>
          </nav>
        </div>
      </div>

      <!-- recommendations -->
      <div class="store-recs">
        <h3>{{ bookStore.recTitle }}</h3>
        <div class="store-rec-row">
          <article v-for="b in recs" :key="b.slug" class="bs-card rec">
            <div class="bs-cover">
              <img :src="img(b)" :alt="`Cover of ${b.name}`" loading="lazy">
              <span class="bs-cat">{{ b.category }}</span>
            </div>
            <div class="bs-body">
              <div class="store-titlerow">
                <h3>{{ b.name }}</h3>
                <b class="bs-price">{{ price(b) }}</b>
              </div>
              <p v-if="b.rating" class="store-rating">⭐ {{ b.rating.toFixed(1) }} <span v-if="b.reviews">({{ b.reviews }} Reviews)</span></p>
              <div class="store-actions">
                <button class="btn ghost store-btn" type="button" @click="addToCart(b)">Add to Cart</button>
                <button class="btn store-btn" type="button" @click="buyNow(b)">Buy Now</button>
              </div>
            </div>
          </article>
        </div>
      </div>

      <!-- newsletter CTA band -->
      <div class="store-cta">
        <h2>
          <template v-for="(line, i) in contentLines(bookStore.cta.title)" :key="i">
            <br v-if="i">{{ line }}
          </template>
        </h2>
        <div class="store-cta-side">
          <p>{{ bookStore.cta.text }}</p>
          <form v-if="!ctaSent" class="store-cta-form" @submit.prevent="sendCta">
            <input v-model="ctaEmail" type="email" :placeholder="bookStore.cta.placeholder" required>
            <button type="submit" :disabled="ctaSending">{{ ctaSending ? '…' : bookStore.cta.button }}</button>
          </form>
          <p v-else class="store-cta-thanks">✓ {{ bookStore.cta.thanks }}</p>
        </div>
      </div>

      <!-- floating cart bar -->
      <Transition name="lb-fade">
        <div v-if="cartCount && !checkoutOpen" class="store-cartbar">
          <span>🛒 {{ cartCount }} item{{ cartCount > 1 ? 's' : '' }} · <b>{{ money(cartTotal) }}</b></span>
          <button data-olx-field="textD" class="btn store-btn" type="button" @click="openCheckout">{{ oluxCms.t('Text D', oluxFb['Text D']) }}</button>
        </div>
      </Transition>

      <!-- checkout modal -->
      <Transition name="lb-fade">
        <div v-if="checkoutOpen" class="lightbox rsvp-lightbox" @click.self="close">
          <div class="rsvp-modal store-modal">
            <div class="rsvp-head">
              <button class="lb-close" type="button" aria-label="Close" @click="close">✕</button>
              <p data-olx-field="textE" class="eyebrow">{{ oluxCms.t('Text E', oluxFb['Text E']) }}</p>
              <h3 data-olx-field="subheadline">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h3>
            </div>
            <form class="rsvp-form" @submit.prevent="buy">
              <div class="cart-lines">
                <div v-for="l in cart" :key="l.book.slug" class="cart-line">
                  <img class="cart-thumb" :src="img(l.book)" :alt="l.book.name">
                  <div class="cart-info">
                    <b>{{ l.book.name }}</b>
                    <span>{{ price(l.book) }} each</span>
                  </div>
                  <div class="ev-stepper cart-stepper">
                    <button type="button" aria-label="Fewer copies" :disabled="l.qty <= 1" @click="l.qty--">−</button>
                    <b>{{ l.qty }}</b>
                    <button type="button" aria-label="More copies" :disabled="l.qty >= 10" @click="l.qty++">+</button>
                  </div>
                  <b class="cart-line-total">{{ money(l.book.price_cents * l.qty, l.book.currency) }}</b>
                  <button class="store-remove" type="button" aria-label="Remove" @click="removeLine(l.book.slug)">✕</button>
                </div>
                <p class="store-total">Total <b>{{ money(cartTotal) }}</b></p>
              </div>
              <label>Full name<input v-model="form.name" type="text" placeholder="Your name" required></label>
              <div class="row">
                <label>Email<input v-model="form.email" type="email" placeholder="you@email.com" required></label>
                <label>Phone<input v-model="form.phone" type="tel" placeholder="For order updates" required></label>
              </div>
              <div class="fulfil-pills" role="radiogroup" aria-label="Delivery option">
                <label class="fulfil-pill" :class="{ on: fulfilment === 'collection' }">
                  <input v-model="fulfilment" type="radio" value="collection">
                  ⛪ Collect at church <small>Free</small>
                </label>
                <label class="fulfil-pill" :class="{ on: fulfilment === 'delivery' }">
                  <input v-model="fulfilment" type="radio" value="delivery">
                  📦 Deliver to me
                </label>
              </div>
              <template v-if="fulfilment === 'delivery'">
                <label>Address<input v-model="address.address_line1" type="text" placeholder="Street address" required></label>
                <div class="row">
                  <label>City<input v-model="address.city" type="text" required></label>
                  <label>Postcode<input v-model="address.postcode" type="text" required></label>
                </div>
              </template>
              <p v-if="buyError" class="form-error">{{ buyError }}</p>
              <button class="btn" type="submit" :disabled="sending || !cart.length">{{ sending ? 'Preparing checkout…' : `Pay ${money(cartTotal)} →` }}</button>
              <p data-olx-field="textF" class="ev-fine">{{ oluxCms.t('Text F', oluxFb['Text F']) }}</p>
            </form>
          </div>
        </div>
      </Transition>
    </div>
  </section>
</template>

<style scoped>
/* sidebar + grid */
.store-layout { display: grid; grid-template-columns: 18rem 1fr; gap: 2rem; margin-top: 2rem; align-items: start; }
.store-side { background: #fff; border-radius: 18px; padding: 1.3rem; box-shadow: 0 10px 28px rgba(20, 24, 29, .08);
  position: sticky; top: 7rem; display: grid; gap: .35rem; }
.store-side h3 { font-size: 1.05rem; color: var(--color-secondary); margin-bottom: .5rem; }
.store-cat { display: flex; justify-content: space-between; align-items: center; gap: .6rem; width: 100%;
  border: 0; background: transparent; text-align: left; font: inherit; font-size: .95rem; color: var(--color-default);
  padding: .5rem .7rem; border-radius: 10px; cursor: pointer; transition: background .2s, color .2s; }
.store-cat:hover { background: #fdeef3; }
.store-cat.on { background: var(--color-primary); color: #fff; font-weight: 700; }
.store-cat .count { font-size: .75rem; background: var(--color-primary); color: #fff; border-radius: 999px; padding: .1rem .5rem; }
.store-cat.on .count { background: #fff; color: var(--color-primary); }
.store-quick { margin-top: .8rem; padding-top: .8rem; border-top: 1px solid #f1ece3; display: grid; gap: .35rem; }

.store-grid { margin-top: 0; }
.store-titlerow { display: flex; justify-content: space-between; align-items: baseline; gap: .8rem; }
.store-titlerow h3 { flex: 1; }
.store-rating { font-size: .82rem; color: var(--color-default); margin: .25rem 0 .4rem; }
.store-rating span { opacity: .7; }
.store-actions { display: flex; gap: .6rem; margin-top: auto; padding-top: .8rem; }
.store-btn { flex: 1; font-size: .85rem; padding: .55rem .8rem; text-align: center; }
.store-empty { margin: 2rem 0; color: var(--color-default); }

/* pagination */
.store-pager { display: flex; justify-content: center; align-items: center; gap: .4rem; margin-top: 2rem; flex-wrap: wrap; }
.store-pager button { border: 0; background: transparent; font: inherit; font-size: .9rem; color: var(--color-default);
  padding: .45rem .8rem; border-radius: 10px; cursor: pointer; }
.store-pager button:hover:not(:disabled) { background: #fdeef3; }
.store-pager button:disabled { opacity: .4; cursor: default; }
.store-pager .num.on { background: var(--color-primary); color: #fff; font-weight: 700; }

/* recommendations */
.store-recs { margin-top: 3.5rem; }
.store-recs > h3 { font-size: 1.5rem; color: var(--color-secondary); margin-bottom: 1.2rem; }
.store-rec-row { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(240px, 1fr); gap: 1.4rem; overflow-x: auto;
  padding-bottom: .6rem; scroll-snap-type: x mandatory; }
.store-rec-row .bs-card { scroll-snap-align: start; }

/* dark newsletter CTA band */
.store-cta { margin-top: 3.5rem; background: var(--color-secondary); color: #fff; border-radius: 24px;
  padding: 2.6rem 2.4rem; display: grid; grid-template-columns: 1.1fr 1fr; gap: 2rem; align-items: center; }
.store-cta h2 { color: #fff; font-size: clamp(1.6rem, 3.4vw, 2.4rem); line-height: 1.15; }
.store-cta-side p { color: #cfd5db; font-size: .95rem; margin-bottom: 1rem; }
.store-cta-form { display: flex; background: #fff; border-radius: 999px; padding: .3rem; max-width: 360px; }
.store-cta-form input { flex: 1; border: 0; background: transparent; padding: .55rem 1rem; font: inherit; font-size: .92rem; outline: none; }
.store-cta-form button { border: 0; border-radius: 999px; background: var(--color-primary); color: #fff; font: inherit;
  font-weight: 700; padding: .55rem 1.4rem; cursor: pointer; }
.store-cta-thanks { color: #9fe3b9; }

/* floating cart bar */
.store-cartbar { position: fixed; left: 50%; transform: translateX(-50%); bottom: 1.2rem; z-index: 60;
  background: #fff; border-radius: 999px; box-shadow: 0 16px 40px rgba(20, 24, 29, .25);
  display: flex; align-items: center; gap: 1rem; padding: .5rem .6rem .5rem 1.3rem; }
.store-cartbar span { font-size: .92rem; color: var(--color-secondary); white-space: nowrap; }
.store-cartbar .store-btn { flex: none; }
.store-remove { border: 0; background: #eceef1; color: var(--color-secondary); width: 26px; height: 26px;
  border-radius: 50%; cursor: pointer; font-size: .75rem; }
.store-total { text-align: right; font-size: .95rem; }

/* mobile filter toggle — hidden on desktop */
.store-filter-toggle { display: none; }

/* checkout modal */
.cart-lines { background: #f8f5f0; border-radius: 14px; padding: .9rem; display: grid; gap: .7rem; }
.cart-line { display: grid; grid-template-columns: 64px 1fr auto;
  grid-template-areas: 'thumb info total' 'thumb stepper remove';
  column-gap: .8rem; row-gap: .5rem; align-items: center;
  padding-bottom: .7rem; border-bottom: 1px solid #eee8dd; }
.cart-line:last-of-type { padding-bottom: 0; border-bottom: 0; }
.cart-line > * { min-width: 0; }
.cart-thumb { grid-area: thumb; align-self: start; }
.cart-info { grid-area: info; }
.cart-stepper { grid-area: stepper; justify-self: start; }
.cart-line-total { grid-area: total; align-self: start; }
.store-remove { grid-area: remove; justify-self: end; }
.cart-thumb { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; flex: none;
  box-shadow: 0 6px 14px rgba(20, 24, 29, .12); }

/* quantity stepper — pink round buttons */
.cart-stepper { display: flex; align-items: center; gap: .45rem; background: #fff; border-radius: 999px;
  padding: .25rem; box-shadow: inset 0 0 0 1.5px #e5e0d7; }
.cart-stepper button { width: 28px; height: 28px; border: 0; border-radius: 50%; background: var(--color-primary);
  color: #fff; font-size: 1rem; font-weight: 700; line-height: 1; cursor: pointer; display: grid; place-items: center;
  transition: transform .15s, background .2s; }
.cart-stepper button:hover:not(:disabled) { transform: scale(1.08); }
.cart-stepper button:disabled { background: #e5e0d7; color: #aeb6bf; cursor: default; }
.cart-stepper b { min-width: 1.4rem; text-align: center; font-size: .95rem; color: var(--color-secondary); }
.cart-info { flex: 1; min-width: 0; display: grid; }
.cart-info b { font-size: .92rem; color: var(--color-secondary); line-height: 1.25;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cart-info span { font-size: .8rem; color: var(--color-default); opacity: .75; }
.cart-stepper { flex: none; }
.cart-line-total { flex: none; font-size: .92rem; color: var(--color-secondary); min-width: 3.6rem; text-align: right; }
.store-remove { border: 0; background: #eceef1; color: var(--color-secondary); width: 24px; height: 24px;
  border-radius: 50%; cursor: pointer; font-size: .7rem; flex: none; }
.store-remove:hover { background: var(--color-primary); color: #fff; }
.store-total { display: flex; justify-content: space-between; border-top: 1px solid #e8e2d8;
  padding-top: .7rem; margin-top: .2rem; font-size: .95rem; color: var(--color-default); }
.store-total b { color: var(--color-secondary); font-size: 1.05rem; }

.fulfil-pills { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
.fulfil-pill { display: flex; align-items: center; justify-content: center; gap: .4rem; padding: .7rem .6rem;
  border: 1.5px solid #e5e0d7; border-radius: 12px; font-size: .88rem; color: var(--color-default);
  cursor: pointer; text-align: center; transition: border-color .2s, background .2s, color .2s; }
.fulfil-pill small { opacity: .7; }
.fulfil-pill input { position: absolute; opacity: 0; pointer-events: none; }
.fulfil-pill.on { border-color: var(--color-primary); background: #fdeef3; color: var(--color-secondary); font-weight: 700; }

@media (max-width: 900px) {
  .store-layout { grid-template-columns: 1fr; gap: 1rem; }
  .store-filter-toggle { display: inline-flex; align-items: center; gap: .5rem; justify-self: start;
    border: 1.5px solid #e5e0d7; background: #fff; border-radius: 999px; padding: .55rem 1.1rem;
    font: inherit; font-size: .92rem; font-weight: 700; color: var(--color-secondary); cursor: pointer; }
  .store-filter-toggle svg { width: 17px; height: 17px; }
  .store-filter-toggle .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--color-primary); }
  /* collapsed by default; expands under the toggle */
  .store-side { display: none; position: static; }
  .store-side.open { display: grid; }
  .fulfil-pills { grid-template-columns: 1fr; }
  .store-cta { grid-template-columns: 1fr; padding: 2rem 1.5rem; }
}
</style>
