<script setup lang="ts">
const oluxCms = useOluxContent('donate-cta')
const oluxFb: Record<string, string> = {}
// copy comes from the global data source
const { donate } = useSiteContent()
</script>

<template>
  <section class="donate" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <h2>{{ donate.title }}</h2>
      <p class="cta-text">{{ donate.text }}</p>
      <CtaButton :to="donate.cta.to" :label="donate.cta.label" />
    </div>
  </section>
</template>
<style scoped>
.container {
  text-align: center;
}
.cta-text{
  margin-block: 1rem;
}
</style>