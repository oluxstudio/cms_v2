<script setup lang="ts">
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "About Points" collection overrides these rows.
const fallbackPoints = [
  { point: 'Long-Lasting Hold' },
  { point: 'Effective Ingredients' },
  { point: 'Gentle Formulas' },
  { point: 'Deep Hydration' },
]
const points = computed(() => items('aboutPoints', fallbackPoints))
</script>

<template>
  <section class="about" data-olx-key="about" data-olx-kind="component">
    <div class="container">
      <div>
        <p class="eyebrow" data-olx-field="caption">{{ field('about', 'caption', 'About Hair Co.') }}</p>
        <h2 data-olx-field="heading">{{ field('about', 'heading', 'Healthy Hair Begins With A Healthy Routine') }}</h2>
        <p data-olx-field="body">{{ field('about', 'body', 'For over fifteen years our collective has cared for hair the slow way — honest consultations, gentle products and techniques that respect what grows naturally.') }}</p>
        <ul data-olx-key="about-points" data-olx-kind="collection">
          <li v-for="p in points" :key="p.point" data-olx-item data-olx-field="point">{{ p.point }}</li>
        </ul>
        <!-- own marker block: keeps this field out of the aboutPoints item schema -->
        <a class="btn" href="/about" data-olx-key="aboutCta" data-olx-kind="component" data-olx-field="label">{{ field('aboutCta', 'label', 'Learn more') }}</a>
      </div>
      <div class="visual">
        <img src="/assets/images/about-1.jpg" alt="Stylist working" data-olx-field="image">
        <img class="secondary" src="/assets/images/about-2.jpg" alt="Finished color result" data-olx-field="imageSecondary">
      </div>
    </div>
  </section>
</template>
