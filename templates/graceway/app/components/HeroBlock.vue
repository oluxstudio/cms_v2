<script setup lang="ts">
const oluxCms = useOluxContent('hero')
const oluxFb: Record<string, string> = {}
// all copy and pills come from the global data source
const { hero } = useSiteContent()
</script>

<template>
  <section class="hero" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="hero-copy">
        <h1>{{ hero.title }} <span class="hl">{{ hero.titleHighlight }}</span></h1>
        <p class="lead">{{ hero.lead }}</p>

        <div class="hero-live">
          <span class="hero-live-dot" aria-hidden="true"></span>
          <span>{{ hero.liveNote }}</span>
        </div>

        <div class="hero-actions">
          <NuxtLink v-for="(a, i) in hero.actions" :key="a.label" class="btn" :class="{ ghost: i > 0 }" :to="a.to">{{ a.label }}</NuxtLink>
        </div>

        <!-- scattered accent dots -->
        <span class="dot d1"></span>
        <span class="dot d2"></span>
        <span class="dot d3"></span>
      </div>

      <div class="hero-collage">
        <div v-for="(col, c) in [hero.pills.slice(0, 2), hero.pills.slice(2, 4), hero.pills.slice(4, 6)]" :key="c" class="col" :class="`col-${['a','b','c'][c]}`">
          <a
            v-for="p in col" :key="p.key"
            class="pill" :class="p.tint" :href="`#${p.section}`"
            :aria-label="`Go to ${p.label} section`"
          >
            <img :src="p.img" :alt="p.label">
            <span class="pill-label">{{ p.label }}</span>
            <span class="pill-overlay">
              <small>{{ p.desc }}</small>
            </span>
          </a>
        </div>
        <span class="dot d4"></span>
        <span class="dot d5"></span>
      </div>
    </div>
    <div class="hero-crowd" aria-hidden="true"></div>
  </section>
</template>
