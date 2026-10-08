<script setup lang="ts">
const oluxCms = useOluxContent('events-grid')
const oluxFb: Record<string, string> = {"Text":"Upcoming events","Headline":"Life together, beyond Sunday","Text B":"No upcoming events are scheduled right now \u2014 see what we've been up to in the <NuxtLink to=\"/event-archive\">event archive</NuxtLink>."}
import type { ChurchEvent } from '../composables/useSiteContent'

// CMS-first: the "Events" collection feeds the grid; authored rows seed it.
// upcoming only — once an event's date passes it moves to the archive
const events = computed<ChurchEvent[]>(() => upcomingEvents(useEventRows()))
const PER_PAGE = 6
const page = ref(1)
// featured card: the soonest upcoming event marked featured, else the soonest one
const featured = computed(() => events.value.find(e => e.featured) ?? events.value[0])
const rest = computed(() => events.value.filter(e => e !== featured.value))
const pages = computed(() => Math.max(1, Math.ceil(rest.value.length / PER_PAGE)))
const shown = computed(() => rest.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))

// "View Event" opens the event's own page — every detail beside the booking form
const { reserved } = useEventReservations()
</script>

<template>
  <section id="events" class="events" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
        <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
      </div>

      <p data-olx-field="textB" v-if="!featured" class="ev-empty" v-html="oluxCms.t('Text B', oluxFb['Text B'])"></p>

      <!-- featured event: horizontal card -->
      <template v-if="featured">
      <EventCard v-bind="featured" horizontal :to="eventPath(featured)" class="featured" />
      <p v-if="reserved[featured.id]" class="ev-booked">✓ You reserved {{ reserved[featured.id] }} {{ reserved[featured.id] === 1 ? 'seat' : 'seats' }}</p>
      </template>

      <!-- remaining events: paginated grid -->
      <div class="events-grid" data-olx-panel="events">
        <div data-olx-item v-for="e in shown" :key="e.id" class="ev-cell">
          <EventCard v-bind="e" :to="eventPath(e)" />
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

    </div>
  </section>
</template>

<style scoped>
.events-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.8rem; margin-top: 1.8rem; }
@media (max-width: 1000px) { .events-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .events-grid { grid-template-columns: 1fr; } }

.ev-cell :deep(.event-card) { height: 100%; }
.ev-empty { margin-top: 1.4rem; font-size: 17px; color: #55606b; }
.ev-empty a { color: var(--color-primary); font-weight: 700; }
.ev-booked { margin-top: .6rem; font-size: 17px; font-weight: 700; color: var(--color-primary); }

</style>
