<script setup lang="ts">

const olux = useOluxContent('hero')
const oluxFb: Record<string, string> = {"Text":"Consulting that delivers","Headline":"Clarity for every business decision","Text B":"Verita helps growing companies untangle strategy, operations and finance with advice you can act on the same week.","CTA Label":"Start a Project","CTA Link":"/contact","CTA B Label":"Meet the Team","CTA B Link":"/about","Image":"/assets/images/hero.svg"}
</script>

<template>
  <section class="hero" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div>
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h1>{{ olux.t('Headline', oluxFb['Headline']) }}</h1>
        <p class="lead">{{ olux.t('Text B', oluxFb['Text B']) }}</p>
        <div class="actions">
          <a class="btn" :href="olux.t('CTA Link', oluxFb['CTA Link'])">{{ olux.t('CTA Label', oluxFb['CTA Label']) }}</a>
          <a class="btn ghost" :href="olux.t('CTA B Link', oluxFb['CTA B Link'])">{{ olux.t('CTA B Label', oluxFb['CTA B Label']) }}</a>
        </div>
      </div>
      <img :src="olux.t('Image', oluxFb['Image'])" alt="Consulting illustration">
    </div>
  </section>
</template>
