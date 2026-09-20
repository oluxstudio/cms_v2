<script setup lang="ts">
const oluxCms = useOluxContent('welcome')
const oluxFb: Record<string, string> = {}
// copy and image come from the global data source
const { welcome } = useSiteContent()
</script>

<template>
  <section class="welcome" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <img :src="welcome.img.src" :alt="welcome.img.alt">
      <div>
        <p class="eyebrow">{{ welcome.eyebrow }}</p>
        <h2>{{ welcome.title }}</h2>
        <blockquote>{{ welcome.quote }}</blockquote>
        <p>{{ welcome.text }}</p>
        <div style="margin-top: 1.5rem;">
          <CtaButton :to="welcome.cta.to" :label="welcome.cta.label" />
        </div>
      </div>
    </div>
  </section>
</template>
