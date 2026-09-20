<script setup lang="ts">
const songs = computed(() => useSongs())
const { worshipSidebar: ws } = useSiteContent()

// worship team from the leadership data source; rows open the profile pages
const members = useMembers()
const teamSlugs = ['grace-lindqvist', 'samuel-reyes', 'ruth-alonso']
const team = teamSlugs.map(s => members.find(m => m.slug === s)!).filter(Boolean)
const firstName = (name: string) => name.replace(/^Rev\. /, '').split(' ')[0]

// follow state persists per visitor (template: Follow buttons)
const following = ref<Record<string, boolean>>({})
onMounted(() => { try { following.value = JSON.parse(localStorage.getItem('worship-follow') || '{}') } catch {} })
const toggleFollow = (slug: string) => {
  following.value[slug] = !following.value[slug]
  try { localStorage.setItem('worship-follow', JSON.stringify(following.value)) } catch {}
}

const history = ws.history
const times = ws.times
</script>

<template>
  <aside class="youth-sidebar">
    <!-- what we're singing -->
    <div class="ys-widget ys-verse">
      <p class="ys-label">{{ ws.songsLabel }}</p>
      <ol class="ws-songs">
        <li v-for="(s, i) in songs" :key="s.title">
          <span class="num">{{ i + 1 }}</span>
          <span class="info"><b>{{ s.title }}</b><small>{{ s.artist }}</small></span>
          <a :href="s.url" target="_blank" rel="noopener" aria-label="Listen">▶</a>
        </li>
      </ol>
      <p class="ws-ccli">{{ ws.ccli }}</p>
    </div>

    <!-- top leaders (template: Top Creators) -->
    <div class="ys-widget">
      <div class="ws-widget-head">
        <p class="ys-label">🏆 Top Leaders</p>
        <NuxtLink to="/about#leadership" class="ws-seeall">See all</NuxtLink>
      </div>
      <div v-for="(l, i) in team" :key="l.slug" class="ws-creator">
        <span class="rank">{{ i + 1 }}.</span>
        <NuxtLink :to="`/leadership/${l.slug}`" class="ws-creator-id">
          <img :src="l.img" :alt="`Photo of ${firstName(l.name)}`">
          <span><b>{{ firstName(l.name) }}</b><small>@{{ l.slug.split('-')[0] }}</small></span>
        </NuxtLink>
        <button
          type="button" class="ws-follow" :class="{ on: following[l.slug] }"
          @click="toggleFollow(l.slug)"
        >{{ following[l.slug] ? 'Following' : 'Follow' }}</button>
      </div>
    </div>

    <!-- history (template: activity feed) -->
    <div class="ys-widget">
      <div class="ws-widget-head">
        <p class="ys-label">{{ ws.historyLabel }}</p>
        <NuxtLink to="/sermons" class="ws-seeall">See all</NuxtLink>
      </div>
      <div v-for="h in history" :key="h.text" class="ws-history">
        <img :src="h.img" alt="">
        <span><b>{{ h.text }}</b><small>{{ h.sub }}</small></span>
        <em>{{ h.when }}</em>
      </div>
    </div>

    <!-- service & rehearsal times -->
    <div class="ys-widget">
      <p class="ys-label">{{ ws.timesLabel }}</p>
      <ul class="ws-times">
        <li v-for="t in times" :key="t.label"><b>{{ t.label }}</b><span>{{ t.value }}</span></li>
      </ul>
    </div>

    <!-- join the team -->
    <div class="ys-widget ys-newhere">
      <p class="ys-label">{{ ws.serveLabel }}</p>
      <p class="ws-join-note">{{ ws.serveNote }}</p>
      <NuxtLink class="btn dark" :to="ws.serveCta.to">{{ ws.serveCta.label }}</NuxtLink>
    </div>
  </aside>
</template>
