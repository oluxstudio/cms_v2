<script setup lang="ts">
const oluxCms = useOluxContent('studies-archive')
const oluxFb: Record<string, string> = {"Text":"Study archive","Headline":"Every study, for everyone","Text B":"Catch up on any week \u2014 filter by series or group, and pick up where you left off."}
const studies = useStudies()

const PER_PAGE = 12
const seriesList = ['All series', ...new Set(studies.map(s => s.series))]
const audiences = ['All', ...new Set(studies.map(s => s.audience))]

const series = ref('All series')
const audience = ref('All')
const sort = ref<'newest' | 'oldest'>('newest')
const page = ref(1)

const filtered = computed(() => {
  const list = studies.filter(s =>
    (series.value === 'All series' || s.series === series.value)
    && (audience.value === 'All' || s.audience === audience.value),
  )
  return list.sort((a, b) => sort.value === 'newest' ? b.date.localeCompare(a.date) : a.date.localeCompare(b.date))
})
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const shown = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))
watch([series, audience, sort], () => { page.value = 1 })
</script>

<template>
  <section class="studies-archive" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
        <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
        <p data-olx-field="textB">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
      </div>

      <!-- audience tabs · series + sort -->
      <div class="gallery-bar">
        <div class="gallery-tabs" role="tablist">
          <button
            v-for="a in audiences" :key="a" type="button" role="tab"
            :class="{ active: audience === a }" :aria-selected="audience === a"
            @click="audience = a"
          >{{ a }}</button>
        </div>
        <div class="studies-controls">
          <select v-model="series" class="gallery-sort" aria-label="Filter by series">
            <option v-for="s in seriesList" :key="s" :value="s">{{ s }}</option>
          </select>
          <select v-model="sort" class="gallery-sort" aria-label="Sort studies">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
          </select>
        </div>
      </div>

      <div class="studies-grid">
        <NuxtLink v-for="s in shown" :key="s.slug" class="study-entry" :to="`/bible-study/studies/${s.slug}`">
          <div class="chips">
            <span class="chip series">{{ s.series }}</span>
            <span class="chip">{{ s.audience }}</span>
            <span v-if="s.videoUrl" class="chip video">▶ Video</span>
          </div>
          <h3>{{ s.title }}</h3>
          <p class="passage">📖 {{ s.passage }}<template v-if="s.week"> · {{ s.week }}</template></p>
          <p class="summary">{{ s.summary }}</p>
          <span class="more">Read Study →</span>
        </NuxtLink>
      </div>

      <div v-if="pages > 1" class="gallery-pages">
        <button type="button" :disabled="page === 1" @click="page--">←</button>
        <button v-for="n in pages" :key="n" type="button" :class="{ active: page === n }" @click="page = n">{{ n }}</button>
        <button type="button" :disabled="page === pages" @click="page++">→</button>
      </div>
    </div>
  </section>
</template>
