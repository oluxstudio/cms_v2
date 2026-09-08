<script setup lang="ts">

const oluxCms = useOluxContent('about')
const oluxFb: Record<string, string> = {"Text":"Our story","Headline":"Advice without the agency overhead","Text B":"Verita was founded on a simple observation: most companies don't need a hundred-page strategy, they need three good decisions made quickly. We keep teams small, senior and accountable.","Image":"/assets/images/hero.svg"}
</script>

<template>
  <section class="about" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
        <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
        <p data-olx-field="textB">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
      </div>
      <img data-olx-field="image" :src="oluxCms.t('Image', oluxFb['Image'])" alt="The Verita office">
    </div>
  </section>
</template>
