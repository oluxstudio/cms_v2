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
        <p class="eyebrow" data-olx-field="eyebrow">{{ oluxCms.t('Eyebrow', sermonsHead.eyebrow) }}</p>
        <h2 data-olx-field="title">{{ oluxCms.t('Title', sermonsHead.title) }}</h2>
        <p data-olx-field="text">{{ oluxCms.t('Text', sermonsHead.text) }}</p>
      </div>

      <div class="sermon-grid" data-olx-panel="sermons">
        <NuxtLink data-olx-item v-for="s in latest" :key="s.slug" class="sermon-card" :to="`/sermons/${s.slug}`">
          <div class="sermon-thumb">
            <img :src="sermonThumb(s)" :alt="s.title" loading="lazy">
            <span v-if="sermonSeriesOf(s)" class="series-badge">{{ sermonSeriesOf(s)!.name }}</span>
          </div>
          <div class="sermon-body">
            <p class="meta">{{ publishedOn(s.date) }}<template v-if="s.scripture"> · 📖 {{ s.scripture }}</template></p>
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
        <CtaButton :to="oluxCms.t('Cta To', sermonsHead.cta.to)" :label="oluxCms.t('Cta Label', sermonsHead.cta.label)" data-olx-field="ctaLabel" />
      </div>
    </div>
  </section>
</template>
