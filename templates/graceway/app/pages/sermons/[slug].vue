<script setup lang="ts">
import { sermonSeriesOf, sermonThumb, sermonMedia } from '~/composables/useSermons'

const route = useRoute()
const sermons = useSermons()
const sermon = sermons.find(s => s.slug === route.params.slug)

if (!sermon) {
  throw createError({ statusCode: 404, statusMessage: 'Sermon not found', fatal: true })
}

const series = sermonSeriesOf(sermon)
const media = sermonMedia(sermon)

// SEO: title, description, Open Graph, article structured data
const pageUrl = computed(() => `https://cacblackburn.org/sermons/${sermon!.slug}`)
useHead({
  title: `${sermon.title} — Sermons — CAC Blackburn`,
  meta: [
    { name: 'description', content: sermon.summary },
    { property: 'og:title', content: sermon.title },
    { property: 'og:description', content: sermon.summary },
    { property: 'og:image', content: sermonThumb(sermon) },
    { property: 'og:type', content: 'article' },
  ],
  script: [{
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org', '@type': 'Article',
      'headline': sermon.title, 'author': { '@type': 'Person', 'name': sermon.speaker },
      'datePublished': sermon.date, 'description': sermon.summary, 'image': sermonThumb(sermon),
    }),
  }],
})

// media tabs — video default, then audio, then read
const tabs = computed(() => media.filter(m => m !== 'text' || media.length > 1))
const tab = ref<'video' | 'audio' | 'text'>(media[0] ?? 'text')

// lazy YouTube facade: thumbnail until clicked
const videoActive = ref(false)

// audio playback speed
const audioEl = ref<HTMLAudioElement>()
const speed = ref(1)
watch(speed, v => { if (audioEl.value) audioEl.value.playbackRate = v })

// share
const copied = ref(false)
const copyLink = async () => {
  try { await navigator.clipboard.writeText(pageUrl.value); copied.value = true; setTimeout(() => copied.value = false, 2000) } catch {}
}
const shareText = computed(() => encodeURIComponent(`${sermon!.title} — ${pageUrl.value}`))

// series navigation
const inSeries = computed(() => sermons
  .filter(s => series && s.seriesSlug === series.slug)
  .sort((a, b) => a.date.localeCompare(b.date)))
const idx = computed(() => inSeries.value.findIndex(s => s.slug === sermon!.slug))
const prev = computed(() => idx.value > 0 ? inSeries.value[idx.value - 1] : null)
const next = computed(() => idx.value >= 0 && idx.value < inSeries.value.length - 1 ? inSeries.value[idx.value + 1] : null)

// related: same series → same speaker → most recent
const related = computed(() => {
  const others = sermons.filter(s => s.slug !== sermon!.slug)
  const bySeries = others.filter(s => series && s.seriesSlug === series.slug)
  const bySpeaker = others.filter(s => !bySeries.includes(s) && s.speaker === sermon!.speaker)
  const rest = others.filter(s => !bySeries.includes(s) && !bySpeaker.includes(s))
    .sort((a, b) => b.date.localeCompare(a.date))
  return [...bySeries, ...bySpeaker, ...rest].slice(0, 3)
})

const tabLabel = { video: '▶ Watch', audio: '🎧 Listen', text: '✍ Read' } as const
</script>

<template>
  <div v-if="sermon">
    <SiteHeader />
    <section class="page-hero">
      <div class="container">
        <BreadCrumbs :items="[{ label: 'Sermons', to: '/sermons' }, { label: sermon.title }]" />
        <PageHeroContent>
          <NuxtLink v-if="series" class="sd-series-badge" :to="`/sermons?series=${series.slug}`">📚 {{ series.name }}</NuxtLink>
          <h1>{{ sermon.title }}</h1>
          <p>🎙 {{ sermon.speaker }} · {{ sermon.date }}<template v-if="sermon.scripture"> · 📖 {{ sermon.scripture }}</template></p>
        </PageHeroContent>
      </div>
    </section>

    <section class="sd-view">
      <div class="container">
        <!-- media tabs -->
        <div v-if="tabs.length > 1" class="sd-tabs" role="tablist">
          <button
            v-for="t in tabs" :key="t" type="button" role="tab"
            :class="{ active: tab === t }" :aria-selected="tab === t"
            @click="tab = t"
          >{{ tabLabel[t] }}</button>
        </div>

        <!-- video: lazy facade until clicked -->
        <div v-if="tab === 'video' && sermon.videoId" class="sermon-media video sd-media">
          <button v-if="!videoActive" type="button" class="sd-facade" :aria-label="`Play ${sermon.title}`" @click="videoActive = true">
            <img :src="sermonThumb(sermon)" :alt="sermon.title">
            <span class="thumb-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg></span>
          </button>
          <iframe
            v-else
            :src="`https://www.youtube.com/embed/${sermon.videoId}?autoplay=1`"
            :title="sermon.title" allow="autoplay; encrypted-media" allowfullscreen
          ></iframe>
        </div>

        <!-- audio player with speed control -->
        <div v-else-if="tab === 'audio' && sermon.audioUrl" class="sd-audio">
          <img :src="sermonThumb(sermon)" :alt="sermon.title">
          <div class="controls">
            <audio ref="audioEl" :src="sermon.audioUrl" controls preload="metadata"></audio>
            <label class="speed">Speed
              <select v-model.number="speed">
                <option :value="0.75">0.75×</option>
                <option :value="1">1×</option>
                <option :value="1.25">1.25×</option>
                <option :value="1.5">1.5×</option>
              </select>
            </label>
          </div>
        </div>

        <!-- body: as Read tab, or directly when it's the only media -->
        <article v-if="sermon.body && (tab === 'text' || media.length === 1 && media[0] === 'text')" class="sd-body">
          <p v-for="(para, i) in sermon.body" :key="i">{{ para }}</p>
        </article>
        <!-- body below the player when present alongside a single other media type -->
        <article v-else-if="sermon.body && tabs.length <= 1" class="sd-body">
          <p v-for="(para, i) in sermon.body" :key="i">{{ para }}</p>
        </article>

        <!-- attachments -->
        <div v-if="sermon.attachments?.length" class="sd-attachments">
          <h3>Downloads</h3>
          <a v-for="a in sermon.attachments" :key="a.name" :href="a.url" class="sd-file">
            📄 {{ a.name }} <small>{{ a.size }}</small>
          </a>
        </div>

        <!-- share -->
        <div class="sd-share">
          <span>Share:</span>
          <a :href="`https://wa.me/?text=${shareText}`" target="_blank" rel="noopener" class="sh wa">WhatsApp</a>
          <a :href="`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(pageUrl)}`" target="_blank" rel="noopener" class="sh fb">Facebook</a>
          <a :href="`https://twitter.com/intent/tweet?text=${shareText}`" target="_blank" rel="noopener" class="sh x">X</a>
          <button type="button" class="sh copy" @click="copyLink">{{ copied ? '✓ Copied!' : 'Copy link' }}</button>
        </div>

        <!-- series navigation -->
        <div v-if="prev || next" class="sd-series-nav">
          <NuxtLink v-if="prev" :to="`/sermons/${prev.slug}`" class="prev">← {{ prev.title }}<small>Previous in series</small></NuxtLink>
          <span v-else></span>
          <NuxtLink v-if="next" :to="`/sermons/${next.slug}`" class="next">{{ next.title }} →<small>Next in series</small></NuxtLink>
        </div>

        <!-- related sermons -->
        <div class="sd-related">
          <h2>Keep listening</h2>
          <div class="sermon-grid">
            <NuxtLink v-for="s in related" :key="s.slug" class="sermon-card" :to="`/sermons/${s.slug}`">
              <div class="sermon-thumb">
                <img :src="sermonThumb(s)" :alt="s.title" loading="lazy">
                <span v-if="sermonSeriesOf(s)" class="series-badge">{{ sermonSeriesOf(s)!.name }}</span>
              </div>
              <div class="sermon-body">
                <p class="meta">{{ s.date }}</p>
                <h3>{{ s.title }}</h3>
                <p class="preacher">🎙 {{ s.speaker }}</p>
              </div>
            </NuxtLink>
          </div>
        </div>
      </div>
    </section>

    <SiteFooter />
  </div>
</template>
