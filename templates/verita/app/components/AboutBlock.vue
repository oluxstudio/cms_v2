<script setup lang="ts">

const olux = useOluxContent('about')
const oluxFb: Record<string, string> = {"Text":"Our story","Headline":"Advice without the agency overhead","Text B":"Verita was founded on a simple observation: most companies don't need a hundred-page strategy, they need three good decisions made quickly. We keep teams small, senior and accountable.","Image":"/assets/images/hero.svg"}
</script>

<template>
  <section class="about" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
      </div>
      <img :src="olux.t('Image', oluxFb['Image'])" alt="The Verita office">
    </div>
  </section>
</template>
