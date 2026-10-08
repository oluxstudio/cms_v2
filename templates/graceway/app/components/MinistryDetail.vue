<script setup lang="ts">
const oluxCms = useOluxContent('ministry-detail')
const oluxFb: Record<string, string> = {"Headline":"About this ministry"}
// @olux-per-page — each page keeps its own copy of this block (its content is per route)
// @olux-source ministry-pages — this page's content rows live in that collection
// Self-contained ministry detail (kids, prayer, …) — copy keyed by route so
// the section lives inside a block the CMS pipeline can carry 1:1.
const route = useRoute()
const { ministryDetail: map } = useSiteContent()
const d = computed(() => map[route.path] || map['/kids'])
</script>

<template>
  <section class="study-detail" data-olx-panel="ministry-pages" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="study-detail-grid">
        <img :src="d.img" :alt="d.alt">
        <div>
          <h2 data-olx-field="Headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
          <p>{{ d.text }}</p>
          <ul class="study-facts">
            <li><EventIcon name="calendar" :size="18" class="md-icon" /><b>Meets:</b> {{ d.meets }}</li>
            <li><EventIcon name="users" :size="18" class="md-icon" /><b>Led by:</b> {{ d.leader }}</li>
          </ul>
          <div class="study-detail-actions">
            <NuxtLink class="btn" to="/contact">Get Involved</NuxtLink>
            <NuxtLink class="btn ghost" to="/ministries">← All Ministries</NuxtLink>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.study-facts li { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }
.md-icon { color: var(--color-primary); }
</style>
