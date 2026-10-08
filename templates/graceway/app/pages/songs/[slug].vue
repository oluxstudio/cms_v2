<script setup lang="ts">
// One song from the "What we're singing" list: author, publish date,
// description, its recordings (from Assets) and the lyrics.
const route = useRoute()
const song = computed(() => useSongs().find(s => s.slug === String(route.params.slug)))
useHead({ title: computed(() => song.value ? `${song.value.title} — Worship — CAC Blackburn` : 'Song — CAC Blackburn') })
const lyrics = computed(() => (song.value?.lyrics ?? '').split(/\n\s*\n/).map(v => v.trim()).filter(Boolean))
</script>

<template>
  <div>
    <SiteHeader />

    <PageHeroContent :crumbs="[{ label: 'Worship', to: '/worship' }, { label: song?.title ?? 'Song' }]">
      <p class="eyebrow">What we're singing</p>
      <h1>{{ song?.title ?? 'Song not found' }}</h1>
      <p v-if="song">By {{ song.artist }}<template v-if="song.date"> · Published {{ publishedOn(song.date) }}</template></p>
    </PageHeroContent>

    <section class="song-view">
      <div v-if="song" class="container song-layout">
        <ContentDetails kind="Song" :title="song.title" :author="song.artist" :date="song.date" :description="song.description" />

        <!-- recordings & videos — MediaPlayer plays local files and YouTube links alike -->
        <div v-if="song.media?.length" class="song-media">
          <div v-for="(m, i) in song.media" :key="i" class="song-media-item">
            <MediaPlayer :src="m.src" :type="m.type === 'audio' ? 'audio' : undefined" :poster="m.img || undefined" :title="m.title || song.title" :item="song" />
            <p v-if="m.title" class="song-media-title">{{ m.title }}</p>
          </div>
        </div>

        <div v-if="lyrics.length" class="song-lyrics">
          <h2>Lyrics</h2>
          <p v-for="(verse, i) in lyrics" :key="i">{{ verse }}</p>
        </div>

        <div class="song-actions">
          <a v-if="song.url" class="btn" :href="song.url" target="_blank" rel="noopener">▶ Listen</a>
          <NuxtLink class="btn ghost" to="/worship">← Back to worship</NuxtLink>
        </div>
      </div>
      <div v-else class="container song-missing">
        <p>We couldn't find that song.</p>
        <NuxtLink class="btn" to="/worship">Back to worship</NuxtLink>
      </div>
    </section>

    <SiteFooter />
  </div>
</template>

<style scoped>
.song-view { padding: 3.5rem 0 4.5rem; }
.song-layout { max-width: 860px; }
.song-media { display: grid; gap: 1.4rem; margin-bottom: 2rem; }
.song-media-title { margin-top: .5rem; font-weight: 700; color: var(--color-secondary); }
.song-lyrics h2 { font-size: 1.5rem; margin-bottom: .8rem; }
.song-lyrics p { white-space: pre-line; font-size: 1.08rem; line-height: 1.75; color: #3d4650; margin-bottom: 1.2rem; }
.song-actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: 1.8rem; }
.song-missing { display: grid; gap: 1rem; justify-items: center; color: #55606b; }
</style>
