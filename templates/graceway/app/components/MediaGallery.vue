<script setup lang="ts">
import type { GalleryItem, MediaType } from '~/composables/useGalleryTypes'
import { slugify } from '~/composables/useGalleryTypes'

const props = withDefaults(defineProps<{
  items: GalleryItem[]
  eyebrow?: string
  heading?: string
}>(), {
  eyebrow: 'Gallery',
  heading: 'Moments that matter',
})
const items = computed(() => props.items)

const PER_PAGE = 16
const cats = computed(() => ['All', ...new Set(items.value.map(i => i.cat))])
const mediaTabs: { key: MediaType | 'all', label: string }[] = [
  { key: 'all', label: 'All media' },
  { key: 'image', label: '📷 Photos' },
  { key: 'video', label: '▶ Videos' },
  { key: 'audio', label: '🎧 Audio' },
]

const activeCat = ref('All')
const activeMedia = ref<MediaType | 'all'>('all')
const sort = ref<'newest' | 'oldest'>('newest')
const page = ref(1)

const filtered = computed(() => {
  const list = items.value.filter(i =>
    (activeCat.value === 'All' || i.cat === activeCat.value)
    && (activeMedia.value === 'all' || i.type === activeMedia.value),
  )
  return list.sort((a, b) => sort.value === 'newest' ? b.date.localeCompare(a.date) : a.date.localeCompare(b.date))
})
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const shown = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))

watch([activeCat, activeMedia, sort], () => { page.value = 1 })

const badge: Record<MediaType, string> = { image: '📷', video: '▶', audio: '🎧' }

// lightbox
const lightbox = ref<number | null>(null) // index within `shown`
const openItem = (i: number) => {
  const g = shown.value[i]
  if (g.type === 'video' || g.type === 'audio') {
    navigateTo(`/media/${slugify(`${g.title}-${g.date}`)}`)
    return
  }
  lightbox.value = i
}
const closeLightbox = () => { lightbox.value = null }
const step = (d: number) => {
  if (lightbox.value === null) return
  lightbox.value = (lightbox.value + d + shown.value.length) % shown.value.length
}
const onKey = (e: KeyboardEvent) => {
  if (lightbox.value === null) return
  if (e.key === 'Escape') closeLightbox()
  if (e.key === 'ArrowRight') step(1)
  if (e.key === 'ArrowLeft') step(-1)
}
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <section class="gallery">
    <div class="container">
      <div class="section-head center">
        <p class="eyebrow">{{ eyebrow }}</p>
        <h2>{{ heading }}</h2>
      </div>

      <!-- media-type tabs · category tabs · sort -->
      <div class="gallery-bar">
        <div class="gallery-tabs media" role="tablist">
          <button
            v-for="m in mediaTabs" :key="m.key" type="button" role="tab"
            :class="{ active: activeMedia === m.key }" :aria-selected="activeMedia === m.key"
            @click="activeMedia = m.key"
          >{{ m.label }}</button>
        </div>
        <select v-model="sort" class="gallery-sort" aria-label="Sort gallery">
          <option value="newest">Newest first</option>
          <option value="oldest">Oldest first</option>
        </select>
      </div>
      <div class="gallery-tabs cats" role="tablist">
        <button
          v-for="c in cats" :key="c" type="button" role="tab"
          :class="{ active: activeCat === c }" :aria-selected="activeCat === c"
          @click="activeCat = c"
        >{{ c }}</button>
      </div>

      <!-- masonry of mixed media — click any item to open the lightbox -->
      <div class="gallery-grid">
        <figure
          v-for="(g, i) in shown" :key="g.title + g.date"
          class="gallery-item clickable" :class="`is-${g.type}`"
          role="button" tabindex="0" :aria-label="`Open ${g.title}`"
          @click="openItem(i)" @keydown.enter="openItem(i)"
        >
          <img v-if="g.type === 'image'" :src="g.img" :alt="g.title" loading="lazy">

          <template v-else-if="g.type === 'video'">
            <img :src="g.img" :alt="g.title" loading="lazy">
            <span class="thumb-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg></span>
          </template>

          <div v-else class="audio-card">
            <span class="audio-icon">🎧</span>
            <span class="audio-wave" aria-hidden="true"><i v-for="n in 14" :key="n"></i></span>
            <span class="audio-hint">Tap to listen</span>
          </div>

          <figcaption>
            <b>{{ badge[g.type] }} {{ g.title }}</b>
            <span>{{ g.cat }}</span>
          </figcaption>
        </figure>
      </div>

      <!-- lightbox -->
      <Transition name="lb-fade">
        <div v-if="lightbox !== null" class="lightbox" @click.self="closeLightbox">
          <button class="lb-close" type="button" aria-label="Close" @click="closeLightbox">✕</button>
          <button class="lb-nav prev" type="button" aria-label="Previous" @click="step(-1)">←</button>

          <div class="lb-body">
            <img v-if="shown[lightbox].type === 'image'" :src="shown[lightbox].img" :alt="shown[lightbox].title">
            <video
              v-else-if="shown[lightbox].type === 'video'" :key="shown[lightbox].src"
              :poster="shown[lightbox].img" :src="shown[lightbox].src" controls autoplay playsinline
            ></video>
            <div v-else class="lb-audio">
              <span class="audio-icon">🎧</span>
              <span class="audio-wave" aria-hidden="true"><i v-for="n in 14" :key="n"></i></span>
              <audio :key="shown[lightbox].src" :src="shown[lightbox].src" controls autoplay></audio>
            </div>
            <p class="lb-caption"><b>{{ shown[lightbox].title }}</b> · {{ shown[lightbox].cat }} · {{ shown[lightbox].date }}</p>
          </div>

          <button class="lb-nav next" type="button" aria-label="Next" @click="step(1)">→</button>
        </div>
      </Transition>

      <!-- pagination -->
      <div v-if="pages > 1" class="gallery-pages">
        <button type="button" :disabled="page === 1" @click="page--">←</button>
        <button
          v-for="n in pages" :key="n" type="button"
          :class="{ active: page === n }" @click="page = n"
        >{{ n }}</button>
        <button type="button" :disabled="page === pages" @click="page++">→</button>
      </div>
    </div>
  </section>
</template>
