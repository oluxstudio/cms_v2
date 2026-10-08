<script setup lang="ts">
const oluxCms = useOluxContent('announcements-preview')
const oluxFb: Record<string, string> = {"Text":"What's happening","Headline":"Latest announcements"}
// @olux-source announcements
// The latest announcements (pinned first) — on the About page.
const latest = computed(() => useAnnouncements().slice(0, 3))
</script>

<template>
  <section class="ap-sec" data-olx-panel="announcements" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div v-if="latest.length" class="container ap-wrap">
      <div class="ap-head">
        <div>
          <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
          <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
        </div>
        <NuxtLink class="btn ghost" to="/announcements">All announcements</NuxtLink>
      </div>
      <div class="ap-grid">
        <AnnouncementCard v-for="a in latest" :key="a.slug" data-olx-item :item="a" />
      </div>
    </div>
  </section>
</template>

<style scoped>
.ap-wrap { padding-top: 2rem; padding-bottom: 4rem; }
.ap-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.ap-head h2 { font-size: clamp(1.8rem, 3vw, 2.4rem); }
.ap-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.4rem; margin-top: 1.8rem; }
@media (max-width: 980px) { .ap-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 640px) { .ap-grid { grid-template-columns: 1fr; } }
</style>
