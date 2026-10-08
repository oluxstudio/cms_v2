<script setup lang="ts">
const oluxCms = useOluxContent('events')
const oluxFb: Record<string, string> = {"Text":"Slide for more <span>\u2192</span>"}
// authored rows come from the global data source; the CMS "Events"
// collection (shared with the events page) overrides when populated
const { eventsHead } = useSiteContent()
// upcoming only — past events live in the archive
const events = computed(() => upcomingEvents(useEventRows()))
// featured card: the soonest upcoming event marked featured, else the soonest one
// carousel: the next upcoming events, max 5, without the featured one
const featured = computed(() => events.value.find(e => e.featured) ?? events.value[0])
const upcoming = computed(() => events.value.filter(e => e !== featured.value).slice(0, 5))
</script>

<template>
  <section id="events" class="events" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow" data-olx-field="eyebrow">{{ oluxCms.t('Eyebrow', eventsHead.eyebrow) }}</p>
        <h2 data-olx-field="title">{{ oluxCms.t('Title', eventsHead.title) }}</h2>
      </div>

      <!-- featured/primed event as the big horizontal card -->
      <EventCard v-if="featured" v-bind="featured" horizontal :to="eventPath(featured)" class="featured" />

      <!-- upcoming events (max 5): horizontal carousel, 3-up on desktop / 1-up on mobile -->
      <div class="event-carousel">
        <div class="event-card-row" data-olx-panel="events">
          <EventCard data-olx-item v-for="e in upcoming" :key="e.id" v-bind="e" :to="eventPath(e)" />
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
