<script setup lang="ts">
// A past event's media as a slideshow: one photo, video or recording at a
// time with arrows, dots and a counter. Photos advance on their own; it
// pauses on hover and whenever a video or recording is showing.
import type { EventMedia } from '../composables/useSiteContent'

const props = defineProps<{ items: EventMedia[] }>()
const i = ref(0)
const current = computed(() => props.items[i.value])
const go = (d: number) => { i.value = (i.value + d + props.items.length) % props.items.length }

const hovering = ref(false)
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  timer = setInterval(() => {
    if (!hovering.value && props.items.length > 1 && current.value?.type === 'image') go(1)
  }, 6000)
})
onUnmounted(() => clearInterval(timer))

const onKey = (e: KeyboardEvent) => {
  if (e.key === 'ArrowRight') go(1)
  if (e.key === 'ArrowLeft') go(-1)
}
</script>

<template>
  <div v-if="current" class="ess" tabindex="0" aria-roledescription="slideshow" @keydown="onKey"
       @mouseenter="hovering = true" @mouseleave="hovering = false">
    <div class="ess-stage">
      <Transition name="ess-fade" mode="out-in">
        <div :key="i" class="ess-slide">
          <video v-if="current.type === 'video' && current.src" :src="current.src" :poster="current.img || undefined" controls playsinline></video>
          <div v-else-if="current.type === 'audio'" class="ess-audio">
            <img v-if="current.img" :src="current.img" :alt="current.title">
            <span v-else class="ess-audio-icon" aria-hidden="true">🎧</span>
            <audio v-if="current.src" :src="current.src" controls></audio>
          </div>
          <img v-else-if="current.img" :src="current.img" :alt="current.title">
        </div>
      </Transition>
      <template v-if="items.length > 1">
        <button class="ess-nav prev" type="button" aria-label="Previous" @click="go(-1)">←</button>
        <button class="ess-nav next" type="button" aria-label="Next" @click="go(1)">→</button>
      </template>
    </div>
    <div class="ess-caption">
      <span class="ess-title">{{ current.title }}</span>
      <span class="ess-count">{{ i + 1 }} / {{ items.length }}</span>
    </div>
    <div v-if="items.length > 1" class="ess-dots">
      <button v-for="(m, n) in items" :key="n" type="button" :class="{ on: n === i }" :aria-label="`Show ${m.title}`" @click="i = n"></button>
    </div>
  </div>
</template>

<style scoped>
.ess { outline: none; }
.ess-stage { position: relative; aspect-ratio: 16 / 9; border-radius: 22px; overflow: hidden; background: var(--color-tertiary);
  box-shadow: 0 18px 40px rgba(20, 24, 29, .16); }
.ess-slide { position: absolute; inset: 0; display: grid; place-items: center; }
.ess-slide > img, .ess-slide > video { width: 100%; height: 100%; object-fit: cover; display: block; }
.ess-slide > video { object-fit: contain; background: #000; }
.ess-audio { position: relative; width: 100%; height: 100%; display: grid; place-items: center; }
.ess-audio img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .55; }
.ess-audio-icon { font-size: 4rem; }
.ess-audio audio { position: absolute; left: 1.2rem; right: 1.2rem; bottom: 1.2rem; width: calc(100% - 2.4rem); }
.ess-nav { position: absolute; top: 50%; transform: translateY(-50%); width: 46px; height: 46px; border-radius: 50%; border: 0;
  background: #fff; color: var(--color-secondary); font-size: 1.2rem; font-weight: 800; cursor: pointer;
  box-shadow: 0 8px 20px rgba(0, 0, 0, .2); }
.ess-nav:hover { background: var(--color-primary); color: #fff; }
.ess-nav.prev { left: 1rem; }
.ess-nav.next { right: 1rem; }
.ess-caption { display: flex; justify-content: space-between; gap: 1rem; margin-top: .9rem; color: var(--color-secondary); }
.ess-title { font-weight: 700; }
.ess-count { color: #8a929b; font-variant-numeric: tabular-nums; }
.ess-dots { display: flex; justify-content: center; gap: .45rem; margin-top: .7rem; }
.ess-dots button { width: 9px; height: 9px; border-radius: 50%; border: 0; padding: 0; background: #d9d2c6; cursor: pointer; }
.ess-dots button.on { background: var(--color-primary); width: 24px; border-radius: 999px; }
.ess-fade-enter-active, .ess-fade-leave-active { transition: opacity .35s ease; }
.ess-fade-enter-from, .ess-fade-leave-to { opacity: 0; }
</style>
