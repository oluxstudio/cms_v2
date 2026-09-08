<script setup lang="ts">
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "stats" collection overrides these rows.
const fallbackStats = [
  { title: '30+', text: 'International Branches' },
  { title: '12k', text: 'Happy Clients World Wide' },
  { title: '15', text: 'Years of In-Field Experience' },
  { title: '98%', text: 'Positive Client Reviews' },
]
const stats = computed(() => items('stats', fallbackStats))
</script>

<template>
  <section class="stats">
    <div class="container">
      <div class="visuals">
        <img src="/assets/images/stats-1.jpg" alt="Precision cutting">
        <img class="secondary" src="/assets/images/stats-2.jpg" alt="Detail work">
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
