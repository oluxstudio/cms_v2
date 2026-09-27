<script setup lang="ts">
const oluxCms = useOluxContent('welcome')
const oluxFb: Record<string, string> = {}
// copy and image come from the global data source
const { welcome } = useSiteContent()
</script>

<template>
  <section class="welcome" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <img :src="oluxCms.t('Img Src', welcome.img.src)" data-olx-field="imgSrc" :alt="oluxCms.t('Img Alt', welcome.img.alt)">
      <div>
        <p class="eyebrow" data-olx-field="eyebrow">{{ oluxCms.t('Eyebrow', welcome.eyebrow) }}</p>
        <h2 data-olx-field="title">{{ oluxCms.t('Title', welcome.title) }}</h2>
        <blockquote data-olx-field="quote">{{ oluxCms.t('Quote', welcome.quote) }}</blockquote>
        <p data-olx-field="text">{{ oluxCms.t('Text', welcome.text) }}</p>
        <div style="margin-top: 1.5rem;">
          <CtaButton :to="oluxCms.t('Cta To', welcome.cta.to)" :label="oluxCms.t('Cta Label', welcome.cta.label)" data-olx-field="ctaLabel" />
        </div>
      </div>
    </div>
  </section>
</template>
