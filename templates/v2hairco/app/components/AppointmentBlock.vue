<script setup lang="ts">
const oluxCms = useOluxContent('appointment')
const oluxFb: Record<string, string> = {"Text":"Your booking","Caption":"Service","Caption B":"Day","Caption C":"Time","Caption D":"Price","Subheadline":"Book an appointment","Text B":"Loading services\u2026","Subheadline B":"Appointment","Caption E":"Service","Caption F":"Date & time","Caption G":"Duration","Caption H":"Total","Text C":"Please keep your reference handy \u2014 you'll need it for any changes.\n            To reschedule or cancel, call us at <b><GlobalPhone link /></b> during opening hours.","Text D":"Add to calendar","Text E":"Book another appointment","Text F":"Choose a service","Text G":"Pick a day and a time","Text H":"Checking the calendar\u2026","Text I":"No open days in the next few weeks \u2014 call us instead.","Text J":"<i /> Available day","Text K":"Free times will appear here once you pick a day.","Text L":"Checking free times\u2026","Text M":"Fully booked that day \u2014 pick another.","Text N":"Your details","Text O":"Review & confirm","Caption I":"Service","Caption J":"Date & time","Caption K":"Duration","Caption L":"Price","Text P":"\ud83d\udd12 You'll be taken to secure payment to complete this booking.","Text Q":"\u2039 Back","Text R":"Continue \u203a"}
const { field, items } = useCms()

import { ref, computed, onMounted, watch, unref } from 'vue'

// Handcoded fallback — the CMS "Working Hours" collection overrides these rows.
const fallbackHours = oluxCms.items('Fallback Hour', {"Title":"title","Text":"text"}, [
  { title: 'Monday — Friday', text: '9:00 — 20:00' },
  { title: 'Saturday', text: '10:00 — 18:00' },
  { title: 'Sunday', text: 'Closed' },
], {})
const hours = computed(() => items('workingHours', fallbackHours))


// ── Booking data comes from the CMS booking API ──
// Which site's booking engine feeds this page, in priority order:
//   1. ?site= / ?booking= in the URL (cached, so in-app navigation keeps it)
//   2. this editable default (change it in the CMS content editor)
const bookingSite = 'v2-hairco'

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

// ── Person fields come from the CMS "appointment" form schema ──
// (GET /form/appointment, same contract as ContactBlock). The handcoded
// fields below are the offline fallback; the CMS `service` field is skipped —
// the booking service picker (slugs, prices, availability) replaces it.
const FORM_NAME = 'appointment'
type FormField = { key: string; label: string; type: string; required: boolean; placeholder?: string | null; options?: string[] }
const fallbackFields: FormField[] = [
  { key: 'name', label: 'Your name', type: 'text', required: true, placeholder: 'Jane Doe' },
  { key: 'phone', label: 'Phone', type: 'tel', required: true, placeholder: '+1 555 000 1234' },
  { key: 'email', label: 'Email', type: 'email', required: true, placeholder: 'you@example.com' },
  { key: 'notes', label: 'Notes for your stylist', type: 'textarea', required: false, placeholder: 'Hair length, texture, inspiration…' },
]
const formFields = ref<FormField[]>(fallbackFields)
const values = ref<Record<string, string>>({})
const val = (k: string) => values.value[k] || ''
const textFields = computed(() => formFields.value.filter(f => f.key !== 'service' && f.type !== 'textarea'))
const areaFields = computed(() => formFields.value.filter(f => f.key !== 'service' && f.type === 'textarea'))

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
  const ics = oluxCms.list('Ics Item', [
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
    refreshAfterReject(String('Payment was cancelled and your time was released — pick a slot to try again.'))
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
      name: val('name'),
      email: val('email'),
      phone: val('phone'),
      notes: val('notes'),
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
  // Person fields from the CMS form schema (fallback fields if unavailable).
  try {
    const schema: any = await $fetch(`${apiBase}/api/sites/${encodeURIComponent(siteName)}/form/${FORM_NAME}`)
    if (Array.isArray(schema?.fields) && schema.fields.length) formFields.value = schema.fields
  } catch (_) { /* keep fallback fields */ }
  for (const f of formFields.value) if (!(f.key in values.value)) values.value[f.key] = ''

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


// A slot rejection means our availability view is stale — send the user back
// to Date & time with FRESH slots instead of stranding them on Review.
function refreshAfterReject(msg: string) {
  if (/no longer available|just taken/i.test(msg || '')) {
    slot.value = ''
    loadSlots()
    step.value = 1
    stepError.value = 'That time was taken — the calendar has been refreshed, please pick another slot.'
  }
}


/** True while the picked slot is still offered by live availability. */
async function revalidateSlot(): Promise<boolean> {
  try {
    const svc = activeSvc.value
    if (!svc) return false
    const res: any = await $fetch(`${api}/availability`, { params: { service: svc.slug, date: day.value } })
    return Array.isArray(res?.slots) && res.slots.some((s: any) => s.iso === slot.value)
  } catch { return true /* availability blip must not block booking; server re-checks anyway */ }
}

async function submit() {
  const svc = activeSvc.value
  if (!svc || !slot.value) { status.value = 'error'; message.value = 'Please pick a service, day and time.'; return }
  status.value = 'saving'
  try {
    const res: any = await $fetch(api, {
      method: 'POST',
      body: {
        ...values.value,
        service: svc.slug, start: slot.value,
        // The CMS form this booking UI is built from — the booking's form
        // response and admin notification route through it.
        form: FORM_NAME,
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
      name: val('name'),
      email: val('email'),
      phone: val('phone'),
      notes: val('notes'),
    }
    status.value = 'done'
    message.value = res.message || 'Thank you — your appointment is booked.'
  } catch (e: any) {
    status.value = 'error'
    message.value = e?.data?.message || `Booking failed${e?.status ? ' (HTTP ' + e.status + ')' : ''} — ${e?.message || 'network error'}. Please try again.`
    refreshAfterReject(String(e?.data?.message || ''))
  }
}

/** Back to a blank form for a new booking (keeps the loaded services). */
function bookAnother() {
  confirmation.value = null
  status.value = 'idle'
  message.value = ''
  stepError.value = ''
  step.value = 0
  for (const k of Object.keys(values.value)) values.value[k] = ''
  day.value = ''; slot.value = ''
  loadDays()
}

// ── Wizard: Service → Date & time → Your details → Review ──
const STEPS = oluxCms.list('S T E P S Item', ['Service', 'Date & time', 'Your details', 'Review'])
const step = ref(0)
const stepError = ref('')
const requiredMissing = computed(() =>
  formFields.value.filter(f => f.key !== 'service' && f.required && !String(values.value[f.key] || '').trim()).map(f => f.label))
const canProceed = computed(() => {
  if (step.value === 0) return !!activeSvc.value
  if (step.value === 1) return !!day.value && !!slot.value
  if (step.value === 2) return requiredMissing.value.length === 0
  return true
})
function next() {
  if (!canProceed.value) {
    stepError.value = step.value === 0 ? 'Please choose a service.'
      : step.value === 1 ? 'Please pick a day and a time.'
      : `Please fill in: ${requiredMissing.value.join(', ')}.`
    return
  }
  stepError.value = ''
  status.value = 'idle'
  const target = Math.min(step.value + 1, STEPS.length - 1)
  // Entering Review: re-check the picked slot against LIVE availability —
  // only genuinely bookable times may reach the confirm button.
  if (target === STEPS.length - 1 && day.value && slot.value) {
    revalidateSlot().then((ok) => {
      if (ok) { step.value = target; return }
      slot.value = ''
      loadSlots()
      step.value = 1
      stepError.value = 'That time is no longer free — the calendar has been refreshed, please pick another slot.'
    })
    return
  }
  step.value = target
}
function back() { stepError.value = ''; step.value = Math.max(step.value - 1, 0) }
/** The stepper lets you jump back to any completed step, never forward. */
function goTo(i: number) { if (i < step.value) { stepError.value = ''; step.value = i } }
const pickedSlotLabel = computed(() => slots.value.find(s => s.iso === slot.value)?.label || '')

// ── Calendar (Monday-first month grid; only API open days are clickable) ──
const WEEKDAYS = oluxCms.list('W E E K D A Y S Item', ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'])
const viewMonth = ref('')  // 'YYYY-MM'
const ym = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
const openSet = computed(() => new Set(days.value.map(d => d.date)))
const monthKeys = computed(() => [...new Set(days.value.map(d => d.date.slice(0, 7)))].sort())
watch(days, ds => { viewMonth.value = ds.length ? ds[0].date.slice(0, 7) : ym(new Date()) }, { immediate: true })
const monthLabel = computed(() =>
  viewMonth.value ? new Date(`${viewMonth.value}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) : '')
const calendarCells = computed(() => {
  if (!viewMonth.value) return []
  const [y, m] = viewMonth.value.split('-').map(Number)
  const lead = (new Date(y, m - 1, 1).getDay() + 6) % 7
  const dim = new Date(y, m, 0).getDate()
  const cells: { date: string; num: number; open: boolean }[] = []
  for (let i = 0; i < lead; i++) cells.push({ date: '', num: 0, open: false })
  for (let d = 1; d <= dim; d++) {
    const date = `${viewMonth.value}-${String(d).padStart(2, '0')}`
    cells.push({ date, num: d, open: openSet.value.has(date) })
  }
  return cells
})
const canPrevMonth = computed(() => monthKeys.value.length > 0 && viewMonth.value > monthKeys.value[0])
const canNextMonth = computed(() => monthKeys.value.length > 0 && viewMonth.value < monthKeys.value[monthKeys.value.length - 1])
function shiftMonth(n: number) {
  const [y, m] = viewMonth.value.split('-').map(Number)
  viewMonth.value = ym(new Date(y, m - 1 + n, 1))
}

/**
 * connect.js also listens for `submit` on every data-olx-kind="form" form and
 * re-posts the raw FormData to the CMS form endpoint — a duplicate, malformed
 * submission that fails with "Sorry, something went wrong". This handler runs
 * in the capture phase (before connect.js's listener) and stops it there; the
 * booking API call below is the only submission that should happen.
 */
function onSubmit(e: Event) {
  e.stopImmediatePropagation()
  submit()
}
</script>

<template>
  <section class="appointment" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container" style="display:block; margin-bottom:0">
      <div class="section-head centered" data-olx-key="appointmentIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="eyebrow">{{ field('appointmentIntro', 'eyebrow', 'Appointments') }}</p>
        <h2 data-olx-field="heading">{{ field('appointmentIntro', 'heading', 'Reserve your chair') }}</h2>
        <p data-olx-field="body">{{ field('appointmentIntro', 'body', 'Pick a service and a time — we confirm every booking personally.') }}</p>
      </div>
    </div>
    <div class="container">
      <div class="hours-card" data-olx-key="appointmentWorkingHours" data-olx-kind="component">
        <h3 data-olx-field="workingHoursTitle">{{ field('appointmentWorkingHours', 'workingHoursTitle', 'Working Hours') }}</h3>
        <ul data-olx-key="workingHours" data-olx-kind="collection">
          <li v-for="h in hours" :key="h.title" data-olx-item>
            <b data-olx-field="title">{{ h.title }}</b><span data-olx-field="text">{{ h.text }}</span>
          </li>
        </ul>
        <!-- own marker block: keeps these fields out of the workingHours
             collection's item schema (the scanner attaches fields to the
             most recently opened block) -->
        <div data-olx-key="appointmentContact" data-olx-kind="component">
          <p class="reach"><span data-olx-field="note">{{ field('appointmentContact', 'note', 'Prefer to talk? Call us any time during opening hours.') }}</span><b><GlobalPhone link /></b></p>
          <p class="reach"><span data-olx-field="emailNote">{{ field('appointmentContact', 'emailNote', 'Or write to us.') }}</span><b><GlobalEmail link /></b></p>
        </div>

        <!-- live summary of what has been chosen so far -->
        <div v-if="!loadingCfg && !apiError && status !== 'done'" class="summary">
          <p data-olx-field="text" class="summary-title">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
          <ul>
            <li :class="{ set: activeSvc }"><span data-olx-field="caption">{{ oluxCms.t('Caption', oluxFb['Caption']) }}</span><b>{{ activeSvc?.name || '—' }}</b></li>
            <li :class="{ set: day }"><span data-olx-field="captionB">{{ oluxCms.t('Caption B', oluxFb['Caption B']) }}</span><b>{{ day ? dayLabel(day) : '—' }}</b></li>
            <li :class="{ set: slot }"><span data-olx-field="captionC">{{ oluxCms.t('Caption C', oluxFb['Caption C']) }}</span><b>{{ pickedSlotLabel || '—' }}</b></li>
            <li v-if="activeSvc?.price" class="set"><span data-olx-field="captionD">{{ oluxCms.t('Caption D', oluxFb['Caption D']) }}</span><b>{{ activeSvc.price }}</b></li>
          </ul>
        </div>
      </div>

      <form class="appt-form wizard" data-olx-key="appointment" data-olx-kind="form" @submit.capture.prevent="onSubmit">
        <h3 data-olx-field="formTitle">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h3>

        <p data-olx-field="textB" v-if="loadingCfg" class="slot-note">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
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
            <h5 data-olx-field="subheadlineB">{{ oluxCms.t('Subheadline B', oluxFb['Subheadline B']) }}</h5>
            <div class="confirm-grid">
              <div class="confirm-item"><span data-olx-field="captionE">{{ oluxCms.t('Caption E', oluxFb['Caption E']) }}</span><b>{{ confirmation.service }}</b></div>
              <div class="confirm-item"><span data-olx-field="captionF">{{ oluxCms.t('Caption F', oluxFb['Caption F']) }}</span><b>{{ confirmation.when }}</b></div>
              <div v-if="confirmation.duration" class="confirm-item"><span data-olx-field="captionG">{{ oluxCms.t('Caption G', oluxFb['Caption G']) }}</span><b>{{ confirmation.duration }}</b></div>
              <div v-if="confirmation.total" class="confirm-item"><span data-olx-field="captionH">{{ oluxCms.t('Caption H', oluxFb['Caption H']) }}</span><b>{{ confirmation.total }}</b></div>
            </div>
          </div>
          <p data-olx-field="textC" class="confirm-note" v-html="oluxCms.t('Text C', oluxFb['Text C'])"></p>
          <div class="confirm-actions">
            <button data-olx-field="textD" class="btn" type="button" @click="addToCalendar">{{ oluxCms.t('Text D', oluxFb['Text D']) }}</button>
            <button data-olx-field="textE" class="btn ghost" type="button" @click="bookAnother">{{ oluxCms.t('Text E', oluxFb['Text E']) }}</button>
          </div>
        </div>

        <template v-else>
          <!-- Stepper: completed steps get a check and can be revisited -->
          <ol class="stepper">
            <li v-for="(s, i) in STEPS" :key="s" :class="{ done: i < step, active: i === step }"
                :aria-current="i === step ? 'step' : undefined" @click="goTo(i)">
              <span class="dot">{{ i < step ? '✓' : i + 1 }}</span>
              <span class="lbl">{{ s }}</span>
            </li>
          </ol>

          <!-- Step 1: service -->
          <div v-if="step === 0" class="step-pane">
            <p data-olx-field="textF" class="step-title">{{ oluxCms.t('Text F', oluxFb['Text F']) }}</p>
            <div class="service-cards">
              <button v-for="s in services" :key="s.slug" type="button"
                      :class="{ active: service === s.name }" @click="service = s.name">
                <span class="svc-check">✓</span>
                <span class="svc-name">{{ s.name }}</span>
                <span class="svc-meta">
                  <span v-if="s.duration">⏱ {{ s.duration }} min</span>
                  <span v-if="s.price">{{ s.price }}</span>
                </span>
              </button>
            </div>
          </div>

          <!-- Step 2: calendar + times -->
          <div v-else-if="step === 1" class="step-pane">
            <p data-olx-field="textG" class="step-title">{{ oluxCms.t('Text G', oluxFb['Text G']) }}</p>
            <div class="datetime">
              <div class="calendar">
                <div class="cal-head">
                  <button type="button" aria-label="Previous month" :disabled="!canPrevMonth" @click="shiftMonth(-1)">‹</button>
                  <b>{{ monthLabel }}</b>
                  <button type="button" aria-label="Next month" :disabled="!canNextMonth" @click="shiftMonth(1)">›</button>
                </div>
                <div class="cal-grid">
                  <span v-for="w in WEEKDAYS" :key="w" class="wd">{{ w }}</span>
                  <button v-for="(c, i) in calendarCells" :key="i" type="button" class="cal-day"
                          :class="{ empty: !c.date, open: c.open, active: day === c.date }"
                          :disabled="!c.open" @click="day = c.date">{{ c.num || '' }}</button>
                </div>
                <p data-olx-field="textH" v-if="daysLoading" class="slot-note">{{ oluxCms.t('Text H', oluxFb['Text H']) }}</p>
                <p data-olx-field="textI" v-else-if="!days.length" class="slot-note">{{ oluxCms.t('Text I', oluxFb['Text I']) }}</p>
                <p data-olx-field="textJ" v-else class="cal-legend" v-html="oluxCms.t('Text J', oluxFb['Text J'])"></p>
              </div>
              <div class="times">
                <p class="times-title">{{ day ? dayLabel(day) : 'Select a day' }}</p>
                <p data-olx-field="textK" v-if="!day" class="slot-note">{{ oluxCms.t('Text K', oluxFb['Text K']) }}</p>
                <p data-olx-field="textL" v-else-if="slotsLoading" class="slot-note">{{ oluxCms.t('Text L', oluxFb['Text L']) }}</p>
                <p data-olx-field="textM" v-else-if="!daySlots.length" class="slot-note">{{ oluxCms.t('Text M', oluxFb['Text M']) }}</p>
                <div v-else class="slot-chips">
                  <button v-for="s in daySlots" :key="s.iso" type="button"
                          :class="{ active: slot === s.iso }" @click="slot = s.iso">{{ s.label }}</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: person fields from the CMS form schema -->
          <div v-else-if="step === 2" class="step-pane">
            <p data-olx-field="textN" class="step-title">{{ oluxCms.t('Text N', oluxFb['Text N']) }}</p>
            <div class="grid">
              <div v-for="f in textFields" :key="f.key">
                <label>{{ f.label }}<i v-if="f.required">*</i></label>
                <select v-if="f.type === 'select'" v-model="values[f.key]" :name="f.key" :required="f.required">
                  <option value="" disabled>Choose…</option>
                  <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                </select>
                <input v-else v-model="values[f.key]" :type="f.type === 'text' ? 'text' : f.type" :name="f.key"
                       :placeholder="f.placeholder || ''" :required="f.required">
              </div>
              <div v-for="f in areaFields" :key="f.key" class="full">
                <label>{{ f.label }}<i v-if="f.required">*</i></label>
                <textarea v-model="values[f.key]" :name="f.key" rows="3"
                          :placeholder="f.placeholder || ''" :required="f.required"></textarea>
              </div>
            </div>
          </div>

          <!-- Step 4: review -->
          <div v-else class="step-pane">
            <p data-olx-field="textO" class="step-title">{{ oluxCms.t('Text O', oluxFb['Text O']) }}</p>
            <div class="review-grid">
              <div class="review-item"><span data-olx-field="captionI">{{ oluxCms.t('Caption I', oluxFb['Caption I']) }}</span><b>{{ activeSvc?.name }}</b></div>
              <div class="review-item"><span data-olx-field="captionJ">{{ oluxCms.t('Caption J', oluxFb['Caption J']) }}</span><b>{{ dayLabel(day) }} · {{ pickedSlotLabel }}</b></div>
              <div v-if="activeSvc?.duration" class="review-item"><span data-olx-field="captionK">{{ oluxCms.t('Caption K', oluxFb['Caption K']) }}</span><b>{{ activeSvc.duration }} min</b></div>
              <div v-if="activeSvc?.price" class="review-item"><span data-olx-field="captionL">{{ oluxCms.t('Caption L', oluxFb['Caption L']) }}</span><b>{{ activeSvc.price }}</b></div>
              <div v-for="f in formFields.filter(x => x.key !== 'service' && val(x.key))" :key="f.key"
                   class="review-item" :class="{ full: f.type === 'textarea' }">
                <span>{{ f.label }}</span><b>{{ val(f.key) }}</b>
              </div>
            </div>
            <p data-olx-field="textP" v-if="activeSvc?.requires_payment" class="slot-note">{{ oluxCms.t('Text P', oluxFb['Text P']) }}</p>
          </div>

          <p v-if="stepError" class="err">{{ stepError }}</p>
          <p v-else-if="status === 'error'" class="err">{{ message }}</p>

          <div class="wizard-nav">
            <button data-olx-field="textQ" v-if="step > 0" class="btn ghost" type="button" @click="back">{{ oluxCms.t('Text Q', oluxFb['Text Q']) }}</button>
            <span v-else />
            <button data-olx-field="textR" v-if="step < STEPS.length - 1" class="btn" type="button" :disabled="!canProceed" @click="next">{{ oluxCms.t('Text R', oluxFb['Text R']) }}</button>
            <button v-else class="btn" type="submit" :disabled="status === 'saving'">
              {{ status === 'saving' ? 'Booking…' : (activeSvc?.requires_payment ? 'Continue to payment' : 'Confirm booking') }}
            </button>
          </div>
        </template>

        <p v-if="status === 'done' && !confirmation" class="ok">{{ message }}</p>
      </form>
    </div>
  </section>
</template>
