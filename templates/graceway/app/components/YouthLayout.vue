<script setup lang="ts">

const oluxCms = useOluxContent('youth-layout')
const oluxFb: Record<string, string> = {}
</script>

<template>
  <div class="container youth-layout" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="youth-main">
      <YouthEvents />
      <YouthEngage />
      <MediaGallery :items="useYouthMedia()" />
    </div>
    <YouthSidebar />
  </div>
</template>
