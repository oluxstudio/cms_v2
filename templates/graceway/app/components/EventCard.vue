<script setup lang="ts">
// An event's poster: the picture (or a themed panel when there is none) with
// the date on it, then the type, title, a short summary and the essentials —
// when, where, entry. Everything else lives on the event's own page (`to`).
const props = withDefaults(defineProps<{
  title: string
  text?: string
  img?: string
  /** ISO datetime — the date badge and a live countdown */
  date?: string
  location?: string
  price?: number
  seatsLeft?: number | ''
  ministry?: string
  featured?: boolean
  /** the event's own page */
  to?: string
  cta?: string
  /** true = poster left / details right (the featured event) */
  horizontal?: boolean
  // the rest of an event row — accepted so v-bind="event" adds no stray attributes
  id?: string
  slug?: string
  tags?: string[]
  speakers?: string[]
  guests?: string[]
  media?: unknown[]
  mediaLayout?: string
}>(), {
  text: '',
  img: '',
  to: '/events',
  cta: 'View Event',
  horizontal: false,
})

// live countdown — ticks once a minute; hides itself once the event has started
const now = ref(Date.now())
let tick: ReturnType<typeof setInterval> | undefined
onMounted(() => { tick = setInterval(() => { now.value = Date.now() }, 60_000) })
onUnmounted(() => clearInterval(tick))

const badge = computed(() => eventBadge(props.date))
const countdown = computed(() => eventCountdown(props.date, now.value))
const when = computed(() => [eventDayLabel(props.date, 'short'), eventTimeLabel(props.date)].filter(Boolean).join(' · '))
const place = computed(() => eventPlace(props.location))
const entry = computed(() => {
  const seats = props.seatsLeft === '' || props.seatsLeft === undefined ? '' : ` · ${props.seatsLeft} seats left`
  return eventPriceLabel(props.price) + (countdown.value ? seats : '')
})
const broken = ref(false)
</script>

<template>
  <article class="event-card" :class="{ horizontal }">
    <NuxtLink :to="to" class="ec-poster" :class="{ 'is-plain': !img || broken }" tabindex="-1" aria-hidden="true">
      <img v-if="img && !broken" :src="img" :alt="title" loading="lazy" @error="broken = true">
      <span v-else class="ec-poster-plain">
        <EventIcon name="calendar" :size="horizontal ? 64 : 46" />
        <span>{{ eventTypeLabel(ministry) }}</span>
      </span>
      <span v-if="badge" class="ec-date">
        <b>{{ badge.day }}</b><span>{{ badge.month }}</span>
      </span>
      <span v-if="countdown" class="ec-countdown">{{ countdown }}</span>
      <span v-if="featured && horizontal" class="ec-featured"><EventIcon name="star" :size="13" /> Featured</span>
    </NuxtLink>

    <div class="ec-body">
      <p class="ec-type">{{ eventTypeLabel(ministry) }}</p>
      <h3><NuxtLink :to="to">{{ title }}</NuxtLink></h3>
      <p v-if="text" class="ec-desc">{{ text }}</p>
      <ul class="ec-meta">
        <li v-if="when"><EventIcon name="clock" :size="17" />{{ when }}</li>
        <li v-if="place"><EventIcon name="pin" :size="17" />{{ place }}</li>
        <li><EventIcon name="ticket" :size="17" />{{ entry }}</li>
      </ul>
      <NuxtLink class="ec-cta" :to="to">{{ cta }} <EventIcon name="arrow" :size="18" /></NuxtLink>
    </div>
  </article>
</template>
