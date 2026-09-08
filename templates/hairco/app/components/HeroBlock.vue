<script setup lang="ts">
const oluxCms = useOluxContent('hero')
const oluxFb: Record<string, string> = {"Image":"/assets/images/hero.jpg"}
const { field } = useCms()
</script>

<template>
  <section class="hero" data-olx-key="hero" data-olx-kind="component" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div>
        <p class="eyebrow" data-olx-field="caption">{{ field('hero', 'caption', 'Salon & Hair Care') }}</p>
        <h1 data-olx-field="heading">{{ field('hero', 'heading', 'Beautiful hair begins the moment you walk in') }}</h1>
        <p class="lead" data-olx-field="lead">{{ field('hero', 'lead', 'From precision cuts to gentle coloring and deep-care routines, our stylists shape a look that feels unmistakably you.') }}</p>
        <div class="actions">
          <a class="btn" href="/appointment" data-olx-field="heroButton">{{ field('hero', 'heroButton', 'Book an appointment') }}</a>
          <a class="btn ghost" href="/services" data-olx-field="servicesButton">{{ field('hero', 'servicesButton', 'Explore services') }}</a>
        </div>
      </div>
      <div class="visual">
        <img :src="oluxCms.t('Image', oluxFb['Image'])" alt="Inside the Hair Co. salon" data-olx-field="image">
      </div>
    </div>
  </section>
</template>
