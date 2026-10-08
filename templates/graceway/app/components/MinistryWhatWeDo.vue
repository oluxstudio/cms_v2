<script setup lang="ts">
const oluxCms = useOluxContent('ministry-what-we-do')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its own activities)
// @olux-source ministry-activities
// "What we do" on a ministry page — its kicker and title are this block's own
// text; the cards come from the Ministry Activities collection. In Edit site ›
// Items in this block: drag ⠿ (or ↑ ↓) to sort the cards, ✕ to hide one here,
// sort by a column, search, or choose the cards one by one. With nothing set
// it shows the activities whose Page is this page, in the collection's order.
// It renders in the Ministry Hub's main column (the hub leaves a slot for it);
// anywhere else as a normal section.
const activitiesKicker = oluxCms.tRef('Activities Kicker', 'Get involved')
const activitiesTitle = oluxCms.tRef('Activities Title', 'What we do')

const route = useRoute()
const path = computed(() => route.path.replace(/\/+$/, '') || '/')
const content = useSiteContent()

const blockId = usePageBlockId('ministry-what-we-do')
const cms = useCms()
const activities = computed(() => {
  const rows = cms.items('ministryActivities', content.ministryActivities) as (MinistryActivity & { id?: string })[]
  const ids = cms.viewIds('ministryActivities', { id: blockId.value })
  const list = ids
    ? ids.map(id => rows.find(r => String(r.id ?? '') === id)).filter(Boolean) as MinistryActivity[]
    : rows.filter(a => (a.page || '').replace(/\/+$/, '') === path.value)
  return list.filter(a => a.title)
})

const inHub = useSidebarSlot('mh-main-activities')
const editing = useEditMode()
</script>

<template>
  <section class="mw-block" :class="{ 'mw-inline': !inHub }" data-olx-panel="ministry-what-we-do" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-main-activities" :disabled="!inHub">
      <div v-if="activities.length || editing" class="mw-wrap" :class="{ 'mw-empty ms-empty': !activities.length }" data-olx-panel="ministry-what-we-do">
        <p class="mw-kicker">{{ activitiesKicker }}</p>
        <h2>{{ activitiesTitle }}</h2>
        <p v-if="!activities.length" class="ms-empty-note" data-olx-skip>No activities for this page yet — click here and choose them from Ministry Activities. (Hidden on the live site until there are some.)</p>
        <div class="mw-acts">
          <article v-for="(a, i) in activities" :key="a.title + i" data-olx-item class="mw-act">
            <span class="mw-icon" aria-hidden="true">{{ a.icon }}</span>
            <div>
              <h3>{{ a.title }}</h3>
              <p>{{ a.text }}</p>
              <p v-if="a.when" class="mw-when"><EventIcon name="clock" :size="15" />{{ a.when }}</p>
            </div>
          </article>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.mw-block:not(.mw-inline) { display: contents; }
.mw-inline { padding: 0 0 3.5rem; }
.mw-inline .mw-wrap { width: min(1180px, calc(100% - 2.5rem)); margin: 0 auto; }
.mw-wrap { min-width: 0; }
.mw-empty { border-radius: 22px; padding: 1.5rem 1.4rem; }
.mw-kicker { font-size: .8rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--color-primary); margin-bottom: .5rem; }
.mw-wrap h2 { font-size: clamp(1.6rem, 2.6vw, 2.1rem); margin-bottom: 1.2rem; }
.mw-acts { display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: 1rem; }
.mw-act { display: flex; gap: 1rem; align-items: flex-start; background: #fff; border-radius: 20px; padding: 1.2rem 1.25rem; box-shadow: 0 12px 30px rgba(20, 24, 29, .07); }
.mw-icon { width: 48px; height: 48px; flex: none; display: grid; place-items: center; border-radius: 14px; background: var(--primary-soft); font-size: 1.45rem; }
.mw-act h3 { font-size: 1.15rem; margin-bottom: .2rem; }
.mw-act p { font-size: .93rem; line-height: 1.55; color: #55606b; }
.mw-act .mw-when { margin-top: .45rem; display: flex; align-items: center; gap: .35rem; font-weight: 700; font-size: .86rem; color: var(--color-secondary); }
.mw-when .event-icon { color: var(--color-primary); }
</style>
