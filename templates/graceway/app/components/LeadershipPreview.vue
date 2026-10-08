<script setup lang="ts">
const oluxCms = useOluxContent('leadership-preview')
const oluxFb: Record<string, string> = {"Text":"Leadership","Headline":"The people who shepherd us"}
// @olux-source leadership
// A few of the leaders on the About page — the whole team is on /leadership.
const shown = computed(() => useMembers().slice(0, 4))
</script>

<template>
  <section class="lp-sec" data-olx-panel="leadership" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div v-if="shown.length" class="container lp-wrap">
      <div class="lp-head">
        <div>
          <p data-olx-field="text" class="eyebrow">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
          <h2 data-olx-field="headline">{{ oluxCms.t('Headline', oluxFb['Headline']) }}</h2>
        </div>
        <NuxtLink class="btn ghost" to="/leadership">Meet the whole team</NuxtLink>
      </div>
      <div class="lp-grid">
        <NuxtLink v-for="m in shown" :key="m.slug" data-olx-item :to="`/leadership/${m.slug}`" class="lp-card">
          <span class="lp-photo"><img :src="m.img" :alt="`Portrait of ${m.name}`" loading="lazy"></span>
          <span class="lp-name">{{ m.name }}</span>
          <span class="lp-role">{{ m.role }}</span>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>

<style scoped>
.lp-wrap { padding-top: 2rem; padding-bottom: 4.5rem; }
.lp-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.lp-head h2 { font-size: clamp(1.8rem, 3vw, 2.4rem); }
.lp-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.4rem; margin-top: 1.8rem; }
@media (max-width: 980px) { .lp-grid { grid-template-columns: 1fr 1fr; } }
.lp-card { background: #fff; border-radius: 22px; padding: .8rem .8rem 1.3rem; text-align: center; color: inherit;
  box-shadow: 0 14px 34px rgba(20, 24, 29, .08); display: flex; flex-direction: column; gap: .3rem; transition: transform .25s ease; }
.lp-card:hover { transform: translateY(-4px); }
.lp-photo { display: block; aspect-ratio: 1; border-radius: 16px; overflow: hidden; margin-bottom: .6rem; }
.lp-photo img { width: 100%; height: 100%; object-fit: cover; transition: transform .45s ease; }
.lp-card:hover .lp-photo img { transform: scale(1.05); }
.lp-name { font-family: var(--font-heading); font-size: 1.2rem; color: var(--color-secondary); }
.lp-role { font-size: .9rem; font-weight: 700; color: var(--color-primary); }
</style>
