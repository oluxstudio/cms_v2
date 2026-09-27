<script setup lang="ts">
const oluxCms = useOluxContent('donate-cta')
const oluxFb: Record<string, string> = {}
// copy comes from the global data source; CMS panel edits to the
// "Donate Cta" block override it live (same pattern as PageHeroContent).
const { donate } = useSiteContent()
const cms = useOluxContent('donate-cta')
const title = computed(() => cms.t('Headline', donate.title))
const text = computed(() => cms.t('Text', donate.text))
const ctaLabel = computed(() => cms.t('CTA Label', donate.cta.label))
const ctaTo = computed(() => cms.t('CTA Link', donate.cta.to))
</script>

<template>
  <section class="donate" data-olx-panel="donate-cta" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <h2 data-olx-field="Headline">{{ title }}</h2>
      <p class="cta-text" data-olx-field="Text">{{ text }}</p>
      <CtaButton :to="ctaTo" :label="ctaLabel" data-olx-field="CTA Label" />
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