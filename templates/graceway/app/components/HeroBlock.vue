<script setup lang="ts">
const oluxCms = useOluxContent('hero')
const oluxFb: Record<string, string> = {}
// copy comes from the global data source; pills from the Hero Pills collection
// @olux-source hero-pills
const { hero } = useSiteContent()
// three collage columns, filled in order; computed so connect-editor saves
// re-render in place (useCms data is reactive)
const columns = computed(() => {
  const pills = useHeroPills()
  const per = Math.max(1, Math.ceil(pills.length / 3))
  return [0, 1, 2].map(c => pills.slice(c * per, (c + 1) * per))
})
</script>

<template>
  <section class="hero" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="hero-copy">
        <h1 data-olx-field="title">{{ oluxCms.t('Title', hero.title) }} <span class="hl" data-olx-field="titleHighlight">{{ oluxCms.t('Title Highlight', hero.titleHighlight) }}</span></h1>
        <p class="lead" data-olx-field="lead">{{ oluxCms.t('Lead', hero.lead) }}</p>

        <div class="hero-live">
          <span class="hero-live-dot" aria-hidden="true"></span>
          <span>{{ hero.liveNote }}</span>
        </div>

        <div class="hero-actions">
          <NuxtLink v-for="(a, i) in hero.actions" :key="a.label" class="btn" :class="{ ghost: i > 0 }" :to="a.to">{{ a.label }}</NuxtLink>
        </div>

        <!-- scattered accent dots -->
        <span class="dot d1"></span>
        <span class="dot d2"></span>
        <span class="dot d3"></span>
      </div>

      <div class="hero-collage" data-olx-panel="hero-pills">
        <div v-for="(col, c) in columns" :key="c" class="col" :class="`col-${['a','b','c'][c]}`">
          <NuxtLink
            data-olx-item
            v-for="p in col" :key="p.key"
            class="pill" :class="p.tint" :to="p.to"
            :aria-label="`Open the ${p.label} page`"
          >
            <img :src="p.img" :alt="p.label">
            <span class="pill-label">{{ p.label }}</span>
            <span class="pill-overlay">
              <small>{{ p.desc }}</small>
            </span>
          </NuxtLink>
        </div>
        <span class="dot d4"></span>
        <span class="dot d5"></span>
      </div>
    </div>
    <div class="hero-crowd" aria-hidden="true"></div>
  </section>
</template>
