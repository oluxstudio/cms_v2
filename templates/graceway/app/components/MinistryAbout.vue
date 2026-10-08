<script setup lang="ts">
const oluxCms = useOluxContent('ministry-about')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its own story)
// "About the ministry" on a ministry page: a photo, a kicker, the ministry's
// name and its story — all editable per page in Edit site. Until you change
// them, the title, story and photo show this ministry's details from Ministry
// Pages (the default photo ministry-default.jpg stands for "use Ministry
// Pages' photo"). It renders at the top of the Ministry Hub's main column
// (the hub leaves a slot for it); anywhere else as a normal section.
const aboutKicker = oluxCms.tRef('About Kicker', 'About the ministry')
const aboutTitle = oluxCms.tRef('About Title', 'Name from Ministry Pages')
const aboutText = oluxCms.tRef('About Text', 'Story from Ministry Pages')
const aboutImage = oluxCms.tRef('About Image', '/assets/images/ministry-default.jpg')

const route = useRoute()
const path = computed(() => route.path.replace(/\/+$/, '') || '/')
const content = useSiteContent()
const d = computed(() => content.ministryDetail[path.value])
const card = computed(() => content.ministriesBento.find(m => m.to === path.value))

// a typed value wins; the defaults above stand for "this ministry's own detail"
const textOr = (typed: string, fallback?: string) => {
  const v = String(typed ?? '').trim()
  return v && !/from Ministry Pages$/.test(v) ? v : String(fallback ?? '')
}
const imageOr = (typed: string) => {
  const v = String(typed ?? '').trim()
  return v && !v.endsWith('/ministry-default.jpg') ? v : String(d.value?.img ?? '')
}

const inHub = useSidebarSlot('mh-intro')
</script>

<template>
  <section class="ma-block" :class="{ 'ma-inline': !inHub }" data-olx-panel="ministry-about" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-intro" :disabled="!inHub">
      <article class="ma-card" data-olx-panel="ministry-about">
        <img v-if="imageOr(aboutImage)" class="ma-photo" :src="imageOr(aboutImage)" :alt="d?.alt || textOr(aboutTitle, card?.title)">
        <div class="ma-body">
          <p class="ma-kicker">{{ aboutKicker }}</p>
          <h2>{{ textOr(aboutTitle, card?.title) }}</h2>
          <p class="ma-text">{{ textOr(aboutText, d?.text) }}</p>
        </div>
      </article>
    </Teleport>
  </section>
</template>

<style scoped>
.ma-block:not(.ma-inline) { display: contents; }
.ma-inline { padding: 3rem 0; }
.ma-inline .ma-card { width: min(860px, calc(100% - 2.5rem)); margin: 0 auto; }
.ma-card { background: #fff; border-radius: 26px; overflow: hidden; box-shadow: 0 18px 40px rgba(20, 24, 29, .08); }
.ma-photo { width: 100%; aspect-ratio: 16 / 8; object-fit: cover; display: block; }
.ma-body { padding: 2rem 2.2rem 2.2rem; }
@media (max-width: 560px) { .ma-body { padding: 1.5rem 1.3rem; } }
.ma-kicker { font-size: .8rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--color-primary); margin-bottom: .5rem; }
.ma-body h2 { font-size: clamp(1.6rem, 2.6vw, 2.1rem); margin-bottom: .8rem; }
.ma-text { font-size: 1.08rem; line-height: 1.8; color: #4b5560; white-space: pre-line; }
</style>
