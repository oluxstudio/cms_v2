<script setup lang="ts">
// One announcement's own page: the full text, its picture and next step,
// with the other recent announcements below.
const route = useRoute()
const all = computed(() => useAnnouncements())
const item = computed(() => all.value.find(a => a.slug === String(route.params.slug)))
const more = computed(() => all.value.filter(a => a.slug !== item.value?.slug).slice(0, 3))

useHead({ title: computed(() => item.value ? `${item.value.title} — CAC Blackburn` : 'Announcement — CAC Blackburn') })

// an internal page opens in-app; anything else in a new tab
const internal = computed(() => (item.value?.link ?? '').startsWith('/'))

const shared = ref(false)
const share = async () => {
  if (!item.value) return
  try {
    if (navigator.share) { await navigator.share({ title: item.value.title, url: location.href }); return }
    await navigator.clipboard.writeText(location.href)
    shared.value = true
    setTimeout(() => { shared.value = false }, 2500)
  } catch { /* dismissed */ }
}
</script>

<template>
  <div>
    <SiteHeader />

    <PageHeroContent :crumbs="[{ label: 'About Us', to: '/about' }, { label: 'Announcements', to: '/announcements' }, { label: item?.title ?? 'Announcement' }]">
      <p class="eyebrow">{{ item ? `${item.category} · ${announcementDate(item.date)}` : 'Announcements' }}</p>
      <h1>{{ item?.title ?? 'Announcement not found' }}</h1>
      <p v-if="item?.summary">{{ item.summary }}</p>
    </PageHeroContent>

    <section v-if="item" class="ann">
      <div class="container ann-layout">
        <article class="ann-main">
          <img v-if="item.img" class="ann-img" :src="item.img" :alt="item.title">
          <div class="ann-card">
            <div class="ann-meta">
              <span class="ann-cat">{{ item.category }}</span>
              <span v-if="item.pinned" class="ann-pin">Pinned</span>
              <span class="ann-date"><EventIcon name="calendar" :size="16" />{{ announcementDate(item.date) }}</span>
            </div>
            <p class="ann-body">{{ item.body }}</p>
            <div class="ann-actions">
              <template v-if="item.link">
                <NuxtLink v-if="internal" class="btn" :to="item.link">{{ item.linkLabel || 'Find out more' }}</NuxtLink>
                <a v-else class="btn" :href="item.link" target="_blank" rel="noopener">{{ item.linkLabel || 'Find out more' }}</a>
              </template>
              <button type="button" class="ann-share" @click="share">
                <EventIcon :name="shared ? 'check' : 'share'" :size="16" /> {{ shared ? 'Link copied' : 'Share' }}
              </button>
            </div>
          </div>
          <NuxtLink class="ann-back" to="/announcements">← All announcements</NuxtLink>
        </article>

        <aside v-if="more.length" class="ann-aside">
          <p class="ann-kicker">More announcements</p>
          <AnnouncementCard v-for="a in more" :key="a.slug" :item="a" compact />
        </aside>
      </div>
    </section>

    <section v-else class="ann">
      <div class="container ann-missing">
        <p>We couldn't find that announcement — it may have been moved or removed.</p>
        <NuxtLink class="btn" to="/announcements">See all announcements</NuxtLink>
      </div>
    </section>

    <SiteFooter />
  </div>
</template>

<style scoped>
.ann { padding: 3.5rem 0 4.5rem; }
.ann-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 2.5rem; align-items: start; }
@media (max-width: 980px) { .ann-layout { grid-template-columns: 1fr; } }
.ann-main { display: grid; gap: 1.5rem; min-width: 0; }
.ann-img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 24px; box-shadow: 0 18px 40px rgba(20, 24, 29, .12); }
.ann-card { background: #fff; border-radius: 24px; padding: 2rem 2.2rem; box-shadow: 0 14px 34px rgba(20, 24, 29, .08); display: grid; gap: 1.2rem; }
@media (max-width: 560px) { .ann-card { padding: 1.5rem 1.3rem; } }
.ann-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .7rem; font-size: .85rem; }
.ann-cat { padding: .25rem .8rem; border-radius: 999px; background: var(--primary-soft); color: var(--color-primary); font-weight: 800; }
.ann-pin { padding: .25rem .8rem; border-radius: 999px; background: var(--color-secondary); color: #fff; font-weight: 800; }
.ann-date { display: inline-flex; align-items: center; gap: .35rem; color: #6b737c; font-weight: 600; }
.ann-date .event-icon { color: var(--color-primary); }
.ann-body { font-size: 1.12rem; line-height: 1.8; color: #4b5560; white-space: pre-line; }
.ann-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .8rem; padding-top: 1.1rem; border-top: 1.5px dashed #ece5da; }
.ann-share { display: inline-flex; align-items: center; gap: .4rem; padding: .65rem 1.2rem; border-radius: 999px; border: 1.5px solid var(--color-primary);
  background: transparent; color: var(--color-primary); font: inherit; font-size: .95rem; font-weight: 700; cursor: pointer; }
.ann-share:hover { background: var(--primary-soft); }
.ann-back { font-weight: 700; color: var(--color-secondary); justify-self: start; }
.ann-back:hover { color: var(--color-primary); }
.ann-aside { display: grid; gap: 1rem; position: sticky; top: 110px; }
@media (max-width: 980px) { .ann-aside { position: static; } }
.ann-kicker { font-size: .8rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--color-primary); }
.ann-missing { display: grid; gap: 1rem; justify-items: center; text-align: center; color: #55606b; }
</style>
