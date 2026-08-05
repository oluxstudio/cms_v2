<script setup lang="ts">

const olux = useOluxContent('hero')
const oluxFb: Record<string, string> = {"Image":"/assets/images/hero.png","Headline":"Project Solutions with <span class=\"circle\">Tekstack.</span>","Subheadline":"Our business is helping you grow your business","Text":"Top Web & Applications Solutions","CTA Label":"Get Started","CTA Link":"#about","Caption":"Watch My Video"}
</script>

<template>
  <section id="hero" class="hero" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container position-relative">
      <div class="row gy-5" data-aos="fade-in">
        <div class="col-lg-6 order-lg-1 order-2">
          <img :src="olux.t('Image', oluxFb['Image'])" class="img-fluid rounded-4 mb-4" alt="">
        </div>
        <div class="col-lg-6 order-lg-2 order-1 d-flex flex-column align-items-right justify-content-center text-left caption">
          <h2 v-html="olux.t('Headline', oluxFb['Headline'])"></h2>
          <h3>{{ olux.t('Subheadline', oluxFb['Subheadline']) }}</h3>
          <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
          <div class="d-flex">
            <a :href="olux.t('CTA Link', oluxFb['CTA Link'])" class="btn-get-started">{{ olux.t('CTA Label', oluxFb['CTA Label']) }}</a>
            <a href="https://youtu.be/ZV16ev0n9J8" class="glightbox btn-watch-video d-flex align-items-center">
              <i class="bi bi-play-circle"></i><span>{{ olux.t('Caption', oluxFb['Caption']) }}</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
