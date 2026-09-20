<script setup lang="ts">
const oluxCms = useOluxContent('worship-showcase')
const oluxFb: Record<string, string> = {}
// Worship main area — banner card, trending carousel, paginated worship
// library and the sticky sidebar. Self-contained so the CMS page rewrite
// keeps it whole.
import { ref, computed, watch } from 'vue'
import { slugify } from '~/composables/useGalleryTypes'
const mediaLink = (m: { title: string, date: string }) => `/media/${slugify(`${m.title}-${m.date}`)}`

const { worshipShowcase } = useSiteContent()
const media = computed(() => useWorshipMedia())
const liked = ref<Record<string, boolean>>({})

// trending worship — newest items in a horizontal carousel
const trending = computed(() =>
  [...media.value].sort((a, b) => b.date.localeCompare(a.date)).slice(0, 8))
const track = ref<HTMLElement | null>(null)
const slide = (d: number) => {
  const el = track.value
  if (!el) return
  const card = el.querySelector<HTMLElement>('.wb-card')
  el.scrollBy({ left: d * ((card?.offsetWidth || 300) + 24), behavior: 'smooth' })
}

// worship library — category tabs + pagination
const PER_PAGE = 4
const libCats = computed(() => ['All', ...new Set(media.value.map(m => m.cat))])
const libCat = ref('All')
const page = ref(1)
const filtered = computed(() =>
  media.value.filter(m => libCat.value === 'All' || m.cat === libCat.value))
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const shown = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))
watch(libCat, () => { page.value = 1 })
</script>

<template>
  <!-- main content + sticky right sidebar (same layout as the youth page) -->
    <div class="container youth-layout" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
      <div class="youth-main">
        <!-- hero banner card (template: gradient discover banner) -->
        <section class="wb-banner-sec">
          <div class="wb-banner">
            <div class="wb-banner-copy">
              <h2>{{ worshipShowcase.banner.title }}</h2>
              <div class="wb-banner-actions">
                <NuxtLink v-for="(a, ai) in worshipShowcase.banner.actions" :key="a.label" class="wb-btn" :class="{ solid: ai === 0 }" :to="a.to">{{ a.label }}</NuxtLink>
              </div>
            </div>
            <img :src="worshipShowcase.banner.img.src" :alt="worshipShowcase.banner.img.alt">
            <span class="wb-dots"><i class="on"></i><i></i><i></i></span>
          </div>
        </section>

        <!-- trending worship carousel -->
        <section class="wb-trending">
          <div class="container">
            <div class="wb-row-head">
              <h2>{{ worshipShowcase.trendingTitle }}</h2>
              <div class="wb-car-nav">
                <button type="button" aria-label="Scroll back" @click="slide(-1)">←</button>
                <button type="button" aria-label="Scroll forward" @click="slide(1)">→</button>
              </div>
            </div>
            <div ref="track" class="wb-carousel">
              <article v-for="m in trending" :key="m.title + m.date" class="wb-card">
                <NuxtLink class="wb-card-media" :to="mediaLink(m)">
                  <img :src="m.img || '/assets/images/gallery-9.jpg'" :alt="m.title">
                  <button class="wb-heart" :class="{ on: liked[m.title] }" type="button" :aria-label="`Like ${m.title}`" @click.prevent="liked[m.title] = !liked[m.title]">♥</button>
                </NuxtLink>
                <div class="wb-card-body">
                  <NuxtLink :to="mediaLink(m)">
                    <h3>{{ m.title }}</h3>
                    <p>{{ m.cat }} · {{ m.date }}</p>
                  </NuxtLink>
                </div>
                <div class="wb-card-foot">
                  <span class="wb-price">{{ m.type === 'video' ? '▶ Video' : m.type === 'audio' ? '🎧 Audio' : '📷 Photo' }}</span>
                  <NuxtLink class="wb-btn dark" :to="mediaLink(m)">{{ m.type === 'audio' ? 'Listen Now' : 'Watch Now' }}</NuxtLink>
                </div>
              </article>
            </div>
          </div>
        </section>

        <!-- worship library — full archive with tabs + pagination -->
        <section class="wb-trending wb-library">
          <div class="container">
            <div class="wb-row-head">
              <h2>{{ worshipShowcase.libraryTitle }}</h2>
              <div class="wb-tabs">
                <button v-for="c in libCats" :key="c" type="button" :class="{ active: libCat === c }" @click="libCat = c">{{ c }}</button>
              </div>
            </div>
            <div class="wb-cards">
              <article v-for="m in shown" :key="m.title + m.date" class="wb-card">
                <NuxtLink class="wb-card-media" :to="mediaLink(m)">
                  <img :src="m.img || '/assets/images/gallery-9.jpg'" :alt="m.title">
                  <button class="wb-heart" :class="{ on: liked[m.title] }" type="button" :aria-label="`Like ${m.title}`" @click.prevent="liked[m.title] = !liked[m.title]">♥</button>
                </NuxtLink>
                <div class="wb-card-body">
                  <NuxtLink :to="mediaLink(m)">
                    <h3>{{ m.title }}</h3>
                    <p>{{ m.cat }} · {{ m.date }}</p>
                  </NuxtLink>
                  <span class="wb-faces">
                    <img src="/assets/images/pastor-2.jpg" alt=""><img src="/assets/images/pastor-3.jpg" alt=""><i>+94</i>
                  </span>
                </div>
                <div class="wb-card-foot">
                  <span class="wb-price">{{ m.type === 'video' ? '▶ Video' : m.type === 'audio' ? '🎧 Audio' : '📷 Photo' }}</span>
                  <NuxtLink class="wb-btn dark" :to="mediaLink(m)">{{ m.type === 'audio' ? 'Listen Now' : 'Watch Now' }}</NuxtLink>
                </div>
              </article>
            </div>
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

      </div>

      <WorshipSidebar />
    </div>
</template>

<style scoped>
/* keep the main grid column from being stretched by the carousel track */
.youth-main { min-width: 0; }
.wb-carousel { max-width: 100%; }

/* trending carousel */
.wb-car-nav { display: flex; gap: .6rem; }
.wb-car-nav button { width: 42px; height: 42px; border-radius: 50%; border: 0; background: #fff; color: var(--color-secondary);
  font-size: 1.1rem; cursor: pointer; box-shadow: 0 8px 20px rgba(20, 24, 29, .1); transition: background .2s, color .2s; }
.wb-car-nav button:hover { background: var(--color-primary); color: #fff; }

.wb-carousel { display: flex; gap: 1.5rem; overflow-x: auto; scroll-snap-type: x mandatory;
  padding-bottom: .6rem; scrollbar-width: none; }
.wb-carousel::-webkit-scrollbar { display: none; }
.wb-carousel .wb-card { flex: 0 0 min(300px, 82%); scroll-snap-align: start; }

.wb-library { margin-top: 2.4rem; }
</style>
