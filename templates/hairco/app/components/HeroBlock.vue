<script setup lang="ts">

const olux = useOluxContent('hero')
const oluxFb: Record<string, string> = {"Text":"Salon & Hair Care","Headline":"Beautiful hair begins the moment you walk in","Text B":"From precision cuts to gentle coloring and deep-care routines, our stylists shape a look that feels unmistakably you.","CTA Label":"Book an appointment","CTA Link":"/appointment","CTA B Label":"Explore services","CTA B Link":"/services","Image":"/assets/images/hero.svg"}
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
      <div class="visual">
        <img :src="olux.t('Image', oluxFb['Image'])" alt="Salon styling">
      </div>
    </div>
  </section>
</template>
