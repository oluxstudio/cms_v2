<script setup lang="ts">
// One announcement as a card — picture (optional), category & date, title, summary.
import type { Announcement } from '../composables/useAnnouncements'

defineProps<{ item: Announcement, compact?: boolean }>()
</script>

<template>
  <NuxtLink :to="announcementPath(item)" class="an-card" :class="{ compact, 'has-img': item.img && !compact }">
    <span v-if="item.img && !compact" class="an-img"><img :src="item.img" :alt="item.title" loading="lazy"></span>
    <span class="an-body">
      <span class="an-meta">
        <span class="an-cat">{{ item.category }}</span>
        <span v-if="item.pinned" class="an-pin">Pinned</span>
        <span class="an-date">{{ announcementDate(item.date) }}</span>
      </span>
      <span class="an-title">{{ item.title }}</span>
      <span class="an-summary">{{ item.summary }}</span>
      <span class="an-more">Read more <EventIcon name="arrow" :size="15" /></span>
    </span>
  </NuxtLink>
</template>

<style scoped>
.an-card { display: flex; flex-direction: column; background: #fff; border-radius: 22px; overflow: hidden; color: inherit; height: 100%;
  box-shadow: 0 14px 34px rgba(20, 24, 29, .08); transition: transform .25s ease, box-shadow .25s ease; }
.an-card:hover { transform: translateY(-4px); box-shadow: 0 22px 44px rgba(20, 24, 29, .13); }
.an-img { display: block; aspect-ratio: 16 / 9; overflow: hidden; }
.an-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .45s ease; }
.an-card:hover .an-img img { transform: scale(1.05); }
.an-body { flex: 1; display: flex; flex-direction: column; gap: .55rem; padding: 1.4rem 1.5rem 1.5rem; }
.an-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem .6rem; font-size: .8rem; }
.an-cat { padding: .2rem .7rem; border-radius: 999px; background: var(--primary-soft); color: var(--color-primary); font-weight: 800; letter-spacing: .04em; }
.an-pin { padding: .2rem .7rem; border-radius: 999px; background: var(--color-secondary); color: #fff; font-weight: 800; }
.an-date { color: #8a929b; font-weight: 600; }
.an-title { font-family: var(--font-heading); font-size: 1.35rem; line-height: 1.2; color: var(--color-secondary); }
.an-card:hover .an-title { color: var(--color-primary); }
.an-summary { font-size: .96rem; line-height: 1.6; color: #55606b; display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.an-more { margin-top: auto; padding-top: .3rem; display: inline-flex; align-items: center; gap: .35rem; font-weight: 700; font-size: .95rem; color: var(--color-primary); }
.an-card.compact .an-body { padding: 1.3rem 1.4rem; }
</style>
