<script setup lang="ts">
import type { YouthEventsContent } from '../composables/useSiteContent'

type YEvent = YouthEventsContent['events'][number]

const { youthEvents } = useSiteContent()
const events: YEvent[] = youthEvents.events

// countdown to the next big event (the first authored event)
const target = new Date(events[0]?.date ?? Date.now())
const now = ref(Date.now())
let timer: ReturnType<typeof setInterval>
onMounted(() => { timer = setInterval(() => { now.value = Date.now() }, 1000) })
onUnmounted(() => clearInterval(timer))
const cd = computed(() => {
  const s = Math.max(0, Math.floor((target.getTime() - now.value) / 1000))
  return { d: Math.floor(s / 86400), h: Math.floor(s / 3600) % 24, m: Math.floor(s / 60) % 60, s: s % 60 }
})

// per-event countdown shown in the RSVP lightbox
const countdownFor = (date: string) => {
  const s = Math.floor((new Date(date).getTime() - now.value) / 1000)
  if (Number.isNaN(s) || s <= 0) return null
  const d = Math.floor(s / 86400), h = Math.floor(s / 3600) % 24, m = Math.floor(s / 60) % 60
  return { d, h, m, s: s % 60 }
}

// RSVP state persists per visitor
const going = ref<Record<string, boolean>>({})
onMounted(() => { try { going.value = JSON.parse(localStorage.getItem('youth-rsvp') || '{}') } catch {} })
const save = () => { try { localStorage.setItem('youth-rsvp', JSON.stringify(going.value)) } catch {} }

// RSVP lightbox form — entries land in the owner's Forms inbox
const { submit: cmsSubmit, sending, error: sendError } = useCmsForm('youth-events')
const rsvpEvent = ref<YEvent | null>(null)
const confirmed = ref(false)
const form = ref({ name: '', age: '', phone: '' })
const openRsvp = (e: YEvent) => {
  if (going.value[e.id]) { // already in — clicking again cancels
    going.value[e.id] = false
    save()
    return
  }
  rsvpEvent.value = e
  confirmed.value = false
}
const closeRsvp = () => { rsvpEvent.value = null }
const submitRsvp = async () => {
  if (!rsvpEvent.value) return
  if (!await cmsSubmit({
    ...form.value,
    event: rsvpEvent.value.title,
    event_date: rsvpEvent.value.date.slice(0, 10),
    spots_left: rsvpEvent.value.spots,
  })) return
  going.value[rsvpEvent.value.id] = true
  save()
  confirmed.value = true
}
const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') closeRsvp() }
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <section class="yv-events">
    <div class="container">
      <div class="section-head center">
        <p class="eyebrow">{{ youthEvents.eyebrow }}</p>
        <h2>{{ youthEvents.title }}</h2>
      </div>

      <!-- countdown to the next big event -->
      <div class="yv-countdown">
        <span class="yv-cd-label">{{ youthEvents.countdownLabel }}</span>
        <div class="yv-cd-units">
          <span><b>{{ cd.d }}</b>days</span>
          <span><b>{{ cd.h }}</b>hrs</span>
          <span><b>{{ cd.m }}</b>min</span>
          <span><b>{{ cd.s }}</b>sec</span>
        </div>
      </div>

      <div class="yv-event-grid">
        <article v-for="e in events" :key="e.id" class="yv-event">
          <div class="date"><b>{{ e.day }}</b><span>{{ e.month }}</span></div>
          <div class="body">
            <h3>{{ e.title }}</h3>
            <p>{{ e.text }}</p>
            <div class="rsvp-row">
              <button class="rsvp-btn" :class="{ on: going[e.id] }" type="button" @click="openRsvp(e)">
                {{ going[e.id] ? '✓ You\'re in!' : "I'm coming" }}
              </button>
              <span class="rsvp-count">👥 {{ e.spots + (going[e.id] ? 1 : 0) }} going</span>
            </div>
          </div>
        </article>
      </div>

      <!-- RSVP confirmation lightbox -->
      <Transition name="lb-fade">
        <div v-if="rsvpEvent" class="lightbox rsvp-lightbox" @click.self="closeRsvp">
          <div class="rsvp-modal">
            <button class="lb-close" type="button" aria-label="Close" @click="closeRsvp">✕</button>

            <template v-if="!confirmed">
              <p class="eyebrow">You're coming to</p>
              <h3>{{ rsvpEvent.title }} · {{ rsvpEvent.day }} {{ rsvpEvent.month }}</h3>
              <div v-if="countdownFor(rsvpEvent.date)" class="rsvp-countdown" aria-label="Time until the event">
                <span v-for="(v, k) in { days: countdownFor(rsvpEvent.date)!.d, hrs: countdownFor(rsvpEvent.date)!.h, min: countdownFor(rsvpEvent.date)!.m, sec: countdownFor(rsvpEvent.date)!.s }" :key="k">
                  <b>{{ v }}</b>{{ k }}
                </span>
              </div>
              <form class="rsvp-form" @submit.prevent="submitRsvp">
                <label>First name<input v-model="form.name" type="text" placeholder="Just your first name is fine" required></label>
                <div class="row">
                  <label>Age<input v-model="form.age" type="number" min="10" max="25" placeholder="13–19" required></label>
                  <label>Phone / WhatsApp<input v-model="form.phone" type="tel" placeholder="So we can remind you" required></label>
                </div>
                <p v-if="sendError" class="form-error">{{ sendError }}</p>
                <button class="btn" type="submit" :disabled="sending">{{ sending ? 'Sending…' : 'Count Me In 🎉' }}</button>
                <p class="yv-fine">We only use this to plan food & send one reminder. Never shared.</p>
              </form>
            </template>

            <div v-else class="rsvp-done">
              <span class="rsvp-emoji">🎉</span>
              <h3>See you there{{ form.name ? `, ${form.name}` : '' }}!</h3>
              <p>You're on the list for <b>{{ rsvpEvent.title }}</b>. We'll ping you a reminder the day before.</p>
              <button class="btn dark" type="button" @click="closeRsvp">Done</button>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </section>
</template>
