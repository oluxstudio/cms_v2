<script setup lang="ts">
const oluxCms = useOluxContent('about-explore')
const oluxFb: Record<string, string> = {"Text":"Get to know us","Headline":"More about our church"}
// @olux-source about-pages
// The About page's guide to its sub-pages — leadership, announcements, media, prayer.
const { aboutPages } = useSiteContent()
</script>

<template>
  <section class="ax-sec" data-olx-panel="about-pages" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head center">
        <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
        <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
      </div>
      <div class="ax-grid">
        <NuxtLink v-for="p in aboutPages" :key="p.to + p.title" data-olx-item :to="p.to" class="ax-card">
          <span class="ax-icon" aria-hidden="true">{{ p.icon }}</span>
          <h3>{{ p.title }}</h3>
          <p>{{ p.text }}</p>
          <span class="ax-cta">{{ p.cta || 'Find out more' }} <EventIcon name="arrow" :size="16" /></span>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>

<style scoped>
.ax-sec { padding: 4rem 0 3rem; }
.ax-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.3rem; margin-top: 2rem; }
@media (max-width: 1100px) { .ax-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .ax-grid { grid-template-columns: 1fr; } }
.ax-card { background: #fff; border-radius: 22px; padding: 1.6rem 1.5rem; box-shadow: 0 14px 34px rgba(20, 24, 29, .08);
  display: flex; flex-direction: column; gap: .55rem; color: inherit; transition: transform .25s ease, box-shadow .25s ease; }
.ax-card:hover { transform: translateY(-4px); box-shadow: 0 22px 44px rgba(20, 24, 29, .13); }
.ax-icon { width: 54px; height: 54px; display: grid; place-items: center; border-radius: 16px; background: var(--primary-soft); font-size: 1.6rem; }
.ax-card h3 { font-size: 1.3rem; margin-top: .3rem; }
.ax-card p { font-size: .96rem; line-height: 1.6; color: #55606b; }
.ax-cta { margin-top: auto; padding-top: .5rem; display: inline-flex; align-items: center; gap: .4rem; font-weight: 700; color: var(--color-primary); }
.ax-card:hover .ax-cta { gap: .65rem; }
</style>
