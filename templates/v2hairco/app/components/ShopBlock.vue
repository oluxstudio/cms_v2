<script setup lang="ts">
const oluxCms = useOluxContent('shop')
const oluxFb: Record<string, string> = {"Text":"Loading products\u2026","Text B":"The shop is being stocked \u2014 check back soon.","Text C":"\u2190 Prev","Text D":"Next \u2192"}
// Retail shop — products come LIVE from the CMS store; the basket/checkout
// logic is shared with the product page via useCart().
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import type { ShopProduct } from '../composables/useCart'

const { field } = useCms()
const { siteName, api, cart, maxFor, add, registerProducts, beacon, clear } = useCart()
const pageSources = usePageSources()

const products = ref<ShopProduct[]>([])
const categories = ref<string[]>([])
const activeCategory = ref('')
const activeTag = ref('')
const showFilters = ref(false)
const activeFilters = computed(() => (activeCategory.value ? 1 : 0) + (activeTag.value ? 1 : 0))
const tags = computed(() => [...new Set(products.value.flatMap(p => p.tags || []))].sort())
const visible = computed(() => products.value
  .filter(p => !activeCategory.value || p.category === activeCategory.value)
  .filter(p => !activeTag.value || (p.tags || []).includes(activeTag.value)))
const loading = ref(true)
const notice = ref('')

// ── Pagination (client-side, 12 per page) ────────────────────────────────
const PER_PAGE = 12
const page = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(visible.value.length / PER_PAGE)))
const pageItems = computed(() => visible.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))
watch([activeCategory, activeTag], () => { page.value = 1 })
async function goTo(n: number) {
  page.value = Math.min(Math.max(1, n), totalPages.value)
  await nextTick()
  document.querySelector('.shop .grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  watchCards()
}

// ── Interest beacons (view once per session, per card) ───────────────────
const SEEN_KEY = `olux-seen:${siteName}`
function watchCards() {
  if (typeof IntersectionObserver === 'undefined') return
  let seen: Record<string, 1> = {}
  try { seen = JSON.parse(sessionStorage.getItem(SEEN_KEY) || '{}') } catch {}
  const io = new IntersectionObserver((entries) => {
    for (const e of entries) {
      if (!e.isIntersecting) continue
      const slug = (e.target as HTMLElement).dataset.slug
      if (!slug || seen[slug]) continue
      seen[slug] = 1
      try { sessionStorage.setItem(SEEN_KEY, JSON.stringify(seen)) } catch {}
      beacon(slug, 'view')
      io.unobserve(e.target)
    }
  }, { threshold: 0.5 })
  document.querySelectorAll('.shop .card[data-slug]').forEach(el => io.observe(el))
}

onMounted(async () => {
  // Return from Stripe: thank the buyer + empty the cart; cancelled keeps it.
  const q = new URLSearchParams(window.location.search)
  if (q.get('paid') === '1') {
    notice.value = 'Thank you! Your order is confirmed — we\'ll be in touch about delivery.'
    clear()
    // Webhooks can't reach local/dev installs — ask the CMS to verify the
    // payment with Stripe directly so the order flips to "paid" either way.
    const orderId = q.get('order')
    if (orderId) {
      try { $fetch(`${api}/store/orders/${encodeURIComponent(orderId)}/confirm`, { method: 'POST' }).catch(() => {}) } catch {}
    }
  } else if (q.get('cancelled') === '1') {
    notice.value = 'Checkout cancelled — your basket is saved below.'
  }
  try {
    // Page content sources (CMS → page → Sources tab): pin this page's
    // product set by category/tags with a limit; default = every product.
    const src = (await loadPageSources())?.products
    const query: Record<string, string> = {}
    if (src?.enabled && src.category) query.category = src.category
    if (src?.enabled && Array.isArray(src.tags) && src.tags.length) query.tag = src.tags[0]
    const qs = Object.keys(query).length ? '?' + new URLSearchParams(query).toString() : ''
    const res: any = await $fetch(`${api}/products${qs}`)
    let list: any[] = Array.isArray(res?.products) ? res.products : []
    if (src?.enabled && Array.isArray(src.tags) && src.tags.length > 1) {
      list = list.filter(p => (p.tags || []).some((t: string) => src.tags.includes(t)))
    }
    if (src?.enabled && src.limit > 0) list = list.slice(0, src.limit)
    products.value = list
    // Chips only cover categories present in the pinned set.
    categories.value = src?.enabled
      ? [...new Set(list.map(p => p.category).filter(Boolean))] as string[]
      : (Array.isArray(res?.categories) ? res.categories : [])
    registerProducts(products.value) // cart lines need names/prices
  } catch { /* leave empty */ }
  loading.value = false
  await nextTick()
  watchCards()
})
</script>

<template>
  <section class="shop" data-olx-key="shop" data-olx-kind="component" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow" data-olx-field="caption">{{ field('shop', 'caption', 'Our products') }}</p>
        <h2 data-olx-field="heading">{{ field('shop', 'heading', 'Take the salon home') }}</h2>
        <p class="lead" data-olx-field="body">{{ field('shop', 'body', 'The same professional products we use at every appointment — delivered to your door.') }}</p>
      </div>

      <p v-if="notice" class="note ok">{{ notice }}</p>

      <p data-olx-field="text" v-if="loading" class="note">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
      <p data-olx-field="textB" v-else-if="!products.length" class="note">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>

      <!-- mobile filter toggle -->
      <button v-if="!loading && products.length && (categories.length || tags.length)" type="button"
              class="filter-toggle" data-olx-skip @click="showFilters = !showFilters">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M3 5h18M6 12h12M10 19h4"/></svg>
        Filters<span v-if="activeFilters" class="fcount">{{ activeFilters }}</span>
      </button>

      <div v-if="!loading && products.length" class="shop-body">
        <!-- left filter panel -->
        <aside v-if="categories.length || tags.length" class="filters" :class="{ open: showFilters }" data-olx-skip>
          <div class="fhead">
            <b>Filters</b>
            <button v-if="activeFilters" type="button" class="fclear" @click="activeCategory = ''; activeTag = ''">Clear</button>
            <button type="button" class="fclose" aria-label="Close filters" @click="showFilters = false">✕</button>
          </div>
          <div v-if="categories.length" class="fgroup">
            <p class="ftitle" data-olx-skip>Category</p>
            <button type="button" :class="{ on: !activeCategory }" @click="activeCategory = ''">All products</button>
            <button v-for="c in categories" :key="c" type="button" :class="{ on: activeCategory === c }"
                    @click="activeCategory = activeCategory === c ? '' : c">{{ c }}</button>
          </div>
          <div v-if="tags.length" class="fgroup">
            <p class="ftitle" data-olx-skip>Tags</p>
            <div class="ftags">
              <button v-for="t in tags" :key="t" type="button" :class="{ on: activeTag === t }"
                      @click="activeTag = activeTag === t ? '' : t">#{{ t }}</button>
            </div>
          </div>
        </aside>

        <div class="shop-main">
      <div class="grid">
        <article v-for="p in pageItems" :key="p.slug" class="card" :data-slug="p.slug">
          <NuxtLink class="pic" :to="`/shop/${p.slug}`">
            <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy">
            <span v-else class="ph">🛍️</span>
            <span v-if="!p.in_stock" class="badge out">Out of stock</span>
            <span v-else-if="p.inventory !== null && p.inventory <= 5" class="badge low">Only {{ p.inventory }} left</span>
          </NuxtLink>
          <h3><NuxtLink :to="`/shop/${p.slug}`">{{ p.name }}</NuxtLink></h3>
          <p v-if="p.category" class="cat">{{ p.category }}</p>
          <p v-if="p.reviews_enabled === true && p.review_count" class="stars">★ {{ p.rating }} <span>· {{ p.review_count }} review{{ p.review_count === 1 ? '' : 's' }}</span></p>
          <p class="desc">{{ p.description }}</p>
          <div class="row">
            <b>{{ p.price }}</b>
            <button type="button" class="btn" :disabled="!p.in_stock || (cart[p.slug] || 0) >= maxFor(p)" @click.prevent="add(p)">
              {{ (cart[p.slug] || 0) > 0 ? `In basket · ${cart[p.slug]}` : 'Add to basket' }}
            </button>
          </div>
        </article>
      </div>

      <nav v-if="totalPages > 1" class="pager" aria-label="Shop pages">
        <button data-olx-field="textC" type="button" :disabled="page <= 1" @click="goTo(page - 1)">{{ oluxCms.t('Text C', oluxFb['Text C']) }}</button>
        <button v-for="n in totalPages" :key="n" type="button" :class="{ on: n === page }" @click="goTo(n)">{{ n }}</button>
        <button data-olx-field="textD" type="button" :disabled="page >= totalPages" @click="goTo(page + 1)">{{ oluxCms.t('Text D', oluxFb['Text D']) }}</button>
      </nav>
        </div><!-- /shop-main -->
      </div><!-- /shop-body -->
    </div>

    <CartDrawer />
  </section>
</template>

<style scoped>
/* Styled to the v2 baby-blue theme tokens (see public/assets/stylesheets/styles.css). */
.shop { padding: var(--section-pad-y, 96px) 0; }
.lead { color: var(--color-muted); max-width: 620px; margin: 0 0 40px; }
h2 { font-size: 2.9rem; margin: .5rem 0 .8rem; }
.note { padding: 12px 18px; border-radius: var(--radius); background: var(--color-surface-2); display: inline-block; margin-bottom: 20px; }
.note.ok { background: rgba(46,160,67,.12); color: #1e5c2c; }
.grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 26px; scroll-margin-top: 110px; }
.card { background: #fff; border: 1px solid rgba(20,36,46,.14); border-radius: var(--radius); padding: 18px; display: flex; flex-direction: column; transition: box-shadow .3s ease, border-color .3s ease; }
.card:hover { border-color: var(--color-primary-soft); box-shadow: 0 18px 34px -24px rgba(15,47,71,.4); }
/* Oval imagery — same rounded look as the home page's hero/stats photos:
   taller portrait ratio, elliptical corners, and a fine offset ring. */
.pic { aspect-ratio: 4/5; border-radius: 50% / 42%; overflow: hidden; background: var(--color-surface-2);
  position: relative; display: grid; place-items: center; margin: 6px 6px 16px;
  outline: 1px solid color-mix(in srgb, currentColor 25%, transparent); outline-offset: 6px; }
.pic img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
.card:hover .pic img { transform: scale(1.04); }
.ph { font-size: 42px; }
.badge { position: absolute; top: 14px; right: 22px; font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; background: #fff; }
.badge.out { color: #c0392b; }
.badge.low { color: #b7791f; }
.cats { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 30px; }
.cats button { border: 1px solid rgba(20,36,46,.2); background: #fff; color: var(--color-secondary); border-radius: 999px; padding: 8px 16px; font-family: var(--font-default); font-size: .72rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; cursor: pointer; transition: all .25s ease; }
.cats button.on { background: var(--color-secondary); color: #fff; border-color: var(--color-secondary); }
.cat { font-size: .68rem; font-weight: 600; letter-spacing: .16em; text-transform: uppercase; color: var(--color-primary); margin: 0 0 4px; }
.card h3 { font-size: 1.25rem; margin: 0 0 4px; }
.card h3 a:hover { color: var(--color-primary); }
.stars { font-size: .78rem; font-weight: 600; color: #b7791f; margin: 0 0 4px; }
.stars span { font-weight: 400; color: var(--color-muted); }
.desc { font-size: .88rem; color: var(--color-muted); flex: 1; }
.row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; }
.row b { color: var(--color-secondary); font-size: 1.05rem; }
.btn { border: 1px solid var(--color-secondary); border-radius: var(--radius); padding: .7rem 1.2rem; font-family: var(--font-default); font-weight: 500; font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; cursor: pointer; background: var(--color-secondary); color: #fff; transition: background .3s ease, border-color .3s ease, color .3s ease; }
.btn:hover:not(:disabled) { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
.btn:disabled { opacity: .45; cursor: default; }
.pager { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 44px; }
.pager button { min-width: 40px; border: 1px solid rgba(20,36,46,.2); background: #fff; color: var(--color-secondary); border-radius: 999px; padding: 8px 14px; font-family: var(--font-default); font-size: .78rem; font-weight: 600; cursor: pointer; transition: all .25s ease; }
.pager button.on { background: var(--color-secondary); color: #fff; border-color: var(--color-secondary); }
.pager button:hover:not(:disabled):not(.on) { border-color: var(--color-primary); color: var(--color-primary); }
.pager button:disabled { opacity: .4; cursor: default; }
@media (max-width: 980px) { .grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }

/* ── Filter sidebar ─────────────────────────────────────────────── */
.shop-body { display: flex; gap: 28px; align-items: flex-start; }
.shop-main { flex: 1; min-width: 0; }
.filters { width: 210px; flex-shrink: 0; position: sticky; top: 90px; }
.fhead { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.fhead b { font-size: 14px; }
.fclear { margin-left: auto; border: 0; background: none; font: 600 12px/1 inherit; cursor: pointer; opacity: .6; text-decoration: underline; }
.fclose { display: none; border: 0; background: none; font-size: 15px; cursor: pointer; opacity: .6; }
.fgroup { margin-bottom: 18px; }
.ftitle { font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; opacity: .5; margin: 0 0 8px; }
.fgroup > button { display: block; width: 100%; text-align: left; background: none; border: 0; padding: 6px 8px;
  border-radius: 8px; font: 500 13px/1.3 inherit; color: inherit; cursor: pointer; opacity: .75; }
.fgroup > button:hover { opacity: 1; background: rgba(0,0,0,.04); }
.fgroup > button.on { opacity: 1; font-weight: 700; background: rgba(0,0,0,.06); }
.ftags { display: flex; flex-wrap: wrap; gap: 6px; }
.ftags button { border: 1px dashed rgba(0,0,0,.25); background: none; border-radius: 999px; padding: 4px 10px;
  font: 600 11px/1 inherit; color: inherit; cursor: pointer; opacity: .75; }
.ftags button.on { opacity: 1; background: rgba(0,0,0,.08); border-style: solid; }
.filter-toggle { display: none; align-items: center; gap: 8px; margin-bottom: 16px; padding: 9px 16px;
  border: 1px solid rgba(0,0,0,.2); border-radius: 999px; background: none; font: 700 13px/1 inherit;
  color: inherit; cursor: pointer; }
.filter-toggle svg { width: 16px; height: 16px; }
.fcount { background: currentColor; border-radius: 999px; min-width: 18px; height: 18px; display: inline-flex;
  align-items: center; justify-content: center; }
.fcount { color: #fff; background: #1d1d1f; font-size: 11px; padding: 0 5px; }
@media (max-width: 768px) {
  .filter-toggle { display: inline-flex; }
  .filters { display: none; position: fixed; inset: 0 auto 0 0; width: min(280px, 85vw); z-index: 70;
    background: #fff; color: #1d1d1f; padding: 20px; overflow-y: auto; box-shadow: 8px 0 30px rgba(0,0,0,.2); }
  .filters.open { display: block; }
  .fclose { display: block; margin-left: 4px; }
}
</style>
