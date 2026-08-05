<script setup lang="ts">
const olux = useOluxContent('about')
const oluxFb: Record<string, string> = {"Image":"/assets/images/about.svg","Text":"About Hair Co.","Headline":"Healthy hair begins with a healthy routine","Text B":"For over fifteen years our collective has cared for hair the slow way \u2014 honest consultations, gentle products and techniques that respect what grows naturally.","CTA Label":"Read our story","CTA Link":"/about"}
const points = olux.list('Point', [
  'Long-Lasting Hold',
  'Effective Ingredients',
  'Gentle Formulas',
  'Deep Hydration',
])
</script>

<template>
  <section class="about tinted" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="visual">
        <img :src="olux.t('Image', oluxFb['Image'])" alt="Inside the salon">
      </div>
      <div>
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
        <ul>
          <li v-for="p in points" :key="p">{{ p }}</li>
        </ul>
        <a class="btn" :href="olux.t('CTA Link', oluxFb['CTA Link'])">{{ olux.t('CTA Label', oluxFb['CTA Label']) }}</a>
      </div>
    </div>
  </section>
</template>
