<script setup lang="ts">
const oluxCms = useOluxContent('youth')
const oluxFb: Record<string, string> = {}
// copy and photo tiles come from the global data source
const { youth } = useSiteContent()
</script>

<template>
  <section id="youth" class="broadcast container" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <!-- 7 photo tiles floating over mosaic squares, left & right -->
    <div class="broadcast-side left" aria-hidden="true">
      <span v-for="(img, i) in youth.tiles.left" :key="img" class="tile photo" :class="`t${i + 1}`"><img :src="img" alt=""></span>
    </div>

    <div class="broadcast-side right" aria-hidden="true">
      <span v-for="(img, i) in youth.tiles.right" :key="img" class="tile photo" :class="`t${i === 2 ? 4 : i + 1}`"><img :src="img" alt=""></span>
    </div>

    <div class="container">
      <div class="youth-center">
        <p class="eyebrow">{{ youth.eyebrow }}</p>
        <h2 class="mega">{{ youth.title }}</h2>
        <p class="sub center">{{ youth.sub }}</p>
        <div class="youth-cta center">
          <CtaButton :to="youth.cta.to" :label="youth.cta.label" />
        </div>
      </div>
    </div>
  </section>
</template>
