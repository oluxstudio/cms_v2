<script setup lang="ts">
const olux = useOluxContent('clients')
const oluxFb: Record<string, string> = {}
const clients = [1, 2, 3, 4, 5, 6, 7, 8]
</script>

<template>
  <section id="clients" class="clients" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container" data-aos="zoom-out">
      <div class="clients-slider swiper">
        <div class="swiper-wrapper align-items-center">
          <div v-for="n in clients" :key="n" class="swiper-slide">
            <img :src="`/assets/images/clients/client-${n}.png`" class="img-fluid" alt="">
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
