<script setup lang="ts">
const oluxCms = useOluxContent('ministry-verse')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its content is per route)
// The ministry's theme passage — its own component on a ministry page. Its
// Verse / Reference fields start as "… from Ministry Pages", which shows this
// ministry's own verse from the Ministry Pages collection; type anything else
// to show that instead on this page. Renders inside the ministry sidebar.
const verseText = oluxCms.tRef('Verse Text', 'Theme verse from Ministry Pages')
const verseRef = oluxCms.tRef('Verse Ref', 'Reference from Ministry Pages')

const route = useRoute()
const detail = computed(() => useSiteContent().ministryDetail[route.path.replace(/\/+$/, '') || '/'])
const inSidebar = useSidebarSlot('mh-sidebar-verse')
</script>

<template>
  <section class="ms-block" :class="{ 'ms-inline': !inSidebar }" data-olx-panel="ministry-verse" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-sidebar-verse" :disabled="!inSidebar">
      <figure
        v-if="verseText !== 'Theme verse from Ministry Pages' || detail?.verse"
        class="mv-card" data-olx-panel="ministry-verse"
      >
        <span class="mv-mark" aria-hidden="true">“</span>
        <blockquote>{{ verseText !== 'Theme verse from Ministry Pages' ? verseText : detail?.verse }}</blockquote>
        <figcaption v-if="verseRef !== 'Reference from Ministry Pages' || detail?.verseRef">
          — {{ verseRef !== 'Reference from Ministry Pages' ? verseRef : detail?.verseRef }}
        </figcaption>
      </figure>
    </Teleport>
  </section>
</template>

<style scoped>
.mv-card { position: relative; margin: 0; overflow: hidden; color: #fff; border-radius: 22px; padding: 1.5rem 1.4rem;
  box-shadow: 0 14px 34px rgba(20, 24, 29, .12);
  background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 35%, var(--color-secondary)) 75%, var(--color-secondary)); }
.ms-inline .mv-card { max-width: 420px; margin: 0 auto; }
.mv-mark { position: absolute; top: -1.6rem; right: .6rem; font-family: Georgia, serif; font-size: 8rem; line-height: 1; opacity: .18; }
.mv-card blockquote { position: relative; margin: 0; font-family: var(--font-secondary); font-size: 1.2rem; font-weight: 700; line-height: 1.5; }
.mv-card figcaption { position: relative; margin-top: .8rem; font-weight: 800; font-size: .9rem; letter-spacing: .04em; opacity: .9; }
</style>
