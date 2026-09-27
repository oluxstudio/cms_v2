<script setup lang="ts">
const oluxCms = useOluxContent('contact-map')
const oluxFb: Record<string, string> = {}
const { contactMap } = useSiteContent()
</script>

<template>
  <section class="ct-map-sec" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head center">
        <span class="ct-chip" data-olx-field="chip">{{ oluxCms.t('Chip', contactMap.chip) }}</span>
        <h2 data-olx-field="Headline">{{ oluxCms.t('Title', contactMap.title) }}</h2>
      </div>
      <div class="ct-map">
        <iframe
          :src="oluxCms.t('Map Src', contactMap.mapSrc)" data-olx-field="mapSrc"
          :title="contactMap.mapTitle" loading="lazy"
        ></iframe>
      </div>
    </div>
  </section>
</template>
