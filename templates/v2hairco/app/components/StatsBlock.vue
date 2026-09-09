<script setup lang="ts">
const oluxCms = useOluxContent('stats')
const oluxFb: Record<string, string> = {"Image":"/assets/images/stats-1.jpg","Image B":"/assets/images/stats-2.jpg"}
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "stats" collection overrides these rows.
const fallbackStats = oluxCms.items('Fallback Stat', {"Title":"title","Text":"text"}, [
  { title: '30+', text: 'International Branches' },
  { title: '12k', text: 'Happy Clients World Wide' },
  { title: '15', text: 'Years of In-Field Experience' },
  { title: '98%', text: 'Positive Client Reviews' },
], {})
const stats = computed(() => items('stats', fallbackStats))
</script>

<template>
  <section class="stats" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="visuals">
        <img data-olx-field="image" :src="oluxCms.t('Image', oluxFb['Image'])" alt="Precision cutting">
        <img data-olx-field="imageB" class="secondary" :src="oluxCms.t('Image B', oluxFb['Image B'])" alt="Detail work">
      </div>
      <div>
        <div class="section-head" data-olx-key="statsIntro" data-olx-kind="component">
          <p class="eyebrow" data-olx-field="caption">{{ field('statsIntro', 'caption', 'Hair Co. in numbers') }}</p>
          <h2 data-olx-field="heading">{{ field('statsIntro', 'heading', 'Keep Your Hair Game Strong') }}</h2>
        </div>
        <div class="stats-grid" data-olx-key="stats" data-olx-kind="collection">
          <div v-for="s in stats" :key="s.text" class="stat" data-olx-item>
            <h3><b data-olx-field="title">{{ s.title }}</b></h3>
            <p data-olx-field="text">{{ s.text }}</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
