<script setup lang="ts">
const oluxCms = useOluxContent('ministry-hub')
const oluxFb: Record<string, string> = {"Text":"Details for this ministry are coming soon."}
// @olux-per-page — each page keeps its own copy of this block (its content is per route)
// @olux-source ministry-pages
// A ministry's own page (men's, women's …): the layout, other ministries, and
// slots its sibling blocks render into — About, What we do, Photos and
// Questions in the main column; At a glance, Leaders, Verse, Events and Prayer
// in the sidebar (MinistryAbout, MinistryWhatWeDo, MinistryPhotos,
// MinistryQuestions, MinistryGlance, MinistryLeaders, MinistryVerse,
// MinistryEvents, MinistryPrayer).
//
// Editable per page in Edit site: the headings below (the consts).
const othersTitle = oluxCms.tRef('Others Title', 'Other ministries')

const route = useRoute()
const path = computed(() => route.path.replace(/\/+$/, '') || '/')
const content = useSiteContent()

const d = computed(() => content.ministryDetail[path.value])

const others = computed(() => content.ministriesBento.filter(m => m.to && m.to !== path.value))

</script>

<template>
  <section class="mh" data-olx-panel="ministry-pages" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div v-if="d" class="container mh-layout">
      <!-- the Ministry About component (its own block) renders here -->
      <div id="mh-intro" class="mh-intro" />

      <!-- ── sidebar (after the story on phones & tablets) ── -->
      <aside class="mh-side">
        <!-- the Ministry Glance component (its own block) renders here -->
        <div id="mh-sidebar-glance" class="mh-slot" />

        <!-- the Ministry Leaders component (its own block) renders here -->
        <div id="mh-sidebar-leaders" class="mh-slot" />

        <!-- the Ministry Verse, Events and Prayer components (their own blocks) render here -->
        <div id="mh-sidebar-verse" class="mh-slot" />
        <div id="mh-sidebar-events" class="mh-slot" />
        <div id="mh-sidebar-prayer" class="mh-slot" />

        <div v-if="others.length" class="mh-card">
          <p class="mh-card-title">{{ othersTitle }}</p>
          <nav class="mh-others">
            <NuxtLink v-for="m in others" :key="m.to" :to="m.to!"><span aria-hidden="true">{{ m.icon }}</span>{{ m.title }}</NuxtLink>
          </nav>
        </div>
      </aside>

      <!-- ── the rest of the main column ── -->
      <div class="mh-main">
        <!-- the Ministry What We Do component (its own block) renders here -->
        <div id="mh-main-activities" class="mh-slot" />

        <!-- the Ministry Photos component (its own block) renders here -->
        <div id="mh-main-gallery" class="mh-slot" />

        <!-- the Ministry Questions component (its own block) renders here -->
        <div id="mh-main-faqs" class="mh-slot" />
      </div>
    </div>
    <div v-else class="container mh-missing">
      <p data-olx-field="text">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
      <NuxtLink class="btn" to="/ministries">All ministries</NuxtLink>
    </div>

  </section>
</template>

<style scoped>
.mh { padding: 3.5rem 0 4.5rem; }
.mh-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; grid-template-areas: "intro side" "main side";
  column-gap: 2.5rem; row-gap: 3rem; align-items: start; }
.mh-intro { grid-area: intro; min-width: 0; }
.mh-main { grid-area: main; display: grid; gap: 3rem; min-width: 0; }
@media (max-width: 1024px) { .mh-layout { grid-template-columns: 1fr; grid-template-areas: "intro" "side" "main"; row-gap: 2rem; } }

/* sidebar */
.mh-slot { display: contents; }
.mh-side { grid-area: side; position: sticky; top: 110px; display: grid; gap: 1.1rem; min-width: 0; }
@media (max-width: 1024px) { .mh-side { position: static; grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr)); align-items: start; } }
.mh-card { background: #fff; border-radius: 22px; padding: 1.5rem 1.4rem; box-shadow: 0 14px 34px rgba(20, 24, 29, .08); }
.mh-card-title { font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-secondary); margin-bottom: .9rem; }
.mh-link { display: inline-flex; align-items: center; gap: .35rem; margin-top: .7rem; font-weight: 700; color: var(--color-primary); }
.mh-others { display: grid; }
.mh-others a { display: flex; align-items: center; gap: .65rem; padding: .6rem 0; border-bottom: 1px solid #f0ebe3; font-weight: 600; color: var(--color-secondary); }
.mh-others a:last-child { border-bottom: 0; }
.mh-others a span { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 10px; background: var(--primary-soft); }
.mh-others a:hover { color: var(--color-primary); }
.mh-missing { display: grid; gap: 1rem; justify-items: center; text-align: center; color: #55606b; }
</style>
