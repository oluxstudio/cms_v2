<script setup lang="ts">
// vertical highlight reels — swap poster/src for real clips the leaders film on phones
const { youthHighlights } = useSiteContent()
const clips = youthHighlights.clips
</script>

<template>
  <section class="yv-highlights">
    <div class="container">
      <div class="section-head center">
        <p class="eyebrow">{{ youthHighlights.eyebrow }}</p>
        <h2>{{ youthHighlights.title }}</h2>
        <p>{{ youthHighlights.sub }}</p>
      </div>
      <div class="yv-reel-row">
        <button v-for="c in clips" :key="c.title" type="button" class="yv-reel" :aria-label="`Play ${c.title}`">
          <img :src="c.poster" :alt="c.title">
          <span class="play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg></span>
          <span class="meta"><b>{{ c.title }}</b><small>▶ {{ c.views }}</small></span>
        </button>
      </div>
    </div>
  </section>
</template>
