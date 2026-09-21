<script setup lang="ts">
const props = withDefaults(defineProps<{
  title: string
  text: string
  img: string
  tags?: string[]
  to?: string
  cta?: string
  /** true = image left / content right; false = stacked */
  horizontal?: boolean
  /** true = CTA is a button emitting `select` instead of a link */
  reservable?: boolean
  /** ISO datetime of the event — renders a live countdown chip */
  date?: string
}>(), {
  tags: () => [],
  to: '/contact',
  cta: 'Get Ticket',
  horizontal: false,
  reservable: false,
})

const emit = defineEmits<{ select: [] }>()

// live countdown — ticks once a minute; hides itself once the event has started
const now = ref(Date.now())
let tick: ReturnType<typeof setInterval> | undefined
onMounted(() => { tick = setInterval(() => { now.value = Date.now() }, 60_000) })
onUnmounted(() => clearInterval(tick))
// prominent event date + time, stacked vertically on the card
const when = computed(() => {
  if (!props.date) return null
  const d = new Date(props.date)
  if (Number.isNaN(d.getTime())) return null
  return {
    day: d.toLocaleDateString('en-GB', { day: 'numeric' }),
    month: d.toLocaleDateString('en-GB', { month: 'short' }).toUpperCase(),
    time: d.toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit', hour12: true }).toUpperCase(),
  }
})

const countdown = computed(() => {
  if (!props.date) return null
  const diff = new Date(props.date).getTime() - now.value
  if (Number.isNaN(diff) || diff <= 0) return null
  const mins = Math.floor(diff / 60_000)
  const days = Math.floor(mins / 1440)
  const hours = Math.floor((mins % 1440) / 60)
  if (days > 0) return `${days}d ${hours}h to go`
  if (hours > 0) return `${hours}h ${mins % 60}m to go`
  return `${mins}m to go`
})
</script>

<template>
  <article class="event-card" :class="{ horizontal }">
    <!-- <div class="event-card-img"><img :src="img" :alt="title"></div> -->
    <div class="event-card-body">
      <!-- header row: date/time stacked on the left · countdown on the right -->
      <div v-if="when || countdown" class="event-when">
        <p v-if="when" class="event-date">
          <b>{{ when.day }}</b>
          <span>{{ when.month }}</span>
          <small>{{ when.time }}</small>
        </p>
        <span v-if="countdown" class="event-countdown" aria-label="Time until the event">⏳ {{ countdown }}</span>
      </div>
      <h3>{{ title }}</h3>
      <p class="desc">{{ text }}</p>
      <ul v-if="tags.length" class="tags">
        <li v-for="t in tags" :key="t">{{ t }}</li>
      </ul>
      <button v-if="props.reservable" class="event-card-cta" type="button" @click="emit('select')">{{ cta }}</button>
      <NuxtLink v-else class="event-card-cta" :to="to">{{ cta }}</NuxtLink>
    </div>
  </article>
</template>

<style scoped>
.event-when {
  /* the card body uses align-items:flex-start — stretch so space-between works */
  width: 100%;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .8rem;
  margin-bottom: .5rem;
}
.event-date {
  display: grid;
  justify-items: center;
  gap: .3rem;
  min-width: 64px;
  background: var(--color-primary);
  color: #fff;
  line-height: 1;
  padding: .65rem .8rem .55rem;
  border-radius: 12px;
  margin: 0;
  text-align: center;
}
.event-date b { font-size: 1.5rem; font-weight: 800; }
.event-date span { font-size: .72rem; font-weight: 700; letter-spacing: .12em; }
.event-date small { font-size: .66rem; opacity: .85; }
.event-when .event-countdown { margin: 0; flex: none; }
</style>
