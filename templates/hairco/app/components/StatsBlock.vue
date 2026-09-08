<script setup lang="ts">
const oluxCms = useOluxContent('stats')
const oluxFb: Record<string, string> = {}
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
      <div class="section-head centered" style="margin-bottom:2.6rem" data-olx-key="statsIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="caption">{{ field('statsIntro', 'caption', 'Hair Co. in numbers') }}</p>
        <h2 style="color:#fff" data-olx-field="heading">{{ field('statsIntro', 'heading', 'Trusted well beyond our own chairs') }}</h2>
      </div>
      <div class="stats-grid" data-olx-key="stats" data-olx-kind="collection">
        <div v-for="s in stats" :key="s.text" class="stat" data-olx-item>
          <h3><b data-olx-field="title">{{ s.title }}</b></h3>
          <p data-olx-field="text">{{ s.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
