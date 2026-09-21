<script setup lang="ts">
const oluxCms = useOluxContent('events-grid')
const oluxFb: Record<string, string> = {"Text":"Upcoming events","Headline":"Life together, beyond Sunday","Caption":"Seats","Caption B":"Total","Text B":"We only use your details for this event. Never shared.","Text C":"Payment","Text D":"\u2190 Back","Text E":"\ud83d\udd12 Payments are processed securely. You'll receive a receipt by email.","Text F":"Done"}
import type { ChurchEvent } from '../composables/useSiteContent'

// authored rows come from the global data source (useSiteContent)
const authoredEvents = useSiteContent().events

// CMS-first: the "Events" collection feeds the grid; authored rows seed it.
const events = computed<ChurchEvent[]>(() => {
  const rows = (useCms().items('events', []) as any[]).filter(e => e.id && e.title)
  return rows.length ? rows.map(e => ({ ...authoredEvents.find(a => a.id === e.id), ...e, price: Number(e.price ?? 0), seatsLeft: Number(e.seatsLeft ?? 50) })) : authoredEvents
})
const PER_PAGE = 6
const page = ref(1)
const featured = computed(() => events.value[0])
const rest = computed(() => events.value.slice(1))
const pages = computed(() => Math.max(1, Math.ceil(rest.value.length / PER_PAGE)))
const shown = computed(() => rest.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))

const ctaFor = (e: ChurchEvent) => e.price > 0 ? `Reserve Seats · £${e.price}` : 'RSVP'

// live countdown shown inside the reservation modal
const now = ref(Date.now())
let tick: ReturnType<typeof setInterval> | undefined
onMounted(() => { tick = setInterval(() => { now.value = Date.now() }, 1000) })
onUnmounted(() => clearInterval(tick))
const countdownFor = (date?: string) => {
  if (!date) return null
  const s = Math.floor((new Date(date).getTime() - now.value) / 1000)
  if (Number.isNaN(s) || s <= 0) return null
  return { d: Math.floor(s / 86400), h: Math.floor(s / 3600) % 24, m: Math.floor(s / 60) % 60, s: s % 60 }
}

// ── reservation modal ──
type Step = 'details' | 'payment' | 'done'
const active = ref<ChurchEvent | null>(null)
const step = ref<Step>('details')
const form = ref({ name: '', email: '', phone: '' })
const seats = ref(1)
const pay = ref({ card: '', expiry: '', cvc: '' })

// reservations persist per visitor
const reserved = ref<Record<string, number>>({})
onMounted(() => { try { reserved.value = JSON.parse(localStorage.getItem('event-reservations') || '{}') } catch {} })
const save = () => { try { localStorage.setItem('event-reservations', JSON.stringify(reserved.value)) } catch {} }

const total = computed(() => active.value ? active.value.price * seats.value : 0)

const openReserve = (e: ChurchEvent) => {
  active.value = e
  step.value = 'details'
  seats.value = 1
  pay.value = { card: '', expiry: '', cvc: '' }
}
const close = () => { active.value = null }
const submitDetails = () => {
  if (!active.value) return
  if (active.value.price > 0) { step.value = 'payment'; return }
  confirm()
}
const { submit: cmsSubmit } = useCmsForm('events-grid')
const confirm = () => {
  if (!active.value) return
  // fire-and-forget: the reservation itself stays instant. The event's own
  // details come from the Events collection row backing this card.
  cmsSubmit({
    ...form.value,
    event: active.value.title,
    event_date: (active.value.date || '').slice(0, 10),
    seats: seats.value,
    price: active.value.price > 0 ? `£${active.value.price} × ${seats.value} = £${total.value}` : 'Free',
  })
  reserved.value[active.value.id] = seats.value
  save()
  step.value = 'done'
}
const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') close() }
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <section id="events" class="events" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
        <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
      </div>

      <!-- featured event: horizontal card -->
      <EventCard v-bind="featured" horizontal reservable :cta="ctaFor(featured)" class="featured" @select="openReserve(featured)" />
      <p v-if="reserved[featured.id]" class="ev-booked">✓ You reserved {{ reserved[featured.id] }} {{ reserved[featured.id] === 1 ? 'seat' : 'seats' }}</p>

      <!-- remaining events: paginated grid -->
      <div class="events-grid">
        <div v-for="e in shown" :key="e.id" class="ev-cell">
          <EventCard v-bind="e" reservable :cta="ctaFor(e)" @select="openReserve(e)" />
          <p v-if="reserved[e.id]" class="ev-booked">✓ You reserved {{ reserved[e.id] }} {{ reserved[e.id] === 1 ? 'seat' : 'seats' }}</p>
        </div>
      </div>

      <div v-if="pages > 1" class="gallery-pages">
        <button type="button" :disabled="page === 1" @click="page--">←</button>
        <button
          v-for="n in pages" :key="n" type="button"
          :class="{ active: page === n }" @click="page = n"
        >{{ n }}</button>
        <button type="button" :disabled="page === pages" @click="page++">→</button>
      </div>

      <!-- reservation modal -->
      <Transition name="lb-fade">
        <div v-if="active" class="lightbox rsvp-lightbox" @click.self="close">
          <div class="rsvp-modal">
            <button class="lb-close" type="button" aria-label="Close" @click="close">✕</button>

            <!-- step 1 · details & seats -->
            <template v-if="step === 'details'">
              <p class="eyebrow">{{ active.price > 0 ? 'Reserve your seats' : 'RSVP for' }}</p>
              <h3>{{ active.title }}</h3>
              <div v-if="countdownFor(active.date)" class="rsvp-countdown" aria-label="Time until the event">
                <span v-for="(v, k) in { days: countdownFor(active.date)!.d, hrs: countdownFor(active.date)!.h, min: countdownFor(active.date)!.m, sec: countdownFor(active.date)!.s }" :key="k">
                  <b>{{ v }}</b>{{ k }}
                </span>
              </div>
              <form class="rsvp-form" @submit.prevent="submitDetails">
                <label>Full name<input v-model="form.name" type="text" placeholder="Your name" required></label>
                <div class="row">
                  <label>Email<input v-model="form.email" type="email" placeholder="you@email.com" required></label>
                  <label>Phone<input v-model="form.phone" type="tel" placeholder="Optional"></label>
                </div>
                <div class="ev-seats">
                  <span data-olx-field="caption" class="ev-seats-label">{{ oluxCms.t('Caption', oluxFb['Caption']) }}</span>
                  <div class="ev-stepper">
                    <button type="button" aria-label="Fewer seats" :disabled="seats <= 1" @click="seats--">−</button>
                    <b>{{ seats }}</b>
                    <button type="button" aria-label="More seats" :disabled="seats >= Math.min(8, active.seatsLeft)" @click="seats++">+</button>
                  </div>
                  <span class="ev-seats-left">{{ active.seatsLeft }} seats left</span>
                </div>
                <div v-if="active.price > 0" class="ev-total">
                  <span data-olx-field="captionB">{{ oluxCms.t('Caption B', oluxFb['Caption B']) }}</span><b>£{{ total }}</b>
                </div>
                <button class="btn" type="submit">{{ active.price > 0 ? 'Continue to Payment →' : 'Confirm RSVP 🎉' }}</button>
                <p data-olx-field="textB" class="ev-fine">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
              </form>
            </template>

            <!-- step 2 · payment (ticketed events) -->
            <template v-else-if="step === 'payment'">
              <p data-olx-field="textC" class="eyebrow">{{ oluxCms.t('Text C', oluxFb['Text C']) }}</p>
              <h3>{{ active.title }}</h3>
              <p class="ev-summary">{{ seats }} {{ seats === 1 ? 'seat' : 'seats' }} × £{{ active.price }} — <b>£{{ total }}</b></p>
              <form class="rsvp-form" @submit.prevent="confirm">
                <label>Card number<input v-model="pay.card" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" required></label>
                <div class="row">
                  <label>Expiry<input v-model="pay.expiry" type="text" autocomplete="cc-exp" placeholder="MM / YY" required></label>
                  <label>CVC<input v-model="pay.cvc" type="text" inputmode="numeric" autocomplete="cc-csc" placeholder="123" required></label>
                </div>
                <button class="btn" type="submit">Pay £{{ total }} & Reserve</button>
                <button data-olx-field="textD" class="btn ghost" type="button" @click="step = 'details'">{{ oluxCms.t('Text D', oluxFb['Text D']) }}</button>
                <p data-olx-field="textE" class="ev-fine">{{ oluxCms.t('Text E', oluxFb['Text E']) }}</p>
              </form>
            </template>

            <!-- step 3 · confirmation -->
            <div v-else class="rsvp-done">
              <span class="rsvp-emoji">🎟</span>
              <h3>You're booked{{ form.name ? `, ${form.name.split(' ')[0]}` : '' }}!</h3>
              <p><b>{{ seats }}</b> {{ seats === 1 ? 'seat' : 'seats' }} reserved for <b>{{ active.title }}</b>{{ active.price > 0 ? ` — £${total} paid` : '' }}. A confirmation is on its way to {{ form.email }}.</p>
              <button data-olx-field="textF" class="btn dark" type="button" @click="close">{{ oluxCms.t('Text F', oluxFb['Text F']) }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </section>
</template>

<style scoped>
.events-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.8rem; margin-top: 1.8rem; }
@media (max-width: 1000px) { .events-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .events-grid { grid-template-columns: 1fr; } }

.ev-cell :deep(.event-card) { height: 100%; }
.ev-booked { margin-top: .6rem; font-size: 17px; font-weight: 700; color: var(--color-primary); }

/* seat stepper */
.ev-seats { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
@media (max-width: 600px) { .ev-seats-label { flex-basis: 100%; } }
.ev-seats-label { font-size: .85rem; font-weight: 700; color: var(--color-secondary); }
.ev-stepper { display: flex; align-items: center; gap: .9rem; background: #f4f1ea; border-radius: 999px; padding: .3rem .5rem; }
.ev-stepper button { width: 34px; height: 34px; border-radius: 50%; border: 0; background: #fff; color: var(--color-secondary);
  font-size: 1.2rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 10px rgba(20, 24, 29, .08); }
.ev-stepper button:disabled { opacity: .4; cursor: default; }
.ev-stepper b { min-width: 1.4rem; text-align: center; font-size: 1.1rem; }
.ev-seats-left { font-size: .85rem; color: #55606b; }

.ev-total { display: flex; justify-content: space-between; align-items: center; background: #f4f1ea;
  border-radius: 12px; padding: .8rem 1.1rem; font-size: 17px; color: var(--color-secondary); }
.ev-total b { font-size: 1.3rem; }
.ev-summary { font-size: 17px; color: #55606b; margin: .4rem 0 1rem; }
.ev-fine { font-size: .8rem; color: #8a929b; text-align: center; }
</style>
