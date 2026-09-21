<script setup lang="ts">
const oluxCms = useOluxContent('broadcast')
const oluxFb: Record<string, string> = {}
// section copy, stats and the social platforms come from the global data source
const { broadcast, socials } = useSiteContent()

// Band is generated from the socials data source: every entry gets a pill,
// in array order, spread over 3 columns; shape classes (half-top / tall /
// half-bottom) come from the pill's position so the collage look survives
// any number of platforms.
const COLS = 3
// balanced split, extras go to the LAST columns (7 → 2/2/3, like the design)
const base = Math.floor(socials.length / COLS)
const extra = socials.length % COLS
const sizes = Array.from({ length: COLS }, (_, c) => base + (c >= COLS - extra ? 1 : 0))
let cursor = 0
const band = sizes.map(n => socials.slice(cursor, cursor += n))
  .filter(col => col.length)
  .map((col, c, all) => col.map((p, i) => {
    let cls = ''
    if (c === 1 && all.length > 2) cls = i === 0 ? 'tall' : (i === col.length - 1 ? 'half-bottom' : '')
    else {
      if (i === 0) cls = 'half-top'
      // first column: equal halves — no short filler pill
      if (i === col.length - 1 && col.length > 1 && c !== 0) cls = 'half-bottom'
    }
    return { ...p, cls }
  }))
</script>

<template>
  <section class="broadcast highlighted-section" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="broadcast-grid">
        <div class="broadcast-copy">
          <p class="eyebrow">{{ broadcast.eyebrow }}</p>
          <h2 class="mega">
            <template v-for="(line, i) in contentLines(broadcast.title)" :key="i">
              <br v-if="i">{{ line }}
            </template>
          </h2>
          <p class="sub">{{ broadcast.sub }}</p>
          <div class="broadcast-stats left">
            <div v-for="s in broadcast.stats" :key="s.label"><b>{{ s.value }}</b><span>{{ s.label }}</span></div>
          </div>
        </div>

        <!-- tight 3-column band of solid pills with pill-sized platform icons -->
        <NuxtLink class="broadcast-band" to="/broadcast" aria-label="Broadcast platforms">
          <span v-for="(col, c) in band" :key="c" class="bc-col" :class="{ 'bc-col-mid': c === 1 }">
            <span
              v-for="p in col" :key="p.key"
              class="bc-pill" :class="[p.cls, { off: !p.available }]"
              :style="{ background: p.color }" :title="p.name"
            >
              <span class="bc-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path :d="p.icon"/></svg></span>
              <span v-if="p.tag" class="tile-tag" :class="p.tag.toLowerCase()">{{ p.tag }}</span>
            </span>
          </span>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* platforms not available (available: false) are grayed out */
.bc-pill.off {
  filter: grayscale(1);
  opacity: .45;
}
</style>
