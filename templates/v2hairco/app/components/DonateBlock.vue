<script setup lang="ts">
// ── Donation block ───────────────────────────────────────────────────────
// Visitors pick or type an amount and are sent to Stripe checkout; the CMS
// confirms via webhook. Hidden entirely when donations aren't available.
const { field } = useCms()

import { ref, onMounted } from 'vue'

const formSite = (useRuntimeConfig().public as any).cmsSite || 'v2-hairco'

function resolveSite(): string {
  if (typeof window === 'undefined') return formSite
  const q = new URLSearchParams(window.location.search)
  const fromUrl = q.get('booking') || q.get('site') || ''
  if (fromUrl) { try { sessionStorage.setItem('olux-booking-site', fromUrl) } catch (_) {} return fromUrl }
  try { const cached = sessionStorage.getItem('olux-booking-site'); if (cached) return cached } catch (_) {}
  return formSite
}

const siteName = resolveSite()
const origin = typeof window !== 'undefined' ? window.location.origin : ''
const cmsServed = typeof window !== 'undefined' && window.location.pathname.startsWith('/nuxt-preview/')
const apiBase = cmsServed ? origin : ((useRuntimeConfig().public.bookingApiBase || '').replace(/\/$/, '') || origin)
const api = `${apiBase}/api/sites/${encodeURIComponent(siteName)}/donate`

const loading = ref(true)
const available = ref(false)
const suggested = ref<number[]>([5, 10, 25, 50])
const symbol = ref('£')
const amount = ref('')
const name = ref(''); const email = ref(''); const message = ref('')
const sending = ref(false)
const errorMessage = ref('')

onMounted(async () => {
  try {
    const res: any = await $fetch(`${api}/config`)
    available.value = !!res.available
    if (res.suggested?.length) { suggested.value = res.suggested; amount.value = String(res.suggested[0]) }
    symbol.value = { gbp: '£', usd: '$', eur: '€', cad: 'CA$', aud: 'A$' }[res.currency] || '£'
  } catch (_) { available.value = false }
  loading.value = false
})

async function donate() {
  errorMessage.value = ''
  sending.value = true
  try {
    const res: any = await $fetch(`${api}/checkout`, {
      method: 'POST',
      body: { amount: parseFloat(amount.value), name: name.value, email: email.value, message: message.value,
              return_url: typeof window !== 'undefined' ? window.location.href : undefined },
    })
    if (res.checkout_url) { window.location.href = res.checkout_url; return }
    errorMessage.value = 'Could not start the donation — please try again.'
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Could not start the donation — please try again.'
  }
  sending.value = false
}
</script>

<template>
  <section class="contact donate-blk">
    <div v-if="!loading && available" class="container">
      <div data-olx-key="donateIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="eyebrow">{{ field('donateIntro', 'eyebrow', 'Donations') }}</p>
        <h2 data-olx-field="heading">{{ field('donateIntro', 'heading', 'Support our work') }}</h2>
        <p class="slot-note" data-olx-field="note">{{ field('donateIntro', 'note', 'Every contribution helps — thank you for keeping us going.') }}</p>
      </div>

      <form class="appt-form" @submit.prevent="donate">
        <div class="donate-amounts">
          <button v-for="amt in suggested" :key="amt" type="button" class="btn ghost"
                  :class="{ on: amount === String(amt) }" @click="amount = String(amt)">{{ symbol }}{{ amt }}</button>
        </div>

        <div class="grid">
          <div><label>Amount ({{ symbol }})</label>
            <input v-model="amount" type="number" step="0.01" min="1" required></div>
          <div><label>Your name (optional)</label><input v-model="name" type="text"></div>
          <div><label>Email for your receipt (optional)</label><input v-model="email" type="email"></div>
          <div class="full"><label>Leave a message (optional)</label><textarea v-model="message" rows="2"></textarea></div>
        </div>

        <button class="btn" type="submit" :disabled="sending || !amount">
          {{ sending ? 'One moment…' : `Donate ${symbol}${amount || ''}` }}
        </button>
        <p v-if="errorMessage" class="err">{{ errorMessage }}</p>
      </form>
    </div>
  </section>
</template>

<style scoped>
.donate-amounts { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.2rem; }
.donate-amounts .btn.on { background: var(--accent, #111); color: #fff; }
</style>
