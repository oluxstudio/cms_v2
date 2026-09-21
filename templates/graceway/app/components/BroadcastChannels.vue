<script setup lang="ts">
const oluxCms = useOluxContent('broadcast-channels')
const oluxFb: Record<string, string> = {}
/** Social platform pills — view the profile or jump straight into the live stream. */
// platform identity (name, profile href, icon) comes from the global `socials`
// array; broadcast-only extras (handle, followers, live, watch, blurb) layer on top
const { socials, broadcastChannels } = useSiteContent()
const channels = broadcastChannels.channels.map((c) => {
  const social = socials.find(s => s.key === c.key)
  // `available` in the socials data source is the single switch for active/live
  return { ...c, live: social?.available ?? c.live, name: social?.name ?? c.key, profile: social?.href ?? c.watch, icon: social?.icon ?? '' }
})
</script>

<template>
  <section class="bcx" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head center">
        <p class="eyebrow">{{ broadcastChannels.eyebrow }}</p>
        <h2>{{ broadcastChannels.title }}</h2>
        <p class="bcx-sub">{{ broadcastChannels.sub }}</p>
      </div>

      <!-- platform pills -->
      <div class="bcx-grid">
        <article v-for="c in channels" :key="c.key" class="bcx-pill" :style="{ '--pc': c.color }">
          <div class="bcx-head">
            <span class="bcx-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path :d="c.icon"/></svg></span>
            <div class="bcx-id">
              <h3>{{ c.name }}</h3>
              <p>{{ c.handle }}</p>
            </div>
            <span v-if="c.live" class="bcx-live">● LIVE Sundays</span>
          </div>
          <p class="bcx-blurb">{{ c.blurb }}</p>
          <p class="bcx-followers">{{ c.followers }}</p>
          <div class="bcx-actions">
            <a class="btn bcx-watch" :href="c.watch" target="_blank" rel="noopener">▶ Watch Live</a>
            <a class="btn ghost" :href="c.profile" target="_blank" rel="noopener">View Profile</a>
          </div>
        </article>
      </div>

      <!-- join the church CTA -->
      <div class="bcx-join">
        <div>
          <h2>{{ broadcastChannels.join.title }}</h2>
          <p>{{ broadcastChannels.join.text }}</p>
        </div>
        <NuxtLink class="btn bcx-join-btn" :to="broadcastChannels.join.cta.to">{{ broadcastChannels.join.cta.label }} <span class="arrow">↗</span></NuxtLink>
      </div>
    </div>
  </section>
</template>

<style scoped>
.bcx { padding: 3rem 0 4rem; }
.bcx-sub { font-size: 17px; color: #55606b; max-width: 560px; margin: .6rem auto 0; }

/* platform pills */
.bcx-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.8rem; margin-top: 2.4rem; }
.bcx-pill { background: #fff; border-radius: 26px; padding: 2rem; box-shadow: 0 14px 32px rgba(20, 24, 29, .08);
  border-top: 6px solid var(--pc); transition: transform .25s ease; }
.bcx-pill:hover { transform: translateY(-4px); }

.bcx-head { display: flex; align-items: center; gap: 1rem; }
.bcx-icon { width: 54px; height: 54px; flex: none; border-radius: 999px; background: var(--pc); color: #fff;
  display: grid; place-items: center; }
.bcx-icon svg { width: 26px; height: 26px; }
.bcx-id { flex: 1; min-width: 0; }
.bcx-id h3 { font-size: 1.15rem; color: var(--color-secondary); }
.bcx-id p { font-size: 17px; color: #55606b; }
.bcx-live { flex: none; font-size: .8rem; font-weight: 800; letter-spacing: .04em; color: #d1242f;
  background: #fdeef3; border-radius: 999px; padding: .35rem .8rem; animation: bcx-pulse 1.8s ease-in-out infinite; }
@keyframes bcx-pulse { 0%, 100% { opacity: .65; } 50% { opacity: 1; } }

.bcx-blurb { font-size: 17px; color: #55606b; line-height: 1.6; margin-top: 1rem; }
.bcx-followers { font-size: 1rem; font-weight: 700; color: var(--color-secondary); margin-top: .6rem; }

.bcx-actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: 1.3rem; }
.bcx-watch { background: var(--pc); }

/* join CTA */
.bcx-join { margin-top: 2.4rem; background: var(--color-secondary); border-radius: 26px; padding: 2.4rem;
  display: flex; align-items: center; justify-content: space-between; gap: 2rem;
  box-shadow: 0 14px 32px rgba(20, 24, 29, .12); }
.bcx-join h2 { color: #fff; }
.bcx-join p { font-size: 17px; color: #d5dce2; margin-top: .5rem; max-width: 480px; }
.bcx-join-btn { flex: none; }

@media (max-width: 820px) {
  .bcx-grid { grid-template-columns: 1fr; }
  .bcx-join { flex-direction: column; text-align: center; }
}
</style>
