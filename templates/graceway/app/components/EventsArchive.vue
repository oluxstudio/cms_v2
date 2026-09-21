<script setup lang="ts">
const oluxCms = useOluxContent('events-archive')
const oluxFb: Record<string, string> = {}
// Past-events gallery — everything before today, newest first, from the
// global events data source (CMS "Events" collection overrides at runtime).
const { events: authoredEvents, eventsArchive } = useSiteContent()
const rows = computed(() => {
  const cms = (useCms().items('events', []) as any[]).filter(e => e.title && e.img)
  return cms.length ? cms : authoredEvents
})
const now = new Date().toISOString()
const past = computed(() => rows.value
  .filter(e => (e.date ?? '') && e.date < now)
  .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? '')))

const when = (iso: string) => {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
    + ' · ' + d.toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit', hour12: true }).toUpperCase()
}

</script>

<template>
  <section class="ev-archive" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head center">
        <p class="eyebrow">{{ eventsArchive.eyebrow }}</p>
        <h2>{{ eventsArchive.title }}</h2>
        <p>{{ eventsArchive.text }}</p>
      </div>

      <p v-if="!past.length" class="eva-empty">{{ eventsArchive.empty }}</p>

      <div v-else class="eva-grid">
        <NuxtLink
          v-for="e in past" :key="e.id ?? e.title" class="eva-card"
          :to="`/event-archive/${e.id}`" :aria-label="`View ${e.title}`"
        >
          <div class="eva-photo">
            <img :src="e.img" :alt="e.title" loading="lazy">
            <span class="eva-date">📅 {{ when(e.date) }}</span>
          </div>
          <div class="eva-body">
            <h3>{{ e.title }}</h3>
            <p class="eva-text">{{ e.text }}</p>
            <ul class="eva-meta">
              <li v-if="e.location">📍 {{ e.location }}</li>
              <li v-if="e.speakers?.length">🎙 {{ e.speakers.join(', ') }}</li>
            </ul>
            <ul v-if="e.tags?.length" class="tags">
              <li v-for="t in e.tags" :key="t">{{ t }}</li>
            </ul>
          </div>
        </NuxtLink>
      </div>
    </div>

  </section>
</template>

<style scoped>
.ev-archive { padding: 3.5rem 0; }
.eva-empty { text-align: center; color: #55606b; margin-top: 2rem; }

.eva-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.6rem; margin-top: 2rem; }
.eva-card { display: flex; background: #fff; border-radius: 18px; overflow: hidden; cursor: pointer;
  box-shadow: 0 12px 30px rgba(20, 24, 29, .08); transition: transform .2s ease, box-shadow .2s ease;
  display: flex; flex-direction: column; }
.eva-card:hover, .eva-card:focus-visible { transform: translateY(-4px); box-shadow: 0 18px 40px rgba(20, 24, 29, .14); }

.eva-photo { position: relative; }
.eva-photo img { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; display: block; }
.eva-date { position: absolute; left: .8rem; bottom: .8rem; background: var(--color-primary); color: #fff;
  font-size: .78rem; font-weight: 700; padding: .4rem .8rem; border-radius: 999px; }

.eva-body { padding: 1.3rem 1.3rem 1.5rem; display: flex; flex-direction: column; gap: .5rem; }
.eva-body h3 { font-size: 1.12rem; color: var(--color-secondary); }
.eva-text { font-size: .93rem; color: #55606b; line-height: 1.55; }
.eva-meta { list-style: none; padding: 0; margin: .2rem 0 0; display: grid; gap: .25rem; }
.eva-meta li { font-size: .86rem; color: var(--color-secondary); font-weight: 600; }
.eva-body .tags { display: flex; flex-wrap: wrap; gap: .4rem; list-style: none; padding: 0; margin-top: .3rem; }
.eva-body .tags li { font-size: .72rem; font-weight: 700; color: var(--color-primary);
  border: 1.5px solid currentColor; border-radius: 999px; padding: .2rem .7rem; }

@media (max-width: 980px) { .eva-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 620px) { .eva-grid { grid-template-columns: 1fr; } }
</style>
