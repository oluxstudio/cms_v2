<script setup lang="ts">
const olux = useOluxContent('appointment')
const oluxFb: Record<string, string> = {"Text":"Appointments","Headline":"Reserve your chair","Text B":"Pick a service and a time \u2014 we confirm every booking personally.","Subheadline":"Working Hours","Text C":"Prefer to talk? Call us any time during opening hours.<b>+1 589 625 3256</b>","Text D":"Or write to us.<b>hello@hairco.salon</b>","Subheadline B":"Book an appointment","Text E":"Loading services\u2026","Subheadline C":"Appointment","Caption":"Service","Caption B":"Date & time","Caption C":"Duration","Caption D":"Total","Subheadline D":"Your details","Caption E":"Name","Caption F":"Phone","Caption G":"Email","Caption H":"Notes","Text F":"Please keep your reference handy \u2014 you'll need it for any changes.\n            To reschedule or cancel, call us at <b>+1 589 625 3256</b> during opening hours.","Text G":"Add to calendar","Text H":"Book another appointment","Text I":"Checking the calendar\u2026","Text J":"No open days in the next few weeks \u2014 call us instead.","Text K":"Checking free times\u2026","Text L":"Fully booked that day \u2014 pick another."}
import { ref, computed, onMounted, watch, unref } from 'vue'

const hours = olux.items('Hour', {"Title":"title","Text":"text"}, [
  { title: 'Monday — Friday', text: '9:00 — 20:00' },
  { title: 'Saturday', text: '10:00 — 18:00' },
  { title: 'Sunday', text: 'Closed' },
], {})

// ── Booking data comes from the CMS booking API ──
// Which site's booking engine feeds this page, in priority order:
//   1. ?site= / ?booking= in the URL (cached, so in-app navigation keeps it)
//   2. this editable default (change it in the CMS content editor)
const bookingSite = olux.tRef('Booking Site', 'law-matters')

function resolveSite(): string {
  if (typeof window === 'undefined') return unref(bookingSite)
  const q = new URLSearchParams(window.location.search)
  const fromUrl = q.get('booking') || q.get('site') || ''
  if (fromUrl) { try { sessionStorage.setItem('olux-booking-site', fromUrl) } catch (_) {} return fromUrl }
  try { const cached = sessionStorage.getItem('olux-booking-site'); if (cached) return cached } catch (_) {}
  return unref(bookingSite)
}

const siteName = resolveSite()
// API origin: a CMS-served preview (/nuxt-preview/…) is ALWAYS same-origin with
// the API — use the page's own origin whatever port it's on. The configured
// bookingApiBase only applies off-CMS (nuxt dev on :3000, static exports).
const origin = typeof window !== 'undefined' ? window.location.origin : ''
const cmsServed = typeof window !== 'undefined' && window.location.pathname.startsWith('/nuxt-preview/')
const apiBase = cmsServed ? origin : ((useRuntimeConfig().public.bookingApiBase || '').replace(/\/$/, '') || origin)
const api = `${apiBase}/api/sites/${encodeURIComponent(siteName)}/booking`

type Svc = { slug: string; name: string; kind: string; requires_payment?: boolean; duration?: number; price?: string }
type Slot = { iso: string; label: string }
type Day = { date: string; label: string; slots: Slot[] }

const services = ref<Svc[]>([])
const service = ref('')
const days = ref<Day[]>([])
const loadingCfg = ref(true)
const daysLoading = ref(false)
const slotsLoading = ref(false)
const day = ref('')
const slot = ref('')
const name = ref('')
const phone = ref('')
const email = ref('')
const notes = ref('')
const status = ref<'idle' | 'saving' | 'done' | 'error'>('idle')
const message = ref('')
const apiError = ref(false)

// Filled from the booking API's response (plus the picked slot) when a booking succeeds.
type Confirmation = {
  reference: string
  confirmed: boolean
  service: string
  when: string
  isoStart: string
  durationMin: number
  duration: string
  total: string
  name: string
  email: string
  phone: string
  notes: string
}
const confirmation = ref<Confirmation | null>(null)

/** Download the confirmed appointment as an .ics calendar event (floating local time). */
function addToCalendar() {
  const c = confirmation.value
  if (!c?.isoStart) return
  const start = new Date(c.isoStart.replace(' ', 'T'))
  const end = new Date(start.getTime() + (c.durationMin || 60) * 60000)
  const fmt = (d: Date) =>
    `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}` +
    `T${String(d.getHours()).padStart(2, '0')}${String(d.getMinutes()).padStart(2, '0')}00`
  const ics = olux.list('Ics Item', [
    'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Hair Co.//Booking//EN', 'BEGIN:VEVENT',
    `UID:${c.reference || fmt(start)}@hairco.salon`,
    `DTSTART:${fmt(start)}`, `DTEND:${fmt(end)}`,
    `SUMMARY:${c.service} — Hair Co.`,
    `DESCRIPTION:Booking reference ${c.reference}`,
    'LOCATION:14 Rosewood Avenue\\, Suite 2\\, Portland OR',
    'END:VEVENT', 'END:VCALENDAR',
  ]).join('\r\n')
  const url = URL.createObjectURL(new Blob([ics], { type: 'text/calendar' }))
  const a = document.createElement('a')
  a.href = url
  a.download = `hairco-${c.reference || 'appointment'}.ics`
  a.click()
  URL.revokeObjectURL(url)
}

const slots = ref<Slot[]>([])
const daySlots = computed(() => slots.value)
const activeSvc = computed(() => services.value.find(s => s.name === service.value))

/** "Mon, Aug 3" from an API "Y-m-d" date — display formatting only. */
function dayLabel(date: string) {
  return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
}

/** Open days for the chosen service — straight from the API's openDates. */
async function loadDays() {
  day.value = ''; slot.value = ''; days.value = []; slots.value = []
  const svc = activeSvc.value
  if (!svc) return
  daysLoading.value = true
  try {
    const res: any = await $fetch(`${api}/availability`, { params: { service: svc.slug } })
    days.value = ((res.openDates || []) as string[]).map(d => ({ date: d, label: dayLabel(d), slots: [] }))
  } catch (_) { days.value = [] }
  daysLoading.value = false
}

/** Free times for the picked day — straight from the API's slots. */
async function loadSlots() {
  slot.value = ''; slots.value = []
  const svc = activeSvc.value
  if (!svc || !day.value) return
  slotsLoading.value = true
  try {
    const res: any = await $fetch(`${api}/availability`, { params: { service: svc.slug, date: day.value } })
    slots.value = (res.slots || []) as Slot[]
  } catch (_) { slots.value = [] }
  slotsLoading.value = false
}

/**
 * Returning from Stripe checkout: the CMS bounced back with ?booking_ref= (paid,
 * verified server-side) or ?booking_cancelled=1. Look the booking up and show
 * the same confirmation panel as an unpaid booking would.
 */
async function handleCheckoutReturn() {
  const q = new URLSearchParams(window.location.search)
  const ref = q.get('booking_ref')
  const cancelled = q.get('booking_cancelled')
  if (!ref && !cancelled) return
  // Clean the query so refresh/bookmark doesn't replay the state.
  q.delete('booking_ref'); q.delete('booking_cancelled')
  const rest = q.toString()
  history.replaceState(null, '', window.location.pathname + (rest ? `?${rest}` : ''))

  if (cancelled) {
    status.value = 'error'
    message.value = 'Payment was cancelled and your time was released — pick a slot to try again.'
    return
  }
  try {
    const b: any = await $fetch(`${api}/${encodeURIComponent(ref!)}`)
    const starts = b.starts_at ? new Date(b.starts_at) : null
    const money = b.total_cents
      ? new Intl.NumberFormat(undefined, { style: 'currency', currency: (b.currency || 'gbp').toUpperCase() }).format(b.total_cents / 100)
      : ''
    confirmation.value = {
      reference: b.reference || ref!,
      confirmed: b.status === 'confirmed',
      service: b.service || '',
      when: starts
        ? `${starts.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })} · ${starts.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`
        : '',
      isoStart: b.starts_at || '',
      durationMin: 0,
      duration: '',
      total: money,
      name: name.value,
      email: email.value,
      phone: phone.value,
      notes: notes.value,
    }
    status.value = 'done'
    message.value = b.status === 'confirmed'
      ? 'Payment received — your appointment is confirmed!'
      : 'Payment received — your booking is being finalised.'
  } catch (_) {
    status.value = 'done'
    message.value = `Payment received — your reference is ${ref}.`
  }
}

onMounted(async () => {
  try {
    const res: any = await $fetch(`${api}/config`)
    services.value = (res.services || []).filter((s: Svc) => s.kind === 'slot')
    if (services.value.length) service.value = services.value[0].name
    else apiError.value = true
  } catch (_) {
    apiError.value = true
  }
  loadingCfg.value = false
  // (the service watcher below fires loadDays once the default service is set)
  handleCheckoutReturn()
})

watch(service, loadDays)
watch(day, loadSlots)

async function submit() {
  const svc = activeSvc.value
  if (!svc || !slot.value) { status.value = 'error'; message.value = 'Please pick a service, day and time.'; return }
  status.value = 'saving'
  try {
    const res: any = await $fetch(api, {
      method: 'POST',
      body: {
        service: svc.slug, start: slot.value,
        name: name.value, email: email.value, phone: phone.value, notes: notes.value,
        // After Stripe checkout the customer returns HERE (this page), not the CMS.
        return_url: window.location.origin + window.location.pathname,
      },
    })
    if (res.checkout_url) { window.location.href = res.checkout_url; return }
    const picked = slots.value.find(s => s.iso === slot.value)
    confirmation.value = {
      reference: res.reference || '',
      confirmed: res.status === 'confirmed',
      service: res.service || svc.name,
      when: `${dayLabel(day.value)} · ${picked?.label || slot.value}`,
      isoStart: slot.value,
      durationMin: svc.duration || 0,
      duration: svc.duration ? `${svc.duration} min` : '',
      total: res.total || svc.price || '',
      name: name.value,
      email: email.value,
      phone: phone.value,
      notes: notes.value,
    }
    status.value = 'done'
    message.value = res.message || 'Thank you — your appointment is booked.'
  } catch (e: any) {
    status.value = 'error'
    message.value = e?.data?.message || 'Sorry, that time is no longer available — please pick another.'
  }
}

/** Back to a blank form for a new booking (keeps the loaded services). */
function bookAnother() {
  confirmation.value = null
  status.value = 'idle'
  message.value = ''
  name.value = ''; phone.value = ''; email.value = ''; notes.value = ''
  day.value = ''; slot.value = ''
  loadDays()
}
</script>

<template>
  <section class="appointment" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container" style="display:block; margin-bottom:0">
      <div class="section-head centered">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
      </div>
    </div>
    <div class="container">
      <div class="hours-card">
        <h3>{{ olux.t('Subheadline', oluxFb['Subheadline']) }}</h3>
        <ul>
          <li v-for="h in hours" :key="h.title"><b>{{ h.title }}</b><span>{{ h.text }}</span></li>
        </ul>
        <p class="reach" v-html="olux.t('Text C', oluxFb['Text C'])"></p>
        <p class="reach" v-html="olux.t('Text D', oluxFb['Text D'])"></p>
      </div>
      <form class="appt-form" @submit.prevent="submit">
        <h3>{{ olux.t('Subheadline B', oluxFb['Subheadline B']) }}</h3>

        <p v-if="loadingCfg" class="slot-note">{{ olux.t('Text E', oluxFb['Text E']) }}</p>
        <p v-else-if="apiError" class="err">Booking is not available right now — no bookable services were found for “{{ siteName }}”. Please call us instead.</p>

        <div v-else-if="status === 'done' && confirmation" class="confirm-panel">
          <div class="confirm-head">
            <span class="confirm-badge">✓</span>
            <div>
              <h4>{{ confirmation.confirmed ? 'Booking confirmed' : 'Booking request received' }}</h4>
              <p class="slot-note">{{ message }}</p>
            </div>
          </div>
          <p v-if="confirmation.reference" class="confirm-ref">
            Booking reference<b>{{ confirmation.reference }}</b>
          </p>
          <div class="confirm-section">
            <h5>{{ olux.t('Subheadline C', oluxFb['Subheadline C']) }}</h5>
            <div class="confirm-grid">
              <div class="confirm-item"><span>{{ olux.t('Caption', oluxFb['Caption']) }}</span><b>{{ confirmation.service }}</b></div>
              <div class="confirm-item"><span>{{ olux.t('Caption B', oluxFb['Caption B']) }}</span><b>{{ confirmation.when }}</b></div>
              <div v-if="confirmation.duration" class="confirm-item"><span>{{ olux.t('Caption C', oluxFb['Caption C']) }}</span><b>{{ confirmation.duration }}</b></div>
              <div v-if="confirmation.total" class="confirm-item"><span>{{ olux.t('Caption D', oluxFb['Caption D']) }}</span><b>{{ confirmation.total }}</b></div>
            </div>
          </div>
          <div class="confirm-section">
            <h5>{{ olux.t('Subheadline D', oluxFb['Subheadline D']) }}</h5>
            <div class="confirm-grid">
              <div class="confirm-item"><span>{{ olux.t('Caption E', oluxFb['Caption E']) }}</span><b>{{ confirmation.name }}</b></div>
              <div class="confirm-item"><span>{{ olux.t('Caption F', oluxFb['Caption F']) }}</span><b>{{ confirmation.phone }}</b></div>
              <div class="confirm-item full"><span>{{ olux.t('Caption G', oluxFb['Caption G']) }}</span><b>{{ confirmation.email }}</b></div>
              <div v-if="confirmation.notes" class="confirm-item full"><span>{{ olux.t('Caption H', oluxFb['Caption H']) }}</span><b>{{ confirmation.notes }}</b></div>
            </div>
          </div>
          <p class="confirm-note" v-html="olux.t('Text F', oluxFb['Text F'])"></p>
          <div class="confirm-actions">
            <button class="btn" type="button" @click="addToCalendar">{{ olux.t('Text G', oluxFb['Text G']) }}</button>
            <button class="btn ghost" type="button" @click="bookAnother">{{ olux.t('Text H', oluxFb['Text H']) }}</button>
          </div>
        </div>

        <div v-else class="grid">
          <div>
            <label>Your name</label>
            <input v-model="name" type="text" name="name" placeholder="Jane Doe" required>
          </div>
          <div>
            <label>Phone</label>
            <input v-model="phone" type="tel" name="phone" placeholder="+1 555 000 1234" required>
          </div>
          <div>
            <label>Email</label>
            <input v-model="email" type="email" name="email" placeholder="you@example.com" required>
          </div>
          <div>
            <label>Service</label>
            <select v-model="service" name="service">
              <option v-for="s in services" :key="s.slug" :value="s.name">{{ s.name }}</option>
            </select>
          </div>
          <div class="full">
            <label>Available days</label>
            <p v-if="daysLoading" class="slot-note">{{ olux.t('Text I', oluxFb['Text I']) }}</p>
            <p v-else-if="!days.length" class="slot-note">{{ olux.t('Text J', oluxFb['Text J']) }}</p>
            <div v-else class="slot-chips">
              <button v-for="d in days" :key="d.date" type="button"
                      :class="{ active: day === d.date }" @click="day = d.date">{{ d.label }}</button>
            </div>
          </div>
          <div v-if="day" class="full">
            <label>Available times</label>
            <p v-if="slotsLoading" class="slot-note">{{ olux.t('Text K', oluxFb['Text K']) }}</p>
            <p v-else-if="!daySlots.length" class="slot-note">{{ olux.t('Text L', oluxFb['Text L']) }}</p>
            <div v-else class="slot-chips">
              <button v-for="s in daySlots" :key="s.iso" type="button"
                      :class="{ active: slot === s.iso }" @click="slot = s.iso">{{ s.label }}</button>
            </div>
          </div>
          <div class="full">
            <label>Notes for your stylist</label>
            <textarea v-model="notes" name="notes" rows="3" placeholder="Hair length, texture, inspiration…"></textarea>
          </div>
        </div>

        <button v-if="!loadingCfg && !apiError && status !== 'done'" class="btn" type="submit" :disabled="status === 'saving'">
          {{ status === 'saving' ? 'Booking…' : 'Request booking' }}
        </button>
        <p v-if="status === 'done' && !confirmation" class="ok">{{ message }}</p>
        <p v-else-if="status === 'error'" class="err">{{ message }}</p>
      </form>
    </div>
  </section>
</template>
