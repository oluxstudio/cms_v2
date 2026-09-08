<script setup lang="ts">
const oluxCms = useOluxContent('cta')
const oluxFb: Record<string, string> = {"CTA Label":"Book an appointment","CTA Link":"/appointment"}
const { field } = useCms()
</script>

<template>
  <section class="cta" data-olx-key="cta" data-olx-kind="component" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="cta-band">
        <div>
          <h2 data-olx-field="heading">{{ field('cta', 'heading', 'Ready for a fresh look?') }}</h2>
          <p data-olx-field="body">{{ field('cta', 'body', 'Chairs fill fast on weekends — reserve yours in under a minute.') }}</p>
        </div>
        <a data-olx-field="ctaLabel" class="btn" :href="oluxCms.t('CTA Link', oluxFb['CTA Link'])">{{ oluxCms.t('CTA Label', oluxFb['CTA Label']) }}</a>
      </div>
    </div>
  </section>
</template>
