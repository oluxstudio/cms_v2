<script setup lang="ts">
const oluxCms = useOluxContent('sermons')
const oluxFb: Record<string, string> = {}
import { sermonSeriesOf, sermonThumb, sermonMedia, mediaIcon } from '~/composables/useSermons'

const sermons = useSermons()
const latest = computed(() => [...sermons].sort((a, b) => b.date.localeCompare(a.date)).slice(0, 3))
// section copy comes from the global data source
const { sermonsHead } = useSiteContent()
</script>

<template>
  <section id="sermons" class="sermons" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head center">
        <p class="eyebrow">{{ sermonsHead.eyebrow }}</p>
        <h2>{{ sermonsHead.title }}</h2>
        <p>{{ sermonsHead.text }}</p>
      </div>

      <div class="sermon-grid">
        <NuxtLink v-for="s in latest" :key="s.slug" class="sermon-card" :to="`/sermons/${s.slug}`">
          <div class="sermon-thumb">
            <img :src="sermonThumb(s)" :alt="s.title" loading="lazy">
            <span v-if="sermonSeriesOf(s)" class="series-badge">{{ sermonSeriesOf(s)!.name }}</span>
          </div>
          <div class="sermon-body">
            <p class="meta">{{ s.date }}<template v-if="s.scripture"> · 📖 {{ s.scripture }}</template></p>
            <h3>{{ s.title }}</h3>
            <p class="preacher">🎙 {{ s.speaker }}</p>
            <p class="summary">{{ s.summary }}</p>
            <p class="sm-media-icons">
              <span v-for="m in sermonMedia(s)" :key="m" :title="m">{{ mediaIcon[m] }}</span>
            </p>
          </div>
        </NuxtLink>
      </div>

      <div class="sermon-more"> 
        <CtaButton :to="sermonsHead.cta.to" :label="sermonsHead.cta.label" />
      </div>
    </div>
  </section>
</template>
