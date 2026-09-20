<script setup lang="ts">
const oluxCms = useOluxContent('times')
const oluxFb: Record<string, string> = {}
// service days & times come from the global data source
const { services } = useSiteContent()
const times = services.map(s => ({ label: s.label, value: `${s.day} ${s.time}` }))
</script>

<template>
  <div class="times" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div v-for="t in times" :key="t.label + t.value">
        <h3>{{ t.label }}</h3>
        <p>{{ t.value }}</p>
      </div>
    </div>
  </div>
</template>
