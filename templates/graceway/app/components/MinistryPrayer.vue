<script setup lang="ts">
const oluxCms = useOluxContent('ministry-prayer')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its content is per route)
// "Need prayer?" — its own component on a ministry page: a title, a short
// text and a link to the prayer page. Renders inside the ministry sidebar.
const prayerTitle = oluxCms.tRef('Prayer Title', 'Need prayer?')
const prayerText = oluxCms.tRef('Prayer Text', 'Our Prayer Watch prays for every request we receive — in confidence.')
const prayerLink = oluxCms.tRef('Prayer Link', 'Send a prayer request')

const inSidebar = useSidebarSlot('mh-sidebar-prayer')
</script>

<template>
  <section class="ms-block" :class="{ 'ms-inline': !inSidebar }" data-olx-panel="ministry-prayer" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-sidebar-prayer" :disabled="!inSidebar">
      <div class="ms-card mp-card" data-olx-panel="ministry-prayer">
        <p class="ms-title">{{ prayerTitle }}</p>
        <p class="mp-text">{{ prayerText }}</p>
        <NuxtLink class="ms-link" to="/prayer">{{ prayerLink }} <EventIcon name="arrow" :size="15" /></NuxtLink>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.mp-card { background: var(--primary-soft); box-shadow: none; }
.mp-text { color: #4b5560; font-size: .95rem; line-height: 1.55; }
</style>
