<script setup lang="ts">
const oluxCms = useOluxContent('ministry-photos')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its own photos)
// @olux-rows — the slides are this block's own rows (add / remove / reorder in Edit site)
// "Photo gallery" on a ministry page — a carousel of portrait photo cards with
// the caption on the photo: 3 per view (2 on tablets, 1 on phones), the arrows
// step one photo, it auto-advances and pauses on hover; a photo opens full size.
// In Edit site every slide is a row of this block: change its photo and
// caption, add or remove slides and reorder them. It renders in the Ministry
// Hub's main column (the hub leaves a slot for it); anywhere else it shows as
// a normal section.
const galleryKicker = oluxCms.tRef('Gallery Kicker', 'Moments together')
const galleryTitle = oluxCms.tRef('Gallery Title', 'Photo gallery')

const slides = oluxCms.items('Slide', {"Image":"img","Caption":"caption"}, [
  { img: '/assets/images/gallery-3.jpg', caption: 'Saturday breakfast' },
  { img: '/assets/images/gallery-1.jpg', caption: 'Midweek Bible study' },
  { img: '/assets/images/gallery-8.jpg', caption: 'Worship together' },
  { img: '/assets/images/gallery-5.jpg', caption: 'Fellowship lunch' },
  { img: '/assets/images/gallery-11.jpg', caption: 'Serve day' },
  { img: '/assets/images/gallery-12.jpg', caption: 'Praying together' },
], {})

const shown = computed(() => slides.filter(s => s.img))

// carousel — a scroll-snap row, so swiping works on touch screens too
const track = ref<HTMLElement | null>(null)
const atStart = ref(true)
const atEnd = ref(false)
const onScroll = () => {
  const t = track.value
  if (!t) return
  atStart.value = t.scrollLeft < 4
  atEnd.value = t.scrollLeft + t.clientWidth >= t.scrollWidth - 4
}
const stepBy = () => {
  const card = track.value?.querySelector<HTMLElement>('.mp-slide')
  return card ? card.offsetWidth + parseFloat(getComputedStyle(track.value!).columnGap || '0') : 0
}
const go = (dir: number) => {
  const t = track.value
  if (!t) return
  // past the last photo → back to the first (and the other way round)
  if (dir > 0 && atEnd.value) t.scrollTo({ left: 0, behavior: 'smooth' })
  else if (dir < 0 && atStart.value) t.scrollTo({ left: t.scrollWidth, behavior: 'smooth' })
  else t.scrollBy({ left: dir * stepBy(), behavior: 'smooth' })
}
const paused = ref(false)
let timer: ReturnType<typeof setInterval> | undefined

// lightbox — reuses the global .lightbox / .lb-* styles
const open = ref<number | null>(null)
const step = (dir: number) => { if (open.value !== null) open.value = (open.value + dir + shown.value.length) % shown.value.length }
const onKey = (e: KeyboardEvent) => {
  if (open.value === null) return
  if (e.key === 'Escape') open.value = null
  if (e.key === 'ArrowRight') step(1)
  if (e.key === 'ArrowLeft') step(-1)
}

// into the ministry page's main column when the page has one
const inHub = ref(false)
onMounted(() => {
  inHub.value = !!document.getElementById('mh-main-gallery')
  window.addEventListener('keydown', onKey)
  timer = setInterval(() => { if (!paused.value && open.value === null && !(atStart.value && atEnd.value)) go(1) }, 5000)
  nextTick(onScroll)
})
onUnmounted(() => { window.removeEventListener('keydown', onKey); clearInterval(timer) })
watch(shown, () => nextTick(onScroll))
</script>

<template>
  <section class="mp-block" :class="{ 'mp-inline': !inHub }" data-olx-panel="ministry-photos" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-main-gallery" :disabled="!inHub">
      <div v-if="shown.length" class="mp-wrap" data-olx-panel="ministry-photos">
        <div class="mp-head">
          <div>
            <p class="mp-kicker">{{ galleryKicker }}</p>
            <h2>{{ galleryTitle }}</h2>
          </div>
          <div v-if="shown.length > 1" class="mp-arrows">
            <button class="mp-nav" type="button" aria-label="Previous photos" @click="go(-1)">←</button>
            <button class="mp-nav" type="button" aria-label="Next photos" @click="go(1)">→</button>
          </div>
        </div>
        <div ref="track" class="mp-track" @scroll.passive="onScroll" @mouseenter="paused = true" @mouseleave="paused = false">
          <figure v-for="(s, i) in shown" :key="s.img + i" data-olx-item class="mp-slide" tabindex="0" @click="open = i" @keydown.enter="open = i">
            <img :src="s.img" :alt="s.caption || galleryTitle" :loading="i < 3 ? 'eager' : 'lazy'">
            <figcaption v-if="s.caption">{{ s.caption }}</figcaption>
          </figure>
        </div>
      </div>
    </Teleport>

    <Transition name="lb-fade">
      <div v-if="open !== null && shown[open]" class="lightbox" @click.self="open = null">
        <button class="lb-close" type="button" aria-label="Close" @click="open = null">✕</button>
        <button v-if="shown.length > 1" class="lb-nav prev" type="button" aria-label="Previous" @click="step(-1)">←</button>
        <div class="lb-body">
          <img :src="shown[open].img" :alt="shown[open].caption || galleryTitle">
          <p v-if="shown[open].caption" class="lb-caption"><b>{{ shown[open].caption }}</b></p>
        </div>
        <button v-if="shown.length > 1" class="lb-nav next" type="button" aria-label="Next" @click="step(1)">→</button>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.mp-block:not(.mp-inline) { display: contents; }
.mp-inline { padding: 0 0 3.5rem; }
.mp-inline .mp-wrap { width: min(1180px, calc(100% - 2.5rem)); margin: 0 auto; }
.mp-wrap { min-width: 0; }
.mp-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1.2rem; }
.mp-kicker { font-size: .8rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--color-primary); margin-bottom: .5rem; }
.mp-head h2 { font-size: clamp(1.6rem, 2.6vw, 2.1rem); }
.mp-arrows { display: flex; gap: .5rem; flex: none; }
.mp-nav { width: 44px; height: 44px; border-radius: 50%; border: 1.5px solid #e2dbd0; background: #fff; color: var(--color-secondary);
  font-size: 1.05rem; font-weight: 800; cursor: pointer; transition: background .2s, color .2s, border-color .2s; }
.mp-nav:hover { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }

/* 3 photos per view, 2 on tablets, ~1 on phones (a peek of the next one) */
.mp-track { --per: 3; --gap: 1rem; display: grid; grid-auto-flow: column; grid-auto-columns: calc((100% - (var(--per) - 1) * var(--gap)) / var(--per));
  column-gap: var(--gap); overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; padding: .2rem 0 1.3rem; }
.mp-track::-webkit-scrollbar { display: none; }
@media (max-width: 760px) { .mp-track { --per: 2; } }
@media (max-width: 480px) { .mp-track { --per: 1.25; --gap: .8rem; } }
.mp-slide { position: relative; margin: 0; scroll-snap-align: start; border-radius: 18px; overflow: hidden; cursor: zoom-in;
  aspect-ratio: 4 / 5; background: var(--color-secondary); box-shadow: 0 6px 14px rgba(20, 24, 29, .1); }
.mp-slide img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .45s ease; }
.mp-slide:hover img { transform: scale(1.05); }
.mp-slide::after { content: ''; position: absolute; inset: auto 0 0; height: 45%; background: linear-gradient(transparent, rgba(10, 12, 16, .6)); pointer-events: none; }
.mp-slide figcaption { position: absolute; left: 1rem; right: 1rem; bottom: .95rem; z-index: 1; color: #fff; font-family: var(--font-heading);
  font-size: 1rem; line-height: 1.3; text-shadow: 0 1px 6px rgba(0, 0, 0, .35); }
</style>
