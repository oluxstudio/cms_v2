<script setup lang="ts">
// Product detail page — live CMS store product, reviews, related products.
import { ref, computed, onMounted } from 'vue'
import type { ShopProduct } from '../composables/useCart'

const route = useRoute()
const slug = computed(() => String(route.params.slug || ''))

const { api, cart, maxFor, add, registerProducts, beacon } = useCart()

const product = ref<ShopProduct | null>(null)
const loading = ref(true)
const missing = ref(false)
const qty = ref(1)

// Related products ("You might also like") — same category first.
const all = ref<ShopProduct[]>([])
const related = computed(() => {
  const p = product.value
  if (!p) return []
  const others = all.value.filter(x => x.slug !== p.slug)
  const same = others.filter(x => x.category && x.category === p.category)
  return [...same, ...others.filter(x => !same.includes(x))].slice(0, 4)
})

// ── Reviews ──────────────────────────────────────────────────────────────
type Review = { name: string; rating: number; body: string; date: string }
const reviews = ref<Review[]>([])
const avg = ref<number | null>(null)
const reviewCount = computed(() => reviews.value.length)
const bars = computed(() => {
  const out = [5, 4, 3, 2, 1].map(star => ({ star, n: reviews.value.filter(r => r.rating === star).length }))
  const max = Math.max(1, ...out.map(o => o.n))
  return out.map(o => ({ ...o, pct: Math.round((o.n / max) * 100) }))
})
const rForm = ref({ name: '', email: '', rating: 5, body: '' })
const rSending = ref(false)
const rNotice = ref('')
const rError = ref('')
async function submitReview() {
  if (rSending.value || !product.value) return
  rSending.value = true
  rError.value = ''
  try {
    const res: any = await $fetch(`${api}/products/${encodeURIComponent(slug.value)}/reviews`, {
      method: 'POST',
      body: { name: rForm.value.name, email: rForm.value.email || undefined, rating: rForm.value.rating, body: rForm.value.body },
    })
    rNotice.value = res?.message || 'Thanks — your review is awaiting approval.'
    rForm.value = { name: '', email: '', rating: 5, body: '' }
  } catch (e: any) {
    rError.value = e?.data?.message || (e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : 'Could not send your review — please try again.')
  } finally {
    rSending.value = false
  }
}

const inBasket = computed(() => (product.value ? cart.value[product.value.slug] || 0 : 0))
const canAdd = computed(() => !!product.value && product.value.in_stock && inBasket.value < maxFor(product.value))
function addToBasket() {
  if (!product.value) return
  add(product.value, qty.value)
  qty.value = 1
}

onMounted(async () => {
  try {
    const res: any = await $fetch(`${api}/products/${encodeURIComponent(slug.value)}`)
    product.value = res?.product || null
  } catch { missing.value = true }
  loading.value = false
  if (product.value) {
    registerProducts([product.value])
    useHead({ title: `${product.value.name} — Shop` })
    beacon(product.value.slug, 'view')
    // Reviews + related in the background.
    $fetch(`${api}/products/${encodeURIComponent(slug.value)}/reviews`).then((r: any) => {
      reviews.value = Array.isArray(r?.reviews) ? r.reviews : []
      avg.value = r?.summary?.average ?? null
    }).catch(() => {})
    $fetch(`${api}/products`).then((r: any) => {
      all.value = Array.isArray(r?.products) ? r.products : []
      registerProducts(all.value) // basket lines from other pages need these
    }).catch(() => {})
  } else {
    missing.value = true
  }
})
</script>

<template>
  <section class="product" data-olx-skip>
    <div class="container">
      <p v-if="loading" class="note">Loading product…</p>

      <template v-else-if="missing || !product">
        <nav class="crumbs"><NuxtLink to="/">Home</NuxtLink><span>/</span><NuxtLink to="/shop">Shop</NuxtLink></nav>
        <p class="note">This product is no longer available.</p>
        <NuxtLink class="btn ghosted" to="/shop">← Back to the shop</NuxtLink>
      </template>

      <template v-else>
        <nav class="crumbs">
          <NuxtLink to="/">Home</NuxtLink><span>/</span>
          <NuxtLink to="/shop">Shop</NuxtLink><span>/</span>
          <b>{{ product.name }}</b>
        </nav>

        <div class="detail">
          <div class="pic">
            <img v-if="product.image" :src="product.image" :alt="product.name">
            <span v-else class="ph">🛍️</span>
            <span v-if="!product.in_stock" class="badge out">Out of stock</span>
            <span v-else-if="product.inventory !== null && product.inventory <= 5" class="badge low">Only {{ product.inventory }} left</span>
          </div>

          <div class="info">
            <p v-if="product.category" class="cat">{{ product.category }}</p>
            <h1>{{ product.name }}</h1>
            <p v-if="product.reviews_enabled === true && avg" class="stars">★ {{ avg }} <span>· {{ reviewCount }} review{{ reviewCount === 1 ? '' : 's' }}</span></p>
            <p class="price">{{ product.price }}</p>
            <p class="desc">{{ product.description }}</p>

            <div class="buy">
              <span class="qty">
                <button type="button" :disabled="qty <= 1" @click="qty--">−</button>
                <b>{{ qty }}</b>
                <button type="button" :disabled="!product.in_stock || qty >= maxFor(product) - inBasket" @click="qty++">＋</button>
              </span>
              <button type="button" class="btn" :disabled="!canAdd" @click="addToBasket">
                {{ product.in_stock ? (inBasket ? `Add more · ${inBasket} in basket` : 'Add to basket') : 'Out of stock' }}
              </button>
            </div>

            <ul v-if="product.tags?.length" class="tags"><li v-for="t in product.tags" :key="t">{{ t }}</li></ul>
          </div>
        </div>

        <!-- Rating & reviews — only when the CMS product has reviews enabled -->
        <div v-if="product.reviews_enabled === true" class="reviews">
          <h2>Rating &amp; reviews</h2>
          <div class="rev-grid">
            <div class="score">
              <b class="big">{{ avg ?? '–' }}</b><span class="of">/5</span>
              <p>{{ reviewCount }} review{{ reviewCount === 1 ? '' : 's' }}</p>
              <div class="bars">
                <div v-for="b in bars" :key="b.star" class="bar">
                  <span>★ {{ b.star }}</span><i><em :style="{ width: b.pct + '%' }" /></i><span class="n">{{ b.n }}</span>
                </div>
              </div>
            </div>

            <div class="rev-list">
              <p v-if="!reviews.length" class="note">No reviews yet — be the first to write one.</p>
              <article v-for="(r, i) in reviews" :key="i" class="rev">
                <header><b>{{ r.name }}</b><span class="rstars">{{ '★'.repeat(r.rating) }}{{ '☆'.repeat(5 - r.rating) }}</span><time>{{ r.date }}</time></header>
                <p>{{ r.body }}</p>
              </article>
            </div>
          </div>

          <form class="rev-form" @submit.prevent="submitReview">
            <h3>Write a review</h3>
            <p v-if="rNotice" class="note ok">{{ rNotice }}</p>
            <template v-else>
              <div class="row2">
                <input v-model="rForm.name" required maxlength="120" placeholder="Your name">
                <input v-model="rForm.email" type="email" placeholder="Email (optional)">
              </div>
              <div class="pick" role="radiogroup" aria-label="Rating">
                <button v-for="s in [1,2,3,4,5]" :key="s" type="button" :class="{ on: rForm.rating >= s }"
                        :aria-label="`${s} star${s === 1 ? '' : 's'}`" @click="rForm.rating = s">★</button>
              </div>
              <textarea v-model="rForm.body" required maxlength="2000" rows="4" placeholder="What did you think of it?" />
              <p v-if="rError" class="note err">{{ rError }}</p>
              <button type="submit" class="btn" :disabled="rSending">{{ rSending ? 'Sending…' : 'Send review' }}</button>
            </template>
          </form>
        </div>

        <!-- Related -->
        <div v-if="related.length" class="also">
          <h2>You might also like</h2>
          <div class="also-grid">
            <NuxtLink v-for="p in related" :key="p.slug" class="mini" :to="`/shop/${p.slug}`">
              <span class="mini-pic">
                <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy">
                <span v-else class="ph">🛍️</span>
              </span>
              <b>{{ p.name }}</b>
              <span class="mini-price">{{ p.price }}</span>
            </NuxtLink>
          </div>
        </div>
      </template>
    </div>

    <CartDrawer />
  </section>
</template>

<style scoped>
.product { padding: 56px 0 var(--section-pad-y, 96px); }
.note { padding: 12px 18px; border-radius: var(--radius); background: var(--color-surface-2); display: inline-block; margin-bottom: 20px; }
.note.ok { background: rgba(46,160,67,.12); color: #1e5c2c; }
.note.err { background: rgba(220,53,69,.1); color: #90222d; display: block; }
.crumbs { display: flex; gap: 10px; align-items: center; font-size: .8rem; letter-spacing: .06em; text-transform: uppercase; color: var(--color-muted); margin-bottom: 34px; }
.crumbs a:hover { color: var(--color-primary); }
.crumbs b { color: var(--color-secondary); font-weight: 600; }
.detail { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 4fr); gap: 56px; align-items: start; }
.pic { aspect-ratio: 1/1; border-radius: var(--radius); overflow: hidden; background: var(--color-surface-2); position: relative; display: grid; place-items: center; }
.pic img { width: 100%; height: 100%; object-fit: cover; }
.ph { font-size: 56px; }
.badge { position: absolute; top: 12px; right: 12px; font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; padding: 4px 12px; border-radius: 999px; background: #fff; }
.badge.out { color: #c0392b; }
.badge.low { color: #b7791f; }
.cat { font-size: .68rem; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: var(--color-primary); margin-bottom: 8px; }
.info h1 { font-size: 2.6rem; margin-bottom: 8px; }
.stars { font-size: .82rem; font-weight: 600; color: #b7791f; margin-bottom: 8px; }
.stars span { font-weight: 400; color: var(--color-muted); }
.price { font-family: var(--font-heading); font-size: 1.8rem; color: var(--color-secondary); margin: 6px 0 16px; }
.desc { color: var(--color-muted); margin-bottom: 26px; max-width: 48ch; }
.buy { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.qty { display: inline-flex; align-items: center; gap: 12px; border: 1px solid rgba(20,36,46,.2); border-radius: 999px; padding: 8px 14px; }
.qty button { width: 26px; height: 26px; border-radius: 999px; border: 0; background: var(--color-surface-2); cursor: pointer; color: var(--color-secondary); font-size: 1rem; }
.qty button:disabled { opacity: .4; cursor: default; }
.qty b { min-width: 1.4ch; text-align: center; color: var(--color-secondary); }
.btn { border: 1px solid var(--color-secondary); border-radius: var(--radius); padding: .95rem 2rem; font-family: var(--font-default); font-weight: 500; font-size: .74rem; letter-spacing: .16em; text-transform: uppercase; cursor: pointer; background: var(--color-secondary); color: #fff; transition: background .3s ease, border-color .3s ease; }
.btn:hover:not(:disabled) { background: var(--color-primary); border-color: var(--color-primary); }
.btn:disabled { opacity: .45; cursor: default; }
.btn.ghosted { display: inline-block; text-align: center; }
.tags { list-style: none; display: flex; flex-wrap: wrap; gap: 8px; padding: 0; margin-top: 24px; }
.tags li { font-size: .7rem; letter-spacing: .1em; text-transform: uppercase; color: var(--color-muted); border: 1px solid rgba(20,36,46,.14); border-radius: 999px; padding: 4px 12px; }

.reviews { margin-top: 84px; }
.reviews h2, .also h2 { font-size: 2rem; margin-bottom: 26px; }
.rev-grid { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(0, 2fr); gap: 48px; align-items: start; }
.score .big { font-family: var(--font-heading); font-size: 4rem; color: var(--color-secondary); line-height: 1; }
.score .of { color: var(--color-muted); font-size: 1.1rem; margin-left: 4px; }
.score p { color: var(--color-muted); font-size: .85rem; margin: 4px 0 18px; }
.bar { display: grid; grid-template-columns: 3.2em 1fr 2em; align-items: center; gap: 10px; font-size: .78rem; color: var(--color-muted); margin-bottom: 6px; }
.bar i { display: block; height: 6px; border-radius: 999px; background: var(--color-surface-2); overflow: hidden; }
.bar em { display: block; height: 100%; background: var(--color-primary); border-radius: 999px; }
.bar .n { text-align: right; }
.rev { border: 1px solid rgba(20,36,46,.12); border-radius: var(--radius); padding: 18px 20px; margin-bottom: 14px; background: #fff; }
.rev header { display: flex; gap: 12px; align-items: baseline; margin-bottom: 6px; flex-wrap: wrap; }
.rev header b { color: var(--color-secondary); }
.rstars { color: #b7791f; letter-spacing: .1em; font-size: .8rem; }
.rev time { color: var(--color-muted); font-size: .75rem; margin-left: auto; }
.rev p { font-size: .92rem; color: var(--color-default); }
.rev-form { margin-top: 34px; max-width: 560px; }
.rev-form h3 { font-size: 1.3rem; margin-bottom: 14px; }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
.rev-form input, .rev-form textarea { width: 100%; padding: 11px 13px; border-radius: var(--radius); border: 1px solid rgba(20,36,46,.2); font-family: var(--font-default); font-size: .9rem; }
.rev-form textarea { resize: vertical; margin-bottom: 10px; }
.rev-form input:focus, .rev-form textarea:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(35,128,191,.14); }
.pick { margin: 4px 0 10px; }
.pick button { border: 0; background: none; font-size: 1.5rem; color: rgba(20,36,46,.25); cursor: pointer; padding: 0 3px; }
.pick button.on { color: #b7791f; }

.also { margin-top: 84px; }
.also-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; }
.mini { display: flex; flex-direction: column; gap: 8px; background: #fff; border: 1px solid rgba(20,36,46,.14); border-radius: var(--radius); padding: 14px; transition: border-color .3s ease, box-shadow .3s ease; }
.mini:hover { border-color: var(--color-primary-soft); box-shadow: 0 18px 34px -24px rgba(15,47,71,.4); }
.mini-pic { aspect-ratio: 4/3; border-radius: var(--radius); overflow: hidden; background: var(--color-surface-2); display: grid; place-items: center; }
.mini-pic img { width: 100%; height: 100%; object-fit: cover; }
.mini b { color: var(--color-secondary); font-size: .95rem; }
.mini-price { color: var(--color-muted); font-size: .85rem; }

@media (max-width: 980px) {
  .detail { grid-template-columns: 1fr; gap: 32px; }
  /* keep the image compact when the columns stack on small screens */
  .pic { aspect-ratio: 4/3; max-height: 420px; width: 100%; }
  .rev-grid { grid-template-columns: 1fr; gap: 30px; }
  .also-grid { grid-template-columns: repeat(2, 1fr); }
  .info h1 { font-size: 2rem; }
}
@media (max-width: 560px) {
  .also-grid { grid-template-columns: 1fr; }
  .row2 { grid-template-columns: 1fr; }
}
</style>
