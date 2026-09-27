<script setup lang="ts">
const oluxCms = useOluxContent('times')
const oluxFb: Record<string, string> = {}
// service days & times come from the Services collection
// @olux-source services
// computed so connect-editor saves re-render in place (useCms data is reactive)
const services = computed(() => useServices())
</script>

<template>
  <div class="times" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div data-olx-item v-for="s in services" :key="s.label + s.day + s.time" class="times-cell">
        <h3>{{ s.label }}</h3>
        <p>{{ [s.day, s.time].filter(Boolean).join(' ') }}</p>
      </div>
    </div>
  </div>
</template>
