<script setup lang="ts">
// Hold the first paint until the rendering site's CMS content is loaded —
// visitors should only ever see CMS content, never the baked fallbacks
// flashing first. useCms opens `ready` instantly for the baked site (its
// snapshot is current) and after the live fetch (or a short safety timeout)
// for every other site.
const { ready } = useCms()
</script>

<template>
  <div>
    <NuxtPage v-if="ready" />
    <div v-else class="olux-boot" aria-hidden="true" />
  </div>
</template>

<style>
.olux-boot { min-height: 100vh; background: #fdfefe; }
</style>
