<script setup lang="ts">
const oluxCms = useOluxContent('worship')
const oluxFb: Record<string, string> = {}
// All copy/images come from the central data source (useSiteContent).
const { worship } = useSiteContent()
const lines = contentLines
</script>

<template>
  <section id="worship" class="worship showcase" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="showcase-copy">
        <h2 class="mega">
          <template v-for="(line, i) in lines(worship.title)" :key="i">
            <br v-if="i">{{ line }}
          </template>
        </h2>
        <p class="sub">{{ worship.sub }}</p>
        <!-- <form class="search-row" @submit.prevent>
          <input type="search" placeholder="Search services, choirs, teams…">
          <button class="search-btn" type="submit" aria-label="Search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
          </button>
        </form> -->
        <div class="trend">
          <span class="trend-label">{{ worship.trendLabel }}</span>
          <a v-for="link in worship.trendLinks" :key="link.label" :href="link.to">{{ link.label }}</a>
        </div>
        <div class="showcase-cta">
          <CtaButton :to="worship.cta.to" :label="worship.cta.label" />
        </div>
      </div>

      <div class="showcase-grid">
        <div class="sg-col">
          <img class="sg-photo" :src="worship.grid.photos.col1.src" :alt="worship.grid.photos.col1.alt">
          <div class="sg-tile">
            <template v-for="(line, i) in lines(worship.grid.tileTop.text)" :key="i">
              <br v-if="i">{{ line }}
            </template>
            <span class="sg-tile-badge">{{ worship.grid.tileTop.badge }}</span>
          </div>
        </div>
        <div class="sg-col sg-col-mid">
          <img v-for="photo in worship.grid.photos.col2" :key="photo.src" class="sg-photo" :class="{ tall: photo.tall }" :src="photo.src" :alt="photo.alt">
        </div>
        <div class="sg-col sg-col-last">
          <div class="sg-tile small">
            <template v-for="(line, i) in lines(worship.grid.tileSmall.text)" :key="i">
              <br v-if="i">{{ line }}
            </template>
            <span class="sg-tile-badge top">{{ worship.grid.tileSmall.badge }}</span>
          </div>
          <img v-for="photo in worship.grid.photos.col3" :key="photo.src" class="sg-photo" :src="photo.src" :alt="photo.alt">
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped >
.showcase-cta { margin-top: 2.5rem; }
</style>