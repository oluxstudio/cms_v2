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
// prominent, human-readable event date, e.g. "Sun, Sep 21 · 12:00 PM"
const prettyDate = computed(() => {
  if (!props.date) return null
  const d = new Date(props.date)
  if (Number.isNaN(d.getTime())) return null
  return d.toLocaleDateString('en-GB', { weekday: 'short', month: 'short', day: 'numeric' })
    + ' · ' + d.toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit', hour12: true }).toUpperCase()
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
    <div class="event-card-img"><img :src="img" :alt="title"></div>
    <div class="event-card-body">
      <span v-if="countdown" class="event-countdown" aria-label="Time until the event">⏳ {{ countdown }}</span>
      <p v-if="prettyDate" class="event-date">{{ prettyDate }}</p>
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
.event-date {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  align-self: flex-start;
  background: var(--color-primary);
  color: #fff;
  font-weight: 700;
  font-size: .85rem;
  line-height: 1;
  padding: .45rem .9rem;
  border-radius: 999px;
  margin: 0 0 .35rem;
}
</style>
