<script setup lang="ts">
const oluxCms = useOluxContent('announcements-list')
const oluxFb: Record<string, string> = {"Text":"All","Text B":"No announcements right now \u2014 check back soon."}
// @olux-source announcements
// Every announcement, pinned first then newest — filter by category; each opens its own page.
const all = computed(() => useAnnouncements())
const categories = computed(() => [...new Set(all.value.map(a => a.category))])
const active = ref<string | null>(null)
const shown = computed(() => active.value ? all.value.filter(a => a.category === active.value) : all.value)
</script>

<template>
  <section class="al-sec" data-olx-panel="announcements" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <nav v-if="categories.length > 1" class="al-filter" aria-label="Filter announcements">
        <button data-olx-field="text" type="button" :class="{ on: !active }" @click="active = null">{{ oluxCms.t('Text', oluxFb['Text']) }}</button>
        <button v-for="c in categories" :key="c" type="button" :class="{ on: active === c }" @click="active = c">{{ c }}</button>
      </nav>
      <div v-if="shown.length" class="al-grid">
        <AnnouncementCard v-for="a in shown" :key="a.slug" data-olx-item :item="a" />
      </div>
      <p data-olx-field="textB" v-else class="al-empty">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
    </div>
  </section>
</template>

<style scoped>
.al-sec { padding: 3.5rem 0 5rem; }
.al-filter { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 2rem; }
.al-filter button { padding: .5rem 1.1rem; border-radius: 999px; border: 1.5px solid #e2dbd0; background: #fff; font: inherit; font-size: .95rem;
  font-weight: 700; color: var(--color-secondary); cursor: pointer; transition: all .2s; }
.al-filter button:hover { border-color: var(--color-primary); color: var(--color-primary); }
.al-filter button.on { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
.al-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.5rem; }
@media (max-width: 980px) { .al-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 640px) { .al-grid { grid-template-columns: 1fr; } }
.al-empty { font-size: 1.1rem; color: #55606b; }
</style>
