<script setup lang="ts">
const oluxCms = useOluxContent('about')
const oluxFb: Record<string, string> = {"Image":"/assets/images/about.jpg"}
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "About Points" collection overrides these rows.
const fallbackPoints = oluxCms.items('Fallback Point', {"Point":"point"}, [
  { point: 'Long-Lasting Hold' },
  { point: 'Effective Ingredients' },
  { point: 'Gentle Formulas' },
  { point: 'Deep Hydration' },
], {})
const points = computed(() => items('aboutPoints', fallbackPoints))
</script>

<template>
  <section class="about tinted" data-olx-key="about" data-olx-kind="component" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="visual">
        <img :src="oluxCms.t('Image', oluxFb['Image'])" alt="Stylist at work" data-olx-field="image">
      </div>
      <div>
        <p class="eyebrow" data-olx-field="caption">{{ field('about', 'caption', 'About Hair Co.') }}</p>
        <h2 data-olx-field="heading">{{ field('about', 'heading', 'Healthy hair begins with a healthy routine') }}</h2>
        <p data-olx-field="body">{{ field('about', 'body', 'For over fifteen years our collective has cared for hair the slow way — honest consultations, gentle products and techniques that respect what grows naturally.') }}</p>
        <ul data-olx-key="about-points" data-olx-kind="collection">
          <li v-for="p in points" :key="p.point" data-olx-item data-olx-field="point">{{ p.point }}</li>
        </ul>
        <!-- own marker block: keeps this field out of the aboutPoints item
             schema (the scanner attaches fields to the last opened block) -->
        <a class="btn" href="/about" data-olx-key="aboutCta" data-olx-kind="component" data-olx-field="label">{{ field('aboutCta', 'label', 'Read our story') }}</a>
      </div>
    </div>
  </section>
</template>
