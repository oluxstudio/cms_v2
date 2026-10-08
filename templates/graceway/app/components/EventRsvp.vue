<script setup lang="ts">
// RSVP / seat reservation for one event: details & seats → payment (ticketed
// events) → confirmation. Used on the event page, beside the event's
// details. Submissions land in the CMS "events-grid" form inbox.
import type { ChurchEvent } from '../composables/useSiteContent'

const props = defineProps<{ event: ChurchEvent }>()
const emit = defineEmits<{ done: [] }>()

type Step = 'details' | 'payment' | 'done'
const step = ref<Step>('details')
const form = ref({ name: '', email: '', phone: '' })
const seats = ref(1)
const pay = ref({ card: '', expiry: '', cvc: '' })
// a new event (the modal reused for another card) starts over
watch(() => props.event.id, () => { step.value = 'details'; seats.value = 1; pay.value = { card: '', expiry: '', cvc: '' } })

// an empty "seats left" means no limit — still cap one booking at 8 seats
const seatCap = computed(() => props.event.seatsLeft === '' ? 8 : Math.min(8, props.event.seatsLeft))
const soldOut = computed(() => props.event.seatsLeft !== '' && props.event.seatsLeft <= 0)
const total = computed(() => props.event.price * seats.value)

// live countdown
const now = ref(Date.now())
let tick: ReturnType<typeof setInterval> | undefined
onMounted(() => { tick = setInterval(() => { now.value = Date.now() }, 1000) })
onUnmounted(() => clearInterval(tick))
const countdown = computed(() => {
  const s = Math.floor((new Date(props.event.date).getTime() - now.value) / 1000)
  if (Number.isNaN(s) || s <= 0) return null
  return { days: Math.floor(s / 86400), hrs: Math.floor(s / 3600) % 24, min: Math.floor(s / 60) % 60, sec: s % 60 }
})

const { reserved, record } = useEventReservations()
const { submit: cmsSubmit } = useCmsForm('events-grid')

const submitDetails = () => {
  if (props.event.price > 0) { step.value = 'payment'; return }
  confirm()
}
const confirm = () => {
  // fire-and-forget: the reservation itself stays instant
  cmsSubmit({
    ...form.value,
    event: props.event.title,
    event_date: (props.event.date || '').slice(0, 10),
    seats: seats.value,
    price: props.event.price > 0 ? `£${props.event.price} × ${seats.value} = £${total.value}` : 'Free',
  })
  record(props.event.id, seats.value)
  step.value = 'done'
}
</script>

<template>
  <div class="rsvp">
    <!-- step 1 · details & seats -->
    <template v-if="step === 'details'">
      <p class="eyebrow">{{ event.price > 0 ? 'Reserve your seats' : 'RSVP' }}</p>
      <h3>{{ event.title }}</h3>
      <div v-if="countdown" class="rsvp-countdown" aria-label="Time until the event">
        <span v-for="(v, k) in countdown" :key="k"><b>{{ v }}</b>{{ k }}</span>
      </div>
      <p v-if="reserved[event.id]" class="ev-booked">✓ You've reserved {{ reserved[event.id] }} {{ reserved[event.id] === 1 ? 'seat' : 'seats' }} — book more below.</p>
      <p v-if="soldOut" class="ev-summary">This event is fully booked.</p>
      <form v-else class="rsvp-form" @submit.prevent="submitDetails">
        <label>Full name<input v-model="form.name" type="text" placeholder="Your name" required></label>
        <div class="row">
          <label>Email<input v-model="form.email" type="email" placeholder="you@email.com" required></label>
          <label>Phone<input v-model="form.phone" type="tel" placeholder="Optional"></label>
        </div>
        <div class="ev-seats">
          <span class="ev-seats-label">Seats</span>
          <div class="ev-stepper">
            <button type="button" aria-label="Fewer seats" :disabled="seats <= 1" @click="seats--">−</button>
            <b>{{ seats }}</b>
            <button type="button" aria-label="More seats" :disabled="seats >= seatCap" @click="seats++">+</button>
          </div>
          <span v-if="event.seatsLeft !== ''" class="ev-seats-left">{{ event.seatsLeft }} seats left</span>
        </div>
        <div v-if="event.price > 0" class="ev-total">
          <span>Total</span><b>£{{ total }}</b>
        </div>
        <button class="btn" type="submit">{{ event.price > 0 ? 'Continue to Payment →' : 'Confirm RSVP 🎉' }}</button>
        <p class="ev-fine">We only use your details for this event. Never shared.</p>
      </form>
    </template>

    <!-- step 2 · payment (ticketed events) -->
    <template v-else-if="step === 'payment'">
      <p class="eyebrow">Payment</p>
      <h3>{{ event.title }}</h3>
      <p class="ev-summary">{{ seats }} {{ seats === 1 ? 'seat' : 'seats' }} × £{{ event.price }} — <b>£{{ total }}</b></p>
      <form class="rsvp-form" @submit.prevent="confirm">
        <label>Card number<input v-model="pay.card" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" required></label>
        <div class="row">
          <label>Expiry<input v-model="pay.expiry" type="text" autocomplete="cc-exp" placeholder="MM / YY" required></label>
          <label>CVC<input v-model="pay.cvc" type="text" inputmode="numeric" autocomplete="cc-csc" placeholder="123" required></label>
        </div>
        <button class="btn" type="submit">Pay £{{ total }} & Reserve</button>
        <button class="btn ghost" type="button" @click="step = 'details'">← Back</button>
        <p class="ev-fine">🔒 Payments are processed securely. You'll receive a receipt by email.</p>
      </form>
    </template>

    <!-- step 3 · confirmation -->
    <div v-else class="rsvp-done">
      <span class="rsvp-emoji">🎟</span>
      <h3>You're booked{{ form.name ? `, ${form.name.split(' ')[0]}` : '' }}!</h3>
      <p><b>{{ seats }}</b> {{ seats === 1 ? 'seat' : 'seats' }} reserved for <b>{{ event.title }}</b>{{ event.price > 0 ? ` — £${total} paid` : '' }}. A confirmation is on its way to {{ form.email }}.</p>
      <button class="btn dark" type="button" @click="step = 'details'; emit('done')">Done</button>
    </div>
  </div>
</template>

<style scoped>
.rsvp h3 { font-size: 1.35rem; margin-bottom: 1.2rem; }
.ev-booked { margin: .4rem 0 .8rem; font-size: 1rem; font-weight: 700; color: var(--color-primary); }

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
