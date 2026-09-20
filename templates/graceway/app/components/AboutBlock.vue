<script setup lang="ts">
const oluxCms = useOluxContent('about')
const oluxFb: Record<string, string> = {"Subheadline":"Our Ministries"}
// all copy, facts, pillars, sidebar and photos come from the global data source
const { about, profile } = useSiteContent()
const { facts, story, pillars, sideMinistries, photos } = about

// lightbox over the story photos — reuses the global .lightbox / .lb-* styles
const lightbox = ref<number | null>(null)
const closeLightbox = () => { lightbox.value = null }
const step = (d: number) => {
  if (lightbox.value === null) return
  lightbox.value = (lightbox.value + d + photos.length) % photos.length
}
const onKey = (e: KeyboardEvent) => {
  if (lightbox.value === null) return
  if (e.key === 'Escape') closeLightbox()
  if (e.key === 'ArrowRight') step(1)
  if (e.key === 'ArrowLeft') step(-1)
}
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <section class="contact-sec about-sec" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="about-layout">
        <!-- main column -->
        <div class="about-main">
          <!-- our story: text left · images right -->
          <div class="about-panel">
            <div class="about-flex">
              <div class="about-text">
                <span class="ct-chip">{{ about.chip }}</span>
                <h2>{{ about.title }}</h2>
                <p class="ct-sub">{{ about.intro }}</p>

                <div v-for="s in story" :key="s.title" class="about-story">
                  <h3>{{ s.title }}</h3>
                  <p>{{ s.text }}</p>
                </div>
              </div>

              <!-- photo column — click to open lightbox -->
              <div class="about-media">
                <figure
                  v-for="(p, i) in photos" :key="p.img"
                  role="button" tabindex="0" :aria-label="`Open ${p.title}`"
                  @click="lightbox = i" @keydown.enter="lightbox = i"
                >
                  <img :src="p.img" :alt="p.title" loading="lazy">
                </figure>
              </div>
            </div>
          </div>

          <!-- at-a-glance: horizontal facts strip -->
          <div class="ct-info about-facts">
            <div v-for="f in facts" :key="f.label" class="ct-row">
              <span class="icon">{{ f.icon }}</span>
              <div>
                <b>{{ f.label }}</b>
                <p>{{ f.value }}</p>
              </div>
            </div>
          </div>

          <!-- mission · vision · pledge -->
          <div class="about-pillars">
            <div v-for="p in pillars" :key="p.chip" class="pillar">
              <span class="ct-chip">{{ p.chip }}</span>
              <h3>{{ p.title }}</h3>
              <p>{{ p.text }}</p>
            </div>
          </div>
        </div>

        <!-- sidebar -->
        <aside class="about-side">
          <!-- our mission -->
          <div class="side-card side-mission">
            <h3>{{ about.sideMission.title }}</h3>
            <p>{{ about.sideMission.text }}</p>
          </div>

          <!-- ministries -->
          <div class="side-card">
            <h3 data-olx-field="subheadline">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h3>
            <NuxtLink v-for="m in sideMinistries" :key="m.title" class="side-row" to="/ministries">
              <span class="icon">{{ m.icon }}</span>
              <div>
                <b>{{ m.title }}</b>
                <p>{{ m.meets }}</p>
              </div>
            </NuxtLink>
          </div>

        </aside>
      </div>
    </div>

    <!-- lightbox -->
    <Transition name="lb-fade">
      <div v-if="lightbox !== null" class="lightbox" @click.self="closeLightbox">
        <button class="lb-close" type="button" aria-label="Close" @click="closeLightbox">✕</button>
        <button class="lb-nav prev" type="button" aria-label="Previous" @click="step(-1)">←</button>
        <div class="lb-body">
          <img :src="photos[lightbox].img" :alt="photos[lightbox].title">
          <p class="lb-caption"><b>{{ photos[lightbox].title }}</b> · {{ profile.shortName }}</p>
        </div>
        <button class="lb-nav next" type="button" aria-label="Next" @click="step(1)">→</button>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
/* main + sidebar */
.about-layout { display: grid; grid-template-columns: 1fr 320px; gap: 1.8rem; align-items: start; }

/* our story: text · images side by side */
.about-flex { display: flex; gap: 2.4rem; align-items: flex-start; }
.about-text { flex: 1 1 58%; }
.about-text .ct-sub { font-size: 17px; }
.about-story { margin-top: 1.4rem; }
.about-story h3 { font-size: 1.15rem; color: var(--color-secondary); margin-bottom: .35rem; }
.about-sec :deep(.ct-chip) { font-size: 1.15rem; }
.about-story p { font-size: 17px; color: #55606b; line-height: 1.65; }

.about-media { flex: 1 1 42%; display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; position: sticky; top: 1.5rem; }
.about-media figure { margin: 0; cursor: pointer; border-radius: 14px; overflow: hidden; }
.about-media img { width: 100%; aspect-ratio: 4 / 5; object-fit: cover; display: block; transition: transform .3s ease; }
.about-media figure:nth-child(even) { transform: translateY(1.2rem); }
.about-media figure:hover img, .about-media figure:focus-visible img { transform: scale(1.06); }

/* at-a-glance: horizontal strip */
.about-facts { margin-top: 1.8rem; display: flex; flex-wrap: wrap; gap: .5rem 2rem; justify-content: space-between; }
.about-facts .ct-row { border-bottom: 0; padding: .6rem 0; flex: 1 1 220px; flex-direction: column; align-items: flex-start; gap: 1.1rem; }
.about-facts .ct-row p { font-size: 17px; }
.about-sec :deep(.ct-row b) { font-size: 1rem; }

/* mission · vision · pledge cards */
.about-pillars { margin-top: 1.8rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.8rem; }
.pillar { background: #fff; border-radius: 20px; padding: 2rem; box-shadow: 0 14px 32px rgba(20, 24, 29, .08); }
.pillar .ct-chip { display: inline-block; }
.pillar h3 { font-size: 1.15rem; color: var(--color-secondary); margin: .8rem 0 .5rem; }
.pillar p { font-size: 17px; color: #55606b; line-height: 1.65; }

/* sidebar cards */
.about-side { display: grid; gap: 1.8rem; position: sticky; top: 7.5rem; }
.side-card { background: #fff; border-radius: 20px; padding: 1.6rem; box-shadow: 0 14px 32px rgba(20, 24, 29, .08); }
.side-card h3 { font-size: 1.15rem; color: var(--color-secondary); margin-bottom: .6rem; }
.side-mission { background: var(--color-secondary); }
.side-mission h3 { color: #f3e6d8; }
.side-mission p { font-family: var(--font-heading); font-size: 1.15rem; color: #fff; line-height: 1.4; }
.side-row { display: flex; gap: .9rem; align-items: center; padding: .7rem 0; border-bottom: 1px solid #f1ece3; }
.side-row:last-child { border-bottom: 0; padding-bottom: 0; }
.side-row .icon { width: 40px; height: 40px; flex: none; border-radius: 12px; background: #fdeef3; display: grid; place-items: center; font-size: 1.1rem; }
.side-row img { width: 46px; height: 46px; flex: none; border-radius: 50%; object-fit: cover; }
.side-row b { display: block; font-size: 1rem; color: var(--color-secondary); }
.side-row p { font-size: 17px; color: #55606b; }
.side-row:hover b { color: var(--color-primary); }

@media (max-width: 1080px) {
  .about-layout { grid-template-columns: 1fr; }
  .about-side { position: static; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
}
@media (max-width: 900px) {
  .about-flex { flex-direction: column; }
  .about-media { position: static; width: 100%; }
  .about-pillars { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .about-media { grid-template-columns: 1fr 1fr; }
  .about-facts { flex-direction: column; }
}
</style>
