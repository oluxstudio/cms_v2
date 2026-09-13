<script setup lang="ts">
const oluxCms = useOluxContent('quote')
const oluxFb: Record<string, string> = {"Subheadline":"Quote requested","Text":"Get another estimate","Subheadline B":"Happy with it? Request this quote"}
// ── Instant quote / estimator block ──────────────────────────────────────
// Visitors answer the owner's estimator fields, the CMS computes a live
// quote server-side, and "Request this quote" files a lead + emails both
// parties. Hidden entirely when the site has no estimators configured.
const { field } = useCms()

import { ref, computed, onMounted } from 'vue'

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
const api = `${apiBase}/api/sites/${encodeURIComponent(siteName)}/estimator`

type EField = { key: string; label: string; type: string; unit?: string | null; required: boolean; options: string[] }
type Estimator = { key: string; name: string; fields: EField[]; has_calculations: boolean }

const estimators = ref<Estimator[]>([])
const current = ref(0)
const est = computed(() => estimators.value[current.value] || null)
const values = ref<Record<string, any>>({})
const results = ref<{ name: string; formatted: string }[]>([])
const available = ref(false)
const loading = ref(true)

// Request-the-quote form
const name = ref(''); const email = ref(''); const phone = ref(''); const notes = ref('')
const sending = ref(false)
const sentRef = ref('')
const errorMessage = ref('')

onMounted(async () => {
  try {
    const res: any = await $fetch(`${api}/config`)
    estimators.value = (res.estimators || []).filter((e: Estimator) => e.fields.length && e.has_calculations)
    available.value = estimators.value.length > 0
  } catch (_) { available.value = false }
  loading.value = false
})

function pick(i: number) {
  current.value = i
  values.value = {}
  results.value = []
  sentRef.value = ''
  errorMessage.value = ''
}

let quoteTimer: any = null
function quoteSoon() { clearTimeout(quoteTimer); quoteTimer = setTimeout(quote, 300) }

async function quote() {
  if (!est.value) return
  try {
    const res: any = await $fetch(`${apiBase}/api/sites/${encodeURIComponent(siteName)}/quote`, {
      method: 'POST', body: { estimator: est.value.key, fields: values.value },
    })
    results.value = res?.results || []
  } catch (_) { results.value = [] }
}

async function request() {
  errorMessage.value = ''
  sending.value = true
  try {
    const res: any = await $fetch(`${api}/request`, {
      method: 'POST',
      body: { estimator: est.value?.key, fields: values.value, name: name.value, email: email.value, phone: phone.value, notes: notes.value },
    })
    sentRef.value = res?.reference || 'OK'
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'We could not send your request — please check your details and try again.'
  }
  sending.value = false
}

function startOver() {
  values.value = {}; results.value = []; sentRef.value = ''
  name.value = ''; email.value = ''; phone.value = ''; notes.value = ''
}
</script>

<template>
  <section class="contact quote-block" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div v-if="!loading && available" class="container">
      <div data-olx-key="quoteIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="eyebrow">{{ field('quoteIntro', 'eyebrow', 'Instant quote') }}</p>
        <h2 data-olx-field="heading">{{ field('quoteIntro', 'heading', 'Get an estimate in seconds') }}</h2>
        <p class="slot-note" data-olx-field="note">{{ field('quoteIntro', 'note', 'Answer a few quick questions — your estimate updates as you type, no obligation.') }}</p>
      </div>

      <form class="appt-form" @submit.prevent="request">
        <!-- estimator picker -->
        <div v-if="estimators.length > 1" class="quote-tabs">
          <button v-for="(e, i) in estimators" :key="e.key" type="button" class="btn ghost"
                  :class="{ on: i === current }" @click="pick(i)">{{ e.name }}</button>
        </div>

        <div v-if="sentRef" class="confirm-panel">
          <div class="confirm-head">
            <span class="confirm-badge">✓</span>
            <div>
              <h4 data-olx-field="subheadline">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h4>
              <p class="slot-note">Reference <b>{{ sentRef }}</b> — a copy is on its way to your email and we'll be in touch shortly.</p>
            </div>
          </div>
          <div class="confirm-actions">
            <button data-olx-field="text" class="btn ghost" type="button" @click="startOver">{{ oluxCms.t('Text', oluxFb['Text']) }}</button>
          </div>
        </div>

        <template v-else>
          <div class="grid">
            <div v-for="f in est?.fields || []" :key="est!.key + f.key">
              <label>{{ f.label }}<span v-if="f.unit"> ({{ f.unit }})</span></label>
              <select v-if="f.type === 'select'" v-model="values[f.key]" :required="f.required" @change="quote">
                <option value="" disabled>Choose…</option>
                <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
              </select>
              <label v-else-if="f.type === 'toggle'" class="quote-toggle">
                <input type="checkbox" v-model="values[f.key]" @change="quote"> Yes
              </label>
              <input v-else :type="f.type === 'number' ? 'number' : 'text'" min="0"
                     v-model="values[f.key]" :required="f.required" @input="quoteSoon">
            </div>
          </div>

          <div v-if="results.length" class="quote-results">
            <div v-for="r in results" :key="r.name" class="quote-line">
              <span>{{ r.name }}</span><b>{{ r.formatted }}</b>
            </div>
          </div>

          <template v-if="results.length">
            <h3 data-olx-field="subheadlineB" style="margin-top:1.2rem">{{ oluxCms.t('Subheadline B', oluxFb['Subheadline B']) }}</h3>
            <div class="grid">
              <div><label>Your name</label><input v-model="name" type="text" required></div>
              <div><label>Email</label><input v-model="email" type="email" required></div>
              <div><label>Phone (optional)</label><input v-model="phone" type="tel"></div>
              <div class="full"><label>Notes (optional)</label><textarea v-model="notes" rows="3"></textarea></div>
            </div>
            <button class="btn" type="submit" :disabled="sending || !name || !email">
              {{ sending ? 'Sending…' : 'Request this quote' }}
            </button>
            <p v-if="errorMessage" class="err">{{ errorMessage }}</p>
          </template>
        </template>
      </form>
    </div>
  </section>
</template>

<style scoped>
.quote-tabs { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.2rem; }
.quote-tabs .btn.on { background: var(--accent, #111); color: #fff; }
.quote-results { margin-top: 1.2rem; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,.08); display: grid; gap: .5rem; }
.quote-line { display: flex; align-items: center; justify-content: space-between; font-size: .95rem; }
.quote-line b { background: #d9f068; color: #2b3110; padding: .25rem .75rem; border-radius: 999px; }
.quote-toggle { display: inline-flex; align-items: center; gap: .5rem; cursor: pointer; }
</style>
