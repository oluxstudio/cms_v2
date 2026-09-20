<script setup lang="ts">
const oluxCms = useOluxContent('broadcast')
const oluxFb: Record<string, string> = {}
// section copy, stats and the social platforms come from the global data source
const { broadcast, socials } = useSiteContent()

// band layout: 3 columns of pills, each slot mapped to a platform by key
const BAND: { key: string; cls: string }[][] = [
  [{ key: 'youtube', cls: 'half-top' }, { key: 'instagram', cls: '' }],
  [{ key: 'facebook', cls: 'tall' }, { key: 'tiktok', cls: 'half-bottom' }],
  [{ key: 'x', cls: 'half-top' }, { key: 'twitch', cls: '' }, { key: 'rss', cls: 'half-bottom' }],
]
const band = BAND.map(col => col.flatMap(slot => {
  const p = socials.find(s => s.key === slot.key)
  return p ? [{ ...p, cls: slot.cls }] : []
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
