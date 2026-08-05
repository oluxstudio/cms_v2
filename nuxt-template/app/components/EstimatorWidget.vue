<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'

/**
 * Estimator widget — instant cost + completion-time quotes for trade services
 * (cleaner, landscaper, laundry, carpenter, mover, builder, plumber,
 * electrician). All figures come from the SERVER (config/estimator.php +
 * per-site scaling) — nothing is priced client-side. Visitors can email
 * themselves the estimate, which also alerts the site owner.
 */
const props = defineProps<{ siteName: string; trade?: string; headline?: string; intro?: string }>()

const api = (useRuntimeConfig().public.apiBase || '/').replace(/\/$/, '')
const base = `${api}/api/sites/${encodeURIComponent(props.siteName)}/estimator`

type Input = { key: string; label: string; type: 'number' | 'select' | 'toggle'; unit?: string; min?: number; max?: number; default?: number; options?: { key: string; label: string }[] }
type Trade = { key: string; name: string; icon: string; inputs: Input[] }
type Quote = { cost_label: string; completion: string; hours: number; breakdown: { label: string; amount_cents: number }[] }

const trades = ref<Trade[]>([])
const loading = ref(true)
const chosen = ref('')
const values = reactive<Record<string, any>>({})
const quote = ref<Quote | null>(null)
const quoting = ref(false)

const lead = reactive({ name: '', email: '', phone: '', notes: '' })
const leadOpen = ref(false)
const status = ref<'idle' | 'saving' | 'done' | 'error'>('idle')
const message = ref('')
const reference = ref('')

const activeTrade = computed(() => trades.value.find(t => t.key === chosen.value))

function resetInputs() {
  const t = activeTrade.value
  if (!t) return
  for (const k of Object.keys(values)) delete values[k]
  for (const i of t.inputs) values[i.key] = i.type === 'number' ? (i.default ?? i.min ?? 0) : (i.type === 'select' ? i.options?.[0]?.key : false)
}

onMounted(async () => {
  try {
    const res: any = await $fetch(`${base}/config`)
    trades.value = res.trades || []
    chosen.value = (props.trade && trades.value.some(t => t.key === props.trade)) ? props.trade! : (trades.value[0]?.key || '')
    resetInputs()
  } catch (_) { trades.value = [] }
  loading.value = false
})

watch(chosen, () => { resetInputs(); quote.value = null; leadOpen.value = false; status.value = 'idle' })

// Live server quote, debounced as inputs change.
let timer: any = null
watch([values, chosen], () => {
  if (!chosen.value) return
  clearTimeout(timer)
  timer = setTimeout(fetchQuote, 350)
}, { deep: true })

async function fetchQuote() {
  if (!activeTrade.value) return
  quoting.value = true
  try {
    quote.value = await $fetch(base, { method: 'POST', body: { trade: chosen.value, inputs: { ...values } } })
  } catch (_) { quote.value = null }
  quoting.value = false
}

async function sendLead() {
  status.value = 'saving'
  try {
    const res: any = await $fetch(`${base}/request`, {
      method: 'POST',
      body: { trade: chosen.value, inputs: { ...values }, ...lead },
    })
    status.value = 'done'
    reference.value = res.reference || ''
    message.value = res.message || 'Estimate saved — check your inbox.'
  } catch (e: any) {
    status.value = 'error'
    message.value = e?.data?.message || 'Could not save the estimate — please try again.'
  }
}
</script>

<template>
  <section class="est">
    <div class="est-head" v-if="headline || intro">
      <h2 v-if="headline" class="est-title">{{ headline }}</h2>
      <p v-if="intro" class="est-intro">{{ intro }}</p>
    </div>

    <div v-if="loading" class="est-empty">Loading…</div>
    <div v-else-if="!trades.length" class="est-empty">The estimator isn’t available right now.</div>

    <template v-else>
      <!-- trade picker (hidden when locked to one trade) -->
      <div v-if="!props.trade || trades.length === 1" class="est-trades">
        <button v-for="t in trades" :key="t.key" type="button"
                class="est-trade" :class="{ active: chosen === t.key }" @click="chosen = t.key">
          <span class="est-trade-icon">{{ t.icon }}</span>
          <span>{{ t.name }}</span>
        </button>
      </div>

      <div class="est-body" v-if="activeTrade">
        <!-- inputs -->
        <div class="est-inputs">
          <div v-for="i in activeTrade.inputs" :key="i.key" class="est-field">
            <template v-if="i.type === 'number'">
              <label>{{ i.label }}<span v-if="i.unit" class="est-unit"> ({{ i.unit }})</span></label>
              <div class="est-stepper">
                <button type="button" @click="values[i.key] = Math.max(i.min ?? 0, (values[i.key] || 0) - 1)">−</button>
                <input type="number" v-model.number="values[i.key]" :min="i.min" :max="i.max">
                <button type="button" @click="values[i.key] = Math.min(i.max ?? 999, (values[i.key] || 0) + 1)">+</button>
              </div>
            </template>
            <template v-else-if="i.type === 'select'">
              <label>{{ i.label }}</label>
              <select v-model="values[i.key]">
                <option v-for="o in i.options" :key="o.key" :value="o.key">{{ o.label }}</option>
              </select>
            </template>
            <label v-else class="est-toggle">
              <input type="checkbox" v-model="values[i.key]">
              <span>{{ i.label }}</span>
            </label>
          </div>
        </div>

        <!-- quote card -->
        <div class="est-quote" :class="{ dim: quoting }">
          <template v-if="quote">
            <p class="est-quote-label">Estimated cost</p>
            <p class="est-cost">{{ quote.cost_label }}</p>
            <p class="est-completion">🕒 {{ quote.completion }}</p>
            <ul class="est-breakdown">
              <li v-for="(b, i) in quote.breakdown" :key="i">{{ b.label }}</li>
            </ul>

            <div v-if="status === 'done'" class="est-done">
              ✓ {{ message }} <b v-if="reference">Ref {{ reference }}</b>
            </div>
            <template v-else>
              <button v-if="!leadOpen" type="button" class="est-btn" @click="leadOpen = true">Email me this estimate</button>
              <form v-else class="est-lead" @submit.prevent="sendLead">
                <input v-model="lead.name" type="text" placeholder="Your name" required>
                <input v-model="lead.email" type="email" placeholder="you@example.com" required>
                <input v-model="lead.phone" type="tel" placeholder="Phone (optional)">
                <textarea v-model="lead.notes" rows="2" placeholder="Anything we should know? (optional)"></textarea>
                <button type="submit" class="est-btn" :disabled="status === 'saving'">
                  {{ status === 'saving' ? 'Sending…' : 'Send my estimate' }}
                </button>
                <p v-if="status === 'error'" class="est-err">{{ message }}</p>
              </form>
            </template>
          </template>
          <p v-else class="est-empty">Adjust the options to see your estimate.</p>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.est { max-width: 880px; margin: 0 auto; }
.est-head { text-align: center; margin-bottom: 1.6rem; }
.est-title { font-size: 1.7rem; }
.est-intro { color: var(--muted, #6b7280); margin-top: .3rem; }
.est-empty { text-align: center; color: var(--muted, #6b7280); padding: 1.4rem 0; }

.est-trades { display: flex; flex-wrap: wrap; gap: .55rem; justify-content: center; margin-bottom: 1.4rem; }
.est-trade { display: inline-flex; align-items: center; gap: .45rem; font: inherit; font-size: .88rem; font-weight: 600;
  padding: .55rem 1rem; border-radius: 999px; cursor: pointer; background: #fff; color: inherit;
  border: 1px solid var(--border, #e5e7eb); transition: border-color .2s, background .2s, transform .2s; }
.est-trade:hover { border-color: var(--accent, #6366f1); transform: translateY(-1px); }
.est-trade.active { background: var(--accent, #6366f1); border-color: var(--accent, #6366f1); color: #fff; }
.est-trade-icon { font-size: 1.05rem; }

.est-body { display: grid; grid-template-columns: 1.1fr .9fr; gap: 1.4rem; align-items: start; }
@media (max-width: 720px) { .est-body { grid-template-columns: 1fr; } }

.est-inputs { display: grid; gap: 1rem; background: #fff; border: 1px solid var(--border, #e5e7eb);
  border-radius: var(--radius, 14px); padding: 1.3rem; }
.est-field label { display: block; font-size: .82rem; font-weight: 600; margin-bottom: .35rem; }
.est-unit { color: var(--muted, #6b7280); font-weight: 400; }
.est-stepper { display: flex; align-items: stretch; gap: .4rem; }
.est-stepper button { width: 38px; border-radius: 10px; border: 1px solid var(--border, #e5e7eb); background: #fff;
  font-size: 1.05rem; cursor: pointer; }
.est-stepper button:hover { border-color: var(--accent, #6366f1); color: var(--accent, #6366f1); }
.est-stepper input { width: 100%; text-align: center; font: inherit; padding: .5rem; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; }
.est-field select { width: 100%; font: inherit; padding: .55rem .7rem; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; background: #fff; }
.est-toggle { display: flex !important; align-items: center; gap: .55rem; cursor: pointer; font-weight: 600; font-size: .88rem; }
.est-toggle input { width: 17px; height: 17px; accent-color: var(--accent, #6366f1); }

.est-quote { background: var(--navy, #0c1a3e); color: #fff; border-radius: var(--radius, 14px); padding: 1.5rem; transition: opacity .2s; }
.est-quote.dim { opacity: .55; }
.est-quote-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .12em; color: rgba(255,255,255,.6); }
.est-cost { font-size: 1.85rem; font-weight: 800; margin: .25rem 0 .3rem; }
.est-completion { font-size: .92rem; color: rgba(255,255,255,.85); margin-bottom: .8rem; }
.est-breakdown { list-style: none; padding: .8rem 0 0; margin: 0 0 1rem; border-top: 1px solid rgba(255,255,255,.15);
  display: grid; gap: .3rem; font-size: .82rem; color: rgba(255,255,255,.75); }
.est-btn { width: 100%; font: inherit; font-weight: 700; font-size: .9rem; padding: .75rem 1rem; border: 0;
  border-radius: 10px; background: var(--accent, #6366f1); color: #fff; cursor: pointer; transition: filter .2s; }
.est-btn:hover { filter: brightness(1.08); }
.est-btn:disabled { opacity: .6; cursor: wait; }
.est-lead { display: grid; gap: .55rem; }
.est-lead input, .est-lead textarea { font: inherit; font-size: .88rem; padding: .6rem .75rem; border-radius: 10px;
  border: 1px solid rgba(255,255,255,.25); background: rgba(255,255,255,.08); color: #fff; }
.est-lead input::placeholder, .est-lead textarea::placeholder { color: rgba(255,255,255,.5); }
.est-done { background: rgba(52,199,123,.15); border: 1px solid rgba(52,199,123,.4); color: #b7f0d3;
  border-radius: 10px; padding: .8rem 1rem; font-size: .88rem; }
.est-err { color: #fca5a5; font-size: .82rem; }
</style>
