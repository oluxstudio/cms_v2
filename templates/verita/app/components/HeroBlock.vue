<script setup lang="ts">

const oluxCms = useOluxContent('hero')
const oluxFb: Record<string, string> = {"Text":"Consulting that delivers","Headline":"Clarity for every business decision","Text B":"Verita helps growing companies untangle strategy, operations and finance with advice you can act on the same week.","CTA Label":"Start a Project","CTA Link":"/contact","CTA B Label":"Meet the Team","CTA B Link":"/about","Image":"/assets/images/hero.svg"}
</script>

<template>
  <section class="hero" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div>
        <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
        <h1 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h1>
        <p data-olx-field="textB" class="lead">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
        <div class="actions">
          <a data-olx-field="ctaLabel" class="btn" :href="oluxCms.t('CTA Link', oluxFb['CTA Link'])">{{ oluxCms.t('CTA Label', oluxFb['CTA Label']) }}</a>
          <a data-olx-field="ctaBLabel" class="btn ghost" :href="oluxCms.t('CTA B Link', oluxFb['CTA B Link'])">{{ oluxCms.t('CTA B Label', oluxFb['CTA B Label']) }}</a>
        </div>
      </div>
      <img data-olx-field="image" :src="oluxCms.t('Image', oluxFb['Image'])" alt="Consulting illustration">
    </div>
  </section>
</template>
