<script setup lang="ts">
const oluxCms = useOluxContent('ministries-overview')
const oluxFb: Record<string, string> = {}
/** Overview of every ministry sub-page — each card links to its own page. */
const { ministriesOverview: ministries } = useSiteContent()
</script>

<template>
  <section class="mn-overview" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <!-- quick jump strip -->
      <nav class="mn-jump" aria-label="Ministries">
        <NuxtLink v-for="m in ministries" :key="m.to" :to="m.to">{{ m.tag }}</NuxtLink>
      </nav>

      <!-- alternating feature rows -->
      <article v-for="(m, i) in ministries" :key="m.to" class="mn-row" :class="{ flip: i % 2 }">
        <NuxtLink class="mn-photo" :to="m.to" :aria-label="`Visit ${m.tag}`">
          <img :src="m.img" :alt="m.tag" loading="lazy">
        </NuxtLink>

        <div class="mn-body">
          <span class="ct-chip">✳ {{ m.tag }}</span>
          <h2>{{ m.title }}</h2>
          <p class="mn-text">{{ m.text }}</p>
          <ul class="mn-facts">
            <li v-for="f in m.facts" :key="f.label"><b>{{ f.label }}</b><span>{{ f.value }}</span></li>
          </ul>
          <NuxtLink class="btn" :to="m.to">Explore {{ m.tag }} <span class="arrow">↗</span></NuxtLink>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.mn-overview { padding: 3rem 0 4rem; }

/* quick jump strip */
.mn-jump { display: flex; flex-wrap: wrap; gap: .7rem; justify-content: center; margin-bottom: 3rem; }
.mn-jump a { padding: .55rem 1.2rem; border-radius: 999px; background: #fff; font-weight: 700; font-size: .95rem;
  color: var(--color-secondary); box-shadow: 0 8px 20px rgba(20, 24, 29, .07); transition: background .2s, color .2s; }
.mn-jump a:hover { background: var(--color-primary); color: #fff; }

/* feature rows */
.mn-row { display: flex; gap: 3rem; align-items: center; background: #fff; border-radius: 24px; padding: 2.4rem;
  box-shadow: 0 14px 32px rgba(20, 24, 29, .08); }
.mn-row + .mn-row { margin-top: 2.4rem; }
.mn-row.flip { flex-direction: row-reverse; }

.mn-photo { flex: 0 0 42%; border-radius: 18px; overflow: hidden; display: block; }
.mn-photo img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; transition: transform .35s ease; }
.mn-photo:hover img { transform: scale(1.05); }

.mn-body { flex: 1; }
.mn-body .ct-chip { font-size: 1.15rem; }
.mn-body h2 { margin: .8rem 0 .6rem; }
.mn-text { font-size: 17px; color: #55606b; line-height: 1.65; }

.mn-facts { list-style: none; padding: 0; margin: 1.2rem 0 1.6rem; display: grid; gap: .5rem; }
.mn-facts li { display: flex; gap: .8rem; font-size: 17px; color: #55606b; }
.mn-facts b { flex: 0 0 84px; color: var(--color-secondary); font-size: 1rem; }

@media (max-width: 900px) {
  .mn-row, .mn-row.flip { flex-direction: column; padding: 1.4rem; gap: 1.4rem; }
  .mn-photo { flex: none; width: 100%; }
}
</style>
