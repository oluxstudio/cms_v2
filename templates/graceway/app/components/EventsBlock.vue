<script setup lang="ts">
const oluxCms = useOluxContent('events')
const oluxFb: Record<string, string> = {"Text":"Slide for more <span>\u2192</span>"}
// authored rows come from the global data source; the CMS "Events"
// collection (shared with the events page) overrides when populated
const { events: authoredEvents, eventsHead } = useSiteContent()
const events = computed(() => {
  const rows = (useCms().items('events', []) as any[]).filter(e => e.title && e.img)
  return rows.length ? rows : authoredEvents
})
// featured card: the first primed/featured event (fallback: soonest upcoming)
// carousel: upcoming events, soonest first, max 5, without the featured one
const now = new Date().toISOString()
const byDate = computed(() => [...events.value].sort((a, b) => (a.date ?? '').localeCompare(b.date ?? '')))
const featured = computed(() =>
  byDate.value.find(e => e.featured && (e.date ?? '') >= now)
  ?? byDate.value.find(e => e.featured)
  ?? byDate.value.find(e => (e.date ?? '') >= now))
const upcoming = computed(() =>
  byDate.value.filter(e => (e.date ?? '') >= now && e !== featured.value).slice(0, 5))
</script>

<template>
  <section id="events" class="events" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">{{ eventsHead.eyebrow }}</p>
        <h2>{{ eventsHead.title }}</h2>
      </div>

      <!-- featured/primed event as the big horizontal card -->
      <EventCard v-if="featured" v-bind="featured" horizontal cta="Join This Event" to="/contact" class="featured" />

      <!-- upcoming events (max 5): horizontal carousel, 3-up on desktop / 1-up on mobile -->
      <div class="event-carousel">
        <div class="event-card-row">
          <EventCard v-for="e in upcoming" :key="e.title" v-bind="e" cta="Join This Event" to="/contact" />
        </div>
        <p data-olx-field="text" class="carousel-hint" aria-hidden="true" v-html="oluxCms.t('Text', oluxFb['Text'])"></p>
      </div>

      <div class="events-more">
        <CtaButton to="/events" label="See All Events" />
      </div>
    </div>
  </section>
</template>

<style scoped>
.events-more {
  margin-top: 2rem;
  text-align: center;
}
</style>
