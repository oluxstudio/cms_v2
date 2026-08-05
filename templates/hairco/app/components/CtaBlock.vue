<script setup lang="ts">

const olux = useOluxContent('cta')
const oluxFb: Record<string, string> = {"Headline":"Ready for a fresh look?","Text":"Chairs fill fast on weekends \u2014 reserve yours in under a minute.","CTA Label":"Book an appointment","CTA Link":"/appointment"}
</script>

<template>
  <section class="cta" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="cta-band">
        <div>
          <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
          <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
        </div>
        <a class="btn" :href="olux.t('CTA Link', oluxFb['CTA Link'])">{{ olux.t('CTA Label', oluxFb['CTA Label']) }}</a>
      </div>
    </div>
  </section>
</template>
