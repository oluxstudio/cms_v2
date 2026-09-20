<script setup lang="ts">
const oluxCms = useOluxContent('donate-giving')
const oluxFb: Record<string, string> = {}
// Giving page — one-off gifts through the site's donation checkout (Stripe).
const { giving } = useSiteContent()
const presets = giving.presets
const amount = ref<number | null>(25)
const custom = ref('')
const form = ref({ name: '', email: '', message: '' })
const sending = ref(false)
const error = ref('')
const thanks = ref(false)

const pick = (v: number) => { amount.value = v; custom.value = '' }
const chosen = computed(() => {
  const c = parseFloat(custom.value)
  return custom.value !== '' && !Number.isNaN(c) ? c : amount.value
})

onMounted(() => {
  // back from a completed checkout → thank the giver
  if (new URLSearchParams(window.location.search).get('gave') === '1') thanks.value = true
})

const give = async () => {
  if (!chosen.value || chosen.value < 1) { error.value = 'Please choose an amount of at least £1.'; return }
  sending.value = true
  error.value = ''
  try {
    const pub: any = (useRuntimeConfig() as any).public || {}
    const site = pub.cmsSite || 'graceway'
    const base = pub.bookingApiBase || window.location.origin
    const url = new URL(window.location.href)
    url.searchParams.set('gave', '1')
    const res = await fetch(`${base}/api/sites/${encodeURIComponent(site)}/donate/checkout`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount: chosen.value, ...form.value, return_url: url.toString() }),
    })
    const body = await res.json().catch(() => ({} as any))
    if (res.ok && body.checkout_url) { window.location.href = body.checkout_url; return }
    error.value = body.message || 'Online giving is unavailable right now — see the other ways to give below.'
  } catch {
    error.value = 'Could not reach the server — please try again.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <section class="giving-page" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="giving-grid">

        <!-- give online -->
        <div class="ct-form-panel giving-panel">
          <span class="ct-chip">{{ giving.chip }}</span>
          <h2 data-olx-field="Headline">{{ giving.title }}</h2>
          <p class="ct-sub">{{ giving.sub }}</p>

          <div v-if="thanks" class="ct-thanks giving-thanks">
            <h3>{{ giving.thanksTitle }}</h3>
            <p>{{ giving.thanksText }}</p>
          </div>

          <form v-else class="ct-form" @submit.prevent="give">
            <div class="giving-amounts" role="group" aria-label="Choose an amount">
              <button v-for="v in presets" :key="v" type="button"
                      class="giving-amt" :class="{ on: chosen === v && custom === '' }" @click="pick(v)">£{{ v }}</button>
              <input v-model="custom" type="number" min="1" step="0.01" placeholder="Other £" class="giving-custom" aria-label="Custom amount">
            </div>
            <div class="row">
              <input v-model="form.name" type="text" placeholder="Name (optional)">
              <input v-model="form.email" type="email" placeholder="Email for your receipt (optional)">
            </div>
            <textarea v-model="form.message" placeholder="A note or a prayer request (optional)"></textarea>
            <p v-if="error" class="form-error">{{ error }}</p>
            <button class="btn ct-send" type="submit" :disabled="sending">
              {{ sending ? 'Preparing secure checkout…' : `Give £${chosen ?? 0} →` }}
            </button>
            <p class="giving-fine">{{ giving.fine }}</p>
          </form>
        </div>

        <!-- other ways to give -->
        <div class="giving-ways">
          <h2>{{ giving.waysTitle }}</h2>
          <div v-for="w in giving.ways" :key="w.title" class="giving-way"><div class="icon">{{ w.icon }}</div><div>
            <h3>{{ w.title }}</h3>
            <p><template v-for="(line, li) in contentLines(w.text)" :key="li"><br v-if="li">{{ line }}</template></p>
          </div></div>
          <blockquote class="giving-verse">{{ giving.verse.text }} <cite>{{ giving.verse.cite }}</cite></blockquote>
        </div>

      </div>
    </div>
  </section>
</template>
