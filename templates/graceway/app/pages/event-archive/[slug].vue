<script setup lang="ts">
import type { EventMedia } from '~/composables/useSiteContent'

const route = useRoute()
// CMS-first, same as the archive grid — authored rows carry the pristine template
const { events: authoredEvents } = useSiteContent()
const rows = computed(() => {
  const cms = (useCms().items('events', []) as any[]).filter(e => e.title && e.img)
  return cms.length ? cms : authoredEvents
})
const event = computed(() => rows.value.find(e => (e.id ?? '') === route.params.slug))

useHead({ title: computed(() => event.value ? `${event.value.title} — CAC Blackburn` : 'Event — CAC Blackburn') })

const when = (iso?: string) => {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    + ' · ' + d.toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit', hour12: true }).toUpperCase()
}

// useCms() rebases top-level /assets/… paths for the preview base, but not
// ones nested inside the media array — apply the app base here.
const assetBase = ((useRuntimeConfig() as any).app?.baseURL || '/').replace(/\/$/, '')
const rebase = (u?: string) => u && u.startsWith('/assets/') ? assetBase + u : u
const media = computed<EventMedia[]>(() => (event.value?.media ?? []).map((m: EventMedia) => ({
  ...m, img: rebase(m.img), src: rebase(m.src),
})))
const badge = { video: '▶ Video', audio: '🎧 Audio', image: '📷 Photo' } as const

// media lightbox — images, video and audio in the global .lightbox shell
const open = ref<number | null>(null)
const close = () => { open.value = null }
const step = (d: number) => {
  if (open.value === null || !media.value.length) return
  open.value = (open.value + d + media.value.length) % media.value.length
}
const onKey = (e: KeyboardEvent) => {
  if (open.value === null) return
  if (e.key === 'Escape') close()
  if (e.key === 'ArrowRight') step(1)
  if (e.key === 'ArrowLeft') step(-1)
}
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div>
    <SiteHeader />

    <PageHeroContent :crumbs="[{ label: 'Events', to: '/events' }, { label: 'Archive', to: '/event-archive' }, { label: event?.title ?? 'Event' }]">
      <p class="eyebrow">Event archive</p>
      <h1>{{ event?.title ?? 'Event not found' }}</h1>
      <p v-if="event">{{ when(event.date) }}</p>
    </PageHeroContent>

    <section v-if="event" class="evd">
      <div class="container">
        <!-- event information -->
        <div class="evd-info">
          <img class="evd-cover" :src="event.img" :alt="event.title">
          <div class="evd-copy">
            <h2>About this event</h2>
            <p class="evd-text">{{ event.text }}</p>
            <ul class="evd-facts">
              <li><b>🗓 When:</b> {{ when(event.date) }}</li>
              <li v-if="event.location"><b>📍 Where:</b> {{ event.location }}</li>
              <li v-if="event.speakers?.length"><b>🎙 Speakers:</b> {{ event.speakers.join(', ') }}</li>
            </ul>
            <ul v-if="event.tags?.length" class="tags evd-tags">
              <li v-for="t in event.tags" :key="t">{{ t }}</li>
            </ul>
            <NuxtLink class="btn ghost" to="/event-archive">← All past events</NuxtLink>
          </div>
        </div>

        <!-- media gallery: photos, video and audio from the event -->
        <div v-if="media.length" class="evd-gallery">
          <div class="section-head">
            <p class="eyebrow">Gallery</p>
            <h2>Photos & recordings</h2>
          </div>
          <div class="evd-media-grid">
            <button
              v-for="(m, i) in media" :key="m.title + i" type="button" class="evd-media"
              :aria-label="`Open ${m.title}`" @click="open = i"
            >
              <img v-if="m.img" :src="m.img" :alt="m.title" loading="lazy">
              <span v-else class="evd-audio-tile" aria-hidden="true">🎧</span>
              <span class="evd-badge">{{ badge[m.type] }}</span>
              <span class="evd-media-title">{{ m.title }}</span>
            </button>
          </div>
        </div>
        <p v-else class="evd-empty">No media has been added for this event yet.</p>
      </div>

      <!-- lightbox: renders the right player per media type -->
      <Transition name="lb-fade">
        <div v-if="open !== null && media[open]" class="lightbox" @click.self="close">
          <button class="lb-close" type="button" aria-label="Close" @click="close">✕</button>
          <button class="lb-nav prev" type="button" aria-label="Previous" @click="step(-1)">←</button>
          <div class="lb-body">
            <video v-if="media[open].type === 'video'" :src="media[open].src" :poster="media[open].img" controls autoplay></video>
            <div v-else-if="media[open].type === 'audio'" class="lb-audio">
              <span class="audio-icon" aria-hidden="true">🎧</span>
              <audio :src="media[open].src" controls autoplay></audio>
            </div>
            <img v-else :src="media[open].img" :alt="media[open].title">
            <p class="lb-caption"><b>{{ media[open].title }}</b> · {{ event.title }}</p>
          </div>
          <button class="lb-nav next" type="button" aria-label="Next" @click="step(1)">→</button>
        </div>
      </Transition>
    </section>

    <section v-else class="evd">
      <div class="container">
        <p class="evd-empty">This event could not be found. <NuxtLink to="/event-archive">Back to the archive</NuxtLink></p>
      </div>
    </section>

    <DonateCta />
    <SiteFooter />
  </div>
</template>

<style scoped>
.evd { padding: 3.5rem 0; }
.evd-info { display: grid; grid-template-columns: 1.05fr .95fr; gap: 2.5rem; align-items: start; }
.evd-cover { width: 100%; aspect-ratio: 16 / 11; object-fit: cover; border-radius: 22px;
  box-shadow: 0 18px 40px rgba(20, 24, 29, .12); }
.evd-copy h2 { font-size: 1.6rem; color: var(--color-secondary); margin-bottom: .8rem; }
.evd-text { color: #55606b; line-height: 1.7; }
.evd-facts { list-style: none; padding: 0; margin: 1.2rem 0; display: grid; gap: .5rem; }
.evd-facts b { color: var(--color-secondary); }
.evd-tags { display: flex; flex-wrap: wrap; gap: .4rem; list-style: none; padding: 0; margin: 0 0 1.4rem; }
.evd-tags li { font-size: .74rem; font-weight: 700; color: var(--color-primary);
  border: 1.5px solid currentColor; border-radius: 999px; padding: .2rem .7rem; }

.evd-gallery { margin-top: 3.5rem; }
/* masonry: CSS columns — tiles keep their natural height and flow down the
   shortest column; break-inside keeps a tile from splitting across columns */
.evd-media-grid { columns: 3; column-gap: 1.2rem; margin-top: 1.6rem; }
.evd-media { position: relative; display: block; width: 100%; margin: 0 0 1.2rem;
  break-inside: avoid; border: 0; padding: 0; background: none; cursor: pointer;
  border-radius: 16px; overflow: hidden; box-shadow: 0 12px 28px rgba(20, 24, 29, .1);
  transition: transform .2s ease; text-align: left; }
.evd-media:hover, .evd-media:focus-visible { transform: translateY(-4px); }
.evd-media img { width: 100%; height: auto; display: block; }
/* vary tile heights so the masonry reads as a collage, not a grid */
.evd-media:nth-child(3n + 1) img { aspect-ratio: 4 / 5; object-fit: cover; }
.evd-media:nth-child(3n + 2) img { aspect-ratio: 16 / 10; object-fit: cover; }
.evd-media:nth-child(3n) img { aspect-ratio: 1 / 1; object-fit: cover; }
.evd-audio-tile { display: grid; place-items: center; width: 100%; aspect-ratio: 4 / 3;
  background: linear-gradient(140deg, var(--color-secondary), #3a2030); font-size: 2.6rem; }
.evd-badge { position: absolute; top: .7rem; left: .7rem; background: rgba(10, 12, 16, .72); color: #fff;
  font-size: .72rem; font-weight: 700; padding: .3rem .7rem; border-radius: 999px; backdrop-filter: blur(4px); }
.evd-media-title { position: absolute; left: 0; right: 0; bottom: 0; padding: 1.6rem .8rem .7rem;
  color: #fff; font-size: .82rem; font-weight: 700;
  background: linear-gradient(transparent, rgba(10, 12, 16, .72)); }
.evd-empty { color: #55606b; margin-top: 2rem; }

.lb-body video { max-width: min(920px, 92vw); max-height: 78vh; border-radius: 14px; }

@media (max-width: 900px) { .evd-info { grid-template-columns: 1fr; } .evd-media-grid { columns: 2; } }
@media (max-width: 560px) { .evd-media-grid { columns: 1; } }
</style>
