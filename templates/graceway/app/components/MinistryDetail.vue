<script setup lang="ts">
const oluxCms = useOluxContent('ministry-detail')
const oluxFb: Record<string, string> = {"Headline":"About this ministry"}
// Self-contained ministry detail (kids, prayer, …) — copy keyed by route so
// the section lives inside a block the CMS pipeline can carry 1:1.
const route = useRoute()
const { ministryDetail: map } = useSiteContent()
const d = computed(() => map[route.path] || map['/kids'])
</script>

<template>
  <section class="study-detail" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="study-detail-grid">
        <img :src="d.img" :alt="d.alt">
        <div>
          <h2 data-olx-field="Headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
          <p>{{ d.text }}</p>
          <ul class="study-facts">
            <li><b>🗓 Meets:</b> {{ d.meets }}</li>
            <li><b>🧑‍🏫 Led by:</b> {{ d.leader }}</li>
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
