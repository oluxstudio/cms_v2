<script setup lang="ts">
const oluxCms = useOluxContent('blog')
const oluxFb: Record<string, string> = {}
import { ref, onMounted } from 'vue'
import { createCms } from '../../lib/olux-cms.mjs'

const { field } = useCms()
const pageSources = usePageSources()

type Post = { title: string; slug: string; cover_image?: string | null; category?: string | null }

// Handcoded fallback — the 3 latest published CMS posts override these cards.
const fallbackPosts: Post[] = [
  { title: 'Five habits that keep color vibrant for months', slug: '#', cover_image: '/assets/images/about-1.jpg', category: 'Color care' },
  { title: 'The curl routine our stylists swear by', slug: '#', cover_image: '/assets/images/services-circle.jpg', category: 'Curls' },
  { title: 'What lamination actually does to your hair', slug: '#', cover_image: '/assets/images/hours.jpg', category: 'Treatments' },
]
const posts = ref<Post[]>(fallbackPosts)

onMounted(async () => {
  try {
    const cfg = useRuntimeConfig().public as any
    const cms = createCms({
      url: (cfg.bookingApiBase || '').replace(/\/$/, '') || window.location.origin,
      site: cfg.cmsSite || 'v2-hairco',
    })
    // Page content sources: filter/limit the posts shown on THIS page.
    const src = (await loadPageSources())?.posts
    if (src?.enabled) {
      const q = new URLSearchParams()
      if (src.category) q.set('category', src.category)
      if (src.tag) q.set('tag', src.tag)
      q.set('per_page', String(src.limit > 0 ? src.limit : 3))
      const base = (cfg.bookingApiBase || '').replace(/\/$/, '') || window.location.origin
      const res = await $fetch(`${base}/api/sites/${encodeURIComponent(cfg.cmsSite || 'v2-hairco')}/posts?${q}`) as any
      const items = res?.posts?.data ?? res?.posts ?? res?.data ?? []
      if (Array.isArray(items) && items.length) posts.value = items.slice(0, src.limit > 0 ? src.limit : 3)
    } else {
      const res = await cms.posts.list()
      if (Array.isArray(res?.posts) && res.posts.length) posts.value = res.posts.slice(0, 3)
    }
  } catch (_) { /* CMS unreachable — fallbacks carry the section */ }
})
</script>

<template>
  <section class="blog tinted" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head centered" data-olx-key="blogIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="caption">{{ field('blogIntro', 'caption', 'From the journal') }}</p>
        <h2 data-olx-field="heading">{{ field('blogIntro', 'heading', 'Hair Wisdom & Stories') }}</h2>
        <p data-olx-field="body">{{ field('blogIntro', 'body', 'Care tips, trends and behind-the-chair stories from our stylists.') }}</p>
      </div>
      <div class="blog-grid">
        <article v-for="p in posts" :key="p.slug" class="blog-card">
          <div class="cover">
            <img :src="p.cover_image || '/assets/images/about-1.jpg'" :alt="p.title">
            <span v-if="p.category" class="category">{{ p.category }}</span>
          </div>
          <h3>{{ p.title }}</h3>
        </article>
      </div>
    </div>
  </section>
</template>
