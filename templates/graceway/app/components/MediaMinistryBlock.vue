<script setup lang="ts">
const oluxCms = useOluxContent('media-ministry')
const oluxFb: Record<string, string> = {}
// Media & Broadcast ministry page body — all content comes from the global
// data source (mediaMinistry section); platform stats derive from `socials`.
const { mediaMinistry: mm, socials } = useSiteContent()
const livePlatforms = socials.filter(s => s.available)
const liveCount = livePlatforms.length

// sidebar: latest sermons from the sermons data source
import { sermonThumb } from '~/composables/useSermons'
const latestSermons = [...useSermons()].sort((a, b) => b.date.localeCompare(a.date)).slice(0, 3)

// join form → CMS forms inbox
const sent = ref(false)
const { submit: cmsSubmit, sending, error: sendError } = useCmsForm('media-team')
const sendForm = async (e: Event) => {
  const data = Object.fromEntries(new FormData(e.target as HTMLFormElement).entries())
  if (await cmsSubmit(data)) sent.value = true
}
</script>

<template>
  <div v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <!-- intro: copy + facts left · photo with chips right -->
    <section class="mm-intro study">
      <div class="container">
        <div class="study-top">
          <div class="study-copy">
            <span class="ct-chip">{{ mm.intro.chip }}</span>
            <h2 class="mega">
              <template v-for="(line, i) in contentLines(mm.intro.title)" :key="i">
                <br v-if="i">{{ line }}
              </template>
            </h2>
            <p v-for="t in mm.intro.paragraphs" :key="t" class="sub">{{ t }}</p>
            <ul class="study-facts mm-facts">
              <li v-for="f in mm.intro.facts" :key="f.label"><b>{{ f.label }}:</b> {{ f.value }}</li>
            </ul>
          </div>
          <div class="study-photo">
            <span class="study-arch" aria-hidden="true"></span>
            <img :src="mm.intro.img.src" :alt="mm.intro.img.alt">
            <span v-for="(chip, i) in mm.intro.chips" :key="chip.label" class="study-chip" :class="`c${i + 1}`">{{ chip.icon }} {{ chip.label }} <b>{{ chip.value }}</b></span>
          </div>
        </div>
      </div>
    </section>

    <!-- main column + media sidebar -->
    <div class="container mm-layout">
      <div class="mm-main">

    <!-- roles / stations grid -->
    <section class="mm-roles">
      <div class="mm-inner">
        <div class="section-head center">
          <p class="eyebrow">{{ mm.rolesEyebrow }}</p>
          <h2>{{ mm.rolesTitle }}</h2>
          <p>{{ liveCount }} platform{{ liveCount === 1 ? '' : 's' }} live every Sunday — these are the hands that make it happen.</p>
        </div>
        <div class="mm-role-grid">
          <article v-for="r in mm.roles" :key="r.title" class="mm-role">
            <span class="mm-role-icon">{{ r.icon }}</span>
            <h3>{{ r.title }}</h3>
            <p>{{ r.text }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- behind-the-scenes gallery strip -->
    <section class="mm-gallery">
      <div class="mm-inner">
        <div class="mm-gallery-row">
          <figure v-for="g in mm.gallery" :key="g.img">
            <img :src="g.img" :alt="g.title" loading="lazy">
            <figcaption>{{ g.title }}</figcaption>
          </figure>
        </div>
      </div>
    </section>


      </div>

      <!-- ── media sidebar ── -->
      <aside class="mm-side">
        <!-- watch & listen quick links -->
        <div class="mms-card">
          <h3>{{ mm.sidebar.watchTitle }}</h3>
          <NuxtLink v-for="l in mm.sidebar.watchLinks" :key="l.label" class="mms-row" :to="l.to">
            <span class="icon">{{ l.icon }}</span>
            <b>{{ l.label }}</b>
          </NuxtLink>
        </div>

        <!-- live platforms, from the socials data source -->
        <div class="mms-card mms-live">
          <h3>{{ mm.sidebar.liveTitle }}</h3>
          <div class="mms-platforms">
            <a
              v-for="s in livePlatforms" :key="s.key" :href="s.href"
              target="_blank" rel="noopener" :title="s.name" :style="{ background: s.color }"
            ><svg viewBox="0 0 24 24" fill="currentColor"><path :d="s.icon"/></svg></a>
          </div>
          <p>{{ mm.sidebar.liveNote }}</p>
        </div>

        <!-- latest sermons the team published -->
        <div class="mms-card">
          <h3>{{ mm.sidebar.latestTitle }}</h3>
          <NuxtLink v-for="sm in latestSermons" :key="sm.slug" class="mms-row mms-sermon" :to="`/sermons/${sm.slug}`">
            <img :src="sermonThumb(sm)" :alt="sm.title">
            <span>
              <b>{{ sm.title }}</b>
              <small>{{ sm.speaker }} · {{ sm.date }}</small>
            </span>
          </NuxtLink>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.mm-layout { display: grid; grid-template-columns: 1fr 300px; gap: 2rem; align-items: start; }
.mm-main { min-width: 0; }
.mm-inner { width: 100%; }

.mm-side { display: grid; gap: 1.4rem; position: sticky; top: 7rem; }
.mms-card { background: #fff; border-radius: 18px; padding: 1.4rem; box-shadow: 0 12px 30px rgba(20, 24, 29, .08); }
.mms-card h3 { font-size: 1.05rem; color: var(--color-secondary); margin-bottom: .8rem; }
.mms-row { display: flex; align-items: center; gap: .7rem; padding: .55rem 0; border-bottom: 1px solid #f1ece3; }
.mms-row:last-child { border-bottom: 0; padding-bottom: 0; }
.mms-row .icon { width: 34px; height: 34px; flex: none; border-radius: 10px; background: #fdeef3; display: grid; place-items: center; }
.mms-row b { font-size: .92rem; color: var(--color-secondary); }
.mms-row:hover b { color: var(--color-primary); }
.mms-live p { font-size: .85rem; color: #55606b; margin-top: .8rem; }
.mms-platforms { display: flex; gap: .55rem; flex-wrap: wrap; }
.mms-platforms a { width: 38px; height: 38px; border-radius: 12px; color: #fff; display: grid; place-items: center;
  transition: transform .2s; }
.mms-platforms a:hover { transform: translateY(-2px); }
.mms-platforms svg { width: 18px; height: 18px; }
.mms-sermon img { width: 52px; height: 52px; flex: none; border-radius: 10px; object-fit: cover; }
.mms-sermon span { min-width: 0; display: grid; }
.mms-sermon b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mms-sermon small { font-size: .78rem; color: #7f8791; }

@media (max-width: 1024px) { .mm-layout { grid-template-columns: 1fr; } .mm-side { position: static; } }

.mm-intro { padding-bottom: 2rem; }
/* the global .sub caps text at 46ch — let the intro copy fill its column */
.mm-intro .sub { max-width: none; }
.mm-intro .sub + .sub { margin-top: 1rem; }
.mm-facts { margin-top: 1.6rem; }

.mm-roles { padding: 3.5rem 0 1rem; }
.mm-role-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.4rem; margin-top: 2rem; }
.mm-role { background: #fff; border-radius: 18px; padding: 1.8rem 1.5rem; box-shadow: 0 12px 30px rgba(20, 24, 29, .08);
  transition: transform .2s ease; }
.mm-role:hover { transform: translateY(-4px); }
.mm-role-icon { display: grid; place-items: center; width: 52px; height: 52px; border-radius: 14px;
  background: #fdeef3; font-size: 1.5rem; margin-bottom: .9rem; }
.mm-role h3 { font-size: 1.1rem; color: var(--color-secondary); margin-bottom: .4rem; }
.mm-role p { font-size: .95rem; color: #55606b; line-height: 1.6; }

.mm-gallery { padding: 3rem 0 1rem; }
.mm-gallery-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.mm-gallery-row figure { margin: 0; border-radius: 16px; overflow: hidden; position: relative; }
.mm-gallery-row img { width: 100%; aspect-ratio: 4 / 5; object-fit: cover; display: block; transition: transform .3s ease; }
.mm-gallery-row figure:hover img { transform: scale(1.05); }
.mm-gallery-row figure:nth-child(even) { transform: translateY(1rem); }
.mm-gallery-row figcaption { position: absolute; left: 0; right: 0; bottom: 0; padding: 1.6rem .9rem .7rem;
  color: #fff; font-size: .8rem; font-weight: 700;
  background: linear-gradient(transparent, rgba(10, 12, 16, .7)); }

.mm-join { padding: 3.5rem 0; }
.mm-join-panel { max-width: 640px; margin: 0; }
.mm-fine { margin-top: .8rem; font-size: .82rem; color: #7f8791; }

@media (max-width: 900px) { .mm-role-grid { grid-template-columns: 1fr 1fr; } .mm-gallery-row { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .mm-role-grid { grid-template-columns: 1fr; } }
</style>
