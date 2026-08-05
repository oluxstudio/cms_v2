<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'

/**
 * Kind-aware booking widget — one component, three archetypes (service.kind):
 *   slot — calendar + free start times (barber/salon/mechanic)
 *   stay — date-range pick on the calendar + guests/units (rooms/houses)
 *   trip — departure list + seat quantity (bus/car transport)
 * Paid services return a Stripe checkout_url — the browser follows it.
 */
const props = defineProps<{ siteName: string; service?: string; headline?: string; intro?: string }>()

const api = (useRuntimeConfig().public.apiBase || '/').replace(/\/$/, '')
const base = `${api}/api/sites/${encodeURIComponent(props.siteName)}/booking`

type Svc = {
  slug: string; name: string; kind: 'slot' | 'stay' | 'trip'
  resources?: { id: number; name: string; price_cents?: number | null }[]; resource_noun?: string
  form_fields?: { key: string; label: string; type: string; required?: boolean; options?: string[] }[]
  type?: string; icon?: string; deposit_cents?: { fixed?: number|null; pct?: number|null } | null
  duration: number; price: string; price_cents: number; currency: string
  requires_payment: boolean; capacity: number; description?: string; config?: any
}
type Slot = { iso: string; label: string }
type Departure = { id: number; origin: string; destination: string; departs_at: string; departs_label: string; seats_left: number; price_cents: number }

const services = ref<Svc[]>([])
const weekdays = ref<number[]>([1, 2, 3, 4, 5])
const horizonDays = ref(30)
const loading = ref(true)

const chosenService = ref<string>('')
const view = ref(startOfMonth(new Date()))
const selectedDate = ref<string>('')   // slot: YYYY-MM-DD
const slots = ref<Slot[]>([])
const slotsLoading = ref(false)
const selectedSlot = ref<string>('')

// stay state
const stayIn = ref<string>('')
const stayOut = ref<string>('')
const stayGuests = ref(1)
const stayUnits = ref(1)
const stayDays = ref<Record<string, number>>({})  // Y-m-d => units_left
const stayQuote = ref<any>(null)
const stayQuoteLoading = ref(false)

// trip state
const departures = ref<Departure[]>([])
const depsLoading = ref(false)
const selectedDeparture = ref<number | null>(null)
const tripQty = ref(1)

// named resource (staff / room) — 0 = any
const selectedResource = ref(0)

const form = reactive({ name: '', email: '', phone: '', notes: '' })
const customFields = reactive<Record<string, string>>({})
const status = ref<'idle' | 'saving' | 'done' | 'error'>('idle')
const message = ref('')
const confirmation = ref<any>(null)

const activeService = computed(() => services.value.find(s => s.slug === chosenService.value))
const kind = computed(() => activeService.value?.kind || 'slot')

function money(cents: number, currency?: string) {
  const code = (currency || activeService.value?.currency || 'gbp').toLowerCase()
  const CUR: Record<string, [string, number]> = {gbp:['£',0],usd:['$',0],eur:['€',1],ngn:['₦',0],cad:['CA$',0],aud:['A$',0],jpy:['¥',0],chf:['CHF',0],inr:['₹',0],zar:['R',0],kes:['KSh',0],ghs:['GH₵',0],sek:['kr',1],nok:['kr',1],dkk:['kr',1],pln:['zł',1],brl:['R$',0],mxn:['MX$',0],aed:['AED',1]}
  const amt = (cents / 100).toFixed(2)
  const m = CUR[code]
  return m ? (m[1] ? amt + ' ' + m[0] : m[0] + amt) : amt + ' ' + code.toUpperCase()
}

function startOfMonth(d: Date) { return new Date(d.getFullYear(), d.getMonth(), 1) }
function ymd(d: Date) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` }

const today = new Date(); today.setHours(0, 0, 0, 0)
const horizonEnd = computed(() => {
  const days = kind.value === 'stay' ? (activeService.value?.config?.horizon_days || 365) : horizonDays.value
  const d = new Date(today); d.setDate(d.getDate() + days); return d
})

const monthLabel = computed(() => view.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }))

// 6-week grid starting on Sunday.
const grid = computed(() => {
  const first = startOfMonth(view.value)
  const start = new Date(first); start.setDate(1 - first.getDay())
  return Array.from({ length: 42 }, (_, i) => {
    const d = new Date(start); d.setDate(start.getDate() + i)
    return d
  })
})

function isBookable(d: Date) {
  const day = new Date(d); day.setHours(0, 0, 0, 0)
  if (day < today || day > horizonEnd.value) return false
  if (kind.value === 'stay') {
    const left = stayDays.value[ymd(day)]
    return left === undefined || left > 0 // unknown month data → allow, server re-checks
  }
  return weekdays.value.includes(day.getDay())
}
function inMonth(d: Date) { return d.getMonth() === view.value.getMonth() }
function inStayRange(d: Date) {
  if (!stayIn.value) return false
  const k = ymd(d)
  if (!stayOut.value) return k === stayIn.value
  return k >= stayIn.value && k < stayOut.value
}

function prevMonth() { const d = new Date(view.value); d.setMonth(d.getMonth() - 1); if (d >= startOfMonth(today)) view.value = d }
function nextMonth() { const d = new Date(view.value); d.setMonth(d.getMonth() + 1); if (d <= horizonEnd.value) view.value = d }

async function pickDay(d: Date) {
  if (!isBookable(d) || !chosenService.value) return
  if (kind.value === 'stay') return pickStayDay(d)

  selectedDate.value = ymd(d)
  selectedSlot.value = ''
  slots.value = []
  slotsLoading.value = true
  try {
    const res: any = await $fetch(`${base}/availability`, { params: { service: chosenService.value, date: selectedDate.value, ...(selectedResource.value ? { resource: selectedResource.value } : {}) } })
    slots.value = res.slots || []
  } catch (_) { slots.value = [] }
  slotsLoading.value = false
}

// stay: first click = check-in, second (later) click = check-out.
async function pickStayDay(d: Date) {
  const k = ymd(d)
  if (!stayIn.value || stayOut.value || k <= stayIn.value) {
    stayIn.value = k; stayOut.value = ''; stayQuote.value = null
    return
  }
  stayOut.value = k
  await quoteStay()
}

async function quoteStay() {
  if (!stayIn.value || !stayOut.value) return
  stayQuoteLoading.value = true
  stayQuote.value = null
  try {
    stayQuote.value = await $fetch(`${base}/availability`, {
      params: { service: chosenService.value, check_in: stayIn.value, check_out: stayOut.value, units: stayUnits.value, guests: stayGuests.value, ...(selectedResource.value ? { resource: selectedResource.value } : {}) },
    })
  } catch (e: any) {
    stayQuote.value = { available: false, message: e?.data?.message || 'Those dates are not available.' }
  }
  stayQuoteLoading.value = false
}
watch([stayUnits, stayGuests], () => { if (stayOut.value) quoteStay() })

async function loadStayMonth() {
  if (kind.value !== 'stay' || !chosenService.value) return
  try {
    const m = `${view.value.getFullYear()}-${String(view.value.getMonth() + 1).padStart(2, '0')}`
    const res: any = await $fetch(`${base}/availability`, { params: { service: chosenService.value, month: m } })
    stayDays.value = { ...stayDays.value, ...(res.days || {}) }
  } catch (_) { /* keep optimistic */ }
}
watch(view, loadStayMonth)
watch(selectedResource, () => {
  if (kind.value === 'slot' && selectedDate.value) { const d = new Date(selectedDate.value + 'T00:00:00'); pickDay(d) }
  if (kind.value === 'stay' && stayOut.value) quoteStay()
})

async function loadDepartures() {
  if (kind.value !== 'trip' || !chosenService.value) return
  depsLoading.value = true
  try {
    const res: any = await $fetch(`${base}/availability`, { params: { service: chosenService.value } })
    departures.value = res.departures || []
  } catch (_) { departures.value = [] }
  depsLoading.value = false
}

const chosenDeparture = computed(() => departures.value.find(d => d.id === selectedDeparture.value))
const tripTotal = computed(() => chosenDeparture.value ? chosenDeparture.value.price_cents * tripQty.value : 0)

function pickService(slug: string) {
  chosenService.value = slug
  selectedDate.value = ''; slots.value = []; selectedSlot.value = ''
  stayIn.value = ''; stayOut.value = ''; stayQuote.value = null; stayDays.value = {}
  selectedDeparture.value = null; tripQty.value = 1
  selectedResource.value = 0
  if (kind.value === 'stay') loadStayMonth()
  if (kind.value === 'trip') loadDepartures()
}

const readyToSubmit = computed(() => {
  if (kind.value === 'stay') return !!(stayIn.value && stayOut.value && stayQuote.value?.available)
  if (kind.value === 'trip') return !!selectedDeparture.value
  return !!selectedSlot.value
})

async function submit() {
  if (!readyToSubmit.value) { message.value = 'Please complete your selection.'; status.value = 'error'; return }
  status.value = 'saving'; message.value = ''
  const payload: any = { service: chosenService.value, ...form }
  const filled = Object.fromEntries(Object.entries(customFields).filter(([, v]) => v))
  if (Object.keys(filled).length) payload.fields = filled
  if (selectedResource.value) payload.resource_id = selectedResource.value
  if (kind.value === 'stay') Object.assign(payload, { check_in: stayIn.value, check_out: stayOut.value, guests: stayGuests.value, units: stayUnits.value })
  else if (kind.value === 'trip') Object.assign(payload, { departure_id: selectedDeparture.value, qty: tripQty.value })
  else payload.start = selectedSlot.value

  try {
    const res: any = await $fetch(`${base}`, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: payload,
    })
    if (res.checkout_url) { window.location.href = res.checkout_url; return }
    confirmation.value = res
    status.value = 'done'
  } catch (e: any) {
    status.value = 'error'
    message.value = e?.data?.message || 'Could not complete the booking. Please try again.'
  }
}

onMounted(async () => {
  try {
    const cfg: any = await $fetch(`${base}/config`)
    services.value = cfg.services || []
    weekdays.value = (cfg.availability?.weekdays || [1, 2, 3, 4, 5]).filter((n: any) => n !== null)
    horizonDays.value = cfg.availability?.horizonDays || 30
    // Lock to a specific service if provided, else default to the first.
    chosenService.value = props.service && services.value.some(s => s.slug === props.service)
      ? props.service
      : (services.value[0]?.slug || '')
    if (kind.value === 'stay') loadStayMonth()
    if (kind.value === 'trip') loadDepartures()
  } catch (_) { /* feature off or no services */ }
  loading.value = false
})

const defaultHeadline = computed(() =>
  kind.value === 'stay' ? 'Book your stay' : kind.value === 'trip' ? 'Book your trip' : 'Book an appointment')
</script>

<template>
  <div class="bw">
    <div class="bw-head">
      <h2 class="bw-title">{{ headline || defaultHeadline }}</h2>
      <p v-if="intro" class="bw-intro">{{ intro }}</p>
    </div>

    <div v-if="loading" class="bw-empty">Loading…</div>
    <div v-else-if="!services.length" class="bw-empty">Online booking isn’t available right now.</div>

    <!-- Success -->
    <div v-else-if="status === 'done'" class="bw-success">
      <div class="bw-check">✓</div>
      <h3>Booking received</h3>
      <p v-if="confirmation?.reference">Reference: <strong>{{ confirmation.reference }}</strong></p>
      <p v-if="confirmation?.resource" class="bw-muted">With: <strong>{{ confirmation.resource }}</strong></p>
      <p v-if="confirmation?.balance_cents" class="bw-muted">Balance due at arrival: <strong>{{ money(confirmation.balance_cents) }}</strong></p>
      <p>{{ confirmation?.service }}<span v-if="confirmation?.total && confirmation.total !== 'Free'"> — {{ confirmation.total }}</span></p>
      <p class="bw-muted">A confirmation has been sent to {{ form.email }}.</p>
    </div>

    <div v-else class="bw-grid">
      <!-- Left: service + (calendar | departures) -->
      <div class="bw-col">
        <div v-if="services.length > 1 && !props.service" class="bw-services">
          <button v-for="s in services" :key="s.slug" type="button"
                  class="bw-svc" :class="{ on: s.slug === chosenService }" @click="pickService(s.slug)">
            <span class="bw-svc-name">{{ s.icon }} {{ s.name }}</span>
            <span class="bw-svc-meta">
              <template v-if="s.type">{{ s.type }} · </template>
              <template v-if="s.kind === 'stay'">{{ s.price }}/night</template>
              <template v-else-if="s.kind === 'trip'">from {{ s.price }}</template>
              <template v-else>{{ s.duration }} min · {{ s.price }}</template>
            </span>
          </button>
        </div>
        <div v-else-if="activeService" class="bw-svc on bw-svc-static">
          <span class="bw-svc-name">{{ activeService.name }}</span>
          <span class="bw-svc-meta">
            <template v-if="kind === 'stay'">{{ activeService.price }}/night</template>
            <template v-else-if="kind === 'trip'">from {{ activeService.price }}</template>
            <template v-else>{{ activeService.duration }} min · {{ activeService.price }}</template>
          </span>
        </div>

        <!-- Named resources: staff / rooms — pick one, or Any -->
        <div v-if="(activeService?.resources?.length || 0) > 0 && kind !== 'trip'" class="bw-res">
          <p class="bw-label">Choose {{ activeService?.resource_noun || 'resource' }}</p>
          <div class="bw-res-row">
            <button type="button" class="bw-res-pill" :class="{ on: selectedResource === 0 }" @click="selectedResource = 0">Any</button>
            <button v-for="r in activeService!.resources" :key="r.id" type="button"
                    class="bw-res-pill" :class="{ on: selectedResource === r.id }" @click="selectedResource = r.id">
              {{ r.name }}<span v-if="r.price_cents" class="bw-res-price">from {{ money(r.price_cents) }}</span>
            </button>
          </div>
        </div>

        <!-- Calendar (slot: pick a day · stay: pick check-in then check-out) -->
        <div v-if="kind !== 'trip'" class="bw-cal">
          <div class="bw-cal-head">
            <button type="button" class="bw-nav" @click="prevMonth" aria-label="Previous month">‹</button>
            <span class="bw-month">{{ monthLabel }}</span>
            <button type="button" class="bw-nav" @click="nextMonth" aria-label="Next month">›</button>
          </div>
          <div class="bw-dow"><span v-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d">{{ d }}</span></div>
          <div class="bw-days">
            <button v-for="(d, i) in grid" :key="i" type="button"
                    class="bw-day"
                    :class="{ out: !inMonth(d), on: kind === 'stay' ? inStayRange(d) : selectedDate === ymd(d), free: isBookable(d) }"
                    :disabled="!isBookable(d)"
                    @click="pickDay(d)">{{ d.getDate() }}</button>
          </div>
          <p v-if="kind === 'stay'" class="bw-muted" style="margin-top:.5rem">
            {{ !stayIn ? 'Pick your check-in date.' : (!stayOut ? 'Now pick your check-out date.' : `${stayIn} → ${stayOut}`) }}
          </p>
        </div>

        <!-- Departures (trip) -->
        <div v-else class="bw-deps">
          <p class="bw-label">Departures</p>
          <div v-if="depsLoading" class="bw-muted">Loading departures…</div>
          <div v-else-if="!departures.length" class="bw-muted">No upcoming departures with seats available.</div>
          <button v-for="d in departures" :key="d.id" type="button"
                  class="bw-dep" :class="{ on: selectedDeparture === d.id }" @click="selectedDeparture = d.id">
            <span class="bw-dep-route">{{ d.origin }} → {{ d.destination }}</span>
            <span class="bw-dep-meta">{{ d.departs_label }} · {{ d.seats_left }} seat(s) left · {{ money(d.price_cents) }}</span>
          </button>
        </div>
      </div>

      <!-- Right: (slots | stay quote | trip qty) + customer form -->
      <div class="bw-col">
        <!-- slot -->
        <template v-if="kind === 'slot'">
          <template v-if="selectedDate">
            <p class="bw-label">Available times</p>
            <div v-if="slotsLoading" class="bw-muted">Loading times…</div>
            <div v-else-if="!slots.length" class="bw-muted">No times left on this day — try another.</div>
            <div v-else class="bw-slots">
              <button v-for="s in slots" :key="s.iso" type="button"
                      class="bw-slot" :class="{ on: selectedSlot === s.iso }"
                      @click="selectedSlot = s.iso">{{ s.label }}</button>
            </div>
          </template>
          <p v-else class="bw-muted">Pick a day to see available times.</p>
        </template>

        <!-- stay -->
        <template v-else-if="kind === 'stay'">
          <p class="bw-label">Your stay</p>
          <div class="bw-qty-row">
            <label class="bw-qty">Guests
              <input v-model.number="stayGuests" type="number" min="1" :max="activeService?.config?.max_guests || 10" />
            </label>
            <label v-if="(activeService?.capacity || 1) > 1" class="bw-qty">Units
              <input v-model.number="stayUnits" type="number" min="1" :max="activeService?.capacity || 1" />
            </label>
          </div>
          <div v-if="stayQuoteLoading" class="bw-muted">Checking availability…</div>
          <div v-else-if="stayQuote" class="bw-quote" :class="{ bad: !stayQuote.available }">
            <template v-if="stayQuote.available">
              <span>{{ stayQuote.nights }} night(s) × {{ stayUnits }} unit(s)</span>
              <strong>{{ money(stayQuote.total_cents, stayQuote.currency) }}</strong>
            </template>
            <template v-else>{{ stayQuote.message || 'Not available for those dates.' }}</template>
          </div>
          <p v-else class="bw-muted">Pick your dates on the calendar.</p>
        </template>

        <!-- trip -->
        <template v-else>
          <p class="bw-label">Seats</p>
          <div class="bw-qty-row">
            <label class="bw-qty">Quantity
              <input v-model.number="tripQty" type="number" min="1" :max="chosenDeparture?.seats_left || 1" />
            </label>
          </div>
          <div v-if="chosenDeparture" class="bw-quote">
            <span>{{ tripQty }} seat(s) — {{ chosenDeparture.origin }} → {{ chosenDeparture.destination }}</span>
            <strong>{{ money(tripTotal) }}</strong>
          </div>
          <p v-else class="bw-muted">Pick a departure.</p>
        </template>

        <!-- customer form -->
        <div v-if="readyToSubmit" class="bw-form">
          <input v-model="form.name" placeholder="Full name *" />
          <input v-model="form.email" type="email" placeholder="Email *" />
          <input v-model="form.phone" placeholder="Phone (optional)" />
          <!-- Owner-defined custom fields for this service -->
          <template v-for="ff in (activeService?.form_fields || [])" :key="ff.key">
            <textarea v-if="ff.type === 'textarea'" v-model="customFields[ff.key]" rows="2" :placeholder="ff.label + (ff.required ? ' *' : '')"></textarea>
            <label v-else-if="ff.type === 'select'" class="bw-ff-label">{{ ff.label }}{{ ff.required ? ' *' : '' }}
              <select v-model="customFields[ff.key]"><option value=""></option><option v-for="o in (ff.options || [])" :key="o">{{ o }}</option></select>
            </label>
            <label v-else-if="ff.type === 'checkbox'" class="bw-ff-check">
              <input type="checkbox" :checked="customFields[ff.key] === 'Yes'" @change="customFields[ff.key] = ($event.target as HTMLInputElement).checked ? 'Yes' : ''" />
              {{ ff.label }}{{ ff.required ? ' *' : '' }}
            </label>
            <label v-else-if="ff.type === 'number' || ff.type === 'date'" class="bw-ff-label">{{ ff.label }}{{ ff.required ? ' *' : '' }}
              <input v-model="customFields[ff.key]" :type="ff.type" />
            </label>
            <input v-else v-model="customFields[ff.key]" type="text" :placeholder="ff.label + (ff.required ? ' *' : '')" />
          </template>
          <textarea v-model="form.notes" rows="2" placeholder="Anything we should know? (optional)"></textarea>
          <p v-if="status === 'error'" class="bw-err">{{ message }}</p>
          <button type="button" class="bw-submit" :disabled="status === 'saving'" @click="submit">
            {{ status === 'saving' ? 'Booking…' : (activeService?.requires_payment ? (activeService?.deposit_cents ? 'Pay deposit to confirm' : 'Continue to payment') : 'Confirm booking') }}
          </button>
          <p v-if="activeService?.deposit_cents" class="bw-muted" style="text-align:center">
            Deposit: {{ activeService.deposit_cents.pct ? activeService.deposit_cents.pct + '% of the total' : money(activeService.deposit_cents.fixed || 0) }} — the balance is due at arrival.
          </p>
        </div>
        <p v-else-if="status === 'error'" class="bw-err">{{ message }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bw { width: 100%; }
.bw-head { text-align: center; margin-bottom: 1.5rem; }
.bw-title { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; }
.bw-intro { color: #6b7280; margin-top: .4rem; }
.bw-empty, .bw-muted { color: #6b7280; font-size: .9rem; }
.bw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; max-width: 820px; margin: 0 auto; }
@media (max-width: 700px) { .bw-grid { grid-template-columns: 1fr; } }
.bw-col { min-width: 0; }
.bw-services { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1rem; }
.bw-svc { display: flex; flex-direction: column; align-items: flex-start; text-align: left; padding: .7rem .9rem; border: 1px solid rgba(0,0,0,.12); border-radius: 12px; background: #fff; cursor: pointer; }
.bw-svc.on { border-color: var(--accent, #6366f1); box-shadow: 0 0 0 1px var(--accent, #6366f1) inset; }
.bw-svc-static { cursor: default; margin-bottom: 1rem; }
.bw-svc-name { font-weight: 700; font-size: .95rem; }
.bw-svc-meta { font-size: .78rem; color: #6b7280; margin-top: .15rem; }
.bw-cal { border: 1px solid rgba(0,0,0,.1); border-radius: 14px; padding: .9rem; }
.bw-cal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; }
.bw-month { font-weight: 700; font-size: .95rem; }
.bw-nav { width: 30px; height: 30px; border-radius: 8px; border: 1px solid rgba(0,0,0,.12); background: #fff; cursor: pointer; font-size: 1.1rem; line-height: 1; }
.bw-dow { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 4px; }
.bw-dow span { text-align: center; font-size: .68rem; font-weight: 700; color: #9ca3af; }
.bw-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.bw-day { aspect-ratio: 1; border: 0; border-radius: 9px; background: transparent; color: #1f2937; font-size: .85rem; cursor: not-allowed; opacity: .35; }
.bw-day.out { visibility: hidden; }
.bw-day.free { cursor: pointer; opacity: 1; background: rgba(0,0,0,.04); font-weight: 600; }
.bw-day.free:hover { background: rgba(0,0,0,.09); }
.bw-day.on { background: var(--accent, #6366f1); color: #fff; }
.bw-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; margin-bottom: .5rem; }
.bw-slots { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; }
.bw-slot { padding: .5rem; border: 1px solid rgba(0,0,0,.14); border-radius: 9px; background: #fff; font-size: .8rem; cursor: pointer; }
.bw-slot.on { background: var(--accent, #6366f1); color: #fff; border-color: var(--accent, #6366f1); }
.bw-res { margin-bottom: 1rem; }
.bw-res-row { display: flex; flex-wrap: wrap; gap: .4rem; }
.bw-res-pill { padding: .45rem .85rem; border: 1px solid rgba(0,0,0,.14); border-radius: 999px; background: #fff; font-size: .8rem; font-weight: 600; cursor: pointer; }
.bw-res-pill.on { background: var(--accent, #6366f1); color: #fff; border-color: var(--accent, #6366f1); }
.bw-res-price { display: block; font-size: .65rem; font-weight: 500; opacity: .7; margin-top: .1rem; }
.bw-deps { display: flex; flex-direction: column; gap: .5rem; }
.bw-dep { display: flex; flex-direction: column; align-items: flex-start; text-align: left; padding: .7rem .9rem; border: 1px solid rgba(0,0,0,.12); border-radius: 12px; background: #fff; cursor: pointer; }
.bw-dep.on { border-color: var(--accent, #6366f1); box-shadow: 0 0 0 1px var(--accent, #6366f1) inset; }
.bw-dep-route { font-weight: 700; font-size: .92rem; }
.bw-dep-meta { font-size: .76rem; color: #6b7280; margin-top: .15rem; }
.bw-qty-row { display: flex; gap: .75rem; margin-bottom: .75rem; }
.bw-qty { display: flex; flex-direction: column; gap: .25rem; font-size: .75rem; font-weight: 600; color: #6b7280; }
.bw-qty input { width: 90px; padding: .45rem .6rem; border: 1px solid rgba(0,0,0,.16); border-radius: 9px; font-size: .85rem; }
.bw-quote { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .7rem .9rem; border-radius: 10px; background: rgba(0,0,0,.045); font-size: .85rem; }
.bw-quote.bad { background: #fef2f2; color: #b91c1c; }
.bw-form { display: flex; flex-direction: column; gap: .5rem; margin-top: 1rem; }
.bw-form input, .bw-form textarea, .bw-form select { padding: .6rem .75rem; border: 1px solid rgba(0,0,0,.16); border-radius: 10px; font-size: .85rem; font-family: inherit; }
.bw-ff-label { display: flex; flex-direction: column; gap: .25rem; font-size: .75rem; font-weight: 600; color: #555; }
.bw-ff-check { display: flex; align-items: center; gap: .5rem; font-size: .85rem; }
.bw-ff-check input { width: auto; }
.bw-submit { padding: .7rem; border: 0; border-radius: 10px; background: var(--accent, #6366f1); color: #fff; font-weight: 700; font-size: .9rem; cursor: pointer; }
.bw-submit:disabled { opacity: .6; }
.bw-err { color: #dc2626; font-size: .8rem; }
.bw-success { text-align: center; padding: 2rem; }
.bw-check { width: 54px; height: 54px; margin: 0 auto 1rem; border-radius: 50%; background: #d1fae5; color: #059669; font-size: 1.6rem; display: flex; align-items: center; justify-content: center; }
.bw-success h3 { font-size: 1.3rem; font-weight: 800; }
</style>
