<script setup lang="ts">
const oluxCms = useOluxContent('ministry-events')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its content is per route)
// @olux-source events
// "Upcoming events" — its own component on a ministry page: a title, a link
// and a grid of Events. In Edit site it opens with its fields and the Events
// grid ("Items in this block" picks, hides and orders which events show on
// THIS page); without a selection it shows this ministry's event type. Only
// upcoming events, at most three. Renders inside the ministry sidebar.
const eventsTitle = oluxCms.tRef('Events Title', 'Upcoming events')
const eventsLink = oluxCms.tRef('Events Link', 'All events')

const route = useRoute()
const path = computed(() => route.path.replace(/\/+$/, '') || '/')
const blockId = usePageBlockId('ministry-events')
const cms = useCms()
const events = computed(() => {
  const rows = useEventRows()
  const ids = cms.viewIds('events', { id: blockId.value })
  if (ids) {
    const upcoming = new Map(upcomingEvents(rows).map(e => [String(e.cmsId ?? ''), e] as const))
    return ids.map(id => upcoming.get(id)).filter(Boolean).slice(0, 3) as ReturnType<typeof useEventRows>
  }
  return upcomingEvents(rows, MINISTRY_EVENT_TYPE[path.value] ?? 'church').slice(0, 3)
})
const inSidebar = useSidebarSlot('mh-sidebar-events')
const editing = useEditMode()
</script>

<template>
  <section class="ms-block" :class="{ 'ms-inline': !inSidebar }" data-olx-panel="ministry-events" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-sidebar-events" :disabled="!inSidebar">
      <div v-if="events.length || editing" class="ms-card" :class="{ 'ms-empty': !events.length }" data-olx-panel="ministry-events">
        <p class="ms-title">{{ eventsTitle }}</p>
        <p v-if="!events.length" class="ms-empty-note" data-olx-skip>No events chosen for this page yet — click here to choose them. (Hidden on the live site until there are some.)</p>
        <ul class="me-list">
          <li v-for="e in events" :key="e.id" data-olx-item>
            <NuxtLink :to="eventPath(e)" class="me-event">
              <span v-if="eventBadge(e.date)" class="me-date"><b>{{ eventBadge(e.date)!.day }}</b>{{ eventBadge(e.date)!.month }}</span>
              <span class="me-body">
                <b>{{ e.title }}</b>
                <small>{{ [eventTimeLabel(e.date), eventPlace(e.location)].filter(Boolean).join(' · ') }}</small>
              </span>
            </NuxtLink>
          </li>
        </ul>
        <NuxtLink class="ms-link" to="/events">{{ eventsLink }} <EventIcon name="arrow" :size="15" /></NuxtLink>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.me-list { list-style: none; padding: 0; margin: 0 0 .4rem; display: grid; }
.me-event { display: flex; gap: .85rem; align-items: center; padding: .6rem 0; border-bottom: 1px solid #f0ebe3; color: inherit; }
.me-list li:last-child .me-event { border-bottom: 0; }
.me-date { flex: none; width: 54px; display: grid; justify-items: center; padding: .45rem 0; border-radius: 14px; background: var(--color-primary);
  color: #fff; font-size: .68rem; font-weight: 800; letter-spacing: .12em; line-height: 1.1; }
.me-date b { font-family: var(--font-heading); font-size: 1.5rem; letter-spacing: 0; }
.me-body b { display: block; color: var(--color-secondary); font-size: 1rem; line-height: 1.3; }
.me-event:hover .me-body b { color: var(--color-primary); }
.me-body small { display: block; margin-top: .15rem; color: #6b737c; font-size: .84rem; }
</style>
