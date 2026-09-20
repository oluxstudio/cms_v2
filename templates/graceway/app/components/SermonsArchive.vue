<script setup lang="ts">
const oluxCms = useOluxContent('sermons-archive')
const oluxFb: Record<string, string> = {"Headline":"Browse by series","Text":"\u2715 Clear","Text B":"No sermons match this filter \u2014 try widening it or <button type=\"button\" @click=\"clearFilters\">clear all filters</button>.","Text C":"No sermons yet \u2014 check back after Sunday!","Subheadline":"Recent messages","Subheadline B":"Series","Subheadline C":"Speakers"}
import { sermonSeriesOf, sermonThumb, sermonMedia, mediaIcon } from '~/composables/useSermons'


const route = useRoute()
const router = useRouter()
const sermons = useSermons()
const { sermonsArchive } = useSiteContent()
const allSeries = useSermonSeries()

const PER_PAGE = 12
const speakers = ['All speakers', ...new Set(sermons.map(s => s.speaker))]

// filters initialised from the URL so filtered views are shareable
const q = ref(String(route.query.q ?? ''))
const series = ref(String(route.query.series ?? ''))
const speaker = ref(String(route.query.speaker ?? 'All speakers'))
const media = ref(String(route.query.media ?? ''))
const page = ref(Math.max(1, Number(route.query.page) || 1))

watch([q, series, speaker, media], () => { page.value = 1 })
watch([q, series, speaker, media, page], () => {
  router.replace({ query: {
    ...(q.value ? { q: q.value } : {}),
    ...(series.value ? { series: series.value } : {}),
    ...(speaker.value !== 'All speakers' ? { speaker: speaker.value } : {}),
    ...(media.value ? { media: media.value } : {}),
    ...(page.value > 1 ? { page: String(page.value) } : {}),
  } })
})

const filtered = computed(() => {
  const needle = q.value.trim().toLowerCase()
  return sermons
    .filter(s => !series.value || s.seriesSlug === series.value)
    .filter(s => speaker.value === 'All speakers' || s.speaker === speaker.value)
    .filter(s => !media.value || sermonMedia(s).includes(media.value as any))
    .filter(s => !needle
      || s.title.toLowerCase().includes(needle)
      || s.summary.toLowerCase().includes(needle)
      || (s.scripture ?? '').toLowerCase().includes(needle))
    .sort((a, b) => b.date.localeCompare(a.date))
})
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const shown = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))

const hasFilters = computed(() => !!(q.value || series.value || media.value || speaker.value !== 'All speakers'))
const clearFilters = () => { q.value = ''; series.value = ''; speaker.value = 'All speakers'; media.value = '' }

// featured sermons carousel — flagged sermons first, padded with the latest to 4 slides
const featuredList = computed(() => {
  const flagged = sermons.filter(s => s.featured)
  const latest = [...sermons].sort((a, b) => b.date.localeCompare(a.date))
    .filter(s => !s.featured)
  return [...flagged, ...latest].slice(0, 4)
})
const slide = ref(0)
const goTo = (i: number) => { slide.value = (i + featuredList.value.length) % featuredList.value.length }
let auto: ReturnType<typeof setInterval> | undefined
const restartAuto = () => {
  clearInterval(auto)
  auto = setInterval(() => goTo(slide.value + 1), 6000)
}
onMounted(restartAuto)
onUnmounted(() => clearInterval(auto))
const nav = (i: number) => { goTo(i); restartAuto() }
const ctaFor = (s: (typeof sermons)[number]) => {
  const m = sermonMedia(s)
  return m.includes('video') ? '▶ Watch' : m.includes('audio') ? '🎧 Listen' : '✍ Read'
}

// ── sidebar data ──
const recent = computed(() => [...sermons].sort((a, b) => b.date.localeCompare(a.date)).slice(0, 4))
const speakerCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const s of sermons) counts[s.speaker] = (counts[s.speaker] || 0) + 1
  return Object.entries(counts).sort((a, b) => b[1] - a[1])
})
const seriesCount = (slug: string) => sermons.filter(s => s.seriesSlug === slug).length
</script>

<template>
  <div v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">

    <!-- featured sermons carousel -->
    <section class="sm-featured" v-if="featuredList.length">
      <div class="container">
        <div class="smf-carousel">
          <div class="smf-track" :style="{ transform: `translateX(-${slide * 100}%)` }">
            <NuxtLink
              v-for="s in featuredList" :key="s.slug"
              class="sm-hero-card smf-slide" :to="`/sermons/${s.slug}`"
            >
              <img :src="sermonThumb(s)" :alt="s.title">
              <div class="body">
                <p class="eyebrow">{{ s.featured ? 'Featured sermon' : 'Latest sermon' }}</p>
                <h2>{{ s.title }}</h2>
                <p class="meta">🎙 {{ s.speaker }} · {{ s.date }}<template v-if="sermonSeriesOf(s)"> · {{ sermonSeriesOf(s)!.name }}</template></p>
                <p class="sum">{{ s.summary }}</p>
                <span class="btn">{{ ctaFor(s) }}</span>
              </div>
            </NuxtLink>
          </div>

          <button class="smf-arrow prev" type="button" aria-label="Previous sermon" @click="nav(slide - 1)">←</button>
          <button class="smf-arrow next" type="button" aria-label="Next sermon" @click="nav(slide + 1)">→</button>

          <div class="smf-dots" role="tablist" aria-label="Featured sermons">
            <button
              v-for="(s, i) in featuredList" :key="s.slug" type="button" role="tab"
              :class="{ on: slide === i }" :aria-selected="slide === i"
              :aria-label="`Show ${s.title}`" @click="nav(i)"
            ></button>
          </div>
        </div>
      </div>
    </section>

    <!-- main column + sidebar -->
    <div class="container sm-layout">
      <div class="sm-main">

    <!-- browse by series -->
    <section class="sm-series">
      <div class="container">
        <div class="section-head"><h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2></div>
        <div class="sm-series-strip">
          <button
            v-for="sr in allSeries" :key="sr.slug" type="button"
            class="sm-series-card" :class="{ active: series === sr.slug }"
            @click="series = series === sr.slug ? '' : sr.slug"
          >
            <img :src="sr.cover" :alt="sr.name">
            <span><b>{{ sr.name }}</b><small>{{ sermons.filter(s => s.seriesSlug === sr.slug).length }} messages</small></span>
          </button>
        </div>
      </div>
    </section>

    <!-- filters + grid -->
    <section class="sm-list">
      <div class="container">
        <div class="sm-filters">
          <input v-model="q" type="search" class="sm-search" placeholder="Search title, summary or scripture…" aria-label="Search sermons">
          <select v-model="speaker" class="gallery-sort" aria-label="Filter by speaker">
            <option v-for="sp in speakers" :key="sp" :value="sp">{{ sp }}</option>
          </select>
          <select v-model="media" class="gallery-sort" aria-label="Filter by media type">
            <option value="">All media</option>
            <option value="video">▶ Video</option>
            <option value="audio">🎧 Audio</option>
            <option value="text">✍ Text</option>
          </select>
          <button data-olx-field="text" v-if="hasFilters" type="button" class="sm-clear" @click="clearFilters">{{ oluxCms.t('Text', oluxFb['Text']) }}</button>
        </div>

        <div v-if="!shown.length" class="sm-empty">
          <p data-olx-field="textB" v-if="hasFilters" v-html="oluxCms.t('Text B', oluxFb['Text B'])"></p>
          <p data-olx-field="textC" v-else>{{ oluxCms.t('Text C', oluxFb['Text C']) }}</p>
        </div>

        <div v-else class="sermon-grid">
          <NuxtLink v-for="s in shown" :key="s.slug" class="sermon-card" :to="`/sermons/${s.slug}`">
            <div class="sermon-thumb">
              <img :src="sermonThumb(s)" :alt="s.title" loading="lazy">
              <span v-if="sermonSeriesOf(s)" class="series-badge">{{ sermonSeriesOf(s)!.name }}</span>
            </div>
            <div class="sermon-body">
              <p class="meta">{{ s.date }}</p>
              <h3>{{ s.title }}</h3>
              <p class="preacher">🎙 {{ s.speaker }}</p>
              <p class="summary">{{ s.summary }}</p>
              <p class="sm-media-icons">
                <span v-for="m in sermonMedia(s)" :key="m" :title="m">{{ mediaIcon[m] }}</span>
              </p>
            </div>
          </NuxtLink>
        </div>

        <div v-if="pages > 1" class="gallery-pages">
          <button type="button" :disabled="page === 1" @click="page--">←</button>
          <button v-for="n in pages" :key="n" type="button" :class="{ active: page === n }" @click="page = n">{{ n }}</button>
          <button type="button" :disabled="page === pages" @click="page++">→</button>
        </div>
      </div>
    </section>

      </div>

      <!-- sidebar -->
      <aside class="sm-side">
        <!-- watch live -->
        <div class="sms-card sms-live">
          <h3>{{ sermonsArchive.live.title }}</h3>
          <p>{{ sermonsArchive.live.text }}</p>
          <NuxtLink class="btn" :to="sermonsArchive.live.cta.to">{{ sermonsArchive.live.cta.label }}</NuxtLink>
        </div>

        <!-- recent messages -->
        <div class="sms-card">
          <h3 data-olx-field="subheadline">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h3>
          <NuxtLink v-for="s in recent" :key="s.slug" class="sms-row" :to="`/sermons/${s.slug}`">
            <img :src="sermonThumb(s)" :alt="s.title">
            <span>
              <b>{{ s.title }}</b>
              <small>🎙 {{ s.speaker }} · {{ s.date }}</small>
            </span>
          </NuxtLink>
        </div>

        <!-- filter by series -->
        <div class="sms-card">
          <h3 data-olx-field="subheadlineB">{{ oluxCms.t('Subheadline B', oluxFb['Subheadline B']) }}</h3>
          <button
            v-for="sr in allSeries" :key="sr.slug" type="button"
            class="sms-filter" :class="{ active: series === sr.slug }"
            @click="series = series === sr.slug ? '' : sr.slug"
          >{{ sr.name }} <i>{{ seriesCount(sr.slug) }}</i></button>
        </div>

        <!-- filter by speaker -->
        <div class="sms-card">
          <h3 data-olx-field="subheadlineC">{{ oluxCms.t('Subheadline C', oluxFb['Subheadline C']) }}</h3>
          <button
            v-for="[sp, n] in speakerCounts" :key="sp" type="button"
            class="sms-filter" :class="{ active: speaker === sp }"
            @click="speaker = speaker === sp ? 'All speakers' : sp"
          >{{ sp }} <i>{{ n }}</i></button>
        </div>

        <!-- take it with you -->
        <div class="sms-card">
          <h3>{{ sermonsArchive.takeAway.title }}</h3>
          <p>{{ sermonsArchive.takeAway.text }}</p>
          <div class="sms-links">
            <NuxtLink v-for="l in sermonsArchive.takeAway.links" :key="l.label" class="btn ghost" :to="l.to">{{ l.label }}</NuxtLink>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
/* featured carousel */
.smf-carousel { position: relative; overflow: hidden; border-radius: 24px; }
.smf-track { display: flex; transition: transform .5s ease; }
.smf-slide { flex: 0 0 100%; min-width: 0; }

.smf-arrow { position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
  width: 44px; height: 44px; border-radius: 50%; border: 0; cursor: pointer;
  background: rgba(255, 255, 255, .92); color: var(--color-secondary); font-size: 1.1rem;
  box-shadow: 0 8px 20px rgba(20, 24, 29, .18); transition: background .2s, color .2s; }
.smf-arrow:hover { background: var(--color-primary); color: #fff; }
.smf-arrow.prev { left: 1rem; }
.smf-arrow.next { right: 1rem; }

.smf-dots { position: absolute; left: 50%; bottom: 1rem; transform: translateX(-50%); z-index: 1;
  display: flex; gap: .5rem; background: rgba(20, 24, 29, .28); padding: .45rem .6rem;
  border-radius: 999px; backdrop-filter: blur(4px); }
.smf-dots button { width: 10px; height: 10px; border-radius: 50%; border: 0; cursor: pointer;
  background: rgba(255, 255, 255, .55); transition: background .2s, transform .2s; }
.smf-dots button.on { background: var(--color-primary); transform: scale(1.25); }

.sm-layout { display: grid; grid-template-columns: 1fr 320px; gap: 2.5rem; align-items: start; }
.sm-main { min-width: 0; }
.sm-main .container { max-width: none; padding: 0; }

.sm-side { display: grid; gap: 1.8rem; position: sticky; top: 1.5rem; }
.sms-card { background: #fff; border-radius: 20px; padding: 1.6rem; box-shadow: 0 14px 32px rgba(20, 24, 29, .08); }
.sms-card h3 { font-size: 1.15rem; color: var(--color-secondary); margin-bottom: .7rem; }
.sms-card > p { font-size: 17px; color: #55606b; line-height: 1.6; margin-bottom: 1rem; }

.sms-live { background: var(--color-secondary); }
.sms-live h3 { color: #fff; }
.sms-live p { color: #d5dce2; }

.sms-row { display: flex; gap: .9rem; align-items: center; padding: .7rem 0; border-bottom: 1px solid #f1ece3; }
.sms-row:last-child { border-bottom: 0; padding-bottom: 0; }
.sms-row img { width: 58px; height: 44px; flex: none; border-radius: 10px; object-fit: cover; }
.sms-row b { display: block; font-size: 1rem; color: var(--color-secondary); line-height: 1.3; }
.sms-row small { font-size: .85rem; color: #55606b; }
.sms-row:hover b { color: var(--color-primary); }

.sms-filter { display: flex; justify-content: space-between; align-items: center; width: 100%; text-align: left;
  font: inherit; font-size: 17px; font-weight: 600; color: #55606b; background: none; border: 0; cursor: pointer;
  padding: .55rem .2rem; border-bottom: 1px solid #f1ece3; }
.sms-filter:last-child { border-bottom: 0; }
.sms-filter:hover { color: var(--color-primary); }
.sms-filter.active { color: var(--color-primary); }
.sms-filter i { font-style: normal; font-size: .82rem; font-weight: 700; background: #f4f1ea; border-radius: 999px;
  padding: .15rem .6rem; color: var(--color-secondary); }
.sms-filter.active i { background: var(--color-primary); color: #fff; }

.sms-links { display: grid; gap: .7rem; }

@media (max-width: 1080px) {
  .sm-layout { grid-template-columns: 1fr; }
  .sm-side { position: static; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
}
</style>
