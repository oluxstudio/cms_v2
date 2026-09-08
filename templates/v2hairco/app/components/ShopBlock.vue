<script setup lang="ts">
// Retail shop — products come LIVE from the CMS store; the basket/checkout
// logic is shared with the product page via useCart().
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import type { ShopProduct } from '../composables/useCart'

const { field } = useCms()
const { siteName, api, cart, maxFor, add, registerProducts, beacon, clear } = useCart()

const products = ref<ShopProduct[]>([])
const categories = ref<string[]>([])
const activeCategory = ref('')
const visible = computed(() => activeCategory.value
  ? products.value.filter(p => p.category === activeCategory.value)
  : products.value)
const loading = ref(true)
const notice = ref('')

// ── Pagination (client-side, 12 per page) ────────────────────────────────
const PER_PAGE = 12
const page = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(visible.value.length / PER_PAGE)))
const pageItems = computed(() => visible.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))
watch(activeCategory, () => { page.value = 1 })
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
    const res: any = await $fetch(`${api}/products`)
    products.value = Array.isArray(res?.products) ? res.products : []
    categories.value = Array.isArray(res?.categories) ? res.categories : []
    registerProducts(products.value) // cart lines need names/prices
  } catch { /* leave empty */ }
  loading.value = false
  await nextTick()
  watchCards()
})
</script>

<template>
  <section class="shop" data-olx-key="shop" data-olx-kind="component">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow" data-olx-field="caption">{{ field('shop', 'caption', 'Our products') }}</p>
        <h2 data-olx-field="heading">{{ field('shop', 'heading', 'Take the salon home') }}</h2>
        <p class="lead" data-olx-field="body">{{ field('shop', 'body', 'The same professional products we use at every appointment — delivered to your door.') }}</p>
      </div>

      <p v-if="notice" class="note ok">{{ notice }}</p>

      <p v-if="loading" class="note">Loading products…</p>
      <p v-else-if="!products.length" class="note">The shop is being stocked — check back soon.</p>

      <div v-if="categories.length" class="cats">
        <button type="button" data-olx-skip :class="{ on: !activeCategory }" @click="activeCategory = ''">All</button>
        <button v-for="c in categories" :key="c" type="button" :class="{ on: activeCategory === c }"
                @click="activeCategory = activeCategory === c ? '' : c">{{ c }}</button>
      </div>

      <div v-if="!loading && products.length" class="grid">
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

      <nav v-if="!loading && totalPages > 1" class="pager" aria-label="Shop pages">
        <button type="button" :disabled="page <= 1" @click="goTo(page - 1)">← Prev</button>
        <button v-for="n in totalPages" :key="n" type="button" :class="{ on: n === page }" @click="goTo(n)">{{ n }}</button>
        <button type="button" :disabled="page >= totalPages" @click="goTo(page + 1)">Next →</button>
      </nav>
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
.pic { aspect-ratio: 4/3; border-radius: var(--radius); overflow: hidden; background: var(--color-surface-2); position: relative; display: grid; place-items: center; margin-bottom: 14px; }
.pic img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
.card:hover .pic img { transform: scale(1.04); }
.ph { font-size: 42px; }
.badge { position: absolute; top: 8px; right: 8px; font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; background: #fff; }
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
</style>
